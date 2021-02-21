<?php

namespace Deli\Models\usda_gov;

class RawNutrient extends \Deli\Model
{
	const TABLE = 'deli_usda_gov_raw_nutrients';

	public static function buildRawNutrientList()
	{
		try {
			\Katu\Utils\Lock::run(['deli', Product::SOURCE, __FUNCTION__], 3600, function () {
				$productNutrientFileName = realpath(dirname(__FILE__) . '/../../Resources/UsdaGov/sr28asc/NUT_DATA.txt');
				$rows = Product::readTextFileToLines($productNutrientFileName);

				$sliceSize = 1000;
				$slices = ceil(count($rows) / $sliceSize);

				for ($slice = 1; $slice <= $slices; $slice++) {
					\Katu\Cache\General::get([__CLASS__, __FUNCTION__, __LINE__], '1 day', function ($sliceSize, $slice) use ($rows) {
						$sliceRows = array_slice($rows, $sliceSize * ($slice - 1), $sliceSize);
						foreach ($sliceRows as $row) {
							$array = Product::getTextFileLineArray(Product::sanitizeTextFileLine($row));

							// in 100 g
							static::upsert([
								'productUri' => $array[0],
								'nutrientUri' => $array[1],
							], [
								'timeCreated' => new \Katu\Tools\DateTime\DateTime,
							], [
								'nutrientAmount' => $array[2],
							]);
						}
						return true;
					}, $sliceSize, $slice);
				}
			}, !in_array(\Katu\Config\Env::getPlatform(), ['dev']));
		} catch (\Exception $e) {
			// Nevermind.
		}
	}
}
