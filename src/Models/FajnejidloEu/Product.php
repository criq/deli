<?php

namespace Deli\Models\FajnejidloEu;

class Product extends \Deli\Models\Product {

	// https://portadesign1.basecamphq.com/projects/13421499-jidelni-plan/todo_items/223522139/comments#376346830

	const TABLE = 'deli_fajnejidlo_eu_products';
	const SOURCE = 'fajnejidlo_eu';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 120, function() {

				@ini_set('memory_limit', '512M');

				\Katu\Utils\Cache::get(function() {

					$src = \Katu\Utils\Cache::get(function() {

						$curl = new \Curl\Curl;
						$curl->setConnectTimeout(30);
						$curl->setTimeout(300);
						$src = $curl->get('https://www.fajnejidlo.eu/xml/heureka_cz.xml');

						return $curl->rawResponse;

					}, static::TIMEOUT);

					$xml = new \SimpleXMLElement($src);
					$chunks = array_chunk($xml->xpath('//SHOP/SHOPITEM'), 200);
					foreach ($chunks as $chunk) {

						\Katu\Utils\Cache::get(function($chunk) {

							foreach ($chunk as $item) {

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

							}

						}, static::TIMEOUT, $chunk);

					}

				}, static::TIMEOUT);

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

}
