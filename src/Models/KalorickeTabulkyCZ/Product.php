<?php

namespace Deli\Models\KalorickeTabulkyCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_kaloricketabulky_cz_products';
	const SOURCE = 'kaloricketabulky_cz';
	const SOURCE_LABEL = 'kaloricketabulky.cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'buildProductList'], 1800, function() {

				$filters = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'Č', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'Š', 'S', 'Š', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'Ž'];
				foreach ($filters as $filter) {

					$url = \Katu\Types\TUrl::make('http://www.kaloricketabulky.cz/tabulka-potravin.php', [
						'pismeno' => $filter,
					]);

					$res = \Katu\Utils\Cache::getUrl($url, static::TIMEOUT);
					$dom = \Katu\Utils\DOM::crawlHtml($res);

					$total = $dom->filter('#page .listing b')->html();
					if ($total) {

						$pages = ceil($total / 50);
						for ($page = 1; $page <= $pages; $page++) {

							\Katu\Utils\Cache::get(function($filter, $page) {

								$offset = ($page - 1) * 50;
								$url = \Katu\Types\TUrl::make('http://www.kaloricketabulky.cz/tabulka-potravin.php', [
									'pismeno' => $filter,
									'from' => $offset,
								]);
								$res = \Katu\Utils\Cache::getUrl($url, static::TIMEOUT);
								$dom = \Katu\Utils\DOM::crawlHtml($res);
								$dom->filter('.vypis tbody tr h3 a')->each(function($e) {

									try {

										static::upsert([
											'uri' => $e->attr('href'),
										], [
											'timeCreated' => new \Katu\Utils\DateTime,
										], [
											'name' => $e->html(),
										]);

									} catch (\Exception $e) {
										// Nevermind.
									}

								});

							}, static::TIMEOUT, $filter, $page);

						}

					}

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function getUrl() {
		return 'http://www.kaloricketabulky.cz/' . urlencode(trim($this->uri, '/'));
	}

	public function getSrc($timeout = 86400) {
		return \Katu\Utils\Cache::getUrl($this->getUrl(), $timeout);
	}

	public function load() {
		$this->loadCategory();
		$this->loadNutrients();

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function scrapeNutrientAssoc() {
		$src = $this->getSrc();

		$list = \Katu\Utils\DOM::crawlHtml($src)->filter('#detailHodnot tr')->each(function($e) {
			if (preg_match('#<td.*>\s*<span( class="ramec")?>(?<amountWithUnit>.+)</span>\s*(<a href=".+">)?(?<name>.+)(</a>)?:\s*</td>#Us', $e->html(), $match)) {

				$name = preg_replace('/[\x00-\x1F\x7F\xA0]/u', null, trim(strip_tags($match['name'])));
				$amountWithUnit = trim(strip_tags($match['amountWithUnit']));

				return [
					'name' => $name,
					'amountWithUnit' => $amountWithUnit,
				];

			}
		});

		$list = array_values(array_filter($list));

		$nutrientAssoc = [];
		foreach (array_values(array_filter($list)) as $listItem) {
			$nutrientAssoc[$listItem['name']] = $listItem['amountWithUnit'];
		}

		return $nutrientAssoc;
	}

	public function loadCategory() {
		$nutrientAssoc = $this->scrapeNutrientAssoc();

		if (isset($nutrientAssoc['Kategorie'])) {
			$this->setCategory($nutrientAssoc['Kategorie']);
			$this->save();
		}

		return true;
	}

	public function scrapeProductAmountWithUnit() {
		$nutrientAssoc = $this->scrapeNutrientAssoc();

		$ingredientUnitSource = $nutrientAssoc['Jednotka'];
		if (preg_match('/(?<times>[0-9]+)x (?<amount>[0-9\,\.\s]+)\s*(?<unit>(g|ml))/u', $ingredientUnitSource, $match)) {
			$productAmountWithUnit = new \Deli\AmountWithUnit($match['times'] * (new \Katu\Types\TString($match['amount']))->getAsFloat(), $match['unit']);
		} elseif (preg_match('/(?<times>[0-9]+)x (?<practicalUnit>.+) \((?<amount>[0-9\,\.\s]+) (?<unit>(g|ml))\)/u', $ingredientUnitSource, $match)) {
			$productAmountWithUnit = new \Deli\AmountWithUnit($match['times'] * (new \Katu\Types\TString($match['amount']))->getAsFloat(), $match['unit']);
		} else {

			$ignore = [
				'1x kyblík',
				'1x porce',
				'1x 1porce',
				'1x 1ks',
				'1x 1kus',
			];

			if (!in_array($ingredientUnitSource, $ignore)) {
				var_dump($ingredientUnitSource); die;
			}

		}

		return $productAmountWithUnit;
	}

	public function scrapeNutrients() {
		$nutrientAssoc = $this->scrapeNutrientAssoc();

		$ignoreNutrientNames = [
			'Jednotka',
			'Kategorie',
			'Značka',
			'Stav',
		];

		$nutrients = [];
		foreach ($nutrientAssoc as $nutrientName => $nutrientAmountSource) {

			if ($nutrientAmountSource == '-') {
				continue;
			}

			if (in_array($nutrientName, $ignoreNutrientNames)) {
				continue;
			}

			if (preg_match('/(?<amount>[0-9\,\.\s]+) (?<unit>kJ|kcal|g|mg)/', $nutrientAmountSource, $match)) {
				$nutrientAmount = (new \Katu\Types\TString($match['amount']))->getAsFloat();
				$nutrientUnit = $match['unit'];
			} else {

				$ignore = [
					'tepelně zpracované',
				];

				if (!in_array($nutrientAmountSource, $ignore)) {
					var_dump($nutrientAmountSource); die;
				}

			}

			$nutrientNameMap = [
				'Energie' => 'energy',
				'Kalorie' => 'calories',
				'Bílkoviny' => 'proteins',
				'Sacharidy' => 'carbs',
				'Z toho cukry' => 'sugar',
				'Tuky' => 'fats',
				'Z toho nasycené mastné kyseliny' => 'saturatedFattyAcids',
				'Transmastné kyseliny' => 'transFattyAcids',
				'Mononenasycené mastné kyseliny' => 'monounsaturatedFattyAcids',
				'Polynenasycené mastné kyseliny' => 'polyunsaturatedFattyAcids',
				'Sůl' => 'salt',
				'Vláknina' => 'fiber',
				'Cholesterol' => 'cholesterol',
				'Vápník' => 'calcium',
				'Voda' => 'water',
			];

			if (isset($nutrientNameMap[$nutrientName])) {
				$nutrients[$nutrientNameMap[$nutrientName]] = new \Deli\AmountWithUnit($nutrientAmount, $nutrientUnit);
			} else {
				var_dump($nutrientName); die;
			}

		}

		return $nutrients;
	}

	public function loadNutrients() {
		try {

			$productAmountWithUnit = $this->scrapeProductAmountWithUnit();

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
