<?php

namespace Deli\Classes;

class AmountWithUnit {

	public $amount;
	public $unit;

	public function __construct($amount, $unit) {
		$this->amount = (new \Katu\Types\TString($amount))->getAsFloat();
		$this->unit = trim($unit);
	}

}
