<?php

namespace Deli\Classes\Sources\fajnejidlo_eu\XML;

class Item extends \Deli\Classes\XML\Item {

	public function getPrices() {
		$prices = parent::getPrices();
		$prices->add(new \Deli\Classes\Price($this->getPrice(), $this->getParam('Hmotnost')));

		return $prices;
	}

}
