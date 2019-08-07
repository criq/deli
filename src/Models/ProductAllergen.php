<?php

namespace Deli\Models;

class ProductAllergen extends \Deli\Model {

	const TABLE = 'deli_product_allergens';
	
	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOJIS_CZ = 'viscojis_cz';

	static $allergenAdviceStrings = [
		"Alergeny jsou označeny <b>tučným písmem</b>.",
		"Alergeny jsou označeny tučným písmem.",
		"Alergeny jsou ve složení vyznačeny <b>tučně</b>.",
		"Alergeny jsou ve složení vyznačeny tučně.",
		"Informace pro alergiky: Alergeny, včetně obilovin obsahujících lepek, jsou ve složení vyznačeny tučně",
	];

	static function getConfig(){
		$configFileName = realpath(dirname(__FILE__) . '/../Config/allergens.yaml');
		$config = \Spyc::YAMLLoad(file_get_contents($configFileName));

		return $config;
	}

}
