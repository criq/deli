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

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 120, function() {

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

				$xml = static::loadXml();
				foreach ($xml->SHOPITEM as $item) {

					$product = static::makeProductFromXml($item);
					$productPrice = $product->getProductPrice();
					if (!$productPrice || !$productPrice->isInTimeout()) {

						if (isset($item->PRICE_VAT)) {

							foreach ($item->PARAM as $param) {

								if ($param->PARAM_NAME == 'Hmotnost') {

									unset($pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);

									if (preg_match('/([0-9]+)\s*x\s*([0-9\.\,]+)\s*(g|kg|ml)/', $param->VAL, $match)) {

										$pricePerProduct = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
										$pricePerUnit = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
										$unitAmount = (new \Katu\Types\TString((string)$match[1]))->getAsFloat() * (new \Katu\Types\TString((string)$match[2]))->getAsFloat();
										$unitCode = trim($match[3]);

									} elseif (preg_match('/([0-9\.\,]+)\s*(g|kg|ml)/', $param->VAL, $match)) {

										$pricePerProduct = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
										$pricePerUnit = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
										$unitAmount = (new \Katu\Types\TString((string)$match[1]))->getAsFloat();
										$unitCode = trim($match[2]);

									} else {

										/*
										$ignore = [
											3,
											1,
										];

										if (!in_array((string)$param->VAL, $ignore)) {
											echo $param->VAL; die;
										}
										*/

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

					}

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
