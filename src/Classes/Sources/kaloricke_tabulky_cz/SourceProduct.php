<?php

namespace Deli\Classes\Sources\kaloricke_tabulky_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct {

	public function getURL() {
		return 'http://www.kaloricke-tabulky.cz' . $this->getProduct()->uri;
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadProductAmountWithUnit() {
		return new \Deli\Classes\AmountWithUnit($this->getProduct()->getProductPropertyValue('baseAmount'), $this->getProduct()->getProductPropertyValue('baseUnit'));
	}

	public function loadNutrients() {
		return $this->getDOM()->filter('.nutrition_base .nutrition_box')->each(function($e) {
			return \Deli\Classes\NutrientAmountWithUnit::createFromStrings($e->filter('.percent_name')->text(), $e->filter('.text')->text());
		});
	}

}
