<?php

namespace Effekt;

class PricePerAmountWithUnit {

	public $price;
	public $amountWithUnit;

	public function __construct($price, $amountWithUnit) {
		$this->price = $price;
		$this->amountWithUnit = $amountWithUnit;
	}

}
