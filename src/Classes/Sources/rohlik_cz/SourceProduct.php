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
			throw new \Deli\Exceptions\ProductNotFoundException;
		}
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

	public function loadEmulgators()
	{
		$json = $this->getJSON();

		$emulgators = [];

		if (isset($json->data->product->composition->ingredients)) {
			foreach ($json->data->product->composition->ingredients as $ingredient) {
				if (isset($ingredient->code) && preg_match('/^E[0-9]+/i', $ingredient->code)) {
					$emulgators[] = strtolower(preg_replace('/[^a-z0-9]/i', null, $ingredient->code));
				}
			}
		}

		return $emulgators;
	}

	public function loadPrice()
	{
		try {
			$json = $this->getJSON();

			$currency = $json->data->product->currency;
			$pricePerProduct = (new \Katu\Types\TString($json->data->product->price))->getAsFloat();
			$pricePerUnit = $unitAmount = $unitCode = null;

			try {

				// $pricePerProduct = \Deli\Classes\AmountWithUnit::createFromString($this->getDOM()->filter('.product-overview .price-per-sellable-unit')->text(), ['Kč']);
				// $amountWithUnitString = $this->getProduct()->getName();
				// $price = new \Deli\Classes\Price($pricePerProduct, $amountWithUnitString);

				$amountWithUnit = \Deli\Models\ProductPrice::createFromString($json->data->product->textualAmount);
				if (!$amountWithUnit) {
					$amountWithUnit = \Deli\Models\ProductPrice::createFromString($json->data->product->productName);
				}

				if ($amountWithUnit) {
					$pricePerUnit = $pricePerProduct;
					$unitAmount = $amountWithUnit->amount;
					$unitCode = $amountWithUnit->unit;
				}

			} catch (\Exception $e) {
				// Nevermind.
			}

			$this->setProductPrice($currency, $pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);

		} catch (\Exception $e) {
			// Nevermind.
		}

		return true;
	}

}
