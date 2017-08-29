<?php

namespace Roubar\Controllers;

use \App\Models\Scraped\PbdOnline\Category;
use \App\Models\Scraped\PbdOnline\Product;
use \Sexy\Sexy as SX;

class PbdOnline extends \Katu\Controller {

	static function buildCategories() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'pbdOnline', 'buildCategories'], 600, function() {

				$categoryIds = \Katu\Utils\Cache::get(function() {

					$url = 'http://www.pbd-online.sk/';
					$src = \Katu\Utils\Cache::getUrl($url);
					$dom = \Katu\Utils\DOM::crawlHtml($src);

					return $dom->filter('ul.jGlide_001_tiles li a')->each(function($e) {

						preg_match("#javascript:menu\('([0-9]+)','([0-9]+)','([0-9]+)','([0-9]+)','.+'\);#U", $e->attr('href'), $match);
						return [
							'id_c_zakladna_skupina' => (int) $match[1],
							'id_c_podskupina'       => (int) $match[2],
							'id_c_komodita'         => (int) $match[3],
							'id_c_subkomodita'      => (int) $match[4],
						];

					});

				});

				foreach ($categoryIds as $categoryId) {
					Category::make($categoryId);
				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function buildProducts() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'pbdOnline', 'buildProducts'], 600, function() {

				$categories = Category::getBy([
					SX::cmpIsNull(Category::getColumn('timeScraped')),
				], [
					'orderBy' => Category::getColumn('categoryIds'),
					'page' => SX::page(1, 100),
				]);

				foreach ($categories as $category) {

					$products = $category->getProducts();
					foreach ($products as $product) {
						Product::make($product['id'], $product['name']);
					}
					$category->setTimeScraped(new \Katu\Utils\DateTime);
					$category->save();

				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function scrape() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'pbdOnline', 'scrape'], 600, function() {

				$products = Product::getBy([
					SX::cmpIsNull(Product::getColumn('timeScraped')),
				], [
					'page' => SX::page(1, 100),
				]);

				foreach ($products as $product) {
					$product->setScraped($product->getNutrientLines());
					$product->save();
				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function import() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'pbdOnline', 'import'], 600, function() {

				$products = Product::getBy([
					SX::cmpIsNotNull(Product::getColumn('timeScraped')),
					SX::cmpIsNull(Product::getColumn('timeImported')),
				], [
					'page' => SX::page(1, 100),
				]);

				foreach ($products as $product) {

					try {
						$product->import();
					} catch (\Exception $e) {
						/* Nevermind. */
					}

				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function translate() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'pbdOnline', 'translate'], 600, function() {

				$products = \App\Models\ScrapedIngredient::getBy([
					'source' => Product::SOURCE,
					SX::lgcOr([
						SX::cmpEq(\App\Models\ScrapedIngredient::getColumn('alternativeName'), ''),
						SX::cmpIsNull(\App\Models\ScrapedIngredient::getColumn('alternativeName')),
					]),
				], [
					'page' => SX::page(1, 100),
				]);

				foreach ($products as $product) {

					$url = \Katu\Types\TUrl::make('https://www.googleapis.com/language/translate/v2', [
						'key' => \Katu\Config::get('google', 'api', 'key'),
						'source' => 'sk',
						'target' => 'cs',
						'q' => $product->name,
					]);

					$res = \Katu\Utils\Cache::getUrl($url);
					if (isset($res->data->translations[0]->translatedText)) {
						$product->setAlternativeName($res->data->translations[0]->translatedText);
						$product->save();
					}

				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

}
