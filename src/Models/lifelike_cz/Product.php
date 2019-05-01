<?php

namespace Deli\Models\lifelike_cz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_lifelike_cz_products';
	const SOURCE = 'lifelike_cz';
	const XML_URL = 'https://www.lifelike.cz/feed-heureka-cz.xml';

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
		@ini_set('memory_limit', '512M');

		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

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

	public function load() {
		try {

			$this->loadNutrients();

			$this->update('isAvailable', 1);

		} catch (\Exception $e) {

			var_dump($e);die;
			$this->update('isAvailable', 0);

		}

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function loadNutrients() {
		$description = (new \Katu\Types\TString($this->getProductPropertyValue('description')))->normalizeSpaces();

		if (preg_match('/Nutriční hodnoty na (?<amount>[0-9]+)(?<unit>g|ml)/', $description, $match)) {

			echo($description);

			$productAmountWithUnit = new \Effekt\AmountWithUnit($match['amount'], $match['unit']);

			if (preg_match('/([0-9]+)\s*kJ/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'energy', new \Effekt\AmountWithUnit($match[1], 'kJ'), $productAmountWithUnit);
			}

			if (preg_match('/([0-9]+)\s*kcal/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'calories', new \Effekt\AmountWithUnit($match[1], 'kcal'), $productAmountWithUnit);
			}

			if (preg_match('/Bílkoviny\s*:\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'proteins', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
			}

			if (preg_match('/Sacharidy\s*\/\s*cukry:\s*([0-9,]+)\s*g\s*\/\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'carbs', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'sugar', new \Effekt\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			} elseif (preg_match('/Sacharidy\s*\/\s*z\s*toho\s*cukry\s*:\s*([0-9,]+)\s*g\s*\/\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'carbs', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'sugar', new \Effekt\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			} elseif (preg_match('/Sacharidy\s*:\s*([0-9,]+)\s*g\s*z\s*toho\s*cukry\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'carbs', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'sugar', new \Effekt\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			}

			if (preg_match('/Tuky\s*\/\s*Nasycené\s*:\s*([0-9,]+)\s*g\s*\/\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fats', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'saturatedFattyAcids', new \Effekt\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			} elseif (preg_match('/Tuky\s*\/\s*z\s*toho\s*nasycené\s*:\s*([0-9,]+)\s*g\s*\/\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fats', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'saturatedFattyAcids', new \Effekt\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			} elseif (preg_match('/Tuky\s*:\s*([0-9,]+)\s*g\s*z\s*toho\s*nasycené\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fats', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'saturatedFattyAcids', new \Effekt\AmountWithUnit($match[2], 'g'), $productAmountWithUnit);
			}

			if (preg_match('/Vláknina\s*:\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fiber', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
			}

			if (preg_match('/Sůl\s*:\s*([0-9,]+)\s*g/x', $description, $match)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'salt', new \Effekt\AmountWithUnit($match[1], 'g'), $productAmountWithUnit);
			}

		}

		return true;
	}

	static function loadProductPrices() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				$xml = static::loadXml();
				foreach ($xml->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {

						$product = static::makeProductFromXml($item);
						if ($product->shouldLoadProductPrice()) {

							$product->update('timeAttemptedPrice', new \Katu\Utils\DateTime);
							$product->save();

							$pricePerProduct = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
							$pricePerUnit = $unitAmount = $unitCode = null;

							$acceptableUnitCodes = implode('|', ProductPrice::$acceptableUnitCodes);
							if (preg_match("/(([0-9]+)\s*x\s*)?([0-9\.\,]+)\s*($acceptableUnitCodes)/", (string)$item->PRODUCTNAME, $match)) {

								$pricePerUnit = (new \Katu\Types\TString((string)$item->PRICE_VAT))->getAsFloat();
								$unitAmount = (new \Katu\Types\TString((string)$match[2] ?: 1))->getAsFloat() * (new \Katu\Types\TString((string)$match[3]))->getAsFloat();
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
