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
