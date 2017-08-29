<?php

namespace Deli\Models\PbdOnlineSK;

class Category extends \Deli\Model {

	const TABLE = 'deli_pbd_online_sk_categories';

	static function create($categoryIds) {
		return static::insert([
			'timeCreated' => (string) (new \Katu\Utils\DateTime),
			'categoryIds' => (string) (\Katu\Utils\JSON::encodeStandard($categoryIds)),
		]);
	}

	static function make($categoryIds) {
		return static::getOneOrCreateWithList([
			'categoryIds' => (string) (\Katu\Utils\JSON::encodeStandard($categoryIds)),
		], $categoryIds);
	}

	public function getProducts() {
		$data = array_merge(\Katu\Utils\JSON::decodeAsArray($this->categoryIds), [
			'pageno' => 1,
			'limit'  => 100,
			'offset' => 0,
			#'id_c_zakladna_skupina' => 1,
			#'id_c_podskupina' => 1,
			#'id_c_komodita' => 1,
			#'id_c_subkomodita' => 1,
		]);

		$url = \Katu\Types\TUrl::make('http://www.pbd-online.sk/sk/menu/welcome/index/', $data);

		$src = \Katu\Utils\Cache::getUrl($url);
		$dom = \Katu\Utils\DOM::crawlHtml($src);

		$tableProducts = $dom->filter('body > .datatable')->each(function($e) {

			return $e->filter('tr')->each(function($e) {
				if (in_array($e->attr('class'), ['r1', 'r2'])) {
					if (preg_match("#javascript:detailAjax\('http://www.pbd-online.sk/sk/menu/welcome/detail/\?id=([0-9]+)'\);#", $e->filter('td:nth-child(1) a')->attr('onclick'), $match)) {
						return [
							'id' => $match[1],
							'name' => $e->filter('td:nth-child(1) a')->html(),
						];
					}
				}
			});

		});

		$products = [];
		foreach ($tableProducts as $tableProduct) {
			$products = array_merge($products, $tableProduct);
		}

		return array_values(array_filter($products));
	}

}
