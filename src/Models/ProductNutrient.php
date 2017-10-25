<?php

namespace Deli\Models;

abstract class ProductNutrient extends \Deli\Model {

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
