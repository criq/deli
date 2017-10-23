<?php

namespace Deli\Models\CountrylifeCz;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_countrylife_cz_products';
	const SOURCE = 'countrylife_cz';

	static function buildProductList() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'buildProductList'], 3600, function() {

				@ini_set('memory_limit', '512M');

				$src = \Katu\Utils\Cache::getUrl('https://www.countrylife.cz/biopotraviny', static::TIMEOUT);
				$dom = \Katu\Utils\DOM::crawlHtml($src);
				$dom->filter('.category-list ul li')->each(function($e) {

					$categoryName = trim($e->filter('a .name')->text());
					$categoryUri = trim($e->filter('a')->attr('href'));

					$src = \Katu\Utils\Cache::getUrl('https://www.countrylife.cz' . $categoryUri, static::TIMEOUT);
					$dom = \Katu\Utils\DOM::crawlHtml($src);
					try {
						$pages = (int)$dom->filter('.pager a')->last()->html();
					} catch (\Exception $e) {
						$pages = 1;
					}

					for ($page = 1; $page <= $pages; $page++) {

						$src = \Katu\Utils\Cache::getUrl(\Katu\Types\TUrl::make('https://www.countrylife.cz' . $categoryUri, [
							'page' => $page,
						]), static::TIMEOUT);
						$dom = \Katu\Utils\DOM::crawlHtml($src);
						$dom->filter('.product-list .item')->each(function($i) use($categoryName) {

							$product = static::upsert([
								'uri' => trim($i->filter('a.top')->attr('href')),
							], [
								'timeCreated' => new \Katu\Utils\DateTime,
							], [
								'name' => trim($i->filter('h2 .name')->html()),
							]);
							$product->setRemoteCategory($categoryName);
							$product->save();

						});

					}

				});

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Katu\Exceptions\LockException $e) {
			// Nevermind.
		}
	}

	public function getUrl() {
		return 'https://www.countrylife.cz' . $this->uri;
	}

	public function getSrc() {
		return \Katu\Utils\Cache::getUrl($this->getUrl());
	}

	public function load() {
		try {

			$this->loadRemoteId();
			$this->loadNutrients();
			$this->loadAllergens();
			$this->loadContents();

			$this->update('isAvailable', 1);

		} catch (\Exception $e) {

			$this->update('isAvailable', 0);

		}

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function loadRemoteId() {
		$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());

		try {

			$this->update('remoteId', $dom->filter('form#frm-productInfo-buyBoxForm [name="productItem"]')->attr('value'));
			$this->save();

		} catch (\Exception $e) {

			$this->update('remoteId', \Katu\Types\TUrl::make('https://www.countrylife.cz' . $dom->filter('.icon-dog')->attr('href'))->getQueryParam('product'));
			$this->save();

		}

		return true;
	}

	public function scrapeNutrients() {
		$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());

		try {

			$table = $dom->filter('#popis-slozeni .ca-box .table-content');

			$nutrientSets = $table->filter('tr')->each(function($e) {

				$nutrients = [];
				if (preg_match('/^Energetická hodnota\s*([0-9\s\,\.]+)\s*kJ\s*\/\s*([0-9\s\,\.]+)\s*kcal$/u', trim($e->text()), $match)) {
					$nutrients['energy'] = new \Effekt\AmountWithUnit($match[1], 'kJ');
					$nutrients['calories'] = new \Effekt\AmountWithUnit($match[2], 'kcal');
				} else {
					$nutrientCode = null;
					switch (trim($e->filter('th')->text())) {
						case 'Tuky:'                            : $nutrientCode = 'fats';                break;
						case 'z toho nasycené mastné kyseliny:' : $nutrientCode = 'saturatedFattyAcids'; break;
						case 'Sacharidy:'                       : $nutrientCode = 'carbs';               break;
						case 'z toho cukry:'                    : $nutrientCode = 'sugar';               break;
						case 'Bílkoviny:'                       : $nutrientCode = 'proteins';            break;
						case 'Sůl:'                             : $nutrientCode = 'salt';                break;
						case 'Vláknina:'                        : $nutrientCode = 'fiber';               break;
						default : var_dump(trim($e->filter('th')->text())); die; break;
					}
					$nutrientAmountWithUnit = null;
					if (preg_match('/([0-9\s\,\.]+)\s*(g)/us', trim($e->filter('td')->text()), $match)) {
						$nutrientAmountWithUnit = new \Effekt\AmountWithUnit($match[1], $match[2]);
					}
					if ($nutrientCode && $nutrientAmountWithUnit) {
						$nutrients[$nutrientCode] = $nutrientAmountWithUnit;
					}
				}

				return $nutrients;

			});

			$nutrients = [];
			foreach ($nutrientSets as $nutrientSet) {
				foreach ($nutrientSet as $nutrientCode => $nutrientAmountWithUnit) {
					$nutrients[$nutrientCode] = $nutrientAmountWithUnit;
				}
			}

			return $nutrients;

		} catch (\Exception $e) {
			// Nevermind.
		}

		return $nutrients;
	}

	public function scrapeProductAmountWithUnit() {
		$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());

		try {

			$text = $dom->filter('#popis-slozeni .ca-box h3')->text();
			if (preg_match('/Výživové údaje na ([0-9\s\,\.]+)\s*(g|ml)/us', $text, $match)) {
				return new \Effekt\AmountWithUnit($match[1], $match[2]);
			} else {
				var_dump($text); die;
			}

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

	public function loadNutrients() {
		try {

			$productAmountWithUnit = $this->getProductAmountWithUnit();
			foreach ($this->scrapeNutrients() as $nutrientCode => $nutrientAmountWithUnit) {
				$this->setProductNutrient($nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit);
			}

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

	public function scrapeInfo() {
		$dom = \Katu\Utils\DOM::crawlHtml($this->getSrc());

		try {

			$info = [];
			foreach (array_filter(array_map('trim', explode('<h3>', $dom->filter('#content')->html()))) as $line) {
				list($title, $content) = explode('</h3>', $line);
				$info[trim($title)] = trim(strip_tags($content));
			}

			return $info;

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

	public function scrapeAllergensInContents() {
		$allergens = [];

		$info = $this->scrapeInfo();
		if (isset($info['Složení']) && preg_match_all('/([\p{Lu}]{2,1000})/u', $info['Složení'], $matches)) {

			$allergens = array_values(array_unique(array_map(function($i) {
				return mb_strtolower($i, 'UTF-8');
			}, array_map('strip_tags', $matches[1]))));

		}

		return $allergens;
	}

	public function scrapeAllergensInAllergens() {
		$allergens = [];

		$info = $this->scrapeInfo();
		if (isset($info['Alergeny'])) {

			$allergens = array_values(array_unique(array_map(function($i) {
				return trim(mb_strtolower($i, 'UTF-8'));
			}, explode(',', $info['Alergeny']))));

		}

		return $allergens;
	}

	public function loadAllergens() {
		$allergenCodes = array_merge(
			static::getAllergenCodesFromTexts($this->scrapeAllergensInContents()),
			static::getAllergenCodesFromTexts($this->scrapeAllergensInAllergens())
		);
		$allergenCodes = array_values(array_unique($allergenCodes));

		foreach ($allergenCodes as $allergenCode) {
			$this->setProductAllergen($allergenCode);
		}

		return true;
	}

	public function scrapeContents() {
		try {
			$info = $this->scrapeInfo();
			if (isset($info['Složení'])) {
				return $info['Složení'];
			}
		} catch (\Exception $e) {
			return false;
		}
	}

	public function loadContents() {
		$this->setProductProperty('contents', $this->scrapeContents());

		return true;
	}

	public function loadPrice() {

		echo $this->getSrc(); die;

	}

}
