<?php

namespace Deli\Models\veganza_cz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_veganza_cz_products';
	const SOURCE = 'veganza_cz';
	const XML_URL = 'https://store.veganza.cz/heureka.xml';

	static function makeProductFromXML($item) {
		$product = static::upsert([
			'uri' => (string)$item->URL,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'name' => (string)$item->PRODUCT,
			'uri' => (string)$item->URL,
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
						$product = static::makeProductFromXML($item);
					}, static::TIMEOUT, $item);

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	static function loadProductPrices() {
		@ini_set('memory_limit', '512M');

		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				$xml = static::loadXml();
				foreach ($xml->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {

						$product = static::makeProductFromXML($item);
						if ($product->shouldLoadProductPrice()) {

							$product->update('timeAttemptedPrice', new \Katu\Utils\DateTime);
							$product->save();

							$pricePerProduct = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
							$pricePerUnit = $unitAmount = $unitCode = null;

							if (preg_match('/(([0-9\.\,]+)\s*x\s*)?([0-9\.\,]+)\s*(g|mg|kg|ml|l)/', (new \Katu\Types\TString((string)$item->PRODUCT))->normalizeSpaces(), $match)) {

								$pricePerUnit = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
								$unitAmount = (new \Katu\Types\TString(ltrim((string)$match[2], '.') ?: 1))->getAsFloat() * (new \Katu\Types\TString((string)$match[3]))->getAsFloat();
								$unitCode = trim($match[4]);

							}

							$product->setProductPrice('CZK', $pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);
							$product->update('timeLoadedPrice', new \Katu\Utils\DateTime);
							$product->save();

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
