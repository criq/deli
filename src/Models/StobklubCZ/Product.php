<?php

namespace Deli\Models\StobklubCZ;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_stobklub_cz_products';
	const SOURCE = 'stobklub.cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'buildProductList'], 600, function() {

				$url = 'http://www.stobklub.cz/databaze-potravin/';
				$src = \Katu\Utils\Cache::getUrl($url);
				$dom = \Katu\Utils\DOM::crawlHtml($src);

				$categories = $dom->filter('.boxSubmenu .list > li')->each(function($e) {

					return [
						'name' => $e->filter('a.plus')->text(),
						'subcategories' => $e->filter('ul li')->each(function($e) {

							return [
								'uri' => $e->filter('a')->attr('href'),
								'name' => $e->text(),
							];

						}),
					];

				});

				foreach ($categories as $category) {
					foreach ($category['subcategories'] as $subcategory) {

						$url = 'http://www.stobklub.cz' . $subcategory['uri'];
						$src = \Katu\Utils\Cache::getUrl($url);
						$dom = \Katu\Utils\DOM::crawlHtml($src);

						$dom->filter('#mainContent table tbody tr')->each(function($e) use($category, $subcategory) {

							$product = static::upsert([
								'uri' => $e->filter('td')->eq(1)->filter('a')->attr('href'),
							], [
								'timeCreated' => new \Katu\Utils\DateTime,
							], [
								'name' => $e->filter('td')->eq(1)->filter('a')->text(),
							]);

							$nutrients = [
								'energy'   => new \Deli\AmountWithUnit($e->filter('td')->eq(2)->text(), 'kJ'),
								'proteins' => new \Deli\AmountWithUnit($e->filter('td')->eq(3)->text(), 'g'),
								'fats'     => new \Deli\AmountWithUnit($e->filter('td')->eq(4)->text(), 'g'),
								'carbs'    => new \Deli\AmountWithUnit($e->filter('td')->eq(5)->text(), 'g'),
								'sugar'    => new \Deli\AmountWithUnit($e->filter('td')->eq(7)->text(), 'g'),
								'fiber'    => new \Deli\AmountWithUnit($e->filter('td')->eq(8)->text(), 'g'),
							];

							$productAmountWithUnit = new \Deli\AmountWithUnit(100, 'g');

							foreach ($nutrients as $nutrientCode => $nutrientAmountWithUnit) {
								ProductNutrient::upsert([
									'productId' => $product->getId(),
									'nutrientCode' => $nutrientCode,
								], [
									'timeCreated' => new \Katu\Utils\DateTime,
								], [
									'timeUpdated' => new \Katu\Utils\DateTime,
									'nutrientAmount' => $nutrientAmountWithUnit->amount,
									'nutrientUnit' => $nutrientAmountWithUnit->unit,
									'ingredientAmount' => $productAmountWithUnit->amount,
									'ingredientUnit' => $productAmountWithUnit->unit,
								]);
							}

							$product->setCategory([
								$category['name'],
								$subcategory['name'],
							]);
							$product->update('timeLoaded', new \Katu\Utils\DateTime);
							$product->save();

						});

					}
				}

			}, \Katu\Env::getPlatform() != 'dev');

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

}
