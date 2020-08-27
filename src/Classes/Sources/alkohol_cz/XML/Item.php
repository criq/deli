<?php

namespace Deli\Classes\Sources\alkohol_cz\XML;

class Item extends \Deli\Classes\XML\Item
{
	public function getProperties()
	{
		$properties = parent::getProperties();

		$params = $this->getParams();
		$properties['country'] = $params['Země'] ?? null;
		$properties['alcoholContent'] = $params['Obsah alkoholu'] ?? null;
		$properties['volume'] = $params['Objem'] ?? null;

		return $properties;
	}

	public function getPrices()
	{
		$prices = parent::getPrices();
		$prices->add(new \Deli\Classes\Price($this->getPrice(), $this->getParam('Objem')));

		return $prices;
	}
}
