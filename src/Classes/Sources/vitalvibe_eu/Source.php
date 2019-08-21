<?php

namespace Deli\Classes\Sources\vitalvibe_eu;

class Source extends \Deli\Classes\Sources\Source {

	const HAS_PRODUCT_LOADING    = true;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_ALLERGENS  = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_NUTRIENTS  = false;
	const HAS_PRODUCT_PRICES     = false;

	const XML_URL = 'http://www.vitalvibe.eu/xml/cs_zbozi_seznam.xml';

}
