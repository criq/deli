<?php

namespace Deli\Models\SklizenoCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_sklizeno_cz_products';
	const SOURCE = 'sklizeno_cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				@ini_set('memory_limit', '512M');

				$src = (new \Curl\Curl)->get('https://www.sklizeno.cz/heureka.xml');
				foreach ($src->SHOPITEM as $item) {

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

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

}
