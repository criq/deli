<?php

namespace Deli\Classes\Sources\lifelike_cz;

class Source extends \Deli\Classes\Sources\Source {

	const HAS_PRODUCT_LOADING    = true;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_ALLERGENS  = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_NUTRIENTS  = false;
	const HAS_PRODUCT_PRICES     = false;

	const XML_URL = 'https://www.lifelike.cz/feed-heureka-cz.xml';




















	static function loadNutrients() {
		$description = (new \Katu\Types\TString($this->getProductPropertyValue('description')))->normalizeSpaces();

		if (preg_match('/Nutriční hodnoty na (?<amount>[0-9]+)(?<unit>g|ml)/', $description, $match)) {

			$productAmountWithUnit = new \Deli\Classes\AmountWithUnit($match['amount'], $match['unit']);

			if (preg_match('/([0-9]+)\s*kJ/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'energy', new \Deli\Classes\AmountWithUnit($match[1], 'kJ'), $productAmountWithUnit);
			}

			if (preg_match('/([0-9]+)\s*kcal/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'calories', new \Deli\Classes\AmountWithUnit($match[1], 'kcal'), $productAmountWithUnit);
			}

			if (preg_match('/Bílkoviny\s*:\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'proteins', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
			}

			if (preg_match('/Sacharidy\s*\/\s*cukry:\s*([0-9,]+)\s*g\s*\/\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'carbs', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'sugar', new \Deli\Classes\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			} elseif (preg_match('/Sacharidy\s*\/\s*z\s*toho\s*cukry\s*:\s*([0-9,]+)\s*g\s*\/\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'carbs', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'sugar', new \Deli\Classes\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			} elseif (preg_match('/Sacharidy\s*:\s*([0-9,]+)\s*g\s*z\s*toho\s*cukry\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'carbs', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'sugar', new \Deli\Classes\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			}

			if (preg_match('/Tuky\s*\/\s*Nasycené\s*:\s*([0-9,]+)\s*g\s*\/\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fats', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'saturatedFattyAcids', new \Deli\Classes\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			} elseif (preg_match('/Tuky\s*\/\s*z\s*toho\s*nasycené\s*:\s*([0-9,]+)\s*g\s*\/\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fats', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'saturatedFattyAcids', new \Deli\Classes\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			} elseif (preg_match('/Tuky\s*:\s*([0-9,]+)\s*g\s*z\s*toho\s*nasycené\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fats', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'saturatedFattyAcids', new \Deli\Classes\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			}

			if (preg_match('/Vláknina\s*:\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fiber', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
			}

			if (preg_match('/Sůl\s*:\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'salt', new \Deli\Classes\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
			}

		}

		return true;
	}

}
