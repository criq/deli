<?php

// TODO - dodělat

namespace Deli\Classes\Sources\veganza_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_ALLERGENS = false;
	const HAS_PRODUCT_DETAILS = true;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_LOADING = true;
	const HAS_PRODUCT_NUTRIENTS = false;
	const HAS_PRODUCT_PRICES = false;
	const SITEMAP_URL = 'https://store.veganza.cz/sitemap.xml';

	public function loadProducts()
	{
		@ini_set('memory_limit', '512M');

		try {
			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__, __LINE__], static::LOCK_TIMEOUT, function () {
				$xml = static::loadXml(static::SITEMAP_URL);
				foreach ($xml->url as $item) {
					$url = (string)$item->loc;
					$src = \Katu\Cache\Url::get($url, static::CACHE_TIMEOUT);
					$dom = \Katu\Utils\DOM::crawlHtml($src);

					if ($dom->filter('body.type-product')->count()) {
						$product = \Deli\Models\Product::upsert([
							'source' => $this->getCode(),
							'uri' => $url,
						], [
							'timeCreated' => new \Katu\Tools\DateTime\DateTime,
						]);
					}
				}
			}, !in_array(\Katu\Config\Env::getPlatform(), ['dev']));
		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}
}
