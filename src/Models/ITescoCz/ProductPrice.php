<?php

namespace Deli\Models\ITescoCz;

class ProductPrice extends \Deli\Model {

	const TABLE = 'deli_tesco_product_prices';

	static function create($scrapedTescoProduct, $currency) {
		if (!static::checkCrudParams($scrapedTescoProduct, $currency)) {
			throw new \Katu\Exceptions\InputErrorException("Invalid arguments.");
		}

		return static::insert([
			'timeCreated'           => (string) (new \Katu\Utils\DateTime),
			'scrapedTescoProductId' => (int)    ($scrapedTescoProduct->getId()),
			'currencyId'            => (int)    ($currency->getId()),
		]);
	}

	static function checkCrudParams($scrapedTescoProduct, $currency) {
		if (!$scrapedTescoProduct || !($scrapedTescoProduct instanceof Product)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid scraped Tesco product."))
				->addErrorName('scrapedTescoProduct')
				;
		}

		if (!$currency || !($currency instanceof \App\Models\Currency)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid currency."))
				->addErrorName('currency')
				;
		}

		return true;
	}

}
