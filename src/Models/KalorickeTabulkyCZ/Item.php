<?php

namespace Deli\Models\KalorickeTabulkyCZ;

class Item extends \Deli\Models\Item {

	const TABLE = 'deli_kaloricke_tabulky';

	const SOURCE = 'kaloricketabulky.cz';

	static function create($uri) {
		return static::insert([
			'timeCreated' => (string) (new \Katu\Utils\DateTime),
			'uri'         => (string) ($uri),
		]);
	}

	static function make($uri) {
		return static::getOneOrCreateWithList([
			'uri' => $uri,
		], $uri);
	}

	public function setName($name) {
		$this->update('name', $name);

		return true;
	}

	public function getName() {
		return $this->name;
	}

	public function getUrl() {
		return 'http://www.kaloricketabulky.cz/' . urlencode(trim($this->uri, '/'));
	}

	public function scrape() {
		try {

			$res = \Katu\Utils\Cache::getUrl($this->getUrl(), 3600);

			$source = \Katu\Utils\DOM::crawlHtml($res)->filter('#detailHodnot tr')->each(function($e) {
				if (preg_match('#<td.*>\s*<span( class="ramec")?>(?<value>.+)</span>\s*(<a href=".+">)?(?<label>.+)(</a>)?:\s*</td>#Us', $e->html(), $match)) {
					$label = ucfirst(trim(preg_replace('#^Z toho#', null, preg_replace('#&nbsp;#', null, strip_tags(trim($match['label']))))));
					$value = preg_replace('#^-$#', 0, strip_tags(trim($match['value'])));

					return [$label, $value];
				}
			});

			$properties = [];
			foreach (array_values(array_filter($source)) as $sourceItem) {
				$properties[$sourceItem[0]] = $sourceItem[1];
			}

			$this->update('scraped', \Katu\Utils\JSON::encode($properties));
			$this->update('timeScraped', (new \Katu\Utils\DateTime));
			$this->save();

			return true;

		} catch (\Exception $e) {

			$this->delete();

			return false;

		}
	}

	public function import() {
		$amounts = [
			'base'            => $this->getBase(),
			'energy'          => $this->getValueAmount('Energie'),
			'calories'        => $this->getValueAmount('Kalorie'),
			'proteins'        => $this->getValueAmount('Bílkoviny'),
			'carbs'           => $this->getValueAmount('Sacharidy'),
			'sugar'           => $this->getValueAmount('Z toho cukry'),
			'fats'            => $this->getValueAmount('Tuky'),
			'fattyAcids'      => $this->getValueAmount('Z toho nasycené mastné kyseliny'),
			'transFattyAcids' => $this->getValueAmount('Transmastné kyseliny'),
			'cholesterol'     => $this->getValueAmount('Cholesterol'),
			'fiber'           => $this->getValueAmount('Vláknina'),
			'natrium'         => $this->getValueAmount('Sodík'),
			'calcium'         => $this->getValueAmount('Vápník'),
		];

		$this->getOrCreateScrapedIngredent()->setScrapedIngredientAmounts($amounts);

		$this->setTimeImported(new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function getValues() {
		$values = [];

		foreach (\Katu\Utils\JSON::decodeAsObjects($this->scraped) as $key => $value) {
			$values[preg_replace('#^\s*#u', null, $key)] = $value;
		}

		return $values;
	}

	public function getValue($key) {
		$values = $this->getValues();

		if (isset($values[$key])) {
			return $values[$key];
		}

		return false;
	}

	public function getValueAmount($key) {
		if (preg_match('#^(?<amount>[0-9, ]+)\s+(?<unit>[a-z]+)$#i', $this->getValue($key), $match)) {
			return new \App\Classes\AmountWithUnit(strtr(preg_replace('#\s#', null, $match['amount']), ',', '.'), $match['unit']);
		}

		return false;
	}

	public function getBase() {
		$value = $this->getValue('Jednotka');

		if (preg_match('#\((?<amount>[0-9\.]+) (?<unit>[a-z]+)\)$#i', $value, $match)) {
			return new \App\Classes\AmountWithUnit($match['amount'], $match['unit']);
		} elseif (preg_match('#^1x (?<amount>[0-9\.]+)\s*(?<unit>[a-z]+)$#i', $value, $match)) {
			return new \App\Classes\AmountWithUnit($match['amount'], $match['unit']);
		} elseif (preg_match('#1x .+ \((?<amount>[0-9\.]+)\s*(?<unit>[a-z]+)\)#i', $value, $match)) {
			return new \App\Classes\AmountWithUnit($match['amount'], $match['unit']);
		} elseif (preg_match('#1x (?<amount>[0-9\.]+)\s*(?<unit>[a-z]+)#i', $value, $match)) {
			return new \App\Classes\AmountWithUnit($match['amount'], $match['unit']);
		} else {
			#var_dump($value);
		}

		return false;
	}

}
