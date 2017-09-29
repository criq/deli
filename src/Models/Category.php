<?php

namespace Deli\Models;

use \Sexy\Sexy as SX;

class Category extends \Deli\Model {

	const TABLE = 'deli_categories';

	static function make($parent, $name) {
		$sql = SX::select()
			->from(static::getTable())
			->where(SX::eq(static::getColumn('name'), trim($name)))
			;

		if ($parent) {
			$sql->where(SX::eq(static::getColumn('categoryId'), $parent->getId()));
		} else {
			$sql->where(SX::cmpIsNull(static::getColumn('categoryId')));
		}

		$object = static::getOneBySql($sql);
		if (!$object) {

			$object = static::upsert([
				'categoryId' => $parent ? $parent->getId() : null,
				'name' => trim($name),
			], [
				'timeCreated' => new \Katu\Utils\DateTime,
			]);

		}

		return $object;
	}

}
