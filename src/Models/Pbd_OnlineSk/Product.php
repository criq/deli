<?php

namespace Deli\Models\Pbd_OnlineSk;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_pbd_online_sk_products';
	const SOURCE = 'pbd_online_sk';
	const SOURCE_LABEL = 'pbd-online.sk';

	public function load() {
		$this->loadName();
		$this->loadNutrients();

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function getUrl() {
		return \Katu\Types\TUrl::make('http://www.pbd-online.sk/sk/menu/welcome/detail', [
			'id' => $this->uri,
		]);
	}

	public function getSrc($timeout = 2419200) {
		return \Katu\Utils\Cache::getUrl($this->getUrl(), $timeout);
	}

	public function loadName() {
		$translation = (new \Deli\Classes\Translation('sk', 'cs', $this->originalName))->translate();
		if ($translation) {
			$this->update('name', $translation);
			$this->save();
		}

		return true;
	}

	public function scrapeNutrientAssoc() {
		$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());

		$list = array_values(array_filter($dom->filter('.datatable')->each(function($e, $i) {
			if ($i == 1) {
				$list = array_values(array_filter($e->filter('tr')->each(function($e) {
					if ($e->attr('class') != 'th') {

						return [
							'name' => trim(preg_replace('/\s+/', ' ', $e->filter('td:nth-child(1)')->html())),
							'amountWithUnit' => trim($e->filter('td:nth-child(3)')->html()),
						];

					}
				})));

				return $list;
			}
		})));

		$nutrientAssoc = [];
		foreach (array_values(array_filter($list[0])) as $listItem) {
			$nutrientAssoc[$listItem['name']] = $listItem['amountWithUnit'];
		}

		return $nutrientAssoc;
	}

	public function scrapeNutrients() {
		$nutrientAssoc = $this->scrapeNutrientAssoc();

		$nutrientNameMap = [
			'VODA CELKOVÁ' => 'water',
			'SUŠINA CELKOVÁ' => 'dryMatter',
			'BIELKOVINY CELKOVÉ (HR. PROTEÍN)' => 'proteins',
			'LIPIDY (TUKY) CELKOVÉ' => 'fats',
			'KYS. PALMITOVÁ 16:0' => 'palmiticAcid',
			'KYS. LINOLOVÁ 18:2n-6 **' => 'linoleicAcid',
			'MASTNÉ KYSELINY NASÝTENÉ CELKOVÉ' => 'saturatedFattyAcids',
			'MASTNÉ KYSELINY MONONENASÝTENÉ CELKOVÉ' => 'monounsaturatedFattyAcids',
			'MASTNÉ KYSELINY POLYNENASÝTENÉ CELKOVÉ' => 'polyunsaturatedFattyAcids',
			'trans-MASTNÉ KYSELINY CELKOVÉ' => 'transFattyAcids',
			'CHOLESTEROL' => 'cholesterol',
			'SACHARIDY CELKOVÉ' => 'carbs',
			'SACHARÓZA' => 'sugar',
			'ŠKROB' => 'starch',
			'POTRAVINOVÁ VLÁKNINA CELKOVÁ' => 'fiber',
			'SODÍK ** Na' => 'sodium',
			'HORČÍK ** Mg' => 'magnesium',
			'FOSFOR ** P' => 'phosphorus',
			'SÍRA ** S' => 'sulphur',
			'DRASLÍK ** K' => 'potassium',
			'VÁPNIK ** Ca' => 'calcium',
			'ŽELEZO ** Fe' => 'iron',
			'MEĎ ** Cu' => 'copper',
			'ZINOK ** Zn' => 'zinc',
			'SELÉN ** Se' => 'selenium',
			'JÓD ** I' => 'iodine',
			'CHLORID SODNÝ (KUCHYNSKÁ SOĽ)' => 'salt',
			'VITAMÍN A 1 (RETINOL)' => 'vitaminA',
			'RETINOL EKVIVALENT (RE) (vypočítaný), VITAMÍN A' => 'retinol',
			'VITAMÍN D (KALCIFEROL)' => 'vitaminD',
			'VITAMÍN E (TOKOFEROLY)' => 'vitaminE',
			'VITAMÍN B 1 (TIAMÍN)' => 'vitaminB1',
			'VITAMÍN B 2 (RIBOFLAVÍN)' => 'vitaminB2',
			'VITAMÍN B 5 (KYS. PANTOTÉNOVÁ)' => 'vitaminB5',
			'VITAMÍN B 6 (PYRIDOXÍNY)' => 'vitaminB6',
			'VITAMÍN B 12 (KOBALAMÍNY)' => 'vitaminB12',
			'VITAMÍN C' => 'vitaminC',
			'ENERGETICKÁ HODNOTA EÚ' => 'energy',
			'ENERGETICKÁ HODNOTA EÚ Z BIELKOVÍN' => 'energyFromProteins',
			'ENERGETICKÁ HODNOTA EÚ LIPIDOV (TUKOV)' => 'energyFromFats',
			'ENERGETICKÁ HODNOTA EÚ ZO SACHARIDOV' => 'energyFromCarbs',
			'ENERGETICKÁ HODNOTA EÚ Z ALKOHOLU' => 'energyFromAlcohol'
		];

		$nutrients = [];
		foreach ($nutrientAssoc as $nutrientName => $nutrientAmountSource) {

			if (!isset($nutrientNameMap[$nutrientName])) {
				continue;
			}

			$amountWithUnit = null;
			if (preg_match('#^(?<amount>[0-9\.]+)\s+(?<unit>[a-z]+)$#ui', $nutrientAmountSource, $match)) {
				switch ($match['unit']) {
					case 'g' :
						$amountWithUnit = new \Deli\AmountWithUnit($match['amount'], 'g');
					break;
					case 'mg' :
						$amountWithUnit = new \Deli\AmountWithUnit($match['amount'] * .001, 'g');
					break;
					case 'ug' :
						$amountWithUnit = new \Deli\AmountWithUnit($match['amount'] * .000001, 'g');
					break;
					case 'RE' :
						$amountWithUnit = new \Deli\AmountWithUnit($match['amount'], 'RE');
					break;
					case 'kcal' :
						$amountWithUnit = new \Deli\AmountWithUnit($match['amount'], 'kcal');
					break;
					case 'kJ' :
						$amountWithUnit = new \Deli\AmountWithUnit($match['amount'], 'kJ');
					break;
					case 'PCT' :
						$amountWithUnit = new \Deli\AmountWithUnit($match['amount'], 'percent');
					break;
				}
			}

			if ($amountWithUnit) {
				$nutrients[$nutrientNameMap[$nutrientName]] = $amountWithUnit;
			}

		}

		return $nutrients;
	}

	public function loadNutrients() {
		try {

			$productAmountWithUnit = new \Deli\AmountWithUnit(100, 'g');

			foreach ($this->scrapeNutrients() as $nutrientCode => $nutrientAmountWithUnit) {
				ProductNutrient::upsert([
					'productId' => $this->getId(),
					'nutrientCode' => $nutrientCode,
				], [
					'timeCreated' => new \Katu\Utils\DateTime,
				], [
					'timeUpdated' => new \Katu\Utils\DateTime,
					'nutrientAmount' => $nutrientAmountWithUnit->amount,
					'nutrientUnit' => $nutrientAmountWithUnit->unit,
					'ingredientAmount' => $productAmountWithUnit->amount,
					'ingredientUnit' => $productAmountWithUnit->unit,
				]);
			}

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

}
