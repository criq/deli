<?php

namespace Deli\Classes;

class Price {

	static $acceptableUnitCodes = ['mg', 'g', 'kg', 'ml', 'l', 'ks'];

	public $pricePerProduct;
	public $pricePerUnit;
	public $unitAmount;
	public $unitCode;

	public function __construct($pricePerProduct, $amountWithUnitString) {
		$this->pricePerProduct = (new \Katu\Types\TString((string)$pricePerProduct))->getAsFloat();
		$amountWithUnit = AmountWithUnit::createFromString((string)$amountWithUnitString, static::$acceptableUnitCodes);
		if ($amountWithUnit) {
			$this->pricePerUnit = (new \Katu\Types\TString((string)$pricePerProduct))->getAsFloat();
			$this->unitAmount = $amountWithUnit->amount;
			$this->unitCode = $amountWithUnit->unit;
		}
	}

	public function isComplete() {
		return $this->pricePerProduct && $this->pricePerUnit && $this->unitAmount && $this->unitCode;
	}

}
