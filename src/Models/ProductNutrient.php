<?php

namespace Deli\Models;

abstract class ProductNutrient extends \Deli\Model {

	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOJIS_CZ = 'viscojis_cz';

	public function getNutrientAmountWithUnit() {
		return new \Effekt\AmountWithUnit($this->nutrientAmount, $this->nutrientUnit);
	}

	public function getNutrientAmountWithUnitPerIngredientAmount($ingredientAmount) {
		return new \Effekt\AmountWithUnit($this->nutrientAmount / $this->ingredientAmount * $ingredientAmount, $this->nutrientUnit);
	}

	public function getIngredientAmountWithUnit() {
		return new \Effekt\AmountWithUnit($this->ingredientAmount, $this->ingredientUnit);
	}

}
