<?php

namespace Deli\Models\custom;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_custom_products';
	const SOURCE = 'custom';

	public function getUrl() {
		$productProperty = $this->getProductProperty('url');
		if ($productProperty) {
			return $productProperty->value;
		}

		return false;
	}

	public function load() {
		$this->loadNutrients();
		$this->loadAllergens();

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function loadNutrients() {
		$property = $this->getProductProperty('nutrients');
		if ($property) {

			$array = \Katu\Utils\JSON::decodeAsArray($property->value);
			if ($array) {

				foreach ($array as $nutrientCode => $rawNutrientUnitWithAmount) {
					$nutrientAmountWithUnit = new \Effekt\AmountWithUnit($rawNutrientUnitWithAmount['amount'], $rawNutrientUnitWithAmount['unit']);
					$this->setProductNutrientIfEmpty(ProductNutrient::SOURCE_ORIGIN, $nutrientCode, $nutrientAmountWithUnit);
				}

			}

		}

		return true;
	}

	public function loadAllergens() {
		$property = $this->getProductProperty('allergens');
		if ($property) {

			$array = \Katu\Utils\JSON::decodeAsArray($property->value);
			if ($array) {

				foreach ($array as $allergenCode) {
					$this->setProductAllergen(ProductAllergen::SOURCE_ORIGIN, $allergenCode);
				}

			}

		}

		return true;
	}


}
