<?php

namespace Deli\Models\Tesco;

class Product extends Deli\Models\Item {

	const TABLE = 'deli_tesco_products';
	const SOURCE = 'itesco.cz';

	static function create($productId) {
		return static::insert([
			'timeCreated' => (string) (new \Katu\Utils\DateTime),
			'productId'   => (string) ($productId),
			'currencyId'  => (int)    (\App\Models\Currency::getOneBy([
				'code' => 'CZK',
			])->getId()),
		]);
	}

	static function make($productId) {
		return static::getOneOrCreateWithList([
			'productId' => $productId,
		], $productId);
	}

	public function getProduct() {
		return new \Chakula\Tesco\Product($this->productId);
	}

	public function getName() {
		return $this->getProduct()->getName();
	}

	public function scrape() {
		return static::transaction(function() {

			try {

				$product = $this->getProduct();
				if ($product->isAvailable()) {

					$this->update('name', $product->getName());
					$this->update('isAvailable', 1);

					$this->setScrapedTescoProductPropertiesFromArray($product->getInfo());

				} else {

					$this->update('isAvailable', 0);

				}

			} catch (\Exception $e) {
				$this->update('isAvailable', 0);
			}

			$this->update('timeScraped', new \Katu\Utils\DateTime);
			$this->save();

			return true;

		});
	}

	public function import() {
		return $this->importAmounts();
	}

	public function importAmounts() {
		$scrapedIngredient = $this->getOrCreateScrapedIngredent();
		$valueArray = $this->getValueArray();
		if ($valueArray) {
			foreach ($valueArray as $value) {
				$scrapedIngredient->setScrapedIngredientAmount($value['code'], $value['amountWithUnit']);
			}
		}

		$this->update('timeImportedAmounts', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function importPrice() {
		$scrapedTescoProductPrice = ProductPrice::create($this, \App\Models\Currency::getOneBy([
			'code' => 'CZK',
		]));

		$product = $this->getProduct();
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

	public function getBaseAmountWithUnit() {
		try {
			$src = $this->getScrapedTescoProductProperty('Výživové hodnoty')->value;
		} catch (\Exception $e) {
			return false;
		}

		$dom = \Katu\Utils\DOM::crawlHtml($src);
		$e = $dom->filter('table thead th[scope="col"]');
		if (!$e->count()) {
			return false;
		}

		if (preg_match('/(?<amount>[0-9]+)\s*(?<unit>g|ml)/ui', $e->html(), $match)) {
			return new \App\Classes\AmountWithUnit($match['amount'], $match['unit']);

		} elseif (preg_match('/Na (?<amount>[0-9]+) výrobku/ui', $e->html(), $match)) {
			return new \App\Classes\AmountWithUnit($match['amount'], 'g');

		} else {
			#var_dump($this->getProduct()->getName()); echo $src; die;
		}

		return false;
	}

	public function getScrapedTescoProductProperties() {
		return ProductProperty::getBy([
			'scrapedTescoProductId' => $this->getId(),
		]);
	}

	public function setScrapedTescoProductPropertiesFromArray($properties) {
		$this->getScrapedTescoProductProperties()->each(function($e) {
			$e->delete();
		});

		foreach ($properties as $property) {
			try {
				$scrapedTescoProductProperty = ProductProperty::make($this, $property->title);
				$scrapedTescoProductProperty->setValue($property->text);
				$scrapedTescoProductProperty->save();
			} catch (\Exception $e) {
				/* Nevermind. */
			}
		}

		return true;
	}

	public function getScrapedTescoProductProperty($name) {
		return ProductProperty::getOneBy([
			'scrapedTescoProductId' => $this->getId(),
			'name' => $name,
		]);
	}

	public function getScrapedTescoProductUnitConversions() {
		return ProductUnitConversion::getBy([
			'scrapedTescoProductId' => $this->getId(),
		]);
	}

	public function getPracticalUnits() {
		$sql = (new \Sexy\Select(\App\Models\PracticalUnit::getTable()))
			->setDistinct()
			->from(\App\Models\PracticalUnit::getTable())
			->joinColumns(\App\Models\PracticalUnit::getIdColumn(), ProductPrice::getColumn('practicalUnitId'))
			->whereEq(\App\Models\PracticalUnit::getColumn('scrapedTescoProductId'), $this->getId())
			;

		return \App\Models\PracticalUnit::getBySql($sql);
	}

	public function getAllergenNamesInContents() {
		$property = $this->getScrapedTescoProductProperty('Složení');
		if ($property) {

			$value = $property->value;
			$value = preg_replace('/<b>(.*)<b>(.*)<\/b>(.*)<\/b>/Uu', '<b>\\1\\2\\3</b>', $value);
			preg_match_all('/<b>(.+)<\/b>/Uu', $value, $matches);

			$allergens = array_values(array_unique(array_map(function($i) {
				return mb_strtolower($i, 'UTF-8');
			}, array_map('strip_tags', $matches[0]))));

			if ($allergens) {
				return $allergens;
			}

		}

		return false;
	}

	public function getNutrientLines() {
		$scrapedTescoProductProperty = $this->getScrapedTescoProductProperty("Výživové hodnoty");
		if ($scrapedTescoProductProperty) {

			$dom = \Katu\Utils\DOM::crawlHtml($scrapedTescoProductProperty->value);

			return $dom->filter('table tbody tr')->each(function($e) {

				try {

					return [
						'name' => trim($e->filter('td:nth-child(1)')->text()),
						'amountWithUnit' => trim($e->filter('td:nth-child(2)')->text()),
					];

				} catch (\Exception $e) {
					return null;
				}

			});

		}
	}

	public function getValueArray() {
		$baseAmountWithUnit = $this->getBaseAmountWithUnit();
		if (!$baseAmountWithUnit) {
			return false;
		}

		$valueArray = [];

		$valueArray[] = [
			'code' => 'base',
			'amountWithUnit' => $baseAmountWithUnit,
		];

		foreach ((array) $this->getNutrientLines() as $nutrientLine) {

			if (!trim($nutrientLine['name']) || !trim($nutrientLine['amountWithUnit'])) {
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

			if (preg_match($preg, $nutrientLine['amountWithUnit'])) {
				continue;
			}
			if (preg_match($preg, $nutrientLine['name'])) {
				continue;
			}

			if ($nutrientLine['name'] == 'Energetická hodnota' && $nutrientLine['amountWithUnit'] == 'kJ/kcal') {
				continue;
			}

			/* Energy ***************************************************************/

			if (preg_match('/(energetická|energie|energeická hodnota|energy|výživová hodnota|energia|energická hodnota|energ\. hodnota|energeie)/ui', $nutrientLine['name'])) {

				if (preg_match('/([0-9\.\,]+)\s*kJ\s*\/\s*([0-9\.\,]+)\s*kcal/', $nutrientLine['amountWithUnit'], $match)) {

					$valueArray[] = [
						'code' => 'energy',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[1], 'kJ'),
					];
					$valueArray[] = [
						'code' => 'calories',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[2], 'kcal'),
					];

				} elseif (preg_match('/([0-9\.\,]+)\s*kcal\s*\/\s*([0-9\.\,]+)\s*kJ/', $nutrientLine['amountWithUnit'], $match)) {

					$valueArray[] = [
						'code' => 'calories',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[1], 'kcal'),
					];
					$valueArray[] = [
						'code' => 'calories',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[2], 'kJ'),
					];

				} elseif (preg_match('/([0-9\.\,]+)\s*kJ/', $nutrientLine['amountWithUnit'], $match)) {

					$valueArray[] = [
						'code' => 'energy',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[1], 'kJ'),
					];

				} elseif (preg_match('/([0-9\.\,]+)\s*kcal/', $nutrientLine['amountWithUnit'], $match)) {

					$valueArray[] = [
						'code' => 'calories',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[1], 'kcal'),
					];

				} elseif (preg_match('/(Energie \(kJ\)|Energie kJ)/', $nutrientLine['name']) && preg_match('/([0-9\.\,]+)/', $nutrientLine['amountWithUnit'], $match)) {

					$valueArray[] = [
						'code' => 'energy',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[1], 'kJ'),
					];

				} elseif (preg_match('/(Energie \(kcal\)|Energie kcal)/', $nutrientLine['name']) && preg_match('/([0-9\.\,]+)/', $nutrientLine['amountWithUnit'], $match)) {

					$valueArray[] = [
						'code' => 'calories',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[1], 'kcal'),
					];

				} elseif (preg_match('/Energetická hodnota \(kJ\s*\/\s*kcal\)/', $nutrientLine['name']) && preg_match('/([0-9\.\,]+)\s*\/\s*([0-9\.\,]+)/', $nutrientLine['amountWithUnit'], $match)) {

					$valueArray[] = [
						'code' => 'energy',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[1], 'kJ'),
					];
					$valueArray[] = [
						'code' => 'calories',
						'amountWithUnit' => new \App\Classes\AmountWithUnit($match[2], 'kcal'),
					];

				}

			} else {

				$item = [
					'code' => null,
					'amountWithUnit' => null,
				];

				if (preg_match('/([0-9\.\,]+)\s*(g)?/', $nutrientLine['amountWithUnit'], $match)) {
					$item['amountWithUnit'] = new \App\Classes\AmountWithUnit($match[1], 'g');
				} elseif (preg_match('/([0-9\.\,]+)\s*(mg)?/', $nutrientLine['amountWithUnit'], $match)) {
					$item['amountWithUnit'] = new \App\Classes\AmountWithUnit($match[1], 'mg');
				}

				/* Monounsaturated fatty acids ****************************************/
				if (preg_match('/(mononenasycené)/ui', $nutrientLine['name'])) {
					$item['code'] = 'monounsaturatedFattyAcids';

				/* Polyunsaturated fatty acids ****************************************/
				} elseif (preg_match('/(polynenasycené)/ui', $nutrientLine['name'])) {
					$item['code'] = 'polyunsaturatedFattyAcids';

				/* Saturated fatty acids **********************************************/
				} elseif (preg_match('/(nasycené|nasyc\.|NMK|nasýtené|nas\. mastné|nasc\. mast\.)/ui', $nutrientLine['name'])) {
					$item['code'] = 'saturatedFattyAcids';

				/* Fat ****************************************************************/
				} elseif (preg_match('/(tuk)/ui', $nutrientLine['name'])) {
					$item['code'] = 'fats';

				/* Carbs **************************************************************/
				} elseif (preg_match('/(sacharid|uhlohydrát)/ui', $nutrientLine['name'])) {
					$item['code'] = 'carbs';

				/* Sugar **************************************************************/
				} elseif (preg_match('/(cukr)/ui', $nutrientLine['name'])) {
					$item['code'] = 'sugar';

				/* Fructose ***********************************************************/
				} elseif (preg_match('/(fruktóza)/ui', $nutrientLine['name'])) {
					$item['code'] = 'fructose';

				/* Protein ************************************************************/
				} elseif (preg_match('/(bílkoviny|bílkovina|proteiny)/ui', $nutrientLine['name'])) {
					$item['code'] = 'proteins';

				/* Fiber **************************************************************/
				} elseif (preg_match('/(vláknina)/ui', $nutrientLine['name'])) {
					$item['code'] = 'fiber';

				/* Salt ***************************************************************/
				} elseif (preg_match('/(sůl)/ui', $nutrientLine['name'])) {
					$item['code'] = 'salt';

				/* Calcium ************************************************************/
				} elseif (preg_match('/(vápník)/ui', $nutrientLine['name'])) {
					$item['code'] = 'calcium';

				/* Sodium *************************************************************/
				} elseif (preg_match('/(sodík)/ui', $nutrientLine['name'])) {
					$item['code'] = 'sodium';

				/* Phosphorus *********************************************************/
				} elseif (preg_match('/(fosfor)/ui', $nutrientLine['name'])) {
					$item['code'] = 'phosphorus';

				/* Vitamin A **********************************************************/
				} elseif (preg_match('/(vitam[ií]n a)/ui', $nutrientLine['name'])) {
					$item['code'] = 'vitaminA';

				/* Vitamin B1 *********************************************************/
				} elseif (preg_match('/(vitam[ií]n b1)/ui', $nutrientLine['name'])) {
					$item['code'] = 'vitaminB1';

				/* Vitamin B2 *********************************************************/
				} elseif (preg_match('/(vitam[ií]n b2)/ui', $nutrientLine['name'])) {
					$item['code'] = 'vitaminB2';

				/* Vitamin B6 *********************************************************/
				} elseif (preg_match('/(vitam[ií]n b6)/ui', $nutrientLine['name'])) {
					$item['code'] = 'vitaminB6';

				/* Vitamin C **********************************************************/
				} elseif (preg_match('/(vitam[ií]n c)/ui', $nutrientLine['name'])) {
					$item['code'] = 'vitaminC';

				/* Vitamin D **********************************************************/
				} elseif (preg_match('/(vitam[ií]n d)/ui', $nutrientLine['name'])) {
					$item['code'] = 'vitaminD';

				/* Vitamin E **********************************************************/
				} elseif (preg_match('/(vitam[ií]n e)/ui', $nutrientLine['name'])) {
					$item['code'] = 'vitaminE';

				/* Omega 3 ************************************************************/
				} elseif (preg_match('/(omega 3|ω\-3|ɷ\-3|omega3|omega\-3)/ui', $nutrientLine['name'])) {
					$item['code'] = 'omega3';

				/* Omega 6 ************************************************************/
				} elseif (preg_match('/(omega 6|omega\-6)/ui', $nutrientLine['name'])) {
					$item['code'] = 'omega6';

				}

				if (in_array($nutrientLine['name'], [
					'255 kcal',
					'Omega*',
					'hodnota',
				])) {
					continue;
				} elseif (!$item['code'] || !$item['amountWithUnit']) {
					#var_dump($nutrientLine); die;
				}

				$valueArray[] = $item;

			}

		}

		return $valueArray;
	}

	public function importEan() {
		$this->update('ean', $this->getProduct()->getEan());
		$this->save();

		return true;
	}

}
