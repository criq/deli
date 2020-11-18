<?php

namespace Deli\Classes\Sources\rohlik_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct
{
	const CACHE_TIMEOUT = 86400;

	public function getJSONURL()
	{
		return new \Katu\Types\TUrl('https://www.rohlik.cz/services/frontend-service/product/' . $this->getProduct()->remoteId . '/full');
	}

	public function getJSON()
	{
		$curl = new \Curl\Curl;
		$res = $curl->get($this->getJSONURL());

		if ($res->messages[0]->messageCode ?? null && $res->messages[0]->messageCode == 'productListService.product_not_enabled_exception') {
			throw new \Deli\Exceptions\ProductUnavailableException;
		}

		if ($res->data->product->archived ?? null) {
			throw new \Deli\Exceptions\ProductUnavailableException;
		}

		return $res;
	}

	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens()
	{
		return \Deli\Models\ProductAllergen::getCodesFromStrings($this->getJSON()->data->product->composition->allergens->contained ?? []);
	}

	/****************************************************************************
	 * Product amount with unit.
	 */
	public function loadProductAmountWithUnit()
	{
		return \Deli\Classes\AmountWithUnit::createFromString((string)($this->getJSON()->data->product->composition->nutritionalValues->dose ?? null));
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function loadNutrients()
	{
		$json = $this->getJSON();

		$nutrients = [
			new \Deli\Classes\NutrientAmountWithUnit('energy', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->energyValueKJ ?? null, 'kJ')),
			new \Deli\Classes\NutrientAmountWithUnit('fats', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->fats ?? null, 'g')),
			new \Deli\Classes\NutrientAmountWithUnit('saturatedFattyAcids', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->saturatedFattyAcids ?? null, 'g')),
			new \Deli\Classes\NutrientAmountWithUnit('carbs', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->carbohydrates ?? null, 'g')),
			new \Deli\Classes\NutrientAmountWithUnit('sugar', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->sugars ?? null, 'g')),
			new \Deli\Classes\NutrientAmountWithUnit('proteins', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->proteins ?? null, 'g')),
			new \Deli\Classes\NutrientAmountWithUnit('salt', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->salt ?? null, 'g')),
			new \Deli\Classes\NutrientAmountWithUnit('fiber', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->fiber ?? null, 'g')),
		];

		return $nutrients;
	}

	/****************************************************************************
	 * Emulgators.
	 */
	public function loadEmulgators()
	{
		$json = $this->getJSON();

		$emulgators = [];

		if (isset($json->data->product->composition->ingredients)) {
			foreach ($json->data->product->composition->ingredients as $ingredient) {
				if (isset($ingredient->code) && preg_match('/^E[0-9]+/i', $ingredient->code)) {
					$emulgators[] = \Deli\Models\Emulgator::getOrCreateByCode(preg_replace('/[^a-z0-9]/i', null, $ingredient->code));
				}
			}
		}

		return $emulgators;
	}

	/****************************************************************************
	 * Price.
	 */
	public function loadPrice()
	{
		$json = $this->getJSON();

		$price = new \Deli\Classes\Price($json->data->product->price->full, $json->data->product->textualAmount);

		return $price;
	}
}
