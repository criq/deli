<?php

namespace Deli\Models\fajnejidlo_eu;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_fajnejidlo_eu_products';
	const SOURCE = 'fajnejidlo_eu';
	const XML_URL = 'https://www.fajnejidlo.eu/xml/heureka_cz.xml';

	static function makeProductFromXml($item) {
		$product = static::upsert([
			'uri' => (string)$item->URL,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'name' => (string)$item->PRODUCTNAME,
			'uri' => (string)$item->URL,
			'ean' => (string)$item->EAN,
			'isAvailable' => 1,
			'remoteId' => (string)$item->ITEM_ID,
			'remoteCategory' => (string)$item->CATEGORYTEXT,
		]);

		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'description', (string)$item->DESCRIPTION);
		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'imageUrl', (string)$item->IMGURL);
		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'manufacturer', (string)$item->MANUFACTURER);

		return $product;
	}

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				@ini_set('memory_limit', '512M');

				\Katu\Utils\Cache::get(function() {

					$xml = static::loadXml();
					$chunks = array_chunk($xml->xpath('//SHOP/SHOPITEM'), 200);
					foreach ($chunks as $chunk) {

						\Katu\Utils\Cache::get(function($chunk) {

							foreach ($chunk as $item) {
								$product = static::makeProductFromXml($item);
							}

						}, static::TIMEOUT, $chunk);

					}

				}, static::TIMEOUT);

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	static function loadProductPrices() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				$xml = static::loadXml();
				foreach ($xml->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {

						$product = static::makeProductFromXml($item);
						if ($product->shouldLoadProductPrice()) {

							$pricePerProduct = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
							$pricePerUnit = $unitAmount = $unitCode = null;

							foreach ($item->PARAM as $param) {

								if ($param->PARAM_NAME == 'Hmotnost') {

									$acceptableUnitCodes = implode('|', ProductPrice::$acceptableUnitCodes);
									if (preg_match("/(([0-9]+)\s*x\s*)?([0-9\.\,]+)\s*($acceptableUnitCodes)/", $param->VAL, $match)) {

										$pricePerUnit = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
										$unitAmount = (new \Katu\Types\TString((string)$match[2] ?: 1))->getAsFloat() * (new \Katu\Types\TString((string)$match[3]))->getAsFloat();
										$unitCode = trim($match[4]);

									}

									$product->setProductPrice('CZK', $pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);
									$product->update('timeLoadedPrice', new \Katu\Utils\DateTime);
									$product->save();

								}

							}

						}

					}, ProductPrice::TIMEOUT, $item);

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function getUrl() {
		return $this->uri;
	}

}
