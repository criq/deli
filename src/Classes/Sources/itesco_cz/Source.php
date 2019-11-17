<?php

namespace Deli\Classes\Sources\itesco_cz;

class Source extends \Deli\Classes\Sources\Source {

	const HAS_PRODUCT_LOADING   = true;
	const HAS_PRODUCT_DETAILS   = true;
	const HAS_PRODUCT_ALLERGENS = true;
	const HAS_PRODUCT_NUTRIENTS = true;
	const HAS_PRODUCT_PRICES    = true;

	const SITEMAP_URL = 'https://nakup.itesco.cz/groceries/CZ.cs.pdp.sitemap.xml';

	static function getURIFromURL($url) {
		if (preg_match('/products\/(?<uri>[0-9]+)/', $url, $match)) {
			return $match['uri'];
		}

		return null;
	}






















	public function loadEan() {
		try {

			$ean = trim($this->getChakulaProduct()->getEan());
			if ($ean) {

				$this->update('ean', $ean);
				$this->save();

				return true;

			}

			throw new \Exception;

		} catch (\Exception $e) {

			$this->update('ean', null);
			$this->save();

			return null;

		}

	}




}
