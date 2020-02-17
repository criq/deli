<?php

namespace Deli\Models;

class ProductAllergen extends \Deli\Model {

	const TABLE = 'deli_product_allergens';

	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOKUPUJES_CZ = 'viscokupujes_cz';

	static $allergenAdviceStrings = [
		"Alergeny jsou označeny <b>tučným písmem</b>.",
		"Alergeny jsou označeny tučným písmem.",
		"Alergeny jsou ve složení vyznačeny <b>tučně</b>.",
		"Alergeny jsou ve složení vyznačeny tučně.",
		"Informace pro alergiky: Alergeny, včetně obilovin obsahujících lepek, jsou ve složení vyznačeny tučně",
	];

	static function getConfig() {
		$configFile = new \Katu\Utils\File(__DIR__, '..', 'Config', 'allergens.yaml');
		$config = \Spyc::YAMLLoad($configFile->get());

		return $config;
	}

	static function getCodesFromStrings(array $strings) {
		$config = static::getConfig();

		$allergenCodes = [];
		foreach ($strings as $string) {

			if (in_array($string, $config['ignoreStrings'])) {
				continue;
			}

			foreach ($config['list'] as $allergenId => $allergenConfig) {

				foreach ($allergenConfig['strings'] as $allergenString) {

					if (strpos($string, $allergenString) !== false) {
						$allergenCodes[] = $allergenConfig['code'];
						continue 3;
					}

				}
			}

		}

		return array_values(array_unique($allergenCodes));
	}

}
