<?php

namespace Roubar\Controllers;
namespace Roubar\Controllers;

use \App\Models\Currency;
use \App\Models\Scraped\Tesco\Product;
use \App\Models\Scraped\Tesco\ProductAllergenTag;
use \Sexy\Sexy as SX;

class Tesco extends \Katu\Controller {

	static function build() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'tesco', 'build'], 600, function() {

				$currency = Currency::getOneBy([
					'code' => 'CZK',
				]);

				foreach (\Chakula\Tesco::getDepartmentTree() as $superDepartment) {
					if (!in_array($superDepartment->name, \Katu\Config::get('tesco', 'superDepartments', 'exclude'))) {
						foreach ($superDepartment->departments as $department) {
							foreach ($department->categories as $category) {

								\Katu\Utils\Cache::get(['scrapers', 'tesco', 'build', 'category', $category->id], function() use($currency, $superDepartment, $department, $category) {

									foreach ($category->getProducts() as $product) {

										try {

											Product::upsert([
												'productId' => (string) ($product->id),
												'currencyId' => (int) ($currency->getId()),
											], [
												'timeCreated' => (string) (new \Katu\Utils\DateTime),
											], [
												'name' => (string) ($product->name),
												'category' => \Katu\Utils\JSON::encodeInline([
													$superDepartment->name,
													$department->name,
													$category->name,
												]),
											]);

										} catch (\Exception $e) {
											/* Nevermind. */
										}

									}

								}, 86400 * 3);

							}
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

			\Katu\Utils\Lock::run(['scrapers', 'tesco', 'scrape'], 600, function() {

				$products = \App\Models\Views\ScrapedTescoProductsToScrape::getBy([
					SX::lgcOr([
						SX::cmpLessThan(\App\Models\Views\ScrapedTescoProductsToScrape::getColumn('timeScraped'), (new \Katu\Utils\DateTime('- 1 week'))),
						SX::cmpIsNull(\App\Models\Views\ScrapedTescoProductsToScrape::getColumn('timeScraped')),
					]),
				], [
					'orderBy' => \App\Models\Views\ScrapedTescoProductsToScrape::getColumn('scrapedTescoProductId'),
					'page' => SX::page(1, 100),
				]);

				foreach ($products as $product) {

					try {
						$product->getScrapedTescoProduct()->scrape();
					} catch (\Exception $e) {
						/* Nothing to do. */
					}

				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function importAmounts() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'tesco', 'importAmounts'], 600, function() {

				$products = Product::getBy([
					SX::cmpIsNotNull(Product::getColumn('timeScraped')),
					SX::lgcOr([
						SX::cmpLessThan(Product::getColumn('timeImportedAmounts'), (new \Katu\Utils\DateTime('- 1 month'))),
						SX::cmpIsNull(Product::getColumn('timeImportedAmounts')),
					]),
				], [
					'orderBy' => Product::getColumn('timeImportedAmounts'),
					'page' => SX::page(1, 100),
				]);

				foreach ($products as $product) {

					try {
						$product->importAmounts();
					} catch (\Exception $e) {
						/* Nothing to do. */
					}

				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function importPrices() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'tesco', 'importPrices'], 600, function() {

				$products = Product::getBy([
					SX::cmpEq(Product::getColumn('isAvailable'), 1),
					SX::lgcOr([
						SX::cmpLessThan(Product::getColumn('timeImportedPrice'), (new \Katu\Utils\DateTime('- 3 days'))),
						SX::cmpIsNull(Product::getColumn('timeImportedPrice')),
					]),
				], [
					'orderBy' => Product::getColumn('timeImportedPrice'),
					'page' => SX::page(1, 100),
				]);

				foreach ($products as $product) {

					try {
						$product->importPrice();
					} catch (\Exception $e) {
						/* Nothing to do. */
					}

				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function importEan() {
		try {

			\Katu\Utils\Lock::run(['scrapers', 'tesco', 'importEan'], 600, function() {

				$sql = SX::select()
					->from(Product::getTable())
					->where(SX::cmpIsNull(Product::getColumn('ean')))
					->setPage(SX::page(1, 100))
					;

				foreach (Product::getBySql($sql) as $product) {
					try {
						$product->importEan();
					} catch (\Exception $e) {
						/* Nevermind. */
					}
				}

			});

		} catch (\Katu\Exceptions\LockException $e) { /* Nevermind. */ }

	}

}
