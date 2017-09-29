<?php

namespace Deli\Models\StobklubCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_stobklub_cz_products';
	const SOURCE = 'stobklub_cz';
	const SOURCE_LABEL = 'stobklub.cz';

	public function getProductAmountWithUnit() {
		return new \Deli\Classes\AmountWithUnit(100, 'g');
	}

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'buildProductList'], 3600, function() {

				@ini_set('memory_limit', '512M');

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

						\Katu\Utils\Cache::get(function($subcategoryUri) use($category, $subcategory) {

							$url = 'http://www.stobklub.cz' . $subcategoryUri;
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
									'energy'   => new \Deli\Classes\AmountWithUnit($e->filter('td')->eq(2)->text(), 'kJ'),
									'proteins' => new \Deli\Classes\AmountWithUnit($e->filter('td')->eq(3)->text(), 'g'),
									'fats'     => new \Deli\Classes\AmountWithUnit($e->filter('td')->eq(4)->text(), 'g'),
									'carbs'    => new \Deli\Classes\AmountWithUnit($e->filter('td')->eq(5)->text(), 'g'),
									'sugar'    => new \Deli\Classes\AmountWithUnit($e->filter('td')->eq(7)->text(), 'g'),
									'fiber'    => new \Deli\Classes\AmountWithUnit($e->filter('td')->eq(8)->text(), 'g'),
								];

								$productAmountWithUnit = $product->getProductAmountWithUnit();
								foreach ($nutrients as $nutrientCode => $nutrientAmountWithUnit) {
									$product->setProductNutrient($nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit);
								}

								$product->setRemoteCategory([
									$category['name'],
									$subcategory['name'],
								]);
								$product->update('timeLoaded', new \Katu\Utils\DateTime);
								$product->save();

							});

						}, static::TIMEOUT, $subcategory['uri']);

					}
				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

}
