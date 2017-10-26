<?php

namespace Deli\Models\ITescoCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_itesco_cz_products';
	const SOURCE = 'itesco_cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'buildProductList'], 3600, function() {

				@ini_set('memory_limit', '512M');

				foreach (\Chakula\Tesco::getDepartmentTree() as $superDepartment) {

					try {
						$exclude = \Katu\Config::get('deli', 'sources', static::SOURCE, 'excludeSuperDepartmentNames');
					} catch (\Katu\Exceptions\MissingConfigException $e) {
						$exclude = [];
					}

					if (!in_array($superDepartment->name, $exclude)) {
						foreach ($superDepartment->departments as $department) {
							foreach ($department->categories as $category) {

								\Katu\Utils\Cache::get(function($categoryUri) use($superDepartment, $department, $category) {

									foreach ($category->getProducts() as $product) {

										try {

											$product = static::upsert([
												'uri' => $product->id,
											], [
												'timeCreated' => new \Katu\Utils\DateTime,
											], [
												'name' => $product->name,
											]);

											$product->setRemoteCategory([
												$superDepartment->name,
												$department->name,
												$category->name,
											]);
											$product->save();

										} catch (\Exception $e) {
											// Nevermind.
										}

									}

								}, static::TIMEOUT, $category->uri);

							}
						}
					}
				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function getChakulaProduct() {
		return new \Chakula\Tesco\Product($this->uri);
	}

	public function getName() {
		try {
			return $this->getChakulaProduct()->getName();
		} catch (\Exception $e) {
			return $this->name;
		}
	}

	public function getUrl() {
		return $this->getChakulaProduct()->getUrl();
	}

	public function load() {
		return static::transaction(function() {

			try {

				$product = $this->getChakulaProduct();
				if ($product->isAvailable()) {

					$this->update('name', $product->getName());
					$this->update('isAvailable', 1);

					$this->loadNutrients();
					$this->loadAllergens();
					$this->loadEan();
					$this->loadProperties();

				} else {

					$this->update('isAvailable', 0);

				}

			} catch (\Exception $e) {

				var_dump($e); die;

				$this->update('isAvailable', 0);

			}

			$this->update('timeLoaded', new \Katu\Utils\DateTime);
			$this->save();

			return true;

		});
	}

	public function scrapeProductAmountWithUnit() {
		$productInfo = $this->getChakulaProduct()->getInfo("Výživové hodnoty");
		if ($productInfo) {

			$dom = \Katu\Utils\DOM::crawlHtml($productInfo->text);
			$e = $dom->filter('table thead th[scope="col"]');
			if (!$e->count()) {
				return false;
			}

			if (preg_match('/(?<amount>[0-9]+)\s*(?<unit>g|ml)/ui', $e->html(), $match)) {
				return new \Effekt\AmountWithUnit($match['amount'], $match['unit']);
			} elseif (preg_match('/Na (?<amount>[0-9]+) výrobku/ui', $e->html(), $match)) {
				return new \Effekt\AmountWithUnit($match['amount'], 'g');
			}

		}

		return false;
	}

	public function scrapeNutrients() {
		$productInfo = $this->getChakulaProduct()->getInfo("Výživové hodnoty");
		if ($productInfo) {

			$dom = \Katu\Utils\DOM::crawlHtml($productInfo->text);
			$list = $dom->filter('table tbody tr')->each(function($e) {

				try {

					return [
						'name' => trim($e->filter('td:nth-child(1)')->text()),
						'amountWithUnit' => trim($e->filter('td:nth-child(2)')->text()),
					];

				} catch (\Exception $e) {
					return null;
				}

			});

			$nutrientAssoc = [];
			foreach (array_values(array_filter($list)) as $listItem) {
				$nutrientAssoc[$listItem['name']] = $listItem['amountWithUnit'];
			}

			foreach ($nutrientAssoc as $nutrientName => $nutrientAmountSource) {

				$nutrientName = trim($nutrientName);
				$nutrientAmountSource = trim($nutrientAmountSource);
				if (!$nutrientName || !$nutrientAmountSource) {
					continue;
				}

				$ignore = [
					'stopy',
					'porcí',
					'referenční hodnota příjmu',
					'gda',
					'doporučená denní dávka',
					'výživná celulóza',
					'balastní látky',
					'%*',
					'*%',
					'laktóza',
					'obsah laktózy',
					'máslo',
					'transisomery',
					'transizomery',
					'z toho trans izomery',
					'rosltinné steroly',
					'jogurtová kultura',
					'z toho škroby',
				];

				$preg = implode('|', array_map(function($i) {
					return preg_quote($i, '/');
				}, $ignore));
				$preg = '/' . $preg . '/ui';

				if (preg_match($preg, $nutrientAmountSource)) {
					continue;
				}
				if (preg_match($preg, $nutrientName)) {
					continue;
				}

				if ($nutrientName == 'Energetická hodnota' && $nutrientAmountSource == 'kJ/kcal') {
					continue;
				}

				/* Energy *************************************************************/

				if (preg_match('/(energetická|energie|energeická hodnota|energy|výživová hodnota|energia|energická hodnota|energ\. hodnota|energeie)/ui', $nutrientName)) {

					if (preg_match('/([0-9\.\,]+)\s*kJ\s*\/\s*([0-9\.\,\s]+)\s*kcal/', $nutrientAmountSource, $match)) {

						$nutrients['energy'] = new \Effekt\AmountWithUnit($match[1], 'kJ');
						$nutrients['calories'] = new \Effekt\AmountWithUnit($match[2], 'kcal');

					} elseif (preg_match('/([0-9\.\,]+)\s*kcal\s*\/\s*([0-9\.\,\s]+)\s*kJ/', $nutrientAmountSource, $match)) {

						$nutrients['calories'] = new \Effekt\AmountWithUnit($match[1], 'kcal');
						$nutrients['energy'] = new \Effekt\AmountWithUnit($match[2], 'kJ');

					} elseif (preg_match('/([0-9\.\,]+)\s*kJ/', $nutrientAmountSource, $match)) {

						$nutrients['energy'] = new \Effekt\AmountWithUnit($match[1], 'kJ');

					} elseif (preg_match('/([0-9\.\,]+)\s*kcal/', $nutrientAmountSource, $match)) {

						$nutrients['calories'] = new \Effekt\AmountWithUnit($match[1], 'kcal');

					} elseif (preg_match('/(Energie \(kJ\)|Energie kJ)/', $nutrientName) && preg_match('/([0-9\.\,]+)/', $nutrientAmountSource, $match)) {

						$nutrients['energy'] = new \Effekt\AmountWithUnit($match[1], 'kJ');

					} elseif (preg_match('/(Energie \(kcal\)|Energie kcal)/', $nutrientName) && preg_match('/([0-9\.\,]+)/', $nutrientAmountSource, $match)) {

						$nutrients['calories'] = new \Effekt\AmountWithUnit($match[1], 'kcal');

					} elseif (preg_match('/Energetická hodnota \(kJ\s*\/\s*kcal\)/', $nutrientName) && preg_match('/([0-9\.\,]+)\s*\/\s*([0-9\.\,]+)/', $nutrientAmountSource, $match)) {

						$nutrients['energy'] = new \Effekt\AmountWithUnit($match[1], 'kJ');
						$nutrients['calories'] = new \Effekt\AmountWithUnit($match[2], 'kcal');

					}

				} else {

					$nutrientCode = null;
					$nutrientAmountWithUnit = null;

					if (preg_match('/^([0-9\.\,]+)\s*(g)?$/', $nutrientAmountSource, $match)) {
						$nutrientAmountWithUnit = new \Effekt\AmountWithUnit($match[1], 'g');
					} elseif (preg_match('/^([0-9\.\,]+)\s*(mg)?$/', $nutrientAmountSource, $match)) {
						$nutrientAmountWithUnit = new \Effekt\AmountWithUnit($match[1], 'mg');
					}

					/* Monounsaturated fatty acids **************************************/
					if (preg_match('/(mononenasycené)/ui', $nutrientName)) {
						$nutrientCode = 'monounsaturatedFattyAcids';

					/* Polyunsaturated fatty acids **************************************/
					} elseif (preg_match('/(polynenasycené)/ui', $nutrientName)) {
						$item['code'] = 'polyunsaturatedFattyAcids';

					/* Saturated fatty acids ********************************************/
					} elseif (preg_match('/(nasycené|nasyc\.|NMK|nasýtené|nas\. mastné|nasc\. mast\.)/ui', $nutrientName)) {
						$nutrientCode = 'saturatedFattyAcids';

					/* Fat **************************************************************/
					} elseif (preg_match('/(tuk)/ui', $nutrientName)) {
						$nutrientCode = 'fats';

					/* Carbs ************************************************************/
					} elseif (preg_match('/(sacharid|uhlohydrát)/ui', $nutrientName)) {
						$nutrientCode = 'carbs';

					/* Sugar ************************************************************/
					} elseif (preg_match('/(cukr)/ui', $nutrientName)) {
						$nutrientCode = 'sugar';

					/* Fructose *********************************************************/
					} elseif (preg_match('/(fruktóza)/ui', $nutrientName)) {
						$nutrientCode = 'fructose';

					/* Protein **********************************************************/
					} elseif (preg_match('/(bílkoviny|bílkovina|proteiny)/ui', $nutrientName)) {
						$nutrientCode = 'proteins';

					/* Fiber ************************************************************/
					} elseif (preg_match('/(vláknina)/ui', $nutrientName)) {
						$nutrientCode = 'fiber';

					/* Salt *************************************************************/
					} elseif (preg_match('/(sůl)/ui', $nutrientName)) {
						$nutrientCode = 'salt';

					/* Calcium **********************************************************/
					} elseif (preg_match('/(vápník)/ui', $nutrientName)) {
						$nutrientCode = 'calcium';

					/* Sodium ***********************************************************/
					} elseif (preg_match('/(sodík)/ui', $nutrientName)) {
						$nutrientCode = 'sodium';

					/* Phosphorus *******************************************************/
					} elseif (preg_match('/(fosfor)/ui', $nutrientName)) {
						$nutrientCode = 'phosphorus';

					/* Vitamin A ********************************************************/
					} elseif (preg_match('/(vitam[ií]n a)/ui', $nutrientName)) {
						$nutrientCode = 'vitaminA';

					/* Vitamin B1 *******************************************************/
					} elseif (preg_match('/(vitam[ií]n b1)/ui', $nutrientName)) {
						$nutrientCode = 'vitaminB1';

					/* Vitamin B2 *******************************************************/
					} elseif (preg_match('/(vitam[ií]n b2)/ui', $nutrientName)) {
						$nutrientCode = 'vitaminB2';

					/* Vitamin B6 *******************************************************/
					} elseif (preg_match('/(vitam[ií]n b6)/ui', $nutrientName)) {
						$nutrientCode = 'vitaminB6';

					/* Vitamin C ********************************************************/
					} elseif (preg_match('/(vitam[ií]n c)/ui', $nutrientName)) {
						$nutrientCode = 'vitaminC';

					/* Vitamin D ********************************************************/
					} elseif (preg_match('/(vitam[ií]n d)/ui', $nutrientName)) {
						$nutrientCode = 'vitaminD';

					/* Vitamin E ********************************************************/
					} elseif (preg_match('/(vitam[ií]n e)/ui', $nutrientName)) {
						$nutrientCode = 'vitaminE';

					/* Omega 3 **********************************************************/
					} elseif (preg_match('/(omega 3|ω\-3|ɷ\-3|omega3|omega\-3)/ui', $nutrientName)) {
						$nutrientCode = 'omega3';

					/* Omega 6 **********************************************************/
					} elseif (preg_match('/(omega 6|omega\-6)/ui', $nutrientName)) {
						$nutrientCode = 'omega6';

					}

					if (in_array($nutrientName, [
						'255 kcal',
						'Omega*',
						'hodnota',
					])) {
						continue;
					} elseif (!$nutrientCode || !$nutrientAmountWithUnit) {
						#var_dump($nutrientLine); die;
					}

					if ($nutrientCode && $nutrientAmountWithUnit) {
						$nutrients[$nutrientCode] = $nutrientAmountWithUnit;
					}

				}

			}

		}

		return $nutrients;
	}

	public function loadNutrients() {
		try {

			$productAmountWithUnit = $this->getProductAmountWithUnit();
			foreach ($this->scrapeNutrients() as $nutrientCode => $nutrientAmountWithUnit) {
				$this->setProductNutrient($nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit);
			}

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

	public function scrapeAllergensInContents() {
		$allergens = [];

		$productInfo = $this->getChakulaProduct()->getInfo("Složení");
		if ($productInfo) {

			$value = $productInfo->text;
			$value = preg_replace('/<b>(.*)<b>(.*)<\/b>(.*)<\/b>/Uu', '<b>\\1\\2\\3</b>', $value);
			preg_match_all('/<b>(.+)<\/b>/Uu', $value, $matches);

			$allergens = array_values(array_unique(array_map(function($i) {
				return mb_strtolower($i, 'UTF-8');
			}, array_map('strip_tags', $matches[0]))));

		}

		return $allergens;
	}

	public function loadAllergens() {
		foreach (static::getAllergenCodesFromTexts($this->scrapeAllergensInContents()) as $allergenCode) {
			$this->setProductAllergen($allergenCode);
		}

		return true;
	}

	public function loadEan() {
		try {

			$ean = trim($this->getChakulaProduct()->getEan());
			if ($ean) {

				$this->update('ean', $ean);
				$this->save();

				return true;

			}

			throw new \Exception;

		} catch (\Exception $e) {

			$this->update('ean', null);
			$this->save();

			return false;

		}

	}

	public function loadProperties() {
		$propertyLabelMap = [
			#'Alergeny'   => 'allergens',
			#'Popis'      => 'description',
			#'Skladování' => 'storage',
			#'Složení'    => 'contents',
		];

		var_dump($this->scrapeInfo()); die;

		foreach ($this->scrapeInfo() as $propertyLabel => $value) {
			if (isset($propertyLabelMap[$propertyLabel])) {
				$this->setProductProperty($propertyLabelMap[$propertyLabel], $value);
			} else {
				var_dump($propertyLabel); die;
			}
		}

		return true;
	}

	public function loadPrice() {
		try {

			$productPriceClass = static::getProductPriceTopClass();

			$chakulaProduct = $this->getChakulaProduct();
			$chakulaProductPrice = $chakulaProduct->getPrice($productPriceClass::TIMEOUT);

			$productPrice = $productPriceClass::insert([
				'timeCreated' => new \Katu\Utils\DateTime,
				'productId' => $this->getId(),
				'currencyCode' => $chakulaProductPrice->price->currency,
			]);

			// Price per item.
			$productPrice->update('pricePerProduct', (float)$chakulaProductPrice->price->amount);

			// Price per quantity.
			$productPrice->update('pricePerUnit', (float)$chakulaProductPrice->pricePerQuantity->price->amount);
			$productPrice->update('unitAmount', (string)$chakulaProductPrice->pricePerQuantity->quantity->amount);
			$productPrice->update('unitCode', (string)$chakulaProductPrice->pricePerQuantity->quantity->unit);

			$productPrice->save();

		} catch (\Exception $e) {
			// Nevermind.
		}

		$this->update('timeLoadedPrice', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

}
