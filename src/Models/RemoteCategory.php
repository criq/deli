<?php

namespace Deli\Models;

class RemoteCategory extends \Deli\Model
{
	const TABLE = 'deli_remote_categories';

	public function getArray()
	{
		return json_decode($this->json);
	}

	public function ban()
	{
		$this->update('isAllowed', 0);
		$this->save();

		return $this;
	}

	public function allow()
	{
		$this->update('isAllowed', 1);
		$this->save();

		return $this;
	}
}
