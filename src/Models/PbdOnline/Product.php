<?php

namespace Deli\Models\PbdOnline;

class Product extends \Deli\Models\Item {

	const TABLE = 'deli_pbdonline_products';

	const SOURCE = 'pbd-online.sk';

	static function create($productId, $name) {
		return static::insert([
			'timeCreated' => (string) (new \Katu\Utils\DateTime),
			'productId'   => (int)    ($productId),
			'name'        => (string) ($name),
		]);
	}

	static function make($productId, $name) {
		return static::getOneOrCreateWithList([
			'productId'   => (int)    ($productId),
		], $productId, $name);
	}

	public function getName() {
		return $this->name;
	}

	public function scrape() {

	}

	public function import() {
		$scrapedIngredient = $this->getOrCreateScrapedIngredent();

		$amounts = [
			'base'                      => new \App\Classes\AmountWithUnit(100, 'g'),
			'water'                     => $this->getValueAmountByCode('WATER'),
			'dryMatter'                 => $this->getValueAmountByCode('DRYMAT'),
			'proteins'                  => $this->getValueAmountByCode('PROT'),
			'fats'                      => $this->getValueAmountByCode('FAT'),
			'palmiticAcid'              => $this->getValueAmountByCode('F16:0'),
			'linoleicAcid'              => $this->getValueAmountByCode('F18:2CN6'),
			'saturatedFattyAcids'       => $this->getValueAmountByCode('FASAT'),
			'monounsaturatedFattyAcids' => $this->getValueAmountByCode('FAMS'),
			'polyunsaturatedFattyAcids' => $this->getValueAmountByCode('FAPU'),
			'transFattyAcids'           => $this->getValueAmountByCode('FATRS'),
			'carbs'                     => $this->getValueAmountByCode('CHOT'),
			'fiber'                     => $this->getValueAmountByCode('FIBT'),
			'sodium'                    => $this->getValueAmountByCode('NA'),
			'magnesium'                 => $this->getValueAmountByCode('MG'),
			'phosphorus'                => $this->getValueAmountByCode('P'),
			'potassium'                 => $this->getValueAmountByCode('K'),
			'calcium'                   => $this->getValueAmountByCode('CA'),
			'iron'                      => $this->getValueAmountByCode('FE'),
			'copper'                    => $this->getValueAmountByCode('CU'),
			'zinc'                      => $this->getValueAmountByCode('ZN'),
			'selenium'                  => $this->getValueAmountByCode('SE'),
			'iodine'                    => $this->getValueAmountByCode('ID'),
			'retinol'                   => $this->getValueAmountByCode('RETOL'),
			'vitaminA'                  => $this->getValueAmountByCode('VITA'),
			'vitaminD'                  => $this->getValueAmountByCode('VITD'),
			'vitaminE'                  => $this->getValueAmountByCode('VITE'),
			'vitaminB1'                 => $this->getValueAmountByCode('THIA'),
			'vitaminB2'                 => $this->getValueAmountByCode('RIBF'),
			'vitaminB5'                 => $this->getValueAmountByCode('PANTAC'),
			'vitaminB6'                 => $this->getValueAmountByCode('VITB6'),
			'vitaminB12'                => $this->getValueAmountByCode('VITB12'),
			'vitaminC'                  => $this->getValueAmountByCode('VITC'),
			'energy'                    => $this->getValueAmountByCodeAndUnit('ENERC', 'kJ'),
			'energyFromFats'            => $this->getEnergyByName('ENERGETICKÁ HODNOTA EÚ LIPIDOV (TUKOV)'),
			'energyFromProteins'        => $this->getEnergyByName('ENERGETICKÁ HODNOTA EÚ Z BIELKOVÍN'),
			'energyFromCarbs'           => $this->getEnergyByName('ENERGETICKÁ HODNOTA EÚ ZO SACHARIDOV'),
			'energyFromAlcohol'         => $this->getEnergyByName('ENERGETICKÁ HODNOTA EÚ Z ALKOHOLU'),
		];

		$scrapedIngredient->setScrapedIngredientAmounts($amounts);

		$this->setTimeImported(new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function getNutrientLines() {
		$url = \Katu\Types\TUrl::make('http://www.pbd-online.sk/sk/menu/welcome/detail', [
			'id' => $this->productId,
		]);
		$src = \Katu\Utils\Cache::getUrl($url);
		$dom = \Katu\Utils\DOM::crawlHtml($src);

		$lines = array_values(array_filter($dom->filter('.datatable')->each(function($e, $i) {
			if ($i == 1) {
				$lines = array_values(array_filter($e->filter('tr')->each(function($e) {
					if ($e->attr('class') != 'th') {

						return [
							'name' => trim($e->filter('td:nth-child(1)')->html()),
							'code' => trim($e->filter('td:nth-child(2)')->html()),
							'amountWithUnit' => trim($e->filter('td:nth-child(3)')->html()),
						];

					}
				})));

				return $lines;
			}
		})));

		return $lines[0];
	}

	public function setScraped($scraped) {
		$this->update('timeScraped', (new \Katu\Utils\DateTime)->getDbDateTimeFormat());
		$this->update('scraped', \Katu\Utils\JSON::encode($scraped));

		return true;
	}

	public function getValueArray() {
		return array_values(array_filter(array_map(function($i) {

			if (preg_match('#^(?<amount>[0-9\.]+)\s+(?<unit>[a-z]+)$#ui', $i['amountWithUnit'], $match)) {
				switch ($match['unit']) {
					case 'g' :
						$amountWithUnit = new \App\Classes\AmountWithUnit($match['amount'], 'g');
					break;
					case 'mg' :
						$amountWithUnit = new \App\Classes\AmountWithUnit($match['amount'] * .001, 'g');
					break;
					case 'ug' :
						$amountWithUnit = new \App\Classes\AmountWithUnit($match['amount'] * .000001, 'g');
					break;
					case 'RE' :
						$amountWithUnit = new \App\Classes\AmountWithUnit($match['amount'], 'RE');
					break;
					case 'kcal' :
						$amountWithUnit = new \App\Classes\AmountWithUnit($match['amount'], 'kcal');
					break;
					case 'kJ' :
						$amountWithUnit = new \App\Classes\AmountWithUnit($match['amount'], 'kJ');
					break;
					case 'PCT' :
						$amountWithUnit = new \App\Classes\AmountWithUnit($match['amount'], 'percent');
					break;
				}
			}

			if (isset($amountWithUnit)) {
				return [
					'name' => $i['name'],
					'code' => $i['code'],
					'amountWithUnit' => $amountWithUnit,
				];
			}

		}, \Katu\Utils\JSON::decodeAsArray($this->scraped))));
	}

	public function getValuesByCode() {
		$values = [];
		foreach ($this->getValueArray() as $value) {
			$values[$value['code']] = $value;
		}

		return $values;
	}

	public function getValueByCode($code) {
		$values = $this->getValuesByCode();
		if (isset($values[$code])) {
			return $values[$code]['amountWithUnit'];
		}

		return false;
	}

	public function getValueAmountByCodeAndUnit($code, $unit) {
		foreach ($this->getValueArray() as $value) {
			if ($value['code'] == $code && $value['amountWithUnit']->unit == $unit) {
				return $value['amountWithUnit'];
			}
		}

		return false;
	}

	public function getValuesByName() {
		$values = [];
		foreach ($this->getValueArray() as $value) {
			$values[$value['name']] = $value;
		}

		return $values;
	}

	public function getValueByName($name) {
		$values = $this->getValuesByName();
		if (isset($values[$name])) {
			return $values[$name]['amountWithUnit'];
		}

		return false;
	}

	public function getValueAmountByCode($code) {
		$value = $this->getValueByCode($code);
		if ($value) {
			return $value;
		}

		return false;
	}

	public function getEnergyByName($name) {
		$energy = $this->getValueAmountByCodeAndUnit('ENERC', 'kJ');
		$value = $this->getValueByName($name);

		if ($energy && $value) {
			return new \App\Classes\AmountWithUnit($energy->amount * $value->amount * .01, $energy->unit);
		}

		return false;
	}

}
