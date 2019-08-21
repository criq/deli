<?php

namespace Deli\Classes;

class NutrientAmountWithUnit {

	public $nutrientCode;
	public $amountWithUnit;

	public function __construct(string $nutrientCode, AmountWithUnit $amountWithUnit) {
		$this->nutrientCode = $nutrientCode;
		$this->amountWithUnit = $amountWithUnit;
	}

	static function createFromStrings($nutrientString, $amountWithUnitString) {
		$nutrientCode = \Deli\Models\ProductNutrient::getCodeFromString($nutrientString);
		$amountWithUnit = \Deli\Classes\AmountWithUnit::createFromString($amountWithUnitString);
		if (is_string($nutrientCode) && $amountWithUnit instanceof \Deli\Classes\AmountWithUnit) {
			return new static($nutrientCode, $amountWithUnit);
		}

		return false;
	}

}
