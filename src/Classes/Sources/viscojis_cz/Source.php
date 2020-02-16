<?php

namespace Deli\Classes\Sources\viscojis_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_ALLERGENS  = false;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_LOADING    = false;
	const HAS_PRODUCT_NUTRIENTS  = false;
	const HAS_PRODUCT_PRICES     = false;

	public function loadProducts()
	{
		@ini_set('memory_limit', '512M');

		try {
			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function () {
				$array = \Katu\Utils\Cache::get(function () {
					$curl = new \Curl\Curl;
					$curl->setBasicAuthentication('jidelniplan', 'modraKarkulka55');
					$res = $curl->get('https://viscokupujes.cz/export/data.json');
					return $res;
				}, static::CACHE_TIMEOUT);

				foreach (array_chunk($array, 100) as $chunk) {
					\Katu\Utils\Cache::get(function ($chunk) {
						foreach ($chunk as $item) {
							if (preg_match('/^tes\-(?<id>[0-9]+)$/', $item->id, $match)) {
								$product = \Deli\Models\Product::upsert([
									'source' => $this->getCode(),
									'uri' => $item->id,
								], [
									'timeCreated' => new \Katu\Utils\DateTime,
								], [
									'timeLoaded' => new \Katu\Utils\DateTime,
									'remoteId' => $match['id'],
									'ean' => $item->bc ?: null,
									'name' => $item->name,
									'isHfcs' => isset($item->gf) ? (bool)$item->gf : false,
									'isPalmOil' => isset($item->po) ? (bool)$item->po : false,
								]);

								// Allergens.
								if (isset($item->a)) {
									$allergens = [
										1  => 'gluten',
										2  => 'crustaceans',
										3  => 'eggs',
										4  => 'fish',
										5  => 'peanuts',
										6  => 'soybeans',
										7  => 'lactose',
										8  => 'nuts',
										9  => 'celery',
										10 => 'mustard',
										11 => 'sesame',
										12 => 'sulphurDioxide',
										13 => 'lupin',
										14 => 'molluscs',
									];

									foreach ($item->a as $a) {
										if (isset($allergens[$a])) {
											ProductAllergen::upsert([
												'productId' => $product,
												'allergenCode' => $allergens[$a],
											], [
												'timeCreated' => new \Katu\Utils\DateTime,
											]);
										}
									}

								}

								// Emulgators.
								if (isset($item->e)) {
									foreach ($item->e as $e) {
										ProductEmulgator::upsert([
											'productId' => $product,
											'emulgatorId' => \Deli\Models\Emulgator::upsert([
												'code' => $e,
											], [
												'timeCreated' => new \Katu\Utils\DateTime,
											])->getId(),
										], [
											'timeCreated' => new \Katu\Utils\DateTime,
										]);
									}
								}
							}
						}
					}, static::TIMEOUT, $chunk);
				}
			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Exception $e) {
			// Nevermind.
		}
	}
}
