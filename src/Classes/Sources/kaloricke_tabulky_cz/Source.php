<?php

namespace Deli\Classes\Sources\kaloricke_tabulky_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_LOADING = true;
	const HAS_PRODUCT_NUTRIENTS = true;

	public function loadProducts()
	{
		@ini_set('memory_limit', '512M');

		try {
			$lock = new \Katu\Tools\Locks\Lock(3600, [__CLASS__, __FUNCTION__], function () {
				$url = 'http://www.kaloricke-tabulky.cz/';
				$src = \Katu\Cache\URL::get($url);
				$dom = \Katu\Tools\DOM\DOM::crawlHtml($src);
				$dom->filter('#dropdown_category ul li a')->each(function ($e) {
					$category = $e->text();

					$url = 'http://www.kaloricke-tabulky.cz' . $e->attr('href');
					$src = \Katu\Cache\URL::get($url);
					$dom = \Katu\Tools\DOM\DOM::crawlHtml($src);

					return $dom->filter('#kalDenik .odd_row_c, #kalDenik .even_row_c')->each(function ($e) use ($category) {
						try {
							$product = \Deli\Models\Product::upsert([
								'source' => $this->getCode(),
								'uri' => trim($e->filter('a')->attr('href')),
							], [
								'timeCreated' => new \Katu\Tools\DateTime\DateTime,
							], [
								'name' => trim($e->filter('a')->text()),
								'originalName' => trim($e->filter('a')->text()),
								'remoteCategory' => $this->getRemoteCategoryJSON($category),
								'originalRemoteCategory' => $this->getRemoteCategoryJSON($category),
							]);

							$baseUnitSource = trim($e->filter('.second_td')->html());
							if (preg_match('/^(?<amount>[0-9\,\.\s]+)\s*(?<unit>g|ml)$/u', $baseUnitSource, $match)) {
								$baseAmountWithUnit = new \Deli\Classes\AmountWithUnit($match['amount'], $match['unit']);
								$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'baseAmount', $baseAmountWithUnit->amount);
								$product->setProductProperty(\Deli\Models\ProductProperty::SOURCE_ORIGIN, 'baseUnit', $baseAmountWithUnit->unit);
							}
						} catch (\Exception $e) {
							\App\Extensions\Errors\Handler::log($e);
						}
					});
				});
			});
			$lock->run();
		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}
}
