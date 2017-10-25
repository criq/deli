<?php

namespace Deli\Models;

class Emulgator extends \Deli\Model {

	const TABLE = 'deli_emulgators';

	public function getViscojisCzEmulgator() {
		return \Deli\Models\ViscojisCz\Emulgator::getOneBy([
			'emulgatorId' => $this->getId(),
		]);
	}

}
