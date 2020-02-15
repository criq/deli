<?php

namespace Deli\Classes\Sources\fajnejidlo_eu;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_ALLERGENS  = true;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_LOADING    = true;
	const HAS_PRODUCT_NUTRIENTS  = true;
	const HAS_PRODUCT_PRICES     = true;
	const XML_URL = 'https://www.fajnejidlo.eu/xml/heureka_cz.xml';
}
