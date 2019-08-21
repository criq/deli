<?php

namespace Deli\Classes;

class Prices {

	protected $prices = [];

	public function __construct($prices = []) {
		$this->prices = (array)$prices;
	}

	public function add(Price $price) {
		$this->prices[] = $price;

		return $this;
	}

	public function getCompletePrices() {
		return new static(array_values(array_filter($this->prices, function($price) {
			return $price->isComplete();
		})));
	}

	public function getPrice() {
		$completePrices = $this->getCompletePrices();

		return $completePrices->prices[0] ?? $this->prices[0] ?? false;
	}

}
