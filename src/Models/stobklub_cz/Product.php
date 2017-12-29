<?php

namespace Deli\Models\stobklub_cz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_stobklub_cz_products';
	const SOURCE = 'stobklub_cz';

	const TIMEOUT = 14515200;

	public function getProductAmountWithUnit() {
		return new \Effekt\AmountWithUnit(100, 'g');
	}

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run([__CLASS__, __FUNCTION__], 600, function() {

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
									'energy'   => new \Effekt\AmountWithUnit($e->filter('td')->eq(2)->text(), 'kJ'),
									'proteins' => new \Effekt\AmountWithUnit($e->filter('td')->eq(3)->text(), 'g'),
									'fats'     => new \Effekt\AmountWithUnit($e->filter('td')->eq(4)->text(), 'g'),
									'carbs'    => new \Effekt\AmountWithUnit($e->filter('td')->eq(5)->text(), 'g'),
									'sugar'    => new \Effekt\AmountWithUnit($e->filter('td')->eq(7)->text(), 'g'),
									'fiber'    => new \Effekt\AmountWithUnit($e->filter('td')->eq(8)->text(), 'g'),
								];

								$productAmountWithUnit = $product->getProductAmountWithUnit();
								foreach ($nutrients as $nutrientCode => $nutrientAmountWithUnit) {
									$product->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, $nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit);
								}

								$product->setRemoteCategory([
									$category['name'],
									$subcategory['name'],
								]);
								$product->save();

								$product->load();

							});

						}, static::TIMEOUT, $subcategory['uri']);

					}
				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function load() {
		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

}
