<?php

namespace Deli\Classes\Sources\lekarna_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_ALLERGENS = false;
	const HAS_PRODUCT_DETAILS = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_LOADING = true;
	const HAS_PRODUCT_NUTRIENTS = false;
	const HAS_PRODUCT_PRICES = true;
	const XML_URL = 'https://www.lekarna.cz/feed/srovnavace-products.xml?a_box=nmhf5uvw';
}
