<?php

namespace Deli\Models;

abstract class ProductPrice extends \Deli\Model {

	const TIMEOUT = 432000;

	static $acceptableUnitCodes = ['mg', 'g', 'kg', 'ml', 'l'];

	public function isInTimeout() {
		return (new \Katu\Utils\DateTime($this->timeCreated))->isInTimeout(static::TIMEOUT);
	}

	public function getProductPricePerIngredientAmount($amount) {
		if ((float)$this->unitAmount) {
			switch ($this->unitCode) {
				case 'mg' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / .001 * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'g'));
				break;
				case 'g' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'g'));
				break;
				case 'kg' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / 1000 * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'g'));
				break;
				case 'ml' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'ml'));
				break;
				case 'l' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / 1000 * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'ml'));
				break;
			}
		}

		return false;
	}

	static function getUnitAmountWithCode($string) {
		$acceptableUnitCodes = implode('|', static::$acceptableUnitCodes);
		if (preg_match("/([0-9\.\,]+)\s*($acceptableUnitCodes)/", $string, $match)) {
			return new \App\Classes\AmountWithUnit((new \Katu\Types\TString((string)$match[1]))->getAsFloat(), trim($match[2]));
		}

		return false;
	}

}
