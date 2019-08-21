<?php

namespace Deli\Models;

class ProductPrice extends \Deli\Model {

	const TABLE = 'deli_product_prices';

	// TODO - konstatnta byla přesunuta do Source
	public function isInTimeout() {
		return (new \Katu\Utils\DateTime($this->timeCreated))->isInTimeout(static::TIMEOUT);
	}

	public function getPricePerAmount($amount = 100) {
		if ((float)$this->unitAmount) {
			switch ($this->unitCode) {
				case 'mg' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / .001 * $amount, $this->currencyCode), new \Deli\Classes\AmountWithUnit($amount, 'g'));
				break;
				case 'g' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount * $amount, $this->currencyCode), new \Deli\Classes\AmountWithUnit($amount, 'g'));
				break;
				case 'kg' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / 1000 * $amount, $this->currencyCode), new \Deli\Classes\AmountWithUnit($amount, 'g'));
				break;
				case 'ml' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount * $amount, $this->currencyCode), new \Deli\Classes\AmountWithUnit($amount, 'ml'));
				break;
				case 'l' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount / 1000 * $amount, $this->currencyCode), new \Deli\Classes\AmountWithUnit($amount, 'ml'));
				break;
				case 'ks' :
					return new \Effekt\PricePerAmountWithUnit(new \Effekt\Price($this->pricePerUnit / $this->unitAmount * $amount, $this->currencyCode), new \Deli\Classes\AmountWithUnit($amount, 'ks'));
				break;
			}
		}

		return false;
	}

}
