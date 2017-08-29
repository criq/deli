<?php

namespace Deli\Models\Tesco;

class ProductProperty extends \Deli\Model {

	const TABLE = 'deli_tesco_product_properties';

	static function create($scrapedTescoProduct, $name) {
		if (!static::checkCrudParams($scrapedTescoProduct, $name)) {
			throw new \Katu\Exceptions\InputErrorException("Invalid arguments.");
		}

		return static::insert([
			'timeCreated'           => (string) (new \Katu\Utils\DateTime),
			'scrapedTescoProductId' => (int)    ($scrapedTescoProduct->getId()),
			'name'                  => (string) (trim($name)),
		]);
	}

	static function make($scrapedTescoProduct, $name) {
		return static::getOneOrCreateWithList([
			'scrapedTescoProductId' => (int)    ($scrapedTescoProduct->getId()),
			'name'                  => (string) (trim($name)),
		], $scrapedTescoProduct, $name);
	}

	static function checkCrudParams($scrapedTescoProduct, $name) {
		if (!$scrapedTescoProduct || !($scrapedTescoProduct instanceof Product)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid scraped Tesco product."))
				->addErrorName('scrapedTescoProduct')
				;
		}
		if (!trim($name)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid name."))
				->addErrorName('name')
				;
		}

		return true;
	}

}
