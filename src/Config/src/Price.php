<?php

namespace Effekt;

class Price {

	public $amount;
	public $currencyCode;

	public function __construct($amount, $currencyCode) {
		$this->amount = $amount;
		$this->currencyCode = $currencyCode;
	}

}
