<?php

namespace Deli\Models;

class Emulgator extends \Deli\Model
{
	const TABLE = 'deli_emulgators';

	public static function getOrCreateByCode($code)
	{
		return static::upsert([
			'code' => strtolower($code),
		], [
			'timeCreated' => new \Katu\Tools\DateTime\DateTime,
		]);
	}
}
