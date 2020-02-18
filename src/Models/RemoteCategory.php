<?php

namespace Deli\Models;

class RemoteCategory extends \Deli\Model
{
	const TABLE = 'deli_remote_categories';

	public function getArray()
	{
		return json_decode($this->json);
	}
}
