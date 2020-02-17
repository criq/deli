<?php

namespace Deli\Classes\Sources;

use \Sexy\Sexy as SX;

abstract class Source
{
	const CACHE_TIMEOUT          = 86400;
	const HAS_PRODUCT_ALLERGENS  = false;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_LOADING    = false;
	const HAS_PRODUCT_NUTRIENTS  = false;
	const HAS_PRODUCT_PRICES     = false;
	const LOCK_TIMEOUT           = 3600;
	const SITEMAP_URL            = null;
	const XML_URL                = null;

	public function __toString()
	{
		return $this->getCode();
	}

	public static function createFromCode($code)
	{
		$class = "\\Deli\\Classes\\Sources\\" . $code . "\\Source";

		return new $class;
	}

	public static function getAllSources()
	{
		$dir = new \Katu\Utils\File(__DIR__);
		$sources = array_map(function ($dir) {
			$code = array_slice(explode('/', $dir), -1)[0];

			return static::createFromCode($code);
		}, $dir->getDirs());

		return $sources;
	}

	public static function getCode()
	{
		return explode('\\', get_called_class())[3];
	}

	public function hasXML()
	{
		return defined('static::XML_URL');
	}

	public function hasSitemap()
	{
		return defined('static::SITEMAP_URL');
	}

	public function hasProductLoading()
	{
		return static::HAS_PRODUCT_LOADING;
	}

	public function hasProductDetails()
	{
		return static::HAS_PRODUCT_DETAILS;
	}

	public function hasProductAllergens()
	{
		return static::HAS_PRODUCT_ALLERGENS;
	}

	public function hasProductEmulgators()
	{
		return static::HAS_PRODUCT_EMULGATORS;
	}

	public function hasProductNutrients()
	{
		return static::HAS_PRODUCT_NUTRIENTS;
	}

	public function hasProductPrices()
	{
		return static::HAS_PRODUCT_PRICES;
	}

	/****************************************************************************
	 * Load XML.
	 */
	public static function loadXML($url)
	{
		$src = \Katu\Cache::get([__CLASS__, __FUNCTION__, __LINE__], static::CACHE_TIMEOUT, function ($url) {
			$curl = new \Curl\Curl;
			$curl->setConnectTimeout(3600);
			$curl->setTimeout(3600);
			$curl->get($url);

			if ($curl->error) {
				throw new \Katu\Exceptions\DoNotCacheException;
			}

			return $curl->rawResponse;
		}, $url);

		return new \SimpleXMLElement($src);
	}

	/****************************************************************************
	 * XML items.
	 */
	public static function getXMLItemClass()
	{
		return "\\Deli\\Classes\\Sources\\" . static::getCode() . "\\XML\\Item";
	}

	public static function getXMLItem($xml)
	{
		$class = static::getXMLItemClass();

		return new $class($xml);
	}

	/****************************************************************************
	 * Categories.
	 */
	public static function getRemoteCategoryArray($text)
	{
		return preg_split('/[>\|\/]/', $text);
	}

	public static function getRemoteCategoryJSON($text)
	{
		return \Katu\Utils\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)static::getRemoteCategoryArray($text)))));
	}

	public static function getSanitizedRemoteCategoryJSON($remoteCategory)
	{
		// Is null - return null.
		if ($remoteCategory === null) {
			return null;
		}

		// Is string.
		if (is_string($remoteCategory) && strlen($remoteCategory)) {
			$array = json_decode($remoteCategory);
			if ($array === null) {
				return static::getRemoteCategoryJSON($remoteCategory);
			} else {
				return $remoteCategory;
			}
		}

		return null;
	}

	/****************************************************************************
	 * Load products.
	 */
	public function loadProducts()
	{
		if ($this->hasXML()) {
			return $this->loadProductsFromXML();
		} elseif ($this->hasSitemap()) {
			return $this->loadProductsFromSitemap();
		}

		return null;
	}

	public function loadProductsFromXML()
	{
		@ini_set('memory_limit', '512M');

		try {
			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__, __LINE__], static::LOCK_TIMEOUT, function () {
				$xml = static::loadXml(static::XML_URL);
				foreach ($xml->SHOPITEM as $item) {
					\Katu\Cache::get([__CLASS__, __FUNCTION__, __LINE__], static::CACHE_TIMEOUT, function ($xml) {
						try {
							$item = static::getXMLItem($xml);
							$product = $item->getOrCreateProduct();
						} catch (\Throwable $e) {
							\App\Extensions\ErrorHandler::log($e);
						}
					}, $item->asXML());
				}
			}, !in_array(\Katu\Env::getPlatform(), ['dev']));
		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function loadProductsFromSitemap()
	{
		@ini_set('memory_limit', '512M');

		try {
			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__, __LINE__], static::LOCK_TIMEOUT, function () {
				$xml = static::loadXml(static::SITEMAP_URL);
				foreach ($xml->url as $item) {
					$url = (string)$item->loc;
					$uri = static::getURIFromURL($url);

					$product = \Deli\Models\Product::upsert([
						'source' => $this->getCode(),
						'uri' => $uri,
					], [
						'timeCreated' => new \Katu\Utils\DateTime,
					]);
				}
			}, !in_array(\Katu\Env::getPlatform(), ['dev']));
		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public static function getURIFromURL($url)
	{
		return $url;
	}

	/****************************************************************************
	 * Load product details.
	 */
	public function loadProductDetails()
	{
		$sql = SX::select()
			->from(\Deli\Models\Product::getTable())
			->where(SX::eq(\Deli\Models\Product::getColumn('source'), static::getCode()))
			->where(SX::cmpIsNull(\Deli\Models\Product::getColumn('timeLoadedDetails')))
			->setPage(SX::page(1, 10))
			;

		foreach (\Deli\Models\Product::getBySql($sql) as $product) {
			try {
				$product->getSourceProduct()->loadDetails();
			} catch (\Deli\Exceptions\ProductNotFoundException $e) {
				$product->setUnavailable();
				$product->setTimeLoadedDetails();
			} catch (\Throwable $e) {
				\App\Extensions\ErrorHandler::log($e);
			}
		}

		return true;
	}

	/****************************************************************************
	 * Load allergens.
	 */
	public function loadProductAllergens()
	{
		$sql = SX::select()
			->from(\Deli\Models\Product::getTable())
			->where(SX::eq(\Deli\Models\Product::getColumn('source'), static::getCode()))
			->where(SX::cmpIsNull(\Deli\Models\Product::getColumn('timeLoadedAllergens')))
			->setPage(SX::page(1, 10))
			;

		foreach (\Deli\Models\Product::getBySql($sql) as $product) {
			try {
				$allergens = $product->getSourceProduct()->loadAllergens();
				$product->setProductAllergens(\Deli\Models\ProductAllergen::SOURCE_ORIGIN, $allergens);
				$product->setTimeLoadedAllergens();
			} catch (\Deli\Exceptions\ProductNotFoundException $e) {
				$product->setUnavailable();
				$product->setTimeLoadedAllergens();
			} catch (\Throwable $e) {
				\App\Extensions\ErrorHandler::log($e);
			}
		}

		return true;
	}

	/****************************************************************************
	 * Load nutrients.
	 */
	public function loadProductNutrients()
	{
		$sql = SX::select()
			->from(\Deli\Models\Product::getTable())
			->where(SX::eq(\Deli\Models\Product::getColumn('source'), static::getCode()))
			->where(SX::cmpIsNull(\Deli\Models\Product::getColumn('timeLoadedNutrients')))
			->setPage(SX::page(1, 10))
			;

		foreach (\Deli\Models\Product::getBySql($sql) as $product) {
			try {
				$productAmountWithUnit = $product->getSourceProduct()->loadProductAmountWithUnit();
				$nutrients = $product->getSourceProduct()->loadNutrients();
				if ($productAmountWithUnit instanceof \Deli\Classes\AmountWithUnit && is_array($nutrients)) {
					$product->setProductNutrients(\Deli\Models\ProductNutrient::SOURCE_ORIGIN, $productAmountWithUnit, $nutrients);
				}
				$product->setTimeLoadedNutrients();
			} catch (\Deli\Exceptions\ProductNotFoundException $e) {
				$product->setUnavailable();
				$product->setTimeLoadedNutrients();
			} catch (\Throwable $e) {
				\App\Extensions\ErrorHandler::log($e);
			}
		}

		return true;
	}

	/****************************************************************************
	 * Load emulgators.
	 */
	public function loadProductEmulgators()
	{
		$sql = SX::select()
			->from(\Deli\Models\Product::getTable())
			->where(SX::eq(\Deli\Models\Product::getColumn('source'), static::getCode()))
			->where(SX::cmpIsNull(\Deli\Models\Product::getColumn('timeLoadedEmulgators')))
			->setPage(SX::page(1, 1000))
			;

		foreach (\Deli\Models\Product::getBySql($sql) as $product) {
			try {
				$emulgators = $product->getSourceProduct()->loadEmulgators();
				if ($emulgators) {
					var_dump($emulgators);die;
				}
			} catch (\Deli\Exceptions\ProductNotFoundException $e) {
				$product->setUnavailable();
				$product->setTimeLoadedEmulgators();
			} catch (\Throwable $e) {
				\App\Extensions\ErrorHandler::log($e);
			}
		}

		return true;
	}

	/****************************************************************************
	 * Load prices.
	 */
	public function loadProductPrices()
	{
		if ($this->hasXML()) {
			return $this->loadProductPricesFromXML();
		}

		$sql = SX::select()
			->from(\Deli\Models\Product::getTable())
			->where(SX::eq(\Deli\Models\Product::getColumn('source'), static::getCode()))
			->where(SX::cmpIsNull(\Deli\Models\Product::getColumn('timeLoadedPrice')))
			->setPage(SX::page(1, 10))
			;

		foreach (\Deli\Models\Product::getBySql($sql) as $product) {
			try {
				$product->setTimeAttemptedPrice();

				$price = $product->getSourceProduct()->loadPrice();
				if ($price) {
					$product->setProductPrice('CZK', $price);
					$product->setTimeLoadedPrice();
				}
			} catch (\Deli\Exceptions\ProductNotFoundException $e) {
				$product->setUnavailable();
			} catch (\Throwable $e) {
				\App\Extensions\ErrorHandler::log($e);
			}
		}

		return true;
	}

	public function loadProductPricesFromXML()
	{
		@ini_set('memory_limit', '512M');

		try {
			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__, __LINE__], static::LOCK_TIMEOUT, function () {
				$xml = static::loadXML(static::XML_URL);
				foreach ($xml->SHOPITEM as $item) {
					\Katu\Cache::get([__CLASS__, __FUNCTION__, __LINE__], static::CACHE_TIMEOUT, function ($xml) {
						try {
							$item = static::getXMLItem($xml);
							$product = $item->getOrCreateProduct();

							if ($product->shouldLoadProductPrice()) {
								$product->update('timeAttemptedPrice', new \Katu\Utils\DateTime);
								$product->save();

								$price = $item->getPrices()->getPrice();
								if ($price) {
									$product->setProductPrice('CZK', $price);
									$product->update('timeLoadedPrice', new \Katu\Utils\DateTime);
									$product->save();
								}
							}
						} catch (\Throwable $e) {
							\App\Extensions\ErrorHandler::log($e);
						}
					}, $item->asXML());
				}
			}, !in_array(\Katu\Env::getPlatform(), ['dev']));
		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}
}
