<?php

namespace Deli\Classes\Sources\goodie_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct {

	/****************************************************************************
	 * Allergens.
	 */
	public function loadAllergens() {
		return \Deli\Models\ProductAllergen::getCodesFromStrings([
			$this->getDOM()->filter('.basic-description')->text(),
		]);
	}

}
