<?php

namespace Deli\Classes\Sources\stobklub_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_ALLERGENS = false;
	const HAS_PRODUCT_DETAILS = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_LOADING = true;
	const HAS_PRODUCT_NUTRIENTS = false;
	const HAS_PRODUCT_PRICES = false;

	public function loadProducts()
	{
		@ini_set('memory_limit', '512M');

		try {
			$lock = new \Katu\Tools\Locks\Lock(3600, [__CLASS__, __FUNCTION__], function () {
				$url = 'http://www.stobklub.cz/databaze-potravin/';
				$src = \Katu\Cache\URL::get($url);
				$dom = \Katu\Tools\DOM\DOM::crawlHtml($src);

				$categories = $dom->filter('.boxSubmenu .list > li')->each(function ($e) {
					return [
						'name' => $e->filter('a.plus')->text(),
						'subcategories' => $e->filter('ul li')->each(function ($e) {
							return [
								'uri' => $e->filter('a')->attr('href'),
								'name' => $e->text(),
							];
						}),
					];
				});

				foreach ($categories as $category) {
					foreach ($category['subcategories'] as $subcategory) {
						\Katu\Cache\General::get([__CLASS__, __FUNCTION__, __LINE__], static::CACHE_TIMEOUT, function ($subcategoryUri) use ($category, $subcategory) {
							$url = 'http://www.stobklub.cz' . $subcategoryUri;
							$src = \Katu\Cache\URL::get($url);
							$dom = \Katu\Tools\DOM\DOM::crawlHtml($src);

							$dom->filter('#mainContent table tbody tr')->each(function ($e) use ($category, $subcategory) {

								$product = \Deli\Models\Product::upsert([
									'source' => $this->getCode(),
									'uri' => $e->filter('td')->eq(1)->filter('a')->attr('href'),
								], [
									'timeCreated' => new \Katu\Tools\DateTime\DateTime,
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

								$productAmountWithUnit = new \Deli\Classes\AmountWithUnit(100, 'g');
								foreach ($nutrients as $nutrientCode => $nutrientAmountWithUnit) {
									$product->setProductNutrient(\Deli\Models\ProductNutrient::SOURCE_ORIGIN, $nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit);
								}

								// var_dump($category);die;

								// TODO
								$product->setRemoteCategory([
									$category['name'],
									$subcategory['name'],
								]);
								$product->save();

								$product->load();
							});
						}, $subcategory['uri']);
					}
				}
			});
			$lock->run();
		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}
}
