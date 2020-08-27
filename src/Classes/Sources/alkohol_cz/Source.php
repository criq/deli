<?php

namespace Deli\Classes\Sources\alkohol_cz;

class Source extends \Deli\Classes\Sources\Source
{
	const HAS_PRODUCT_ALLERGENS = false;
	const HAS_PRODUCT_DETAILS = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_LOADING = true;
	const HAS_PRODUCT_NUTRIENTS = false;
	const HAS_PRODUCT_PRICES = true;
	const XML_URL = 'https://www.alkohol.cz/export/?type=affilcz&hash=CE7bqK2NhDGkFdTQJZWnH6k35f2M4qKR';
}
