<?php

namespace Deli\Classes\Sources\fajnejidlo_eu;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct {

	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens() {
		return \Deli\Models\ProductAllergen::getCodesFromStrings([
			$this->getDOM()->filter('#ttmoreinfo')->text(),
		]);
	}

	/****************************************************************************
	 * Product amount with unit.
	 */
	public function loadProductAmountWithUnit() {
		return \Deli\Classes\AmountWithUnit::createFromString($this->getDOM()->filter('#ttdatasheet .table-data-sheet tr')->eq(0)->text());
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadNutrients() {
		return $this->getDOM()->filter('#ttdatasheet .table-data-sheet tr')->each(function($e) {
			return \Deli\Classes\NutrientAmountWithUnit::createFromStrings($e->filter('td')->eq(0)->text(), $e->filter('td')->eq(1)->text());
		});
	}

}
