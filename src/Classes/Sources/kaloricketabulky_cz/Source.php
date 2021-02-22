<?php

namespace Deli\Classes\Sources\kaloricketabulky_cz;

class Source extends \Deli\Classes\Sources\Source
{
	public function loadProducts()
	{
		@ini_set('memory_limit', '512M');

		try {
			$lock = new \Katu\Tools\Locks\Lock(static::CACHE_TIMEOUT, [__CLASS__, __FUNCTION__], function () {
				$pages = 1;
				$page = 1;
				$limit = 50;

				while ($page <= $pages) {
					$res = \Katu\Cache\General::get([__CLASS__, __FUNCTION__, __LINE__], static::CACHE_TIMEOUT, function ($page, $limit) {
						$url = \Katu\Types\TUrl::make('https://www.kaloricketabulky.cz/foodstuff/normal/filter', [
							'format' => 'json',
							'page' => $page,
							'limit' => $limit,
						]);

						$res = \Katu\Cache\Url::get($url, static::CACHE_TIMEOUT);

						return $res;
					}, $page, $limit);

					$pages = ceil($res->count / $limit);
					$page++;

					foreach ($res->data as $item) {
						$product = \Deli\Models\Product::upsert([
							'source' => $this->getCode(),
							'uri' => '/' . $item->url,
						], [
							'timeCreated' => new \Katu\Tools\DateTime\DateTime,
							'remoteId' => $item->id,
						]);

						$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'baseAmount', 100);
						$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'baseUnit', 'g');
					}
				}
			});
			$lock->run();
		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}
}
