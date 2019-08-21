<?php

namespace Deli\Models;

class ProductNutrient extends \Deli\Model {

	const TABLE = 'deli_product_nutrients';

	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOJIS_CZ = 'viscojis_cz';

	static function getConfig() {
		$configFile = new \Katu\Utils\File(__DIR__, '..', 'Config', 'nutrients.yaml');
		$config = \Spyc::YAMLLoad($configFile->get());

		return $config;
	}

	static function getCodeFromString(string $string) {
		$config = static::getConfig();
		$string = rtrim(mb_strtolower(trim($string)), ":");

		foreach ($config['list'] as $nutrientCode => $nutrientConfig) {

			foreach ($nutrientConfig['strings'] as $nutrientString) {

				if ($string == $nutrientString) {
					return $nutrientCode;
				}

			}
		}

		return false;
	}

	public function getNutrientAmountWithUnit() {
		return new \Deli\Classes\AmountWithUnit($this->nutrientAmount, $this->nutrientUnit);
	}

	public function getNutrientAmountWithUnitPerIngredientAmount($ingredientAmount) {
		return new \Deli\Classes\AmountWithUnit($this->nutrientAmount / $this->ingredientAmount * $ingredientAmount, $this->nutrientUnit);
	}

	public function getIngredientAmountWithUnit() {
		return new \Deli\Classes\AmountWithUnit($this->ingredientAmount, $this->ingredientUnit);
	}

}
