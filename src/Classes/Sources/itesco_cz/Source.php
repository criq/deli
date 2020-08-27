<?php

namespace Deli\Classes\Sources\itesco_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_ALLERGENS = true;
	const HAS_PRODUCT_DETAILS = true;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_LOADING = true;
	const HAS_PRODUCT_NUTRIENTS = true;
	const HAS_PRODUCT_PRICES = true;
	const SITEMAP_URL = 'https://nakup.itesco.cz/groceries/CZ.cs.pdp.sitemap.xml';

	public static function getURIFromURL($url)
	{
		if (preg_match('/products\/(?<uri>[0-9]+)/', $url, $match)) {
			return $match['uri'];
		}

		return null;
	}
}
