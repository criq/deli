<?php

namespace Deli\Models\KalorickeTabulkyCZ;

class Item extends \Deli\Models\Item {

	const TABLE = 'deli_kaloricketabulky_cz_items';
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









	static function build() {
		try {

			var_dump("A");

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'build'], 600, function() {

				$timeout = 86400;

				$filters = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'Č', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'Š', 'S', 'Š', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'Ž'];
				foreach ($filters as $filter) {

					$url = \Katu\Types\TUrl::make('http://www.kaloricketabulky.cz/tabulka-potravin.php', [
						'pismeno' => $filter,
					]);
					$res = \Katu\Utils\Cache::getUrl($url, $timeout);
					$dom = \Katu\Utils\DOM::crawlHtml($res);

					$total = $dom->filter('#page .listing b')->html();
					if ($total) {

						$pages = ceil($total / 50);
						for ($page = 1; $page <= $pages; $page++) {

							$offset = ($page - 1) * 50;
							$url = \Katu\Types\TUrl::make('http://www.kaloricketabulky.cz/tabulka-potravin.php', [
								'pismeno' => $filter,
								'from' => $offset,
							]);
							$res = \Katu\Utils\Cache::getUrl($url, $timeout);
							$dom = \Katu\Utils\DOM::crawlHtml($res);
							$dom->filter('.vypis tbody tr h3 a')->each(function($e) {
								try {
									$object = Item::make($e->attr('href'));
									$object->setName($e->html());
									$object->save();
								} catch (\Exception $e) {

								}
							});

						}

					}

				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function scrape() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'scrape'], 600, function() {

				$items = Item::getBy([
					SX::lgcOr([
						SX::cmpIsNull(Item::getColumn('timeScraped')),
						SX::cmpLessThan(Item::getColumn('timeScraped'), (new \Katu\Utils\DateTime('- 1 month'))->getDbDateTimeFormat()),
					]),
				], [
					'page' => SX::page(1, 100),
				]);

				foreach ($items as $item) {
					try {
						$item->scrape();
					} catch (\Exception $e) {
						/* Nevermind. */
					}
				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

	static function import() {
		try {

			\Katu\Utils\Lock::run(['deli', static::SOURCE, 'import'], 600, function() {

				$items = Item::getBy([
					SX::cmpIsNotNull(Item::getColumn('timeScraped')),
					SX::lgcOr([
						SX::cmpIsNull(Item::getColumn('timeImported')),
						SX::cmpLessThan(Item::getColumn('timeImported'), (new \Katu\Utils\DateTime('- 1 month'))->getDbDateTimeFormat()),
					]),
				], [
					'page' => SX::page(1, 100),
				]);

				foreach ($items as $item) {
					try {
						$item->import();
						$item->getOrCreateScrapedIngredent()->refreshIngredientNutrients();
					} catch (\Exception $e) {
						/* Nevermind. */
					}
				}

			});

		} catch (\Katu\Exceptions\LockException $e) {
			/* Nevermind. */
		}
	}

}
