<?php

namespace Deli\Models\sklizeno_cz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_sklizeno_cz_products';
	const SOURCE = 'sklizeno_cz';
	const XML_URL = 'https://www.sklizeno.cz/heureka.xml';

	static function makeProductFromXml($item) {
		$product = static::upsert([
			'remoteId' => $item->ITEM_ID,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'name' => (string)$item->PRODUCTNAME,
			'uri' => (string)$item->URL,
			'ean' => (string)$item->EAN,
			'isAvailable' => 1,
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

	static function loadProductPrices() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				@ini_set('memory_limit', '512M');

				$xml = static::loadXml();
				foreach ($xml->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {

						unset($pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);

						$product = static::makeProductFromXml($item);
						if ($product->shouldLoadProductPrice()) {

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

								$product->update('timeLoadedPrice', new \Katu\Utils\DateTime);
								$product->save();

							}

						}

					}, static::TIMEOUT, $item);

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}

		die;
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

	public function load() {
		$this->loadNutrients();
		$this->loadAllergens();
		#$this->loadProperties();

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function loadNutrients() {
		$nutrients = $this->scrapeNutrients();

		$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());
		if ($dom->filter('#nutricni-hodnoty tr')->count()) {

			if (preg_match('/na(.+)<span class="unit">(.+)<\/span>/', $dom->filter('#nutricni-hodnoty tr')->eq(0)->filter('th')->eq(1)->html(), $match)) {

				$productAmount = (new \Katu\Types\TString($match[1]))->normalizeSpaces()->trim()->getAsFloat();
				$productUnit = $match[2];
				$productAmountWithUnit = new \Effekt\AmountWithUnit($productAmount, $productUnit);

				try {

					foreach ($this->scrapeNutrients() as $nutrientCode => $nutrientAmountWithUnit) {
						$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, $nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit);
					}

				} catch (\Exception $e) {
					// Nevermind.
				}

			}

		}

		return true;
	}

	public function scrapeNutrients() {
		$src = $this->getSrc();
		$dom = \Katu\Utils\DOM::crawlHtml($src);

		$scrapedNutrients = $dom->filter('#nutricni-hodnoty tr')->each(function($e) {

			if ($e->filter('td')->count()) {
				if (preg_match('/(.+)\(<span class="unit">(.+)<\/span>\)/', $e->filter('td')->eq(0)->html(), $match)) {

					$nutrientName = (string)(new \Katu\Types\TString($match[1]))->normalizeSpaces()->trim();
					$nutrientAmount = (new \Katu\Types\TString($e->filter('td')->eq(1)->html()))->getAsFloat();
					$nutrientUnit = $match[2];

					$nutrientCode = null;
					switch ($nutrientName) {
						case 'Energie'                           : $nutrientCode = 'energy';              break;
						case 'Tuky'                              : $nutrientCode = 'fats';                break;
						case 'Sacharidy'                         : $nutrientCode = 'carbs';               break;
						case 'Bílkoviny'                         : $nutrientCode = 'proteins';            break;
						case 'Cukry v sacharidech'               : $nutrientCode = 'sugar';               break;
						case 'Sůl'                               : $nutrientCode = 'salt';                break;
						case 'Nasycené mastné kyseliny v tucích' : $nutrientCode = 'saturatedFattyAcids'; break;
						case 'Vláknina'                          : $nutrientCode = 'fiber';               break;
					}

					if ($nutrientCode && (($nutrientCode == 'energy' && $nutrientUnit == 'kJ') || $nutrientCode != 'energy')) {
						return [
							'nutrientCode' => $nutrientCode,
							'nutrientAmountWithUnit' => new \Effekt\AmountWithUnit($nutrientAmount, $nutrientUnit),
						];
					}

				}
			}

		});

		$scrapedNutrients = array_values(array_filter($scrapedNutrients));

		$nutrients = [];
		foreach ($scrapedNutrients as $scrapedNutrient) {
			$nutrients[$scrapedNutrient['nutrientCode']] = $scrapedNutrient['nutrientAmountWithUnit'];
		}

		return $nutrients;
	}

	public function loadAllergens() {
		foreach ($this->scrapeAllergens() as $allergen) {
			$this->setProductAllergen(ProductAllergen::SOURCE_ORIGIN, $allergen);
		}

		return true;
	}

	public function scrapeAllergens() {
		$config = \Deli\Models\ProductAllergen::getConfig();

		$allergens = [];

		$src = $this->getSrc();
		if (preg_match('/<a href="#(atribut-(.+))">Pro alergiky<\/a>/', $src, $match)) {

			$dom = \Katu\Utils\DOM::crawlHtml($src);
			if (preg_match_all('/\(([0-9]+)\)/', $dom->filter('#atribut-2')->html(), $matches)) {

				foreach ($matches[1] as $allergenId) {

					if (isset($config['list'][$allergenId]['code'])) {
						$allergens[] = $config['list'][$allergenId]['code'];
					}

				}

			}

		}

		return $allergens;
	}

}
