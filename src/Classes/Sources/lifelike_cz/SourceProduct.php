<?php

namespace Deli\Classes\Sources\lifelike_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct
{

	public function getDescriptionHTML()
	{
		$html = null;

		try {
			$html .= $this->getDOM()->filter('#popis')->html();
		} catch (\Throwable $e) {
			// Nevermins.
		}

		try {
			$html .= $this->getDOM()->filter('#nutricni-hodnoty')->html();
		} catch (\Throwable $e) {
			// Nevermind.
		}

		return (new \Katu\Types\TString($html))->normalizeSpaces();
	}

	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens()
	{
		if (preg_match('/<h2>Složení:<\/h2>\s*<p>(?<text>.+)<\/p>/', $this->getDescriptionHTML(), $match)) {
			return \Deli\Models\ProductAllergen::getCodesFromStrings([
				$match['text'],
			]);
		}

		return null;
	}

	/****************************************************************************
	 * Product amount with unit.
	 */
	public function loadProductAmountWithUnit()
	{
		if (preg_match('/Nutriční hodnoty ve (?<amount>[0-9]+) (?<unit>g|ml) výrobku:/u', $this->getDescriptionHTML(), $match)) {
			return new \Deli\Classes\AmountWithUnit($match['amount'], $match['unit']);
		}

		return new \Deli\Classes\AmountWithUnit(100, 'g');
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadNutrients()
	{
		$nutrients = [];

		$text = preg_replace('/\s+/', ' ', strip_tags($this->getDescriptionHTML()));

		if (preg_match('/(?<nutrientName>Energie): ([0-9]+) kcal \/ (?<amountWithUnit>[0-9]+ kJ)/', $text, $match)) {
			$nutrients[] = \Deli\Classes\NutrientAmountWithUnit::createFromStrings($match['nutrientName'], $match['amountWithUnit']);
		}

		if (preg_match('/z toho (?<amountWithUnit>[0-9,]+ g) cukry/', $text, $match)) {
			$nutrients[] = \Deli\Classes\NutrientAmountWithUnit::createFromStrings('cukr', $match['amountWithUnit']);
		}

		if (preg_match('/(?<nutrientName>z toho nasycené) (?<amountWithUnit>[0-9,]+\s?g)/', $text, $match)) {
			$nutrients[] = \Deli\Classes\NutrientAmountWithUnit::createFromStrings($match['nutrientName'], $match['amountWithUnit']);
		}

		if (preg_match_all('/(?<nutrientName>Bílkoviny|Sacharidy|Vláknina|Tuky|Sůl): (?<amountWithUnit>[0-9,]+ g)/', $text, $matches, \PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$nutrients[] = \Deli\Classes\NutrientAmountWithUnit::createFromStrings($match['nutrientName'], $match['amountWithUnit']);
			}
		}

		return $nutrients;
	}
}
