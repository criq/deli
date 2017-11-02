<?php

namespace Deli\Models;

abstract class ProductAllergen extends \Deli\Model {

	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOJIS_CZ = 'viscojis_cz';

	static function getConfig(){
		$configFileName = realpath(dirname(__FILE__) . '/../Config/allergens.yaml');
		$config = \Spyc::YAMLLoad(file_get_contents($configFileName));

		return $config;
	}

}
