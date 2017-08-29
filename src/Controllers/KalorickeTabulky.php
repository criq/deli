<?php

namespace Roubar\Controllers;

use \Roubar\Models\KalorickeTabulky\Item;
use \Sexy\Sexy as SX;

class KalorickeTabulky extends \Katu\Controller {

	static function build() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'kalorickeTabulky', 'build'], 600, function() {

				$timeout = 86400;

				$filters = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'Č', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'Š', 'S', 'Š', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'Ž'];
				foreach ($filters as $filter) {

					$url = \Katu\Types\TUrl::make('http://www.kaloricketabulky.cz/tabulka-potravin.php', ['pismeno' => $filter]);
					$res = \Katu\Utils\Cache::getUrl($url, $timeout);
					$dom = \Katu\Utils\DOM::crawlHtml($res);

					$total = $dom->filter('#page .listing b')->html();
					if ($total) {

						$pages = ceil($total / 50);
						for ($page = 1; $page <= $pages; $page++) {

							$offset = ($page - 1) * 50;
							$url = \Katu\Types\TUrl::make('http://www.kaloricketabulky.cz/tabulka-potravin.php', [
								'pismeno' => $filter,
								'from' => $offset,
							]);
							$res = \Katu\Utils\Cache::getUrl($url, $timeout);
							$dom = \Katu\Utils\DOM::crawlHtml($res);
							$dom->filter('.vypis tbody tr h3 a')->each(function($e) {
								try {
									$object = Item::make($e->attr('href'));
									$object->setName($e->html());
									$object->save();
								} catch (\Exception $e) {

								}
							});

						}

					}

				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function scrape() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'kalorickeTabulky', 'scrape'], 600, function() {

				$items = Item::getBy([
					SX::lgcOr([
						SX::cmpIsNull(Item::getColumn('timeScraped')),
						SX::cmpLessThan(Item::getColumn('timeScraped'), (new \Katu\Utils\DateTime('- 1 month'))->getDbDateTimeFormat()),
					]),
				], [
					'page' => SX::page(1, 100),
				]);

				foreach ($items as $item) {
					try {
						$item->scrape();
					} catch (\Exception $e) {
						/* Nevermind. */
					}
				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function import() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'kalorickeTabulky', 'import'], 600, function() {

				$items = Item::getBy([
					SX::cmpIsNotNull(Item::getColumn('timeScraped')),
					SX::lgcOr([
						SX::cmpIsNull(Item::getColumn('timeImported')),
						SX::cmpLessThan(Item::getColumn('timeImported'), (new \Katu\Utils\DateTime('- 1 month'))->getDbDateTimeFormat()),
					]),
				], [
					'page' => SX::page(1, 100),
				]);

				foreach ($items as $item) {
					try {
						$item->import();
						$item->getOrCreateScrapedIngredent()->refreshIngredientNutrients();
					} catch (\Exception $e) {
						/* Nevermind. */
					}
				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

}
