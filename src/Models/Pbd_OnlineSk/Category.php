<?php

namespace Deli\Models\Pbd_OnlineSk;

class Category extends \Deli\Model {

	const TABLE = 'deli_pbd_online_sk_categories';

	static function buildCategoryList() {
		try {

			\Katu\Utils\Lock::run(['deli', Product::SOURCE, 'buildCategoryList'], 1800, function() {

				$categoryIds = \Katu\Utils\Cache::get(function() {

					$url = 'http://www.pbd-online.sk/';
					$src = \Katu\Utils\Cache::getUrl($url);
					$dom = \Katu\Utils\DOM::crawlHtml($src);

					return $dom->filter('ul.jGlide_001_tiles li a')->each(function($e) {

						preg_match("#javascript:menu\('([0-9]+)','([0-9]+)','([0-9]+)','([0-9]+)','.+'\);#U", $e->attr('href'), $match);
						return [
							'id_c_zakladna_skupina' => (int) $match[1],
							'id_c_podskupina' => (int) $match[2],
							'id_c_komodita' => (int) $match[3],
							'id_c_subkomodita' => (int) $match[4],
						];

					});

				});

				foreach ($categoryIds as $categoryId) {

					static::upsert([
						'zakladnaSkupinaId' => $categoryId['id_c_zakladna_skupina'],
						'podskupinaId' => $categoryId['id_c_podskupina'],
						'komoditaId' => $categoryId['id_c_komodita'],
						'subkomoditaId' => $categoryId['id_c_subkomodita'],
					], [
						'timeCreated' => new \Katu\Utils\DateTime,
					]);
				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function scrapeProducts() {
		$data = [
			'id_c_zakladna_skupina' => $this->zakladnaSkupinaId,
			'id_c_podskupina' => $this->podskupinaId,
			'id_c_komodita' => $this->komoditaId,
			'id_c_subkomodita' => $this->subkomoditaId,
			'pageno' => 1,
			'limit'  => 100,
			'offset' => 0,
		];

		$url = \Katu\Types\TUrl::make('http://www.pbd-online.sk/sk/menu/welcome/index/', $data);

		$src = \Katu\Utils\Cache::getUrl($url);
		$dom = \Katu\Utils\DOM::crawlHtml($src);

		$products = $dom->filter('body > .datatable')->each(function($e) {

			return $e->filter('tr')->each(function($e) {
				if (in_array($e->attr('class'), ['r1', 'r2'])) {
					if (preg_match("#javascript:detailAjax\('http://www.pbd-online.sk/sk/menu/welcome/detail/\?id=([0-9]+)'\);#", $e->filter('td:nth-child(1) a')->attr('onclick'), $match)) {

						return [
							'uri' => $match[1],
							'name' => $e->filter('td:nth-child(1) a')->html(),
						];

					}
				}
			});

		});

		$products = array_values(array_filter($products[0]));

		return $products;
	}

	public function load() {
		try {

			$products = $this->scrapeProducts();
			foreach ($products as $product) {

				Product::upsert([
					'uri' => $product['uri'],
				], [
					'timeCreated' => new \Katu\Utils\DateTime,
				], [
					'originalName' => $product['name'],
					'categoryId' => $this->getId(),
				]);

			}

		} catch (\Exception $e) {
			// Nevermind.
		}

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

}
