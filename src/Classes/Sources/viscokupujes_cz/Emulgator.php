<?php

// TODO - přesunout do Deli\Models\Emulgator

namespace Deli\Models\viscokupujes_cz;

class Emulgator extends \Deli\Model
{
	const TABLE = 'deli_viscokupujes_cz_emulgators';

	public function getEmulgatorName()
	{
		return EmulgatorName::getOneBy([
			'emulgatorId' => $this->getId(),
		]);
	}
}
