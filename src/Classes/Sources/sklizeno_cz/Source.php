<?php

namespace Deli\Classes\Sources\sklizeno_cz;

class Source extends \Deli\Classes\Sources\Source {

	const HAS_PRODUCT_LOADING    = false;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_ALLERGENS  = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_NUTRIENTS  = false;
	const HAS_PRODUCT_PRICES     = false;

	const XML_URL = 'https://www.sklizeno.cz/heureka.xml';












	static function loadNutrients() {
		$nutrients = $this->scrapeNutrients();

		$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());
		if ($dom->filter('#nutricni-hodnoty tr')->count()) {

			if (preg_match('/na(.+)<span class="unit">(.+)<\/span>/', $dom->filter('#nutricni-hodnoty tr')->eq(0)->filter('th')->eq(1)->html(), $match)) {

				$productAmount = (new \Katu\Types\TString($match[1]))->normalizeSpaces()->trim()->getAsFloat();
				$productUnit = $match[2];
				$productAmountWithUnit = new \Deli\Classes\AmountWithUnit($productAmount, $productUnit);

				try {

					foreach ($this->scrapeNutrients() as $nutrientCode => $nutrientAmountWithUnit) {
						$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, $nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit);
					}

				} catch (\Exception $e) {
					// Nevermind.
				}

			}

		}

		return true;
	}

	public function scrapeNutrients() {
		$src = $this->getSrc();
		$dom = \Katu\Utils\DOM::crawlHtml($src);

		$scrapedNutrients = $dom->filter('#nutricni-hodnoty tr')->each(function($e) {

			if ($e->filter('td')->count()) {
				if (preg_match('/(.+)\(<span class="unit">(.+)<\/span>\)/', $e->filter('td')->eq(0)->html(), $match)) {

					$nutrientName = (string)(new \Katu\Types\TString($match[1]))->normalizeSpaces()->trim();
					$nutrientAmount = (new \Katu\Types\TString($e->filter('td')->eq(1)->html()))->getAsFloat();
					$nutrientUnit = $match[2];

					$nutrientCode = null;
					switch ($nutrientName) {
						case 'Energie'                           : $nutrientCode = 'energy';              break;
						case 'Tuky'                              : $nutrientCode = 'fats';                break;
						case 'Sacharidy'                         : $nutrientCode = 'carbs';               break;
						case 'Bílkoviny'                         : $nutrientCode = 'proteins';            break;
						case 'Cukry v sacharidech'               : $nutrientCode = 'sugar';               break;
						case 'Sůl'                               : $nutrientCode = 'salt';                break;
						case 'Nasycené mastné kyseliny v tucích' : $nutrientCode = 'saturatedFattyAcids'; break;
						case 'Vláknina'                          : $nutrientCode = 'fiber';               break;
					}

					if ($nutrientCode && (($nutrientCode == 'energy' && $nutrientUnit == 'kJ') || $nutrientCode != 'energy')) {
						return [
							'nutrientCode' => $nutrientCode,
							'nutrientAmountWithUnit' => new \Deli\Classes\AmountWithUnit($nutrientAmount, $nutrientUnit),
						];
					}

				}
			}

		});

		$scrapedNutrients = array_values(array_filter($scrapedNutrients));

		$nutrients = [];
		foreach ($scrapedNutrients as $scrapedNutrient) {
			$nutrients[$scrapedNutrient['nutrientCode']] = $scrapedNutrient['nutrientAmountWithUnit'];
		}

		return $nutrients;
	}

	static function loadAllergens() {
		foreach ($this->scrapeAllergens() as $allergen) {
			$this->setProductAllergen(ProductAllergen::SOURCE_ORIGIN, $allergen);
		}

		return true;
	}

	public function scrapeAllergens() {
		$config = \Deli\Models\ProductAllergen::getConfig();

		$allergens = [];

		$src = $this->getSrc();
		if (preg_match('/<a href="#(atribut-(.+))">Pro alergiky<\/a>/', $src, $match)) {

			$dom = \Katu\Utils\DOM::crawlHtml($src);
			if (preg_match_all('/\(([0-9]+)\)/', $dom->filter('#atribut-2')->html(), $matches)) {

				foreach ($matches[1] as $allergenId) {

					if (isset($config['list'][$allergenId]['code'])) {
						$allergens[] = $config['list'][$allergenId]['code'];
					}

				}

			}

		}

		return $allergens;
	}

}
