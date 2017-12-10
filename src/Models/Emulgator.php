<?php

namespace Deli\Models;

class Emulgator extends \Deli\Model {

	const TABLE = 'deli_emulgators';

	public function getViscojisCzEmulgator() {
		return \Deli\Models\viscojis_cz\Emulgator::getOneBy([
			'emulgatorId' => $this->getId(),
		]);
	}

}
