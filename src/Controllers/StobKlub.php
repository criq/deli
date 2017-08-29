<?php

namespace Roubar\Controllers;

class StobKlub extends \Katu\Controller {

	static function scrape() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'stobKlub', 'scrape'], 600, function() {

				$timeout = 86400;

				$url = 'http://www.stobklub.cz/databaze-potravin/';
				$res = \Katu\Utils\Cache::getUrl($url, $timeout);
				$dom = \Katu\Utils\DOM::crawlHtml($res);

				$dom->filter('#content ul.list > li > a')->each(function($e) use($timeout) {

					$url = 'http://www.stobklub.cz' . $e->attr('href');
					$res = \Katu\Utils\Cache::getUrl($url, $timeout);
					$dom = \Katu\Utils\DOM::crawlHtml($res);

					$dom->filter('#mainContent a')->each(function($e) use($timeout) {

						$url = 'http://www.stobklub.cz' . $e->attr('href');
						$res = \Katu\Utils\Cache::getUrl($url, $timeout);
						$dom = \Katu\Utils\DOM::crawlHtml($res);

						$dom->filter('#mainContent table tbody tr')->each(function($e) {

							$scrapedIngredient = \App\Models\ScrapedIngredient::make('stobklub.cz', $e->filter('td:nth-child(2) a')->html());

							$amounts = [
								'base'     => new \App\Classes\AmountWithUnit(100, 'g'),
								'energy'   => new \App\Classes\AmountWithUnit($e->filter('td:nth-child(3)')->html(), 'kJ'),
								'proteins' => new \App\Classes\AmountWithUnit($e->filter('td:nth-child(4)')->html(), 'g'),
								'fats'     => new \App\Classes\AmountWithUnit($e->filter('td:nth-child(5)')->html(), 'g'),
								'carbs'    => new \App\Classes\AmountWithUnit($e->filter('td:nth-child(6)')->html(), 'g'),
								'sugar'    => new \App\Classes\AmountWithUnit($e->filter('td:nth-child(8)')->html(), 'g'),
								'fiber'    => new \App\Classes\AmountWithUnit($e->filter('td:nth-child(9)')->html(), 'g'),
							];

							$scrapedIngredient->setScrapedIngredientAmounts($amounts);

						});

					});

				});

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

}
