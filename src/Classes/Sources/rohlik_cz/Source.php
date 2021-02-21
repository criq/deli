<?php

namespace Deli\Classes\Sources\rohlik_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const CATEGORY_TIMEOUT = 259200;
	const HAS_PRODUCT_ALLERGENS = true;
	const HAS_PRODUCT_DETAILS = false;
	const HAS_PRODUCT_EMULGATORS = true;
	const HAS_PRODUCT_LOADING = true;
	const HAS_PRODUCT_NUTRIENTS = true;
	const HAS_PRODUCT_PRICES = true;
	const JSON_URL = 'https://www.rohlik.cz/services/frontend-service/renderer/navigation/flat.json';

	public function loadCategoryIds()
	{
		return \Katu\Cache\General::get([__CLASS__, __FUNCTION__, __LINE__], '1 day', function () {
			$categoryIds = [];
			$json = \Katu\Cache\Url::get(static::JSON_URL);
			foreach ((array)$json->navigation as $category) {
				$categoryIds = array_merge($categoryIds, [$category->id], [$category->parentId], $category->children);
			}

			$categoryIds = array_values(array_filter(array_unique($categoryIds)));

			return $categoryIds;
		});
	}

	public function loadProducts()
	{
		$categoryIds = $this->loadCategoryIds();
		foreach ($categoryIds as $categoryId) {
			\Katu\Cache\General::get([__CLASS__, __FUNCTION__, __LINE__], static::CATEGORY_TIMEOUT, function ($categoryId) {
				$page = 1;
				$pages = 1;
				$perPage = 100;

				do {
					$offset = ($page * $perPage) - $perPage;
					$url = \Katu\Types\TUrl::make('https://www.rohlik.cz/services/frontend-service/products/' . $categoryId, [
						'limit' => $perPage,
						'offset' => $offset,
					]);

					$res = \Katu\Cache\Url::get($url);
					if ($res->data->totalHits ?? null) {
						$pages = ceil($res->data->totalHits / $perPage);
					}

					foreach ($res->data->productList as $item) {
						$categories = array_map(function ($i) {
							return $i->name;
						}, array_reverse($item->categories));

						$product = \Deli\Models\Product::upsert([
							'remoteId' => $item->productId,
						], [
							'timeCreated' => new \Katu\Tools\DateTime\DateTime,
						], [
							'timeLoaded' => new \Katu\Tools\DateTime\DateTime,
							'timeLoadedDetails' => new \Katu\Tools\DateTime\DateTime,
							'source' => $this->getCode(),
							'uri' => 'https://www.rohlik.cz/' . $item->baseLink,
							'name' => $item->productName,
							'originalName' => $item->productName,
							'isAvailable' => $item->archived ? 0 : 1,
							'remoteCategory' => static::encodeRemoteCategory($categories),
							'originalRemoteCategory' => static::encodeRemoteCategory($categories),
						]);
					}
				} while (++$page <= $pages);
			}, $categoryId);
		}
	}
}
