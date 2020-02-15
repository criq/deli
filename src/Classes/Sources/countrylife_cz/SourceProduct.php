<?php

namespace Deli\Classes\Sources\countrylife_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct
{
	/****************************************************************************
	 * Product information.
	 */
	public function loadProductInfos()
	{
		$productInfos = new \Deli\Classes\ProductInfos;

		foreach (array_filter(array_map('trim', explode('<h3>', $this->getDOM()->filter('#content')->html()))) as $line) {
			try {
				list($title, $text) = explode('</h3>', $line);
				$productInfos->add(new \Deli\Classes\ProductInfo($title, $text));
			} catch (\Throwable $e) {
				// Nevermind.
			}
		}

		return $productInfos;
	}

	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens()
	{
		return \Deli\Models\ProductAllergen::getCodesFromStrings([
			$this->loadProductInfos()->filterByTitle('Složení')[0]->text ?? null,
			$this->loadProductInfos()->filterByTitle('Alergeny')[0]->text ?? null,
		]);
	}

	/****************************************************************************
	 * Product amount with unit.
	 */
	public function loadProductAmountWithUnit()
	{
		$el = $this->getDOM()->filter('#popis-slozeni .ca-box h3');
		if ($el->count()) {
			return \Deli\Classes\AmountWithUnit::createFromString($el->text());
		}

		return false;
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadNutrients()
	{
		return $this->getDOM()->filter('#popis-slozeni .ca-box .table-content tr')->each(function ($e) {
			if (preg_match('/^Energetická hodnota\s*([0-9\s\,\.]+)\s*kJ\s*\/\s*([0-9\s\,\.]+)\s*kcal$/u', trim($e->text()), $match)) {
				return new \Deli\Classes\NutrientAmountWithUnit('energy', new \Deli\Classes\AmountWithUnit($match[1], 'kJ'));
			} else {
				return \Deli\Classes\NutrientAmountWithUnit::createFromStrings($e->filter('th')->text(), $e->filter('td')->text());
			}
		});
	}
}
