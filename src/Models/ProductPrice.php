<?php

namespace Deli\Models;

abstract class ProductPrice extends \Deli\Model {

	const TIMEOUT = 432000;

	public function isInTimeout() {
		return (new \Katu\Utils\DateTime($this->timeCreated))->isInTimeout(static::TIMEOUT);
	}

	public function getProductPricePerIngredientAmount($amount) {
		if ($this->unitAmount) {
			switch ($this->unitCode) {
				case 'kg' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / 1000 * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'g'));
				break;
				case 'g' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'g'));
				break;
				case 'mg' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / .001 * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'g'));
				break;
				case 'l' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / 1000 * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'ml'));
				break;
				case 'ml' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount * $amount, $this->currencyCode), new \Effekt\AmountWithUnit($amount, 'ml'));
				break;
				default :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount, $this->currencyCode), new \Effekt\AmountWithUnit(1, $this->unitCode));
				break;
			}
		}

		return false;
	}

}
