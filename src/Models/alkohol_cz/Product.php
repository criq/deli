<?php

namespace Deli\Models\alkohol_cz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_alkohol_cz_products';
	const SOURCE = 'alkohol_cz';
	const XML_URL = 'https://www.alkohol.cz/export/?type=affilcz&hash=CE7bqK2NhDGkFdTQJZWnH6k35f2M4qKR';

	static function makeProductFromXML($item) {
		$product = static::upsert([
			'remoteId' => $item->ITEM_ID,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'source' => static::SOURCE,
			'uri' => (string)$item->URL,
			'name' => (string)$item->PRODUCT,
			'originalName' => (string)$item->PRODUCT,
			'remoteCategory' => static::getRemoteCategoryJSON((string)$item->CATEGORYTEXT),
			'originalRemoteCategory' => static::getRemoteCategoryJSON((string)$item->CATEGORYTEXT),
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
		@ini_set('memory_limit', '512M');

		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				$xml = static::loadXml();
				foreach ($xml->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {
						return static::makeProductFromXML($item);
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

					\Katu\Utils\Cache::get(function($item) {

						$product = static::makeProductFromXML($item);
						if ($product->shouldLoadProductPrice()) {

							$product->update('timeAttemptedPrice', new \Katu\Utils\DateTime);
							$product->save();

							$pricePerProduct = (new \Katu\Types\TString((string)$item->PRICE))->getAsFloat();
							$pricePerUnit = $unitAmount = $unitCode = null;

							foreach ($item->PARAM as $param) {

								if ($param->PARAM_NAME == 'Objem') {

									$amountWithUnit = ProductPrice::getUnitAmountWithCode((string)$param->VAL);
									if ($amountWithUnit) {
										$pricePerUnit = $pricePerProduct;
										$unitAmount = $amountWithUnit->amount;
										$unitCode = $amountWithUnit->unit;
									}

								}

							}

							if (!$pricePerUnit || !$unitAmount || !$unitCode) {

								$amountWithUnit = ProductPrice::getUnitAmountWithCode((string)$item->PRODUCT);
								if ($amountWithUnit) {
									$pricePerUnit = $pricePerProduct;
									$unitAmount = $amountWithUnit->amount;
									$unitCode = $amountWithUnit->unit;
								}

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
