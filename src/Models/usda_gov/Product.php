<?php

namespace Deli\Models\usda_gov;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_usda_gov_products';
	const SOURCE = 'usda_gov';

	const TIMEOUT = 14515200;

	static function readTextFileToLines($fileName) {
		$lines = [];

		$handle = fopen($fileName, 'r');
		while (!feof($handle)) {
			$line = fgets($handle);
			if (trim($line)) {
				$lines[] = $line;
			}
		}

		return $lines;
	}

	static function readTextFileToArray($fileName) {
		return array_map('static::getTextFileLineArray', array_map('static::sanitizeTextFileLine', static::readTextFileToLines($fileName)));
	}

	static function sanitizeTextFileLine($line) {
		return iconv('iso-8859-1', 'utf-8', trim($line));
	}

	static function getTextFileLineArray($line) {
		return array_map(function($line) {
			return trim($line, '~');
		}, explode('^', $line));
	}

	static function buildProductList() {
		@ini_set('memory_limit', '512M');

		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				$categoryFileName = realpath(dirname(__FILE__) . '/../../Resources/UsdaGov/sr28asc/FD_GROUP.txt');
				$categories = [];
				foreach (static::readTextFileToArray($categoryFileName) as $row) {
					$categories[$row[0]] = $row[1];
				}
				#var_dump($categories);

				$productFileName = realpath(dirname(__FILE__) . '/../../Resources/UsdaGov/sr28asc/FOOD_DES.txt');
				$products = static::readTextFileToArray($productFileName);
				foreach ($products as $productLine) {

					$product = static::upsert([
						'uri' => $productLine[0],
					], [
						'timeCreated' => new \Katu\Utils\DateTime,
					], [
						'originalName' => $productLine[2],
					]);

					// TODO
					$product->setRemoteCategory($categories[$productLine[1]], 'remoteOriginalCategory');
					$product->save();

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

	public function load() {
		$this->loadName();
		$this->loadCategory();
		$this->loadNutrients();

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function loadName() {
		$translation = (new \Deli\Classes\Translation('en', 'cs', $this->originalName))->translate();
		if ($translation) {
			$this->update('name', $translation);
			$this->save();
		}

		return true;
	}

	public function loadCategory() {
		$categories = [];
		foreach (\Katu\Utils\JSON::decodeAsArray($this->remoteOriginalCategory) as $remoteOriginalCategory) {
			$translation = (new \Deli\Classes\Translation('en', 'cs', $remoteOriginalCategory))->translate();
			if ($translation) {
				$categories[] = $translation;
			}
		}

		// TODO
		$this->setRemoteCategory($categories);
		$this->save();

		return true;
	}

	static function getRawNutrientsDefinitions() {
		$nutrientFileName = realpath(dirname(__FILE__) . '/../../Resources/UsdaGov/sr28asc/NUTR_DEF.txt');
		$nutrients = [];
		foreach (static::readTextFileToArray($nutrientFileName) as $row) {
			$nutrients[$row[0]] = [
				'name' => $row[3],
				'unit' => $row[1],
			];
		}

		return $nutrients;
	}

	public function scrapeNutrients() {
		$nutrientNameMap = [
			'Protein' => 'proteins',
			'Total lipid (fat)' => 'fats',
			'Carbohydrate, by difference' => 'carbs',
			'Energy' => 'energy',
			'Ash' => 'ash',
			'Alcohol, ethyl' => 'alcohol',
			'Water' => 'water',
			'Caffeine' => 'caffeine',
			'Theobromine' => 'theobromine',
			'Sugars, total' => 'sugar',
			'Fiber, total dietary' => 'fiber',
			'Calcium, Ca' => 'calcium',
			'Iron, Fe' => 'iron',
			'Magnesium, Mg' => 'magnesium',
			'Phosphorus, P' => 'phosphorus',
			'Potassium, K' => 'potassium',
			'Sodium, Na' => 'sodium',
			'Zinc, Zn' => 'zinc',
			'Copper, Cu' => 'copper',
			'Fluoride, F' => 'fluoride',
			'Manganese, Mn' => 'manganese',
			'Selenium, Se' => 'selenium',
			'Retinol' => 'retinol',
			'Vitamin A, IU' => 'vitaminA',
			'Carotene, alpha' => 'alphaCarotene',
			'Carotene, beta' => 'betaCarotene',
			'Vitamin E (alpha-tocopherol)' => 'vitaminE',
			'Vitamin D' => 'vitaminD',
			'Vitamin C, total ascorbic acid' => 'vitaminC',
			'Thiamin' => 'vitaminB1',
			'Riboflavin' => 'vitaminB2',
			'Niacin' => 'vitaminB3',
			'Pantothenic acid' => 'vitaminB5',
			'Vitamin B-6' => 'vitaminB6',
			'Folate, total' => 'vitaminB9',
			'Vitamin B-12' => 'vitaminB12',
			'Vitamin E, added' => 'vitaminE',
			'Vitamin K (phylloquinone)' => 'vitaminK',
			'Cholesterol' => 'cholesterol',
			'Fatty acids, total saturated' => 'saturatedFattyAcids',
			'Fatty acids, total trans' => 'transFattyAcids',
			'Fatty acids, total monounsaturated' => 'monounsaturatedFattyAcids',
			'Fatty acids, total polyunsaturated' => 'polyunsaturatedFattyAcids',
		];

		$rawNutrientDefinitions = static::getRawNutrientsDefinitions();

		$rawNutrients = RawNutrient::getBy([
			'productUri' => $this->uri,
		]);

		$nutrients = [];
		foreach ($rawNutrients as $rawNutrient) {

			if (isset($rawNutrientDefinitions[$rawNutrient->nutrientUri])) {
				$rawNutrientDefinition = $rawNutrientDefinitions[$rawNutrient->nutrientUri];
				if (isset($nutrientNameMap[$rawNutrientDefinition['name']])) {

					$nutrientCode = $nutrientNameMap[$rawNutrientDefinition['name']];

					switch ($rawNutrientDefinition['unit']) {
						case 'mg' :
							$nutrientAmount = (new \Katu\Types\TString($rawNutrient->nutrientAmount))->getAsFloat() * .001;
							$nutrientUnit = 'g';
						break;
						case 'µg' :
							$nutrientAmount = (new \Katu\Types\TString($rawNutrient->nutrientAmount))->getAsFloat() * .000001;
							$nutrientUnit = 'g';
						break;
						default :
							$nutrientAmount = (new \Katu\Types\TString($rawNutrient->nutrientAmount))->getAsFloat();
							$nutrientUnit = $rawNutrientDefinition['unit'];
						break;
					}

					$nutrients[$nutrientCode] = new \Effekt\AmountWithUnit($nutrientAmount, $nutrientUnit);

				}
			}

		}

		return $nutrients;
	}

	public function getProductAmountWithUnit() {
		return new \Effekt\AmountWithUnit(100, 'g');
	}

	public function loadNutrients() {
		try {

			$productAmountWithUnit = $this->getProductAmountWithUnit();
			foreach ($this->scrapeNutrients() as $nutrientCode => $nutrientAmountWithUnit) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, $nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit);
			}

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

}
