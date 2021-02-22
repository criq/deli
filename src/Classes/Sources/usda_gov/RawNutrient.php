<?php

namespace Deli\Models\usda_gov;

class RawNutrient extends \Deli\Model
{
	const TABLE = 'deli_usda_gov_raw_nutrients';

	public static function buildRawNutrientList()
	{
		try {
			$lock = new \Katu\Tools\Locks\Lock(3600, [__CLASS__, __FUNCTION__], function () {
				$productNutrientFileName = realpath(dirname(__FILE__) . '/../../Resources/UsdaGov/sr28asc/NUT_DATA.txt');
				$rows = Source::readTextFileToLines($productNutrientFileName);

				$sliceSize = 1000;
				$slices = ceil(count($rows) / $sliceSize);

				for ($slice = 1; $slice <= $slices; $slice++) {
					\Katu\Cache\General::get([__CLASS__, __FUNCTION__, __LINE__], '1 day', function ($sliceSize, $slice) use ($rows) {
						$sliceRows = array_slice($rows, $sliceSize * ($slice - 1), $sliceSize);
						foreach ($sliceRows as $row) {
							$array = Source::getTextFileLineArray(Source::sanitizeTextFileLine($row));

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
			});
			$lock->run();
		} catch (\Exception $e) {
			// Nevermind.
		}
	}
}
