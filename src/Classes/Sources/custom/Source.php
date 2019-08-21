<?php

namespace Deli\Classes\Sources\custom;

class Source extends \Deli\Classes\Sources\Source {

	const HAS_PRODUCT_LOADING    = false;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_ALLERGENS  = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_NUTRIENTS  = false;
	const HAS_PRODUCT_PRICES     = false;










	static function loadNutrients() {
		$property = $this->getProductProperty('nutrients');
		if ($property) {

			$array = \Katu\Utils\JSON::decodeAsArray($property->value);
			if ($array) {

				foreach ($array as $nutrientCode => $rawNutrientUnitWithAmount) {
					$nutrientAmountWithUnit = new \Deli\Classes\AmountWithUnit($rawNutrientUnitWithAmount['amount'], $rawNutrientUnitWithAmount['unit']);
					$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, $nutrientCode, $nutrientAmountWithUnit);
				}

			}

		}

		return true;
	}

	static function loadAllergens() {
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
