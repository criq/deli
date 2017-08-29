<?php

namespace Roubar\Controllers;

class KalorickeTabulky2 extends \Katu\Controller {

	static function scrape() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'kalorickeTabulky2', 'scrape'], 600, function() {

				\App\Classes\Scrapers\KalorickeTabulky2\Homepage::scrape(86400);

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

}
