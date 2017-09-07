<?php

namespace Deli\Models\UsdaGov;

class RawNutrient extends \Deli\Model {

	const TABLE = 'deli_usda_gov_raw_nutrients';

	static function buildRawNutrientList() {
		try {

			\Katu\Utils\Lock::run(['deli', Product::SOURCE, 'buildRawNutrientList'], 3600, function() {

				$productNutrientFileName = realpath(dirname(__FILE__) . '/../../Resources/UsdaGov/sr28asc/NUT_DATA.txt');
				$rows = Product::readTextFileToLines($productNutrientFileName);

				$sliceSize = 1000;
				$slices = ceil(count($rows) / $sliceSize);

				for ($slice = 1; $slice <= $slices; $slice++) {

					\Katu\Utils\Cache::get(function($sliceSize, $slice) use($rows) {

						$sliceRows = array_slice($rows, $sliceSize * ($slice - 1), $sliceSize);
						foreach ($sliceRows as $row) {

							$array = Product::getTextFileLineArray(Product::sanitizeTextFileLine($row));

							// in 100 g
							static::upsert([
								'productUri' => $array[0],
								'nutrientUri' => $array[1],
							], [
								'timeCreated' => new \Katu\Utils\DateTime,
							], [
								'nutrientAmount' => $array[2],
							]);

						}

						return true;

					}, null, $sliceSize, $slice);

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev', 'prod']));

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

}
