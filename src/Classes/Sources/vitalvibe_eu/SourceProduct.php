<?php

namespace Deli\Classes\Sources\vitalvibe_eu;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct {

	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens() {
		if (preg_match('/<p>\s*<strong>Složení:<\/strong>(?<text>.+)<\/p>/Uu', $this->getDOM()->filter('#podrobnosti')->html(), $match)) {
			return \Deli\Models\ProductAllergen::getCodesFromStrings([
				$match['text'],
			]);
		}

		return null;
	}

	/****************************************************************************
	 * Product amount with unit.
	 */
	public function loadProductAmountWithUnit() {
		if (preg_match('/ve? (?<amount>[0-9]+) (?<unit>g|ml)/', $this->getDOM()->filter('#podrobnosti')->html(), $match)) {
			return \Deli\Classes\AmountWithUnit::createFromString($match[0]);
		}

		return null;
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadNutrients() {
		$nutrients = $this->getDOM()->filter('#podrobnosti table')->each(function($e) {
			if (trim($e->filter('tr')->eq(0)->filter('td')->eq(0)->text()) == "Nutriční hodnoty") {
				return $e->filter('tbody tr')->each(function($e) {
					$nutrientString = trim((new \Katu\Types\TString($e->filter('td')->eq(0)->text()))->normalizeSpaces()->trim(), '*');
					$amountWithUnitString = (string)(new \Katu\Types\TString($e->filter('td')->eq(1)->text()))->normalizeSpaces()->trim();
					switch ($nutrientString) {
						default :
							return \Deli\Classes\NutrientAmountWithUnit::createFromStrings($nutrientString, $amountWithUnitString);
						break;
					}
				});
			}
		});

		return array_values(array_filter(array_flatten($nutrients)));
	}

}
