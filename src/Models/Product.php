<?php

namespace Deli\Models;

use \Sexy\Sexy as SX;
use Deli\AmountWithUnit;

abstract class Product extends \Deli\Model {

	const TIMEOUT = 2419200;

	public function getSource() {
		return static::SOURCE;
	}

	public function getSourceLabel() {
		return static::SOURCE_LABEL;
	}

	public function getName() {
		return $this->name;
	}

	public function setRemoteCategory($source, $property = 'remoteCategory') {
		$this->update($property, \Katu\Utils\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)$source)))));

		return true;
	}

	public function setEan($ean) {
		$this->update('ean', trim($ean) ?: null);

		return true;
	}

	public function setShoppingList($shoppingList) {
		$this->update('shoppingListId', $shoppingList->getId());

		return true;
	}

	public function setShoppingListByName($name) {
		return $this->setShoppingList(ShoppingList::upsert([
			'name' => trim($name),
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		]));
	}

	public function setCategoryByName($name) {
		if (is_string($name)) {
			$name = [$name];
		}

		$category = null;
		foreach ($name as $categoryName) {
			$category = Category::make($category, $categoryName);
		}

		$this->update('categoryId', $category->getId());

		return true;
	}

	public function setProductAllergen($allergenCode) {
		$class = static::getClass();
		$productAllergenClass = $class . 'Allergen';

		return $productAllergenClass::upsert([
			'productId' => $this->getId(),
			'allergenCode' => $allergenCode,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		]);
	}

	public function getProductAmountWithUnit() {
		$class = static::getClass();
		$productNutrientClass = $class . 'Nutrient';

		// Look into existing first.
		$productNutrient = $productNutrientClass::getOneBy([
			'productId' => $this->getId(),
		]);
		if ($productNutrient) {
			return new \Deli\Classes\AmountWithUnit($productNutrient->ingredientAmount, $productNutrient->ingredientUnit);
		}

		// Else scrape.
		if (method_exists($this, 'scrapeProductAmountWithUnit')) {
			$scraped = $this->scrapeProductAmountWithUnit();
			if ($scraped) {
				return $scraped;
			}
		}

		// Default.
		return new \Deli\Classes\AmountWithUnit(100, 'g');
	}

	public function setProductNutrientIfEmpty($nutrientCode, $nutrientAmountWithUnit) {
		$class = static::getClass();
		$productNutrientClass = $class . 'Nutrient';

		$productNutrient = $productNutrientClass::getOneBy([
			'productId' => $this->getId(),
			'nutrientCode' => $nutrientCode,
		]);
		if (!$productNutrient) {
			$productNutrient = $this->setProductNutrient($nutrientCode, $nutrientAmountWithUnit, $this->getProductAmountWithUnit());
		}

		return $productNutrient;
	}

	public function setProductNutrient($nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit) {
		$class = static::getClass();
		$productNutrientClass = $class . 'Nutrient';

		return $productNutrientClass::upsert([
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

	public function getProductNutrients() {
		$class = static::getClass();
		$productNutrientClass = $class . 'Nutrient';

		if (class_exists($productNutrientClass)) {
			return $productNutrientClass::getBy([
				'productId' => $this->getId(),
			]);
		}

		return false;
	}

	static function getForLoadSql() {
		$sql = SX::select()
			->setForTotal()
			->from(static::getTable())
			->where(SX::lgcOr([
				SX::cmpIsNull(static::getColumn('timeLoaded')),
				SX::cmpLessThan(static::getColumn('timeLoaded'), new \Katu\Utils\DateTime('- 1 month')),
			]))
			->orderBy(static::getColumn('timeCreated'))
			;

		return $sql;
	}

	public function getOrCreateScrapedIngredent() {
		return \App\Models\ScrapedIngredient::make(static::SOURCE, $this->getName());
	}

	static function getAllergenCodesFromTexts($texts) {
		$allergenCodes = [];

		$configFileName = realpath(dirname(__FILE__) . '/../Config/allergens.yaml');
		$config = \Spyc::YAMLLoad(file_get_contents($configFileName));

		foreach ($texts as $text) {

			if (in_array($text, $config['ignore'])) {
				continue;
			}

			foreach ($config['texts'] as $allergenCode => $allergenTexts) {
				foreach ($allergenTexts as $allergenText) {

					if (strpos($text, $allergenText) !== false) {
						$allergenCodes[] = $allergenCode;
						continue 3;
					}

				}
			}

			// Dev.
			var_dump($text);

		}

		return array_values(array_unique($allergenCodes));
	}

}
