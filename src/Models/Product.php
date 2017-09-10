<?php

namespace Deli\Models;

use \Sexy\Sexy as SX;

abstract class Product extends \Deli\Model {

	const TIMEOUT = 2419200;

	#abstract public function scrape();
	#abstract public function import();
	#abstract public function getName();

	public function setCategory($source, $property = 'category') {
		$this->update($property, \Katu\Utils\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)$source)))));

		return true;
	}

	static function getForLoadSql() {
		$sql = SX::select()
			->setForTotal()
			->from(static::getTable())
			->where(SX::lgcOr([
				SX::cmpIsNull(static::getColumn('timeLoaded')),
				SX::cmpLessThan(static::getColumn('timeLoaded'), new \Katu\Utils\DateTime('- 1 month')),
			]))
			->orderBy(static::getColumn('timeCreated'))
			;

		return $sql;
	}

	public function getOrCreateScrapedIngredent() {
		return \App\Models\ScrapedIngredient::make(static::SOURCE, $this->getName());
	}

}
