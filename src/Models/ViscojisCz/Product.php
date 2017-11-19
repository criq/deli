<?php

namespace Deli\Models\ViscojisCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_viscojis_cz_products';
	const SOURCE = 'viscojis_cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 3600, function() {

				@ini_set('memory_limit', '512M');

				$array = \Katu\Utils\Cache::get(function() {

					$curl = new \Curl\Curl;
					$curl->setBasicAuthentication('jidelniplan', 'modraKarkulka55');
					$res = $curl->get('https://viscokupujes.cz/export/data.json');

					return $res;

				}, static::TIMEOUT);

				foreach (array_chunk($array, 100) as $chunk) {

					\Katu\Utils\Cache::get(function($chunk) {

						foreach ($chunk as $item) {

							if (preg_match('/^tes\-(?<id>[0-9]+)$/', $item->id, $match)) {

								$product = static::upsert([
									'uri' => $item->id,
								], [
									'timeCreated' => new \Katu\Utils\DateTime,
								], [
									'timeLoaded' => new \Katu\Utils\DateTime,
									'remoteSource' => \Deli\Models\ITescoCz\Product::SOURCE,
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
