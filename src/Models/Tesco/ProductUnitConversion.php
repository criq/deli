<?php

namespace Deli\Models\Tesco;

class ProductUnitConversion extends \Deli\Model {

	const TABLE = 'deli_tesco_product_unit_conversions';

	static function create($creator, $scrapedTescoProduct, $practicalUnit, $baseUnit) {
		if (!static::checkCrudParams($creator, $scrapedTescoProduct, $practicalUnit, $baseUnit)) {
			throw new \Katu\Exceptions\InputErrorException("Invalid arguments.");
		}

		return static::insert([
			'timeCreated'           => (string) (new \Katu\Utils\DateTime),
			'creatorId'             => (int)    ($creator->getId()),
			'scrapedTescoProductId' => (int)    ($scrapedTescoProduct->getId()),
			'practicalUnitId'       => (int)    ($practicalUnit->getId()),
			'baseUnitId'            => (int)    ($baseUnit->getId()),
		]);
	}

	static function make($creator, $scrapedTescoProduct, $practicalUnit, $baseUnit) {
		return static::getOneOrCreateWithList([
			'scrapedTescoProductId' => (int)    ($scrapedTescoProduct->getId()),
			'practicalUnitId'       => (int)    ($practicalUnit->getId()),
			'baseUnitId'            => (int)    ($baseUnit->getId()),
		], $creator, $scrapedTescoProduct, $practicalUnit, $baseUnit);
	}

	static function checkCrudParams($creator, $scrapedTescoProduct, $practicalUnit, $baseUnit) {
		if (!$creator || !($creator instanceof \App\Models\User)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid creator."))
				->addErrorName('creator')
				;
		}

		if (!$scrapedTescoProduct || !($scrapedTescoProduct instanceof ScrapedTescoProduct)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid scraped Tesco product."))
				->addErrorName('scrapedTescoProduct')
				;
		}

		if (!$practicalUnit || !($practicalUnit instanceof \App\Models\PracticalUnit)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid practical unit."))
				->addErrorName('practicalUnit')
				;
		}

		if (!$baseUnit || !($baseUnit instanceof \App\Models\BaseUnit)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid base unit."))
				->addErrorName('baseUnit')
				;
		}

		return true;
	}

}
