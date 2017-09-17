<?php

namespace Deli\Models;

use \Sexy\Sexy as SX;

abstract class Product extends \Deli\Model {

	const TIMEOUT = 2419200;

	public function getSource() {
		return static::SOURCE;
	}

	public function getSourceLabel() {
		return static::SOURCE_LABEL;
	}

	public function getName() {
		return $this->name;
	}

	public function setCategory($source, $property = 'category') {
		$this->update($property, \Katu\Utils\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)$source)))));

		return true;
	}

	static function getForLoadSql() {
		$sql = SX::select()
			->setForTotal()
			->from(static::getTable())
			->where(SX::lgcOr([
				SX::cmpIsNull(static::getColumn('timeLoaded')),
				SX::cmpLessThan(static::getColumn('timeLoaded'), new \Katu\Utils\DateTime('- 1 month')),
			]))
			->orderBy(static::getColumn('timeCreated'))
			;

		return $sql;
	}

	public function getOrCreateScrapedIngredent() {
		return \App\Models\ScrapedIngredient::make(static::SOURCE, $this->getName());
	}

	static function getAllergenCodesFromTexts($texts) {
		$allergenCodes = [];

		$configFileName = realpath(dirname(__FILE__) . '/../Config/allergens.yaml');
		$config = \Spyc::YAMLLoad(file_get_contents($configFileName));

		foreach ($texts as $text) {

			if (in_array($text, $config['ignore'])) {
				continue;
			}

			foreach ($config['texts'] as $allergenCode => $allergenTexts) {
				foreach ($allergenTexts as $allergenText) {

					if (strpos($text, $allergenText) !== false) {
						$allergenCodes[] = $allergenCode;
						continue 3;
					}

				}
			}

			// Dev.
			var_dump($text);

		}

		return array_values(array_unique($allergenCodes));
	}


}
