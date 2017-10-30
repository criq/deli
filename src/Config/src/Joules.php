<?php

namespace Effekt;

class Joules {

	const CONVERSION_CALORIES = 4.184;

	public $amount;

	public function __construct($amount, $unit) {
		switch (strtolower($unit)) {
			case 'cal' :
				$this->amount = $amount * static::CONVERSION_CALORIES;
			break;
			case 'kcal' :
				$this->amount = $amount * static::CONVERSION_CALORIES * 1000;
			break;
			case 'j' :
				$this->amount = $amount;
			break;
			case 'kj' :
				$this->amount = $amount * 1000;
			break;
		}
	}

	public function toCal() {
		return new AmountWithUnit($this->amount / static::CONVERSION_CALORIES, 'cal');
	}

	public function toKCal() {
		return new AmountWithUnit($this->amount / static::CONVERSION_CALORIES / 1000, 'kcal');
	}

	public function toJ() {
		return new AmountWithUnit($this->amount, 'J');
	}

	public function toKJ() {
		return new AmountWithUnit($this->amount / 1000, 'kJ');
	}

}
