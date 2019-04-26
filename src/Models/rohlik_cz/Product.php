<?php

namespace Deli\Models\rohlik_cz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_rohlik_cz_products';
	const SOURCE = 'rohlik_cz';
	const XML_URL = 'https://www.rohlik.cz/heureka.xml';

	static function makeProductFromXml($item) {
		$product = static::upsert([
			'uri' => (string)$item->URL,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'name' => (string)$item->PRODUCTNAME,
			'ean' => (string)$item->EAN,
			'isAvailable' => 1,
			'remoteId' => (string)$item->ITEM_ID,
			'remoteCategory' => (string)$item->CATEGORYTEXT,
		]);

		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'description', (string)(new \Katu\Types\TString((string)$item->DESCRIPTION))->normalizeSpaces()->trim());
		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'imageUrl', (string)$item->IMGURL);
		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'manufacturer', (string)$item->MANUFACTURER);

		return $product;
	}

	static function buildProductList() {
		@ini_set('memory_limit', '512M');

		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				$xml = static::loadXml();
				foreach ($xml->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {
						$product = static::makeProductFromXml($item);
					}, static::TIMEOUT, $item);

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function getUrl() {
		return $this->uri;
	}

	public function getSrc($timeout = null) {
		if (is_null($timeout)) {
			$timeout = static::TIMEOUT;
		}

		return \Katu\Utils\Cache::getUrl($this->getUrl());
	}

	public function getJsonUrl() {
		return new \Katu\Types\TUrl('https://www.rohlik.cz/services/frontend-service/product/' . $this->remoteId . '/full');
	}

	public function getJson($timeout = null) {
		if (is_null($timeout)) {
			$timeout = static::TIMEOUT;
		}

		return \Katu\Cache\Url::get($this->getJsonUrl(), $timeout);
	}

	public function load() {
		try {

			$this->loadNutrients();

			$this->update('isAvailable', 1);

		} catch (\Exception $e) {

			$this->update('isAvailable', 0);

		}

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function loadNutrients() {
		$json = $this->getJson();

		if (preg_match('/^(?<amount>[0-9]+)\s?(?<unit>[a-z]+)$/', $json->data->product->composition->nutritionalValues->dose, $match)) {

			$productAmountWithUnit = new \Effekt\AmountWithUnit($match['amount'], $match['unit']);

			if (isset($json->data->product->composition->nutritionalValues->energyValueKJ)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'energy', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->energyValueKJ, 'kJ'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->energyValueKcal)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'calories', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->energyValueKcal, 'kcal'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->fats)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fats', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->fats, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->saturatedFattyAcids)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'saturatedFattyAcids', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->saturatedFattyAcids, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->carbohydrates)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'carbs', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->carbohydrates, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->sugars)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'sugar', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->sugars, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->proteins)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'proteins', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->proteins, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->salt)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'salt', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->salt, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->fiber)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fiber', new \Effekt\AmountWithUnit($json->data->product->composition->nutritionalValues->fiber, 'g'), $productAmountWithUnit);
			}

		} else {
			var_dump($json->data->product->composition->nutritionalValues->dose);die;
		}
	}

	public function loadPrice() {
		$this->update('timeAttemptedPrice', new \Katu\Utils\DateTime);
		$this->save();

		try {

			$json = $this->getJson();

			$currency = $json->data->product->currency;
			$pricePerProduct = (new \Katu\Types\TString($json->data->product->price))->getAsFloat();
			$pricePerUnit = $unitAmount = $unitCode = null;

			try {

				$amountWithUnit = \Deli\Models\ProductPrice::getUnitAmountWithCode($json->data->product->textualAmount);
				if (!$amountWithUnit) {
					$amountWithUnit = \Deli\Models\ProductPrice::getUnitAmountWithCode($json->data->product->productName);
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
