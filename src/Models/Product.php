<?php

namespace Deli\Models;

use \Sexy\Sexy as SX;
use \Deli\AmountWithUnit;

abstract class Product extends \Deli\Model {

	const TIMEOUT = 2419200;

	static function getBySourceAndId($source, $id) {
		$productClass = '\\Deli\\Models\\' . $source . '\\Product';
		if (!class_exists($productClass)) {
			throw new \Exception("Product class " . $productClass . " doesn't exist.");
		}

		return $productClass::get($id);
	}

	static function getSourceClass($sourceCode) {
		return static::getAllSources()[$sourceCode];
	}

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
			\Deli\Models\custom\Product::SOURCE               => \Deli\Models\custom\Product::getTopClass(),
			\Deli\Models\alkohol_cz\Product::SOURCE           => \Deli\Models\alkohol_cz\Product::getTopClass(),
			\Deli\Models\countrylife_cz\Product::SOURCE       => \Deli\Models\countrylife_cz\Product::getTopClass(),
			\Deli\Models\fajnejidlo_eu\Product::SOURCE        => \Deli\Models\fajnejidlo_eu\Product::getTopClass(),
			\Deli\Models\itesco_cz\Product::SOURCE            => \Deli\Models\itesco_cz\Product::getTopClass(),
			\Deli\Models\fajnejidlo_eu\Product::SOURCE        => \Deli\Models\fajnejidlo_eu\Product::getTopClass(),
			\Deli\Models\kaloricke_tabulky_cz\Product::SOURCE => \Deli\Models\kaloricke_tabulky_cz\Product::getTopClass(),
			\Deli\Models\kaloricketabulky_cz\Product::SOURCE  => \Deli\Models\kaloricketabulky_cz\Product::getTopClass(),
			\Deli\Models\lekarna_cz\Product::SOURCE           => \Deli\Models\lekarna_cz\Product::getTopClass(),
			\Deli\Models\pbd_online_sk\Product::SOURCE        => \Deli\Models\pbd_online_sk\Product::getTopClass(),
			\Deli\Models\rohlik_cz\Product::SOURCE            => \Deli\Models\rohlik_cz\Product::getTopClass(),
			\Deli\Models\sklizeno_cz\Product::SOURCE          => \Deli\Models\sklizeno_cz\Product::getTopClass(),
			\Deli\Models\stobklub_cz\Product::SOURCE          => \Deli\Models\stobklub_cz\Product::getTopClass(),
			\Deli\Models\usda_gov\Product::SOURCE             => \Deli\Models\usda_gov\Product::getTopClass(),
			\Deli\Models\vitalvibe_eu\Product::SOURCE         => \Deli\Models\vitalvibe_eu\Product::getTopClass(),
			\Deli\Models\veganza_cz\Product::SOURCE           => \Deli\Models\veganza_cz\Product::getTopClass(),
		];

		return $sources;
	}

	static function loadXml() {
		$src = \Katu\Utils\Cache::get(function($xmlUrl) {

			$curl = new \Curl\Curl;
			$curl->setConnectTimeout(3600);
			$curl->setTimeout(3600);
			$curl->get($xmlUrl);

			if ($curl->error) {
				throw new \Katu\Exceptions\DoNotCacheException;
			}

			return $curl->rawResponse;

		}, static::TIMEOUT, static::XML_URL);

		return new \SimpleXMLElement($src);
	}

	public function getSource() {
		return static::SOURCE;
	}

	public function getName() {
		return $this->name;
	}

	public function getOriginalName() {
		return isset($this->originalName) ? $this->originalName : null;
	}

	public function setRemoteCategory($source, $property = 'remoteCategory') {
		$this->update($property, \Katu\Utils\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)$source)))));

		return true;
	}

	public function setEan($ean) {
		$this->update('ean', trim($ean) ?: null);

		return true;
	}

	public function setCategory($category) {
		$this->update('categoryId', $category->getId());

		return true;
	}

	public function getCategory() {
		return Category::get($this->categoryId);
	}

	public function getShoppingList() {
		return Category::get($this->shoppingListId);
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

		if (class_exists($class)) {
			return $class::getBy([
				'productId' => $this->getId(),
			]);
		}

		return false;
	}

	public function getProductNutrientByCode($code) {
		$class = static::getProductNutrientTopClass();

		if (class_exists($class)) {
			return $class::getBy([
				'productId' => $this->getId(),
				'nutrientCode' => $code,
			])->getOne();
		}

		return false;
	}

	public function getProductAllergens() {
		$class = static::getProductAllergenTopClass();

		if (class_exists($class)) {
			return $class::getBy([
				'productId' => $this->getId(),
			]);
		}

		return false;
	}

	public function getProductEmulgators() {
		$class = static::getProductEmulgatorTopClass();
		if (class_exists($class)) {

			return $class::getBy([
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
				SX::lgcOr([
					SX::cmpIsNull(static::getColumn('timeLoadedFromViscojisCz')),
					SX::cmpLessThan(static::getColumn('timeLoadedFromViscojisCz'), new \Katu\Utils\DateTime('- ' . static::TIMEOUT . ' seconds')),
				]),
				SX::cmpIsNull(static::getColumn('isViscojisCzValid'))
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
					->select(SX::aka(SX::val($sourceCode), SX::a('sourceCode')))
					->select($sourceClass::getIdColumn())
					->select($sourceClass::getColumn('name'))
					->select($sourceClass::getColumn('timeLoadedFromViscojisCz'))
					->select($sourceClass::getColumn('isViscojisCzValid'))
					;

			}

		}

		$sql = SX::select()
			->from(SX::aka(SX::union($sqls), SX::a('_t')))
			->orderBy([
				SX::orderBy(SX::a('timeLoadedFromViscojisCz')),
				SX::orderBy(SX::a('id')),
				SX::orderBy(SX::a('sourceCode')),
				SX::orderBy(SX::a('name')),
			])
			;

		return $sql;
	}

	static function getAllSourcesForLoadProductPricesSql() {
		$sqls = [];
		foreach (static::getAllSources() as $sourceCode => $sourceClass) {

			if (in_array('timeAttemptedPrice', $sourceClass::getTable()->getColumnNames())) {

				$sourceProductPriceClass = $sourceClass::getProductPriceTopClass();
				if (method_exists($sourceClass, 'loadPrice') && class_exists($sourceProductPriceClass)) {

					$sqls[] = SX::select()
						->setOptGetTotalRows(false)
						->select(SX::aka(SX::val($sourceCode), SX::a('sourceCode')))
						->select($sourceClass::getIdColumn())
						->select($sourceClass::getColumn('name'))
						->select($sourceClass::getColumn('timeAttemptedPrice'))
						->select($sourceClass::getColumn('timeLoadedPrice'))
						->from($sourceClass::getTable())
						->where(SX::lgcOr([
							SX::cmpIsNull($sourceClass::getColumn('timeAttemptedPrice')),
							SX::cmpLessThan($sourceClass::getColumn('timeAttemptedPrice'), new \Katu\Utils\DateTime('- ' . ProductPrice::TIMEOUT . ' seconds')),
						]))
						;

				}

			}

		}

		$sql = SX::select()
			->from(SX::aka(SX::union($sqls), SX::a('_t')))
			->orderBy([
				SX::orderBy(SX::a('timeAttemptedPrice')),
				SX::orderBy(SX::a('id')),
				SX::orderBy(SX::a('sourceCode')),
				SX::orderBy(SX::a('name')),
			])
			;

		return $sql;
	}

	public function shouldLoadProductPrice() {
		if (!$this->timeAttemptedPrice || !$this->timeLoadedPrice) {
			return true;
		}

		$productPrice = $this->getLatestProductPrice();
		if (!$productPrice) {
			return true;
		}

		return !$productPrice->isInTimeout();
	}

	static function getForLoadPriceSql() {
		$sql = SX::select()
			->from(static::getTable())
			->where(SX::lgcOr([
				SX::cmpIsNull(static::getColumn('timeAttemptedPrice')),
				SX::cmpLessThan(static::getColumn('timeAttemptedPrice'), new \Katu\Utils\DateTime('- ' . ProductPrice::TIMEOUT . ' seconds')),
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
		if (class_exists($class)) {

			return $class::getOneBy([
				'productId' => $this->getId(),
				'property' => trim($property),
			]);

		}

		return null;
	}

	public function getProductPropertyValue($property) {
		$productProperty = $this->getProductProperty($property);
		if ($productProperty) {
			return $productProperty->getValue();
		}

		return null;
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

	public function getSanitizedContentsString() {
		$string = $this->getContentsString();

		$allergenInfoString = implode("|", array_map(function($i) {
			return preg_quote($i, "/");
		}, ProductAllergen::$allergenAdviceStrings));

		$preg = "/\s*$allergenInfoString\s*/";
		$string = trim(preg_replace($preg, ' ', $string));

		return $string;
	}

	public function setProductPrice($currencyCode, $pricePerProduct, $pricePerUnit = null, $unitAmount = null, $unitCode = null) {
		$class = static::getProductPriceTopClass();

		$data = [
			'timeCreated' => new \Katu\Utils\DateTime,
			'productId' => $this->getId(),
			'currencyCode' => $currencyCode,
			'pricePerProduct' => $pricePerProduct,
		];

		if ($pricePerUnit && $unitAmount && $unitCode && in_array($unitCode, $class::$acceptableUnitCodes)) {
			$data['pricePerUnit'] = $pricePerUnit;
			$data['unitAmount'] = $unitAmount;
			$data['unitCode'] = $unitCode;
		}

		return $class::insert($data);
	}

	public function getLatestProductPrice() {
		$class = static::getProductPriceTopClass();
		if (class_exists($class)) {

			return $class::getOneBy([
				'productId' => $this->id,
			], [
				'orderBy' => SX::orderBy($class::getColumn('timeCreated'), SX::kw('desc')),
			]);

		}

		return false;
	}

	public function getViscojisCzProduct() {
		if ($this->ean) {

			return viscojis_cz\Product::getOneBy([
				'ean' => $this->ean,
			]);

		}

		return false;
	}

	public function getCombinedEmulgators() {
		$sqls = [];

		// ProductEmulgator table.
		$class = static::getProductEmulgatorTopClass();
		if (class_exists($class)) {

			$sqls[] = SX::select()
				->setOptGetTotalRows(false)
				->select(SX::aka(\Deli\Models\Emulgator::getIdColumn(), SX::a('emulgatorId')))
				->from($class::getTable())
				->where(SX::eq($class::getColumn('productId'), $this->getId()))
				->joinColumns($class::getColumn('emulgatorId'), \Deli\Models\Emulgator::getIdColumn())
				;

		}

		// EAN.
		if ($this->ean) {

			$sqls[] = SX::select()
				->setOptGetTotalRows(false)
				->select(SX::aka(\Deli\Models\Emulgator::getIdColumn(), SX::a('emulgatorId')))
				->from(\Deli\Models\viscojis_cz\Product::getTable())
				->where(SX::eq(\Deli\Models\viscojis_cz\Product::getColumn('ean'), $this->ean))
				->joinColumns(\Deli\Models\viscojis_cz\Product::getIdColumn(), \Deli\Models\viscojis_cz\ProductEmulgator::getColumn('productId'))
				->joinColumns(\Deli\Models\viscojis_cz\ProductEmulgator::getColumn('emulgatorId'), \Deli\Models\Emulgator::getIdColumn())
				->joinColumns(\Deli\Models\Emulgator::getIdColumn(), \Deli\Models\viscojis_cz\Emulgator::getColumn('emulgatorId'))
				;

		}

		if (!$sqls) {
			return false;
		}

		$sql = SX::select()
			->select(\Deli\Models\Emulgator::getTable())
			->from(SX::aka(SX::union($sqls), SX::a('_t')))
			->join(SX::join(\Deli\Models\Emulgator::getTable(), SX::lgcAnd([
				SX::eq(\Deli\Models\Emulgator::getIdColumn(), SX::a('_t.emulgatorId')),
			])))
			->joinColumns(\Deli\Models\Emulgator::getIdColumn(), \Deli\Models\viscojis_cz\Emulgator::getColumn('emulgatorId'))
			->orderBy([
				\Deli\Models\viscojis_cz\Emulgator::getColumn('rating'),
				\Deli\Models\Emulgator::getColumn('code'),
			])
			;

		return \Deli\Models\Emulgator::getBySql($sql);
	}

	public function loadProductDataFromViscojisCz() {
		$isViscojisCzValid = false;
		/***************************************************************************
		 * Load by contents.
		 */

		$string = (string)trim($this->getContentsString());
		if ($string) {

			$isViscojisCzValid = true;

			$res = \Katu\Utils\Cache::get(function($string) {

				$curl = new \Curl\Curl;
				$curl->setHeader('Content-Type', 'application/json');
				$res = $curl->post('https://viscokupujes.cz/api/get-info', \Katu\Utils\JSON::encodeInline([
					'ingredients' => $string,
				]));

				return $res;

			}, static::TIMEOUT, $string);

			// Allergens.
			if (isset($res->a)) {

				$config = ProductAllergen::getConfig();
				foreach ((array)$res->a as $allergenId) {
					$this->setProductAllergen(ProductAllergen::SOURCE_VISCOJIS_CZ, $config['list'][$allergenId]['code']);
				}

			}

			// Emulgators.
			if (isset($res->e)) {

				foreach ((array)$res->e as $emulgatorData) {
					$emulgator = Emulgator::upsert([
						'code' => $emulgatorData->id,
					], [
						'timeCreated' => new \Katu\Utils\DateTime,
					]);
					$this->setProductEmulgator(ProductEmulgator::SOURCE_VISCOJIS_CZ, $emulgator);
				}

			}

			// Palm oil.
			if (isset($res->po)) {
				$this->setProductProperty(ProductEmulgator::SOURCE_VISCOJIS_CZ, 'isPalmOil', $res->po);
			}

			// HFCS.
			if (isset($res->gf)) {
				$this->setProductProperty(ProductEmulgator::SOURCE_VISCOJIS_CZ, 'isHfcs', $res->gf);
			}

		}

		/***************************************************************************
		 * Load by EAN.
		 */
		if ($this->ean) {

			$viscojisCzProduct = $this->getViscojisCzProduct();
			if ($viscojisCzProduct) {

				$isViscojisCzValid = true;

				// Allergens.
				$productAllergens = $viscojisCzProduct->getProductAllergens();
				foreach ($productAllergens as $productAllergen) {
					$this->setProductAllergen(ProductAllergen::SOURCE_VISCOJIS_CZ, $productAllergen->allergenCode);
				}

				// Emulgators.
				$productEmulgators = $viscojisCzProduct->getProductEmulgators();
				foreach ($productEmulgators as $productEmulgator) {
					$this->setProductEmulgator(ProductEmulgator::SOURCE_VISCOJIS_CZ, Emulgator::get($productEmulgator->emulgatorId));
				}

			}

		}

		$this->update('timeLoadedFromViscojisCz', new \Katu\Utils\DateTime);
		$this->update('isViscojisCzValid', $isViscojisCzValid ? 1 : 0);
		$this->save();

		return true;
	}

	public function isPalmOil() {
		$viscojisCzProduct = $this->getViscojisCzProduct();
		if ($viscojisCzProduct) {
			return (bool)$viscojisCzProduct->isPalmOil;
		}

		$productProperty = $this->getProductProperty('isPalmOil');
		if ($productProperty) {
			return $productProperty->getValue();
		}

		return null;
	}

	public function isHfcs() {
		$viscojisCzProduct = $this->getViscojisCzProduct();
		if ($viscojisCzProduct) {
			return (bool)$viscojisCzProduct->isHfcs;
		}

		$productProperty = $this->getProductProperty('isHfcs');
		if ($productProperty) {
			return $productProperty->getValue();
		}

		return null;
	}

	public function ban() {
		$this->update('isBanned', 1);
		$this->save();

		return true;
	}

	public function unban() {
		$this->update('isBanned', 0);
		$this->save();

		return true;
	}

}
