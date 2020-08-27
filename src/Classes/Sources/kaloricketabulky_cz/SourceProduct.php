<?php

namespace Deli\Classes\Sources\kaloricketabulky_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct
{
	public function getURL()
	{
		return 'http://www.kaloricketabulky.cz/' . urlencode(trim($this->getProduct()->uri, '/'));
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadProductAmountWithUnit()
	{
		return new \Deli\Classes\AmountWithUnit($this->getProduct()->getProductPropertyValue('baseAmount'), $this->getProduct()->getProductPropertyValue('baseUnit'));
	}

	public function loadNutrients()
	{
		$src = $this->getSrc();
		$dom = \Katu\Utils\DOM::crawlHtml($src);

		return $dom->filter('[ng-if="foodstuff==null"]')->eq(1)->filter('tr')->each(function ($e) {
			$nutrientCode = \Deli\Models\ProductNutrient::getCodeFromString($e->filter('td')->eq(0)->text());
			$amountWithUnit = \Deli\Classes\AmountWithUnit::createFromString($e->filter('td')->eq(1)->text());
			if (is_string($nutrientCode) && $amountWithUnit instanceof \Deli\Classes\AmountWithUnit) {
				return new \Deli\Classes\NutrientAmountWithUnit($nutrientCode, $amountWithUnit);
			}
		});
	}

	/****************************************************************************
	 * Bordel.
	 */
	// public function loadCategory()
	// {
	// 	$nutrientAssoc = $this->scrapeNutrientAssoc();

	// 	if (isset($nutrientAssoc['Kategorie'])) {
	// 		// TODO
	// 		$this->setRemoteCategory($nutrientAssoc['Kategorie']);
	// 		$this->save();
	// 	}

	// 	return true;
	// }
}
