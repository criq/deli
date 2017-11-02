<?php

namespace Deli\Models;

use \Sexy\Sexy as SX;
use \Deli\AmountWithUnit;

abstract class Product extends \Deli\Model {

	const TIMEOUT = 2419200;

	static function getProductAllergenTopClass() {
		return implode([
			static::getTopClass(),
			'Allergen',
		]);
	}

	static function getProductEmulgatorTopClass() {
		return implode([
			static::getTopClass(),
			'Emulgator',
		]);
	}

	static function getProductNutrientTopClass() {
		return implode([
			static::getTopClass(),
			'Nutrient',
		]);
	}

	static function getProductPriceTopClass() {
		return implode([
			static::getTopClass(),
			'Price',
		]);
	}

	static function getProductPropertyTopClass() {
		return implode([
			static::getTopClass(),
			'Property',
		]);
	}

	static function getAllSources() {
		$sources = [
			\Deli\Models\Custom\Product::SOURCE              => \Deli\Models\Custom\Product::getTopClass(),
			\Deli\Models\CountrylifeCz\Product::SOURCE       => \Deli\Models\CountrylifeCz\Product::getTopClass(),
			\Deli\Models\ITescoCz\Product::SOURCE            => \Deli\Models\ITescoCz\Product::getTopClass(),
			\Deli\Models\Kaloricke_TabulkyCz\Product::SOURCE => \Deli\Models\Kaloricke_TabulkyCz\Product::getTopClass(),
			\Deli\Models\KalorickeTabulkyCz\Product::SOURCE  => \Deli\Models\KalorickeTabulkyCz\Product::getTopClass(),
			\Deli\Models\Pbd_OnlineSk\Product::SOURCE        => \Deli\Models\Pbd_OnlineSk\Product::getTopClass(),
			\Deli\Models\StobklubCz\Product::SOURCE          => \Deli\Models\StobklubCz\Product::getTopClass(),
			\Deli\Models\UsdaGov\Product::SOURCE             => \Deli\Models\UsdaGov\Product::getTopClass(),
		];

		return $sources;
	}

	public function getSource() {
		return static::SOURCE;
	}

	public function getName() {
		return $this->name;
	}

	public function setRemoteCategory($source, $property = 'remoteCategory') {
		$this->update($property, \Katu\Utils\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)$source)))));

		return true;
	}

	public function setEan($ean) {
		$this->update('ean', trim($ean) ?: null);

		return true;
	}

	public function setShoppingList($shoppingList) {
		$this->update('shoppingListId', $shoppingList->getId());

		return true;
	}

	public function setShoppingListByName($name) {
		return $this->setShoppingList(ShoppingList::upsert([
			'name' => trim($name),
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		]));
	}

	public function setCategoryByName($name) {
		if (is_string($name)) {
			$name = [$name];
		}

		$category = null;
		foreach ($name as $categoryName) {
			$category = Category::make($category, $categoryName);
		}

		$this->update('categoryId', $category->getId());

		return true;
	}

	public function setProductAllergen($source, $allergenCode) {
		$class = static::getProductAllergenTopClass();

		return $class::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'allergenCode' => $allergenCode,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		]);
	}

	public function setProductEmulgator($source, $emulgator) {
		$class = static::getProductEmulgatorTopClass();

		return $class::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'emulgatorId' => $emulgator->getId(),
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		]);
	}

	public function getProductAmountWithUnit() {
		$class = static::getProductNutrientTopClass();

		// Look into existing first.
		$productNutrient = $class::getOneBy([
			'productId' => $this->getId(),
		]);
		if ($productNutrient) {
			return new \Effekt\AmountWithUnit($productNutrient->ingredientAmount, $productNutrient->ingredientUnit);
		}

		// Else scrape.
		if (method_exists($this, 'scrapeProductAmountWithUnit')) {
			$scraped = $this->scrapeProductAmountWithUnit();
			if ($scraped) {
				return $scraped;
			}
		}

		// Default.
		return new \Effekt\AmountWithUnit(100, 'g');
	}

	public function setProductNutrientIfEmpty($source, $nutrientCode, $nutrientAmountWithUnit) {
		$class = static::getClass();
		$productNutrientClass = $class . 'Nutrient';

		$productNutrient = $productNutrientClass::getOneBy([
			'productId' => $this->getId(),
			'source' => $source,
			'nutrientCode' => $nutrientCode,
		]);
		if (!$productNutrient) {
			$productNutrient = $this->setProductNutrient($source, $nutrientCode, $nutrientAmountWithUnit, $this->getProductAmountWithUnit());
		}

		return $productNutrient;
	}

	public function setProductNutrient($source, $nutrientCode, $nutrientAmountWithUnit, $productAmountWithUnit) {
		$class = static::getProductNutrientTopClass();

		return $class::upsert([
			'productId' => $this->getId(),
			'source' => $source,
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

	public function getProductNutrients() {
		$class = static::getProductNutrientTopClass();

		if (class_exists($productNutrientClass)) {
			return $class::getBy([
				'productId' => $this->getId(),
			]);
		}

		return false;
	}

	public function getProductNutrientByCode($code) {
		$class = static::getClass();
		$productNutrientClass = $class . 'Nutrient';

		if (class_exists($productNutrientClass)) {
			return $productNutrientClass::getBy([
				'productId' => $this->getId(),
				'nutrientCode' => $code,
			])->getOne();
		}

		return false;
	}

	public function getProductAllergens() {
		$class = static::getClass();
		$productAllergenClass = $class . 'Allergen';

		if (class_exists($productAllergenClass)) {
			return $productAllergenClass::getBy([
				'productId' => $this->getId(),
			]);
		}

		return false;
	}

	static function getForLoadSql() {
		$sql = SX::select()
			->from(static::getTable())
			->where(SX::lgcOr([
				SX::cmpIsNull(static::getColumn('timeLoaded')),
				SX::cmpLessThan(static::getColumn('timeLoaded'), new \Katu\Utils\DateTime('- 1 month')),
			]))
			->where(SX::eq(static::getColumn('isBanned'), 0))
			->orderBy([
				SX::orderBy(static::getColumn('timeLoaded')),
				SX::orderBy(static::getIdColumn()),
			])
			;

		return $sql;
	}

	static function getAllSourcesForLoadSql() {
		$sqls = [];
		foreach (static::getAllSources() as $sourceCode => $sourceClass) {

			$sqls[] = $sourceClass::getForLoadSql()
				->setOptGetTotalRows(false)
				->select(SX::aka(SX::val($sourceClass), SX::a('class')))
				->select($sourceClass::getIdColumn())
				->select($sourceClass::getColumn('name'))
				->select($sourceClass::getColumn('timeLoaded'))
				;

		}

		$sql = SX::select()
			->from(SX::aka(SX::union($sqls), SX::a('_t')))
			->orderBy([
				SX::orderBy(SX::a('timeLoaded')),
				SX::orderBy(SX::a('name')),
				SX::orderBy(SX::a('id')),
				SX::orderBy(SX::a('class')),
			])
			;

		return $sql;
	}

	static function getForLoadProductDataFromViscojisCzSql() {
		$sql = SX::select()
			->from(static::getTable())
			->where(SX::lgcOr([
				SX::cmpIsNull(static::getColumn('timeLoadedFromViscojisCz')),
				SX::cmpLessThan(static::getColumn('timeLoadedFromViscojisCz'), new \Katu\Utils\DateTime('- ' . static::TIMEOUT . ' seconds')),
			]))
			->where(SX::eq(static::getColumn('isBanned'), 0))
			;

		return $sql;
	}

	static function getAllSourcesForLoadProductDataFromViscojisCzSql() {
		$sqls = [];
		foreach (static::getAllSources() as $sourceCode => $sourceClass) {

			if (in_array('timeLoadedFromViscojisCz', $sourceClass::getTable()->getColumnNames())) {

				$sqls[] = $sourceClass::getForLoadProductDataFromViscojisCzSql()
					->setOptGetTotalRows(false)
					->select(SX::aka(SX::val($sourceClass), SX::a('class')))
					->select($sourceClass::getIdColumn())
					->select($sourceClass::getColumn('name'))
					->select($sourceClass::getColumn('timeLoadedFromViscojisCz'))
					;

			}

		}

		$sql = SX::select()
			->from(SX::aka(SX::union($sqls), SX::a('_t')))
			->orderBy([
				SX::orderBy(SX::a('timeLoadedFromViscojisCz')),
				SX::orderBy(SX::a('name')),
				SX::orderBy(SX::a('id')),
				SX::orderBy(SX::a('class')),
			])
			;

		return $sql;
	}

	static function getForLoadPriceSql() {
		$sql = SX::select()
			->from(static::getTable())
			->where(SX::lgcOr([
				SX::cmpIsNull(static::getColumn('timeLoadedPrice')),
				SX::cmpLessThan(static::getColumn('timeLoadedPrice'), new \Katu\Utils\DateTime('- 1 week')),
			]))
			->orderBy(static::getColumn('timeCreated'))
			;

		return $sql;
	}

	public function getOrCreateScrapedIngredent() {
		return \App\Models\ScrapedIngredient::make(static::SOURCE, $this->getName());
	}

	static function getAllergenCodesFromTexts($texts) {
		$config = ProductAllergen::getConfig();

		$allergenCodes = [];
		foreach ($texts as $text) {

			if (in_array($text, $config['ignoreTexts'])) {
				continue;
			}

			foreach ($config['list'] as $allergenId => $allergenConfig) {

				foreach ($allergenConfig['texts'] as $allergenText) {

					if (strpos($text, $allergenText) !== false) {
						$allergenCodes[] = $allergenConfig['code'];
						continue 3;
					}

				}
			}

		}

		return array_values(array_unique($allergenCodes));
	}

	public function setProductProperty($source, $property, $value) {
		$class = static::getProductPropertyTopClass();

		$productProperty = $class::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'property' => trim($property),
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		]);
		$productProperty->setValue($value);
		$productProperty->save();

		return true;
	}

	public function getProductProperty($property) {
		$class = static::getProductPropertyTopClass();

		return $class::getOneBy([
			'productId' => $this->getId(),
			'property' => trim($property),
		]);
	}

	public function getContents() {
		return $this->getProductProperty('contents');
	}

	public function getContentsString() {
		$contents = $this->getContents();
		if (!$contents) {
			return false;
		}

		return trim(preg_replace('/\s+/', ' ', preg_replace('/\v/u', ' ', strip_tags((new \Katu\Types\TString((string)$contents->getValue()))->normalizeSpaces()))));
	}

	public function getProductPrice() {
		$productPriceClass = static::getProductPriceTopClass();
		if (class_exists($productPriceClass)) {

			return $productPriceClass::getOneBy([
				'productId' => $this->id,
			], [
				'orderBy' => SX::orderBy($productPriceClass::getColumn('timeCreated'), SX::kw('desc')),
			]);

		}

		return false;
	}

	public function getViscojisCzProduct() {
		if ($this->ean) {

			return ViscojisCz\Product::getOneBy([
				'ean' => $this->ean,
			]);

		}

		return false;
	}

	public function getViscojisCzEmulgators() {
		if ($this->ean) {

			$sql = SX::select()
				->setOptGetTotalRows(false)
				->select(\Deli\Models\Emulgator::getTable())
				->from(\Deli\Models\ViscojisCz\Product::getTable())
				->where(SX::eq(\Deli\Models\ViscojisCz\Product::getColumn('ean'), $this->ean))
				->joinColumns(\Deli\Models\ViscojisCz\Product::getIdColumn(), \Deli\Models\ViscojisCz\ProductEmulgator::getColumn('productId'))
				->joinColumns(\Deli\Models\ViscojisCz\ProductEmulgator::getColumn('emulgatorId'), \Deli\Models\Emulgator::getIdColumn())
				->joinColumns(\Deli\Models\Emulgator::getIdColumn(), \Deli\Models\ViscojisCz\Emulgator::getColumn('emulgatorId'))
				->orderBy([
					\Deli\Models\ViscojisCz\Emulgator::getColumn('rating'),
					\Deli\Models\Emulgator::getColumn('code'),
				])
				;

			return \Deli\Models\Emulgator::getBySql($sql);

		}

		return false;
	}

	public function loadProductDataFromViscojisCz() {
		/***************************************************************************
		 * Load by contents.
		 */

		$string = (string)trim($this->getContentsString());
		if ($string) {

			$res = \Katu\Utils\Cache::get(function($string) {

				$curl = new \Curl\Curl;
				$curl->setHeader('Content-Type', 'application/json');
				$res = $curl->post('https://viscokupujes.cz/api/get-info', \Katu\Utils\JSON::encodeInline([
					'ingredients' => $string,
				]));

				return $res;

			}, 86400 * 28, $string);

			// Allergens.
			$config = ProductAllergen::getConfig();
			foreach ($res->a as $allergenId) {
				$this->setProductAllergen(ProductAllergen::SOURCE_VISCOJIS_CZ, $config['list'][$allergenId]['code']);
			}

			// Emulgators.
			foreach ($res->e as $emulgatorData) {
				$emulgator = Emulgator::upsert([
					'code' => $emulgatorData->id,
				], [
					'timeCreated' => new \Katu\Utils\DateTime,
				]);
				$this->setProductEmulgator(ProductEmulgator::SOURCE_VISCOJIS_CZ, $emulgator);
			}

		}

		$this->update('timeLoadedFromViscojisCz', new \Katu\Utils\DateTime);
		$this->save();

		return true;

		/*
		die;
		var_dump($res->po);
		var_dump($res->gf);

		die;

		if ($this->ean) {

			$viscojisCzProduct = $this->getViscojisCzProduct();
			if ($viscojisCzProduct) {

				$productAllergens = $viscojisCzProduct->getProductAllergens();
				foreach ($productAllergens as $productAllergen) {

					$this->setProductAllergen(ProductAllergen::SOURCE_VISCOJIS_CZ, $productAllergen->allergenCode);

				}

			}



		}

		return false;
		*/
	}

}
