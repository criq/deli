<?php

// TODO - dodělat

namespace Deli\Classes\Sources\veganza_cz;

class Source extends \Deli\Classes\Sources\Source {

	const HAS_PRODUCT_LOADING    = true;
	const HAS_PRODUCT_DETAILS    = true;
	const HAS_PRODUCT_ALLERGENS  = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_NUTRIENTS  = false;
	const HAS_PRODUCT_PRICES     = false;

	const SITEMAP_URL = 'https://store.veganza.cz/sitemap.xml';

}
