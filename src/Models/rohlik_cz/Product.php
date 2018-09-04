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

	public function loadPrice() {
		$this->update('timeAttemptedPrice', new \Katu\Utils\DateTime);
		$this->save();

		try {

			$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());

			$el = $dom->filter('.product-detail .product-detail__price strong');
			if ($el->count()) {

				if (preg_match('/^(?<price>[0-9\,\s]+)\s+(?<currencyCode>Kč)$/u', trim($el->text()), $match)) {

					$pricePerProduct = (new \Katu\Types\TString($match['price']))->getAsFloat();
					$pricePerUnit = $unitAmount = $unitCode = null;

					$el = $dom->filter('.product-detail .product-detail__amount');
					if ($el->count()) {

						$amountText = trim($el->text());
						if ($amountText) {

							$amountWithUnit = \Deli\Models\ProductPrice::getUnitAmountWithCode($amountText);
							if ($amountWithUnit) {

								$pricePerUnit = $pricePerProduct;
								$unitAmount = $pricePerProduct->amount;
								$unitCode = $pricePerProduct->unit;

							}

						}

					}

					// Zkontrolovat název, pokud to nemá v tagu.
					$this->setProductPrice('CZK', $pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);

				}

			}



			/*



			$productPriceClass = static::getProductPriceTopClass();

			$chakulaProduct = $this->getChakulaProduct();
			$chakulaProductPrice = $chakulaProduct->getPrice($productPriceClass::TIMEOUT);



			$this->setProductPrice($currencyCode, $pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);
			$this->update('timeLoadedPrice', new \Katu\Utils\DateTime);
			$this->save();
			*/

		} catch (\Exception $e) {
			// Nevermind.
		}

		return true;
	}

}
