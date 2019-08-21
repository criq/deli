<?php

namespace Deli\Models;

use \Sexy\Sexy as SX;

class Product extends \Deli\Model {

	const TABLE = 'deli_products';

	/****************************************************************************
	 * Source.
	 */
	public function getSourceClass() {
		return '\\Deli\\Classes\\Sources\\' . $this->source . '\\Source';
	}

	public function getSourceProductClass() {
		return '\\Deli\\Classes\\Sources\\' . $this->source . '\\SourceProduct';
	}

	public function getSource() {
		$class = $this->getSourceClass();

		return new $class;
	}

	public function getSourceProduct() {
		$class = $this->getSourceProductClass();

		return new $class($this);
	}

	/****************************************************************************
	 * Timestamps.
	 */
	public function setTimeLoadedDetails() {
		$this->update('timeLoadedDetails', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function setTimeLoadedAllergens() {
		$this->update('timeLoadedAllergens', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function setTimeLoadedNutrients() {
		$this->update('timeLoadedNutrients', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	/****************************************************************************
	 * Properties.
	 */
	public function getName() {
		return $this->name;
	}

	public function getOriginalName() {
		return isset($this->originalName) ? $this->originalName : null;
	}

	static function getRemoteCategoryArray($text) {
		return preg_split('/[>\|\/]/', $text);
	}

	static function getRemoteCategoryJSON($text) {
		return \Katu\Utils\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)static::getRemoteCategoryArray($text)))));
	}

	static function getSanitizedRemoteCategoryJSON($remoteCategory) {
		// Is null - return null.
		if ($remoteCategory === null) {
			return null;
		}

		// Is string.
		if (is_string($remoteCategory) && strlen($remoteCategory)) {

			$array = json_decode($remoteCategory);
			if ($array === null) {
				return static::getRemoteCategoryJSON($remoteCategory);
			} else {
				return $remoteCategory;
			}

		}

		return null;
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

	/****************************************************************************
	 * Allergens.
	 */
	public function setProductAllergens($source, $allergenCodes) {
		foreach ($allergenCodes as $allergenCode) {
			$this->setProductAllergen($source, $allergenCode);
		}

		return true;
	}

	public function setProductAllergen($source, $allergenCode) {
		return ProductAllergen::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'allergenCode' => $allergenCode,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		]);
	}

	public function getProductAllergens() {
		return \Deli\Models\ProductAllergen::getBy([
			'productId' => $this->getId(),
		]);
	}

	/****************************************************************************
	 * Nutrients.
	 */

	public function setProductNutrients(string $source, \Deli\Classes\AmountWithUnit $productAmountWithUnit, array $nutrients) {
		foreach ($nutrients as $nutrientAmountWithUnit) {
			if (is_string($source) && $productAmountWithUnit instanceof \Deli\Classes\AmountWithUnit && $nutrientAmountWithUnit instanceof \Deli\Classes\NutrientAmountWithUnit) {
				$this->setProductNutrient($source, $productAmountWithUnit, $nutrientAmountWithUnit);
			}
		}

		return true;
	}

	public function setProductNutrient(string $source, \Deli\Classes\AmountWithUnit $productAmountWithUnit, \Deli\Classes\NutrientAmountWithUnit $nutrientAmountWithUnit) {
		if (!$nutrientAmountWithUnit->nutrientCode || !$nutrientAmountWithUnit->amountWithUnit) {
			throw new \Exception("Missing nutrient code.");
		}

		return \Deli\Models\ProductNutrient::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'nutrientCode' => $nutrientAmountWithUnit->nutrientCode,
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'timeUpdated' => new \Katu\Utils\DateTime,
			'nutrientAmount' => $nutrientAmountWithUnit->amountWithUnit->amount,
			'nutrientUnit' => $nutrientAmountWithUnit->amountWithUnit->unit,
			'ingredientAmount' => $productAmountWithUnit->amount,
			'ingredientUnit' => $productAmountWithUnit->unit,
		]);
	}

	public function getProductNutrients() {
		return \Deli\Models\ProductNutrient::getBy([
			'productId' => $this->getId(),
		]);
	}

	public function getProductNutrientByCode($code) {
		return \Deli\Models\ProductNutrient::getBy([
			'productId' => $this->getId(),
			'nutrientCode' => $code,
		])->getOne();
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
























	public function getProductEmulgators() {
		$class = static::getProductEmulgatorTopClass();
		if (class_exists($class)) {

			return $class::getBy([
				'productId' => $this->getId(),
			]);

		}

		return false;
	}



	public function getProductPrices() {
		$class = static::getProductPriceTopClass();

		if (class_exists($class)) {
			return $class::getBy([
				'productId' => $this->getId(),
			]);
		}

		return false;
	}

	public function getProductProperties() {
		$class = static::getProductPropertyTopClass();

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
		foreach (static::getAllSources() as $source => $sourceClass) {

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
		foreach (static::getAllSources() as $source => $sourceClass) {

			if (in_array('timeLoadedFromViscojisCz', $sourceClass::getTable()->getColumnNames())) {

				$sqls[] = $sourceClass::getForLoadProductDataFromViscojisCzSql()
					->setOptGetTotalRows(false)
					->select(SX::aka(SX::val($source), SX::a('source')))
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
				SX::orderBy(SX::a('source')),
				SX::orderBy(SX::a('name')),
			])
			;

		return $sql;
	}

	static function getAllSourcesForLoadPriceSql() {
		$sqls = [];
		foreach (static::getAllSources() as $source => $sourceClass) {

			if (in_array('timeAttemptedPrice', $sourceClass::getTable()->getColumnNames())) {

				$sourceProductPriceClass = $sourceClass::getProductPriceTopClass();
				if (method_exists($sourceClass, 'loadPrice') && class_exists($sourceProductPriceClass)) {

					$sqls[] = SX::select()
						->setOptGetTotalRows(false)
						->select(SX::aka(SX::val($source), SX::a('source')))
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
				SX::orderBy(SX::a('source')),
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

	public function setProductProperty($source, $property, $value) {
		$productProperty = ProductProperty::upsert([
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

	public function setProductProperties($source, $properties) {
		foreach ($properties as $property => $value) {
			$this->setProductProperty($source, $property, $value);
		}

		return true;
	}

	public function getProductProperty($property) {
		return ProductProperty::getOneBy([
			'productId' => $this->getId(),
			'property' => trim($property),
		]);
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

	public function setProductPrice($currencyCode, \Deli\Classes\Price $price) {
		return ProductPrice::insert([
			'timeCreated'     => new \Katu\Utils\DateTime,
			'productId'       => $this->getId(),
			'currencyCode'    => $currencyCode,
			'pricePerProduct' => $price->pricePerProduct,
			'pricePerUnit'    => $price->pricePerUnit,
			'unitAmount'      => $price->unitAmount,
			'unitCode'        => $price->unitCode,
		]);
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
