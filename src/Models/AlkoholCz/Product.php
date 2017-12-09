<?php

namespace Deli\Models\AlkoholCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_alkohol_cz_products';
	const SOURCE = 'alkohol_cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 120, function() {

				@ini_set('memory_limit', '512M');

				$curl = new \Curl\Curl;
				$src = $curl->get('https://www.alkohol.cz/export/?type=affilcz&hash=CE7bqK2NhDGkFdTQJZWnH6k35f2M4qKR');
				foreach ($src->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {

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
							switch ($param->PARAM_NAME) {
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

					}, static::TIMEOUT, $item);

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

}
