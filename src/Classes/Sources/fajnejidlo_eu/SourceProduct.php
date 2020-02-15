<?php

namespace Deli\Classes\Sources\fajnejidlo_eu;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct
{
	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens()
	{
		$el = $this->getDOM()->filter('#ttmoreinfo');
		if ($el->count()) {
			return \Deli\Models\ProductAllergen::getCodesFromStrings([
				$el->text(),
			]);
		}

		return false;
	}

	/****************************************************************************
	 * Product amount with unit.
	 */
	public function loadProductAmountWithUnit()
	{
		$el = $this->getDOM()->filter('#ttdatasheet .table-data-sheet tr');
		if ($el->count()) {
			return \Deli\Classes\AmountWithUnit::createFromString($el->eq(0)->text());
		}

		return false;
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadNutrients()
	{
		return $this->getDOM()->filter('#ttdatasheet .table-data-sheet tr')->each(function ($e) {
			return \Deli\Classes\NutrientAmountWithUnit::createFromStrings($e->filter('td')->eq(0)->text(), $e->filter('td')->eq(1)->text());
		});
	}
}
