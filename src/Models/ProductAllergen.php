<?php

namespace Deli\Models;

abstract class ProductAllergen extends \Deli\Model {

	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOJIS_CZ = 'viscojis_cz';

	static $allergenAdviceStrings = [
		"Alergeny jsou označeny <b>tučným písmem</b>.",
		"Alergeny jsou označeny tučným písmem.",
		"Alergeny jsou ve složení vyznačeny <b>tučně</b>.",
		"Alergeny jsou ve složení vyznačeny tučně.",
		#"Informace pro alergiky: Výrobek neobsahuje alergeny.",
		#"Výrobek neobsahuje alergeny.",
		#"Výrobek neobsahuje alergeny",
		#"Informace pro alergiky: neobsahuje alergeny, ani jejich stopy.",
		#"Informace pro alergiky: Alergeny, včetně obilovin obsahujících lepek, jsou ve složení vyznačeny tučně",
	];

	static function getConfig(){
		$configFileName = realpath(dirname(__FILE__) . '/../Config/allergens.yaml');
		$config = \Spyc::YAMLLoad(file_get_contents($configFileName));

		return $config;
	}

}
