<?php

namespace Deli\Classes\Sources\rohlik_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_ALLERGENS  = true;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_EMULGATORS = true;
	const HAS_PRODUCT_LOADING    = true;
	const HAS_PRODUCT_NUTRIENTS  = true;
	const HAS_PRODUCT_PRICES     = true;
	const XML_URL = 'https://www.rohlik.cz/heureka.xml';
}
