<?php

namespace Deli\Models;

class ProductNutrient extends \Deli\Model
{
	const TABLE = 'deli_product_nutrients';
	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOKUPUJES_CZ = 'viscokupujes_cz';

	public static function getConfig()
	{
		$configFile = new \Katu\Files\File(__DIR__, '..', 'Config', 'nutrients.yaml');
		$config = \Spyc::YAMLLoad($configFile->get());

		return $config;
	}

	public static function getCodeFromString(string $string)
	{
		$config = static::getConfig();
		$string = rtrim(mb_strtolower(trim($string)), ":");

		foreach ($config['list'] as $nutrientCode => $nutrientConfig) {
			foreach ($nutrientConfig['strings'] as $nutrientString) {
				if ($string == $nutrientString) {
					return $nutrientCode;
				}
			}
		}

		return null;
	}

	public function getNutrientAmountWithUnit()
	{
		return new \Deli\Classes\AmountWithUnit($this->nutrientAmount, $this->nutrientUnit);
	}

	public function getNutrientAmountWithUnitPerIngredientAmount($ingredientAmount)
	{
		return new \Deli\Classes\AmountWithUnit($this->nutrientAmount / $this->ingredientAmount * $ingredientAmount, $this->nutrientUnit);
	}

	public function getIngredientAmountWithUnit()
	{
		return new \Deli\Classes\AmountWithUnit($this->ingredientAmount, $this->ingredientUnit);
	}
}
