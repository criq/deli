<?php

namespace Deli\Models\lekarna_cz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_lekarna_cz_products';
	const SOURCE = 'lekarna_cz';
	const XML_URL = 'https://www.lekarna.cz/feed/srovnavace-products.xml?a_box=nmhf5uvw';

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

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 600, function() {

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

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 600, function() {

				@ini_set('memory_limit', '512M');
				@ini_set('display_errors', true);

				\Katu\Utils\Cache::get(function() {

					$xml = static::loadXml();
					$chunks = array_chunk($xml->xpath('//SHOP/SHOPITEM'), 200);
					foreach ($chunks as $chunk) {

						\Katu\Utils\Cache::get(function($chunk) {

							foreach ($chunk as $item) {

								unset($pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);

								$product = static::makeProductFromXml($item);
								$productPrice = $product->getProductPrice();
								if (!$productPrice || !$productPrice->isInTimeout()) {

									if (isset($item->PRICE_VAT)) {

										if (preg_match('/(([0-9\.\,]+)\s*x\s*)?([0-9\.\,]+)\s*(g|mg|kg|ml|l)/', $item->PRODUCTNAME, $match)) {

											$pricePerProduct = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
											$pricePerUnit = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
											$unitAmount = (new \Katu\Types\TString(ltrim((string)$match[2], '.') ?: 1))->getAsFloat() * (new \Katu\Types\TString((string)$match[3]))->getAsFloat();
											$unitCode = trim($match[4]);

										}

										if (isset($pricePerProduct, $pricePerUnit, $unitAmount, $unitCode)) {

											ProductPrice::insert([
												'timeCreated' => new \Katu\Utils\DateTime,
												'productId' => $product->getId(),
												'currencyCode' => 'CZK',
												'pricePerProduct' => $pricePerProduct,
												'pricePerUnit' => $pricePerUnit,
												'unitAmount' => $unitAmount,
												'unitCode' => $unitCode,
											]);

										}

									}

								}

							}

						}, static::TIMEOUT, $chunk);

					}

				}, static::TIMEOUT);

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function getUrl() {
		return $this->uri;
	}

}
