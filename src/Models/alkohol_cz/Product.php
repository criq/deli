<?php

namespace Deli\Models\alkohol_cz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_alkohol_cz_products';
	const SOURCE = 'alkohol_cz';
	const XML_URL = 'https://www.alkohol.cz/export/?type=affilcz&hash=CE7bqK2NhDGkFdTQJZWnH6k35f2M4qKR';

	static function makeProductFromXml($item) {
		$product = static::upsert([
			'remoteId' => $item->ITEM_ID,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'name' => (string)$item->PRODUCT,
			'uri' => (string)$item->URL,
			'isAvailable' => 1,
			'remoteCategory' => (string)$item->CATEGORYTEXT,
		]);

		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'description', (string)$item->DESCRIPTION);
		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'imageUrl', (string)$item->IMGURL);
		$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'manufacturer', (string)$item->MANUFACTURER);

		foreach ($item->PARAM as $param) {
			switch ((string)$param->PARAM_NAME) {
				case 'Země' :
					$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'country', trim($param->VAL));
				break;
				case 'Obsah alkoholu' :
					$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'alcoholContent', (string)$param->VAL);
				break;
				case 'Objem' :
					$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'volume', (string)$param->VAL);
				break;
			}
		}

		return $product;
	}

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				@ini_set('memory_limit', '512M');

				$xml = static::loadXml();
				foreach ($xml->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {
						return static::makeProductFromXml($item);
					}, static::TIMEOUT, $item);

				}

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

					$product = static::makeProductFromXml($item);
					if ($product->shouldLoadProductPrice()) {

						if (isset($item->PRICE)) {

							foreach ($item->PARAM as $param) {
								if ($param->PARAM_NAME == 'Objem') {
									if (preg_match('/^([0-9\.]+)\s*(l)$/', $param->VAL, $match)) {

										ProductPrice::insert([
											'timeCreated' => new \Katu\Utils\DateTime,
											'productId' => $product->getId(),
											'currencyCode' => 'CZK',
											'pricePerProduct' => (new \Katu\Types\TString((string)$item->PRICE))->getAsFloat(),
											'pricePerUnit' => (new \Katu\Types\TString((string)$item->PRICE))->getAsFloat(),
											'unitAmount' => (new \Katu\Types\TString((string)$match[1]))->getAsFloat(),
											'unitCode' => trim($match[2]),
										]);

										$product->update('timeLoadedPrice', new \Katu\Utils\DateTime);
										$product->save();

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
