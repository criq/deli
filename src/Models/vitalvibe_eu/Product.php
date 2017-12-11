<?php

namespace Deli\Models\vitalvibe_eu;

class Product extends \Deli\Models\Product {

	// https://portadesign1.basecamphq.com/projects/13421499-jidelni-plan/todo_items/223579569/comments#comment_376477145

	const TABLE = 'deli_vitalvibe_eu_products';
	const SOURCE = 'vitalvibe_eu';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 120, function() {

				@ini_set('memory_limit', '512M');

				$curl = new \Curl\Curl;
				$src = $curl->get('http://www.vitalvibe.eu/xml/cs_zbozi_seznam.xml');
				foreach ($src->SHOPITEM as $item) {

					\Katu\Utils\Cache::get(function($item) {

						$product = static::upsert([
							'uri' => (string)$item->URL,
						], [
							'timeCreated' => new \Katu\Utils\DateTime,
						], [
							'name' => (string)$item->PRODUCT,
							'uri' => (string)$item->URL,
							'ean' => (string)$item->EAN,
							'isAvailable' => 1,
							'remoteId' => (string)$item->ITEM_ID,
							'remoteCategory' => (string)$item->CATEGORYTEXT,
						]);

						$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'description', (string)(new \Katu\Types\TString((string)$item->DESCRIPTION))->normalizeSpaces()->trim());
						$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'imageUrl', (string)$item->IMGURL);
						$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'manufacturer', (string)$item->MANUFACTURER);

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

}
