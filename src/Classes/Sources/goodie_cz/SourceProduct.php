<?php

namespace Deli\Classes\Sources\goodie_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct
{
	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens()
	{
		return \Deli\Models\ProductAllergen::getCodesFromStrings([
			$this->getDOM()->filter('.basic-description')->text(),
		]);
	}

	/****************************************************************************
	 * Product amount with unit.
	 */
	public function loadProductAmountWithUnit()
	{
		return \Deli\Classes\AmountWithUnit::createFromString($this->getProduct()->getName());
	}

	/****************************************************************************
	 * Allergens.
	 */
	public function loadNutrients()
	{
		if (preg_match_all('/(?<nutrientName>Energie|Tuky|z toho nas\. mast\. kys\.|Sacharidy|z toho cukry|Bílkoviny|Sůl): (?<amountWithUnit>([0-9]+) (kJ|g))/', $this->getDOM()->filter('.basic-description')->text(), $matches, \PREG_SET_ORDER)) {
			return array_map(function ($match) {
				return \Deli\Classes\NutrientAmountWithUnit::createFromStrings($match['nutrientName'], $match['amountWithUnit']);
			}, $matches);
		}

		return null;
	}
}
