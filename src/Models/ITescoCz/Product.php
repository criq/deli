<?php

namespace Deli\Models\ITescoCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_itesco_cz_products';
	const SOURCE = 'itesco_cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'buildProductList'], 3600, function() {

				foreach (\Chakula\Tesco::getDepartmentTree() as $superDepartment) {

					try {
						$exclude = \Katu\Config::get('deli', static::SOURCE, 'excludeSuperDepartmentNames');
					} catch (\Katu\Exceptions\MissingConfigException $e) {
						$exclude = [];
					}

					if (!in_array($superDepartment->name, $exclude)) {
						foreach ($superDepartment->departments as $department) {
							foreach ($department->categories as $category) {

								\Katu\Utils\Cache::get(['deli', static::SOURCE, 'buildProductList', 'category', $category->uri], function() use($superDepartment, $department, $category) {

									foreach ($category->getProducts() as $product) {

										try {

											$product = static::upsert([
												'uri' => $product->id,
											], [
												'timeCreated' => new \Katu\Utils\DateTime,
											], [
												'name' => $product->name,
											]);

											$product->setCategory([
												$superDepartment->name,
												$department->name,
												$category->name,
											]);
											$product->save();

										} catch (\Exception $e) {
											// Nevermind.
										}

									}

								}, 86400 * 3);

							}
						}
					}
				}

			}, \Katu\Env::getPlatform() != 'dev');

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	public function getChakulaProduct() {
		return new \Chakula\Tesco\Product($this->uri);
	}

	public function getName() {
		return $this->getChakulaProduct()->getName();
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

				} else {

					$this->update('isAvailable', 0);

				}

			} catch (\Exception $e) {
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
				return new \Deli\AmountWithUnit($match['amount'], $match['unit']);
			} elseif (preg_match('/Na (?<amount>[0-9]+) výrobku/ui', $e->html(), $match)) {
				return new \Deli\AmountWithUnit($match['amount'], 'g');
			} else {
				#var_dump($this->getChakulaProduct()->getName()); echo $src; die;
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

				if (!trim($nutrientName) || !trim($nutrientAmountSource)) {
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

						$nutrients['energy'] = new \Deli\AmountWithUnit($match[1], 'kJ');
						$nutrients['calories'] = new \Deli\AmountWithUnit($match[2], 'kcal');

					} elseif (preg_match('/([0-9\.\,]+)\s*kcal\s*\/\s*([0-9\.\,\s]+)\s*kJ/', $nutrientAmountSource, $match)) {

						$nutrients['calories'] = new \Deli\AmountWithUnit($match[1], 'kcal');
						$nutrients['energy'] = new \Deli\AmountWithUnit($match[2], 'kJ');

					} elseif (preg_match('/([0-9\.\,]+)\s*kJ/', $nutrientAmountSource, $match)) {

						$nutrients['energy'] = new \Deli\AmountWithUnit($match[1], 'kJ');

					} elseif (preg_match('/([0-9\.\,]+)\s*kcal/', $nutrientAmountSource, $match)) {

						$nutrients['calories'] = new \Deli\AmountWithUnit($match[1], 'kcal');

					} elseif (preg_match('/(Energie \(kJ\)|Energie kJ)/', $nutrientName) && preg_match('/([0-9\.\,]+)/', $nutrientAmountSource, $match)) {

						$nutrients['energy'] = new \Deli\AmountWithUnit($match[1], 'kJ');

					} elseif (preg_match('/(Energie \(kcal\)|Energie kcal)/', $nutrientName) && preg_match('/([0-9\.\,]+)/', $nutrientAmountSource, $match)) {

						$nutrients['calories'] = new \Deli\AmountWithUnit($match[1], 'kcal');

					} elseif (preg_match('/Energetická hodnota \(kJ\s*\/\s*kcal\)/', $nutrientName) && preg_match('/([0-9\.\,]+)\s*\/\s*([0-9\.\,]+)/', $nutrientAmountSource, $match)) {

						$nutrients['energy'] = new \Deli\AmountWithUnit($match[1], 'kJ');
						$nutrients['calories'] = new \Deli\AmountWithUnit($match[2], 'kcal');

					}

				} else {

					$nutrientCode = null;
					$nutrientAmountWithUnit = null;

					if (preg_match('/([0-9\.\,]+)\s*(g)?/', $nutrientAmountSource, $match)) {
						$nutrientAmountWithUnit = new \Deli\AmountWithUnit($match[1], 'g');
					} elseif (preg_match('/([0-9\.\,]+)\s*(mg)?/', $nutrientAmountSource, $match)) {
						$nutrientAmountWithUnit = new \Deli\AmountWithUnit($match[1], 'mg');
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

			$productAmountWithUnit = $this->scrapeProductAmountWithUnit();

			foreach ($this->scrapeNutrients() as $nutrientCode => $nutrientAmountWithUnit) {
				ProductNutrient::upsert([
					'productId' => $this->getId(),
					'nutrientCode' => $nutrientCode,
				], [
					'timeCreated' => new \Katu\Utils\DateTime,
				], [
					'timeUpdated' => new \Katu\Utils\DateTime,
					'nutrientAmount' => $nutrientAmountWithUnit->amount,
					'nutrientUnit' => $nutrientAmountWithUnit->unit,
					'ingredientAmount' => $productAmountWithUnit->amount,
					'ingredientUnit' => $productAmountWithUnit->unit,
				]);
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

	public function scrapeAllergenCodes() {
		$allergenCodes = [];

		$configFileName = realpath(dirname(__FILE__) . '/../../Config/allergens.yaml');
		$config = \Spyc::YAMLLoad(file_get_contents($configFileName));

		foreach ($this->scrapeAllergensInContents() as $productAllergenText) {

			if (in_array($productAllergenText, $config['ignore'])) {
				continue;
			}

			foreach ($config['texts'] as $allergenCode => $allergenTexts) {
				foreach ($allergenTexts as $allergenText) {

					if (strpos($productAllergenText, $allergenText) !== false) {
						$allergenCodes[] = $allergenCode;
						continue 3;
					}

				}
			}

			var_dump($productAllergenText);

		}

		return array_values(array_unique($allergenCodes));
	}

	public function loadAllergens() {
		foreach ($this->scrapeAllergenCodes() as $allergenCode) {
			ProductAllergen::upsert([
				'productId' => $this->getId(),
				'allergenCode' => $allergenCode,
			], [
				'timeCreated' => new \Katu\Utils\DateTime,
			]);
		}

		return true;
	}


















































	public function importPrice() {
		$scrapedTescoProductPrice = ProductPrice::create($this, \App\Models\Currency::getOneBy([
			'code' => 'CZK',
		]));

		$product = $this->getChakulaProduct();
		$productPrice = $product->getPrice();

		// Price per item.
		$scrapedTescoProductPrice->update('pricePerItem', (float) $productPrice->price->amount);

		// Price per quantity.
		$scrapedTescoProductPrice->update('pricePerUnit', (float) $productPrice->pricePerQuantity->price->amount);
		$scrapedTescoProductPrice->update('unitAbbr', (string) $productPrice->pricePerQuantity->quantity->unit);

		// Price per base unit.
		switch ($scrapedTescoProductPrice->unitAbbr) {
			case 'kg' :

				$scrapedTescoProductPrice->update('pricePerPracticalUnit', $scrapedTescoProductPrice->pricePerUnit / 1000);
				$scrapedTescoProductPrice->update('practicalUnitId', \App\Models\PracticalUnit::getOneBy(['abbr' => 'g'])->getId());

			break;
			case 'l' :

				$scrapedTescoProductPrice->update('pricePerPracticalUnit', $scrapedTescoProductPrice->pricePerUnit / 1000);
				$scrapedTescoProductPrice->update('practicalUnitId', \App\Models\PracticalUnit::getOneBy(['abbr' => 'ml'])->getId());

			break;
			case 'Kus' :

				$scrapedTescoProductPrice->update('pricePerPracticalUnit', $scrapedTescoProductPrice->pricePerItem);
				$scrapedTescoProductPrice->update('practicalUnitId', \App\Models\PracticalUnit::getOneBy(['abbr' => 'ks'])->getId());

			break;
			default :
				var_dump($this, $productPrice); die;
			break;
		}

		$scrapedTescoProductPrice->save();

		$this->update('timeImportedPrice', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}











	public function importEan() {
		$this->update('ean', $this->getChakulaProduct()->getEan());
		$this->save();

		return true;
	}

}
