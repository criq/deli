<?php

namespace Deli\Classes\Sources\itesco_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct {

	public function getURL() {
		return 'https://nakup.itesco.cz/groceries/cs-CZ/products/' . $this->getProduct()->uri;
	}

	/****************************************************************************
	 * Product information.
	 */
	public function loadProductInfos() {
		return new \Deli\Classes\ProductInfos($this->getDOM()->filter('.brand-bank--brand-info .groupItem')->each(function($e) {
			return new \Deli\Classes\ProductInfo($e->filter('h3')->text(), preg_replace("/<h3>.+<\/h3>/", null, $e->html()));
		}));
	}

	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens() {
		return \Deli\Models\ProductAllergen::getCodesFromStrings([
			strip_tags($this->loadProductInfos()->filterByTitle('Složení')[0]->text),
		]);
	}

	/****************************************************************************
	 * Product amount with unit.
	 */
	public function loadProductAmountWithUnit() {
		try {
			$dom = \Katu\Utils\DOM::crawlHtml($this->loadProductInfos()->filterByTitle('Výživové hodnoty')[0]->text);
			return \Deli\Classes\AmountWithUnit::createFromString($dom->filter('table thead th')->eq(1)->html());
		} catch (\Throwable $e) {
			return false;
		}
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadNutrients() {
		try {
			$dom = \Katu\Utils\DOM::crawlHtml($this->loadProductInfos()->filterByTitle('Výživové hodnoty')[0]->text);
			return $dom->filter('tbody tr')->each(function($e) {
				$nutrientName = $e->filter('td')->eq(0)->text();
				$amountWithUnitString = explode('/', $e->filter('td')->eq(1)->text())[0];
				return \Deli\Classes\NutrientAmountWithUnit::createFromStrings($nutrientName, $amountWithUnitString);
			});
		} catch (\Throwable $e) {
			return false;
		}
	}

	/****************************************************************************
	 * Price.
	 */
	public function loadPrice() {
		var_dump(\Deli\Classes\AmountWithUnit::createFromString($this->getDOM()->filter('.product-overview .price-per-sellable-unit')->text(), ['Kč']));
		var_dump(\Deli\Classes\AmountWithUnit::createFromString($this->getDOM()->filter('.product-overview .price-per-quantity-weight')->text(), ['Kč']));

		die;
	}

}
