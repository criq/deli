<?php

namespace Deli\Models\Kaloricke_TabulkyCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_kaloricke_tabulky_cz_products';
	const SOURCE = 'kaloricke_tabulky_cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'buildProductList'], 1800, function() {

				$url = 'http://www.kaloricke-tabulky.cz/';
				$src = \Katu\Utils\Cache::getUrl($url);
				$dom = \Katu\Utils\DOM::crawlHtml($src);
				$dom->filter('#dropdown_category ul li a')->each(function($e) {

					$category = $e->text();

					$url = 'http://www.kaloricke-tabulky.cz' . $e->attr('href');
					$src = \Katu\Utils\Cache::getUrl($url);
					$dom = \Katu\Utils\DOM::crawlHtml($src);

					return $dom->filter('#kalDenik .odd_row_c, #kalDenik .even_row_c')->each(function($e) use($category) {

						try {

							$baseUnitSource = trim($e->filter('.second_td')->html());
							if (preg_match('/^(?<amount>[0-9\,\.\s]+)\s*(?<unit>g|ml)$/u', $baseUnitSource, $match)) {
								$baseAmount = $match['amount'];
								$baseUnit = $match['unit'];
							} else {
								#var_dump($baseUnitSource); die;
							}

							$product = static::upsert([
								'uri' => trim($e->filter('a')->attr('href')),
							], [
								'timeCreated' => new \Katu\Utils\DateTime,
							], [
								'name' => trim($e->filter('a')->text()),
								'baseAmount' => $baseAmount,
								'baseUnit' => $baseUnit,
							]);

							$product->setCategory($category);
							$product->save();

						} catch (\Exception $e) {
							// Nevermind.
						}

					});

				});

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function getUrl() {
		return 'http://www.kaloricke-tabulky.cz' . $this->uri;
	}

	public function getSrc() {
		return \Katu\Utils\Cache::getUrl($this->getUrl());
	}

	public function getRemoteId() {
		preg_match('/([0-9]+)$/', trim($this->uri, '/'), $match);

		return $match[1];
	}

	public function load() {
		$this->loadNutrients();

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function scrapeNutrientAssoc() {
		$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());

		// Basic.
		$listBasic = $dom->filter('.nutrition_base .nutrition_box')->each(function($e) {

			return [
				'name' => trim($e->filter('.percent_name')->text()),
				'amountWithUnit' => trim($e->filter('.text')->text()),
			];

		});

		$src = \Katu\Utils\Cache::get(function($remoteId) {

			$url = \Katu\Types\TUrl::make('http://www.kaloricke-tabulky.cz/stranka/suroviny/', [
				'section_contentLeft-widget_46-surovina_uid' => $remoteId,
				'do' => 'section_contentLeft-widget_46-ShowNutrition',
			]);

			$curl = new \Curl\Curl;
			$curl->setHeader('Accept', 'application/json');
			$curl->setHeader('X-Requested-With', 'XMLHttpRequest');
			$res = $curl->get($url);
			$src = $res->snippets->{'snippet-section_contentLeft-widget_46-nutrition'};

			return $src;

		}, null, $this->getRemoteId());

		$dom = \Katu\Utils\DOM::crawlHtml($src);
		$listAdvanced = $dom->filter('.full_nutrition table tr')->each(function($e) {

			return [
				'name' => trim($e->filter('td')->eq(0)->text()),
				'amountWithUnit' => trim($e->filter('td')->eq(1)->text()),
			];

		});

		$list = array_merge($listBasic, $listAdvanced);

		$nutrientAssoc = [];
		foreach (array_values(array_filter($list)) as $listItem) {
			$nutrientAssoc[$listItem['name']] = $listItem['amountWithUnit'];
		}

		return $nutrientAssoc;
	}

	public function scrapeNutrients() {
		$nutrientAssoc = $this->scrapeNutrientAssoc();

		$nutrientNameMap = [
		  'Kalorie' => 'calories',
		  'Sacharidy' => 'carbs',
		  'Tuky' => 'fats',
		  'Bílkoviny' => 'proteins',
		  'Sodík' => 'sodium',
		  'Voda' => 'water',
		  #'Ash' =>
		  'Vláknina' => 'fiber',
		  'Cukr' => 'sugar',
		  'Vápník' => 'calcium',
		  'Železo' => 'iron',
		  'Magnézium' => 'magnesium',
		  'Fosfor' => 'phosphorus',
		  'Draslík' => 'potassium',
		  'Zinek' => 'zinc',
		  'Měď' => 'copper',
		  'Mangan' => 'manganese',
		  'Selen' => 'selenium',
		  'Vitamin C' => 'vitaminC',
		  'Vitamin B1' => 'vitaminB1',
		  'Vitamin B2' => 'vitaminB2',
		  'Vitamin B3' => 'vitaminB3',
		  'Vitamin B5' => 'vitaminB5',
		  'Vitamin B6' => 'vitaminB6',
		  #'Folate total' => string '0.005 µg' (length=9)
		  #'Folate, food' => string '0.005 µg' (length=9)
		  #'Folate, DFE' => string '0.005 µg' (length=9)
		  'Vitamin A' => 'vitaminA',
		  'Vitamin A1' => 'vitaminA1',
		  'Vitamin E' => 'vitaminE',
		  'Vitamin K' => 'vitaminK',
		  #'Cholin' => string '3.8 mg' (length=6)
		  'Mastné kyseliny' => 'saturatedFattyAcids',
		  #'16:0' => string '0.005 g' (length=7)
		  #'18:0' => string '0.003 g' (length=7)
		  'Nenasycené mastné kyseliny' => 'monounsaturatedFattyAcids',
		  #'16:1' => string '0.001 g' (length=7)
		  #'18:1' => string '0.012 g' (length=7)
		  'Polynenasycené mastné kyseliny' => 'polyunsaturatedFattyAcids',
		  #'18:2' => string '0.023 g' (length=7)
		  #'18:3' => string '0.017 g' (length=7)
		  #'Tryptofa' => string '0.005 g' (length=7)
		  #'Threonin' => string '0.009 g' (length=7)
		  #'Isoleucin' => string '0.009 g' (length=7)
		  #'Leucin' => string '0.013 g' (length=7)
		  #'Lysin' => string '0.016 g' (length=7)
		  #'Methionin' => string '0.009 g' (length=7)
		  #'Cystein' => string '0.001 g' (length=7)
		  #'Fenylalanin' => string '0.009 g' (length=7)
		  #'Tyrosin' => string '0.008 g' (length=7)
		  #'Valin' => string '0.011 g' (length=7)
		  #'Arginin' => string '0.012 g' (length=7)
		  #'Histidin' => string '0.008 g' (length=7)
		  #'Alanin' => string '0.018 g' (length=7)
		  #'Kyselina aspartová' => string '0.081 g' (length=7)
		  #'Kyselina glutamová' => string '0.041 g' (length=7)
		  #'Glycin' => string '0.014 g' (length=7)
		  #'Prolin' => string '0.009 g' (length=7)
		  #'Serin' => string '0.019 g' (length=7)
		  #'Theobromin' => string '8 mg' (length=4)
		  #'Uhlovodany' => string '20.2 g' (length=6)
		];

		$nutrients = [];
		foreach ($nutrientAssoc as $nutrientName => $nutrientAmountSource) {

			if (!isset($nutrientNameMap[$nutrientName])) {
				continue;
			}

			$nutrientAmountWithUnit = null;
			if (preg_match('/(?<amount>[0-9\,\.\s]+)\s*(?<unit>kcal|g|mg|µg)/', $nutrientAmountSource, $match)) {

				$amount = (new \Katu\Types\TString($match['amount']))->getAsFloat();

				switch ($match['unit']) {
					case 'µg' :
						$nutrientAmountWithUnit = new \Deli\AmountWithUnit($amount * .000001, 'g');
					break;
					case 'mg' :
						$nutrientAmountWithUnit = new \Deli\AmountWithUnit($amount * .001, 'g');
					break;
					default :
						$nutrientAmountWithUnit = new \Deli\AmountWithUnit($amount, $match['unit']);
					break;
				}

				if ($nutrientAmountWithUnit) {
					$nutrients[$nutrientNameMap[$nutrientName]] = $nutrientAmountWithUnit;
				}

			}

		}

		return $nutrients;
	}

	public function loadNutrients() {
		try {

			$productAmountWithUnit = new \Deli\AmountWithUnit($this->baseAmount, $this->baseUnit);

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
