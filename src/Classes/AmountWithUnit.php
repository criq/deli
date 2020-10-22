<?php

namespace Deli\Classes;

class AmountWithUnit extends \Effekt\AmountWithUnit
{
	public static $acceptableUnitCodes = ['µg', 'mg', 'g', 'kg', 'ml', 'l', 'ks', 'cal', 'kcal', 'J', 'kJ'];

	public static function createFromString($string, $acceptableUnitCodes = null)
	{
		if (!$acceptableUnitCodes) {
			$acceptableUnitCodes = static::$acceptableUnitCodes;
		}

		$string = (new \Katu\Types\TString($string))->normalizeSpaces();

		$acceptableUnitCodesRegexp = implode('|', $acceptableUnitCodes);
		if (preg_match("/([0-9\.\,\h]*[0-9])\s*($acceptableUnitCodesRegexp)/", $string, $match)) {
			return new static((new \Katu\Types\TString((string)$match[1]))->getAsFloat(), trim($match[2]));
		}

		return null;
	}
}
