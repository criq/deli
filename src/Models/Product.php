<?php

namespace Deli\Models;

use \Sexy\Sexy as SX;

class Product extends \Deli\Model
{
	const TABLE = 'deli_products';
	const TIMEOUT = 86400;

	/****************************************************************************
	 * Source.
	 */
	public function getSourceClass()
	{
		return '\\Deli\\Classes\\Sources\\' . $this->source . '\\Source';
	}

	public function getSourceProductClass()
	{
		return '\\Deli\\Classes\\Sources\\' . $this->source . '\\SourceProduct';
	}

	public function getSource()
	{
		$class = $this->getSourceClass();

		return new $class;
	}

	public function getSourceProduct()
	{
		$class = $this->getSourceProductClass();
		if (class_exists($class)) {
			return new $class($this);
		}

		return null;
	}

	public function getRemoteCategory()
	{
		return RemoteCategory::get($this->remoteCategoryId);
	}

	/****************************************************************************
	 * Availability.
	 */
	public function setAvailable()
	{
		$this->update('isAvailable', 1);
		$this->save();

		return $this;
	}

	public function setUnavailable()
	{
		$this->update('isAvailable', 0);
		$this->save();

		return $this;
	}

	public function setAllowed()
	{
		$this->update('isAllowed', 1);
		$this->save();

		return $this;
	}

	public function setBanned()
	{
		$this->update('isAllowed', 0);
		$this->save();

		return $this;
	}

	/****************************************************************************
	 * Timestamps.
	 */
	public function setTimeLoadedDetails()
	{
		$this->update('timeLoadedDetails', new \Katu\Tools\DateTime\DateTime);
		$this->save();

		return true;
	}

	public function setTimeLoadedAllergens()
	{
		$this->update('timeLoadedAllergens', new \Katu\Tools\DateTime\DateTime);
		$this->save();

		return true;
	}

	public function setTimeLoadedNutrients()
	{
		$this->update('timeLoadedNutrients', new \Katu\Tools\DateTime\DateTime);
		$this->save();

		return true;
	}

	public function setTimeLoadedEmulgators()
	{
		$this->update('timeLoadedEmulgators', new \Katu\Tools\DateTime\DateTime);
		$this->save();

		return true;
	}

	public function setTimeAttemptedPrice()
	{
		$this->update('timeAttemptedPrice', new \Katu\Tools\DateTime\DateTime);
		$this->save();

		return true;
	}

	public function setTimeLoadedPrice()
	{
		$this->update('timeLoadedPrice', new \Katu\Tools\DateTime\DateTime);
		$this->save();

		return true;
	}

	/****************************************************************************
	 * Properties.
	 */
	public function getName()
	{
		return $this->name;
	}

	public function getOriginalName()
	{
		return isset($this->originalName) ? $this->originalName : null;
	}

	public function setRemoteId($remoteId)
	{
		$this->update('remoteId', $remoteId);

		return $this;
	}

	public function setRemoteCategory(array $remoteCategory)
	{
		$this->update('remoteCategory', \Katu\Files\Formats\JSON::encodeInline($remoteCategory));

		return $this;
	}

	public function setOriginalRemoteCategory(array $remoteCategory)
	{
		$this->update('originalRemoteCategory', \Katu\Files\Formats\JSON::encodeInline($remoteCategory));

		return $this;
	}

	public static function getRemoteCategoryArray($text)
	{
		return preg_split('/[>\|\/]/', $text);
	}

	public static function getRemoteCategoryJSON($text)
	{
		return \Katu\Files\Formats\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)static::getRemoteCategoryArray($text)))));
	}

	public static function getSanitizedRemoteCategoryJSON($remoteCategory)
	{
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

	public function setEan($ean)
	{
		$this->update('ean', trim($ean) ?: null);

		return true;
	}

	public function setCategory($category)
	{
		$this->update('categoryId', $category->getId());

		return true;
	}

	public function getCategory()
	{
		return Category::get($this->categoryId);
	}

	public function getShoppingList()
	{
		return Category::get($this->shoppingListId);
	}

	public function setShoppingList($shoppingList)
	{
		$this->update('shoppingListId', $shoppingList->getId());

		return true;
	}

	public function setShoppingListByName($name)
	{
		return $this->setShoppingList(ShoppingList::upsert([
			'name' => trim($name),
		], [
			'timeCreated' => new \Katu\Tools\DateTime\DateTime,
		]));
	}

	public function setCategoryByName($name)
	{
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

	public function setProductProperty($source, $property, $value)
	{
		$productProperty = ProductProperty::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'property' => trim($property),
		], [
			'timeCreated' => new \Katu\Tools\DateTime\DateTime,
		]);
		$productProperty->setValue($value);
		$productProperty->save();

		return true;
	}

	public function setProductProperties($source, $properties)
	{
		foreach ($properties as $property => $value) {
			$this->setProductProperty($source, $property, $value);
		}

		return true;
	}

	public function getProductProperties()
	{
		return ProductProperty::getBy([
			'productId' => $this->getId(),
		]);
	}

	public function getProductProperty($property)
	{
		return ProductProperty::getOneBy([
			'productId' => $this->getId(),
			'property' => trim($property),
		]);
	}

	public function getProductPropertyValue($property)
	{
		$productProperty = $this->getProductProperty($property);
		if ($productProperty) {
			return $productProperty->getValue();
		}

		return null;
	}

	public function getContents()
	{
		return $this->getProductProperty('contents');
	}

	public function getContentsString()
	{
		$contents = $this->getContents();
		if (!$contents) {
			return null;
		}

		return trim(preg_replace('/\s+/', ' ', preg_replace('/\v/u', ' ', strip_tags((new \Katu\Types\TString((string)$contents->getValue()))->normalizeSpaces()))));
	}

	public function getSanitizedContentsString()
	{
		$string = $this->getContentsString();

		$allergenInfoString = implode("|", array_map(function ($i) {
			return preg_quote($i, "/");
		}, ProductAllergen::$allergenAdviceStrings));

		$preg = "/\s*$allergenInfoString\s*/";
		$string = trim(preg_replace($preg, ' ', $string));

		return $string;
	}

	public function isPalmOil()
	{
		$viscokupujesCzProduct = $this->getViscokupujesCzProduct();
		if ($viscokupujesCzProduct) {
			$viscokupujesCzProductProperty = $viscokupujesCzProduct->getProductProperty('isPalmOil');
			if ($viscokupujesCzProductProperty) {
				return (bool)$viscokupujesCzProductProperty->getValue();
			}
		}

		$productProperty = $this->getProductProperty('isPalmOil');
		if ($productProperty) {
			return $productProperty->getValue();
		}

		return null;
	}

	public function isHfcs()
	{
		$viscokupujesCzProduct = $this->getViscokupujesCzProduct();
		if ($viscokupujesCzProduct) {
			$viscokupujesCzProductProperty = $viscokupujesCzProduct->getProductProperty('isHfcs');
			if ($viscokupujesCzProductProperty) {
				return (bool)$viscokupujesCzProductProperty->getValue();
			}
		}

		$productProperty = $this->getProductProperty('isHfcs');
		if ($productProperty) {
			return $productProperty->getValue();
		}

		return null;
	}

	/****************************************************************************
	 * Allergens.
	 */
	public function setProductAllergens($source, $allergenCodes)
	{
		foreach ((array)$allergenCodes as $allergenCode) {
			$this->setProductAllergen($source, $allergenCode);
		}

		return true;
	}

	public function setProductAllergen($source, $allergenCode)
	{
		return ProductAllergen::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'allergenCode' => $allergenCode,
		], [
			'timeCreated' => new \Katu\Tools\DateTime\DateTime,
		]);
	}

	public function getProductAllergens()
	{
		return \Deli\Models\ProductAllergen::getBy([
			'productId' => $this->getId(),
		]);
	}

	/****************************************************************************
	 * Nutrients.
	 */
	public function setProductNutrients(
		string $source,
		\Deli\Classes\AmountWithUnit $productAmountWithUnit,
		array $nutrients
	) {
		foreach ($nutrients as $nutrientAmountWithUnit) {
			if (is_string($source) && $productAmountWithUnit instanceof \Deli\Classes\AmountWithUnit && $nutrientAmountWithUnit instanceof \Deli\Classes\NutrientAmountWithUnit) {
				$this->setProductNutrient($source, $productAmountWithUnit, $nutrientAmountWithUnit);
			}
		}

		return true;
	}

	public function setProductNutrient(string $source, \Deli\Classes\AmountWithUnit $productAmountWithUnit, \Deli\Classes\NutrientAmountWithUnit $nutrientAmountWithUnit)
	{
		if (!$nutrientAmountWithUnit->nutrientCode || !$nutrientAmountWithUnit->amountWithUnit) {
			throw new \Exception("Missing nutrient code.");
		}

		return \Deli\Models\ProductNutrient::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'nutrientCode' => $nutrientAmountWithUnit->nutrientCode,
		], [
			'timeCreated' => new \Katu\Tools\DateTime\DateTime,
		], [
			'timeUpdated' => new \Katu\Tools\DateTime\DateTime,
			'nutrientAmount' => $nutrientAmountWithUnit->amountWithUnit->amount,
			'nutrientUnit' => $nutrientAmountWithUnit->amountWithUnit->unit,
			'ingredientAmount' => $productAmountWithUnit->amount,
			'ingredientUnit' => $productAmountWithUnit->unit,
		]);
	}

	public function getProductNutrients()
	{
		return \Deli\Models\ProductNutrient::getBy([
			'productId' => $this->getId(),
		]);
	}

	public function getProductNutrientByCode($code)
	{
		return \Deli\Models\ProductNutrient::getOneBy([
			'productId' => $this->getId(),
			'nutrientCode' => $code,
		]);
	}

	/****************************************************************************
	 * Emulgators.
	 */
	public function setProductEmulgator($source, $emulgator)
	{
		return ProductEmulgator::upsert([
			'productId' => $this->getId(),
			'source' => $source,
			'emulgatorId' => $emulgator->getId(),
		], [
			'timeCreated' => new \Katu\Tools\DateTime\DateTime,
		]);
	}


	public function getProductEmulgators()
	{
		return ProductEmulgator::getBy([
			'productId' => $this->getId(),
		]);
	}

	// TODO - zkontrolovat
	public function getCombinedEmulgators()
	{
		$sqls = [];

		// ProductEmulgator table.
		$sqls[] = SX::select()
			->setOptGetTotalRows(false)
			->select(SX::aka(\Deli\Models\Emulgator::getIdColumn(), SX::a('emulgatorId')))
			->from(ProductEmulgator::getTable())
			->where(SX::eq(ProductEmulgator::getColumn('productId'), $this->getId()))
			->joinColumns(ProductEmulgator::getColumn('emulgatorId'), \Deli\Models\Emulgator::getIdColumn())
			;

		// EAN.
		if ($this->ean) {
			$sqls[] = SX::select()
				->setOptGetTotalRows(false)
				->select(SX::aka(\Deli\Models\Emulgator::getIdColumn(), SX::a('emulgatorId')))
				->from(static::getTable())
				->where(SX::eq(static::getColumn('ean'), $this->ean))
				->where(SX::eq(static::getColumn('source'), 'viscokupujes_cz'))
				->joinColumns(static::getIdColumn(), ProductEmulgator::getColumn('productId'))
				->joinColumns(ProductEmulgator::getColumn('emulgatorId'), \Deli\Models\Emulgator::getIdColumn())
				;
		}

		if (!$sqls) {
			return null;
		}

		$sql = SX::select()
			->select(\Deli\Models\Emulgator::getTable())
			->from(SX::aka(SX::union($sqls), SX::a('_t')))
			->join(SX::join(\Deli\Models\Emulgator::getTable(), SX::lgcAnd([
				SX::eq(\Deli\Models\Emulgator::getIdColumn(), SX::a('_t.emulgatorId')),
			])))
			->orderBy([
				\Deli\Models\Emulgator::getColumn('code'),
			])
			;

		return \Deli\Models\Emulgator::getBySql($sql);
	}

	/****************************************************************************
	 * Prices.
	 */
	public function getProductPrices()
	{
		return ProductPrice::getBy([
			'productId' => $this->getId(),
		], [
			'orderBy' => SX::orderBy(ProductPrice::getColumn('timeCreated'), SX::kw('desc')),
		]);
	}

	public function getLatestProductPrice()
	{
		return ProductPrice::getOneBy([
			'productId' => $this->id,
		], [
			'orderBy' => SX::orderBy(ProductPrice::getColumn('timeCreated'), SX::kw('desc')),
		]);
	}

	public function shouldLoadProductPrice()
	{
		if (!$this->timeAttemptedPrice || !$this->timeLoadedPrice) {
			return true;
		}

		$productPrice = $this->getLatestProductPrice();
		if (!$productPrice) {
			return true;
		}

		return !$productPrice->isInTimeout();
	}

	public function setProductPrice($currencyCode, \Deli\Classes\Price $price)
	{
		return ProductPrice::insert([
			'timeCreated'     => new \Katu\Tools\DateTime\DateTime,
			'productId'       => $this->getId(),
			'currencyCode'    => $currencyCode,
			'pricePerProduct' => $price->pricePerProduct,
			'pricePerUnit'    => $price->pricePerUnit,
			'unitAmount'      => $price->unitAmount ? $price->unitAmount : null,
			'unitCode'        => $price->unitAmount ? $price->unitCode : null,
		]);
	}

	/****************************************************************************
	 * Víš co kupuješ.
	 */
	public function getViscokupujesCzProduct()
	{
		if ($this->ean) {
			return static::getOneBy([
				'source' => 'viscokupujes_cz',
				'ean' => $this->ean,
			]);
		}

		return false;
	}

	public static function getForLoadProductDataFromViscokupujesCzSql()
	{
		$sql = SX::select()
			->setOptGetTotalRows(false)
			->select(static::getTable())
			->from(static::getTable())
			->where(SX::lgcOr([
				SX::lgcOr([
					SX::cmpIsNull(static::getColumn('timeLoadedFromViscokupujesCz')),
					SX::cmpLessThan(static::getColumn('timeLoadedFromViscokupujesCz'), new \Katu\Tools\DateTime\DateTime('- ' . static::TIMEOUT . ' seconds')),
				]),
				SX::cmpIsNull(static::getColumn('isViscokupujesCzValid'))
			]))
			->where(SX::eq(static::getColumn('isAllowed'), 1))
			->orderBy([
				SX::orderBy(static::getColumn('timeLoadedFromViscokupujesCz')),
				SX::orderBy(static::getColumn('id')),
				SX::orderBy(static::getColumn('source')),
				SX::orderBy(static::getColumn('name')),
			])
			;

		#echo $sql;die;

		return $sql;
	}

	public function loadProductDataFromViscokupujesCz()
	{
		$isViscokupujesCzValid = false;

		/***************************************************************************
		 * Load by contents.
		 */
		$string = (string)trim($this->getContentsString());
		if ($string) {
			$isViscokupujesCzValid = true;

			$res = \Katu\Cache\General::get([__CLASS__, __FUNCTION__, __LINE__], $this->getSource()::CACHE_TIMEOUT, function ($string) {
				$curl = new \Curl\Curl;
				$curl->setHeader('Content-Type', 'application/json');
				$res = $curl->post('https://viscokupujes.cz/api/get-info', \Katu\Files\Formats\JSON::encodeInline([
					'ingredients' => $string,
				]));

				return $res;
			}, $string);

			// Allergens.
			if (isset($res->a)) {
				$config = ProductAllergen::getConfig();
				foreach ((array)$res->a as $allergenId) {
					$this->setProductAllergen(ProductAllergen::SOURCE_VISCOKUPUJES_CZ, $config['list'][$allergenId]['code']);
				}
			}

			// Emulgators.
			if (isset($res->e)) {
				foreach ((array)$res->e as $emulgatorData) {
					$emulgator = Emulgator::upsert([
						'code' => $emulgatorData->id,
					], [
						'timeCreated' => new \Katu\Tools\DateTime\DateTime,
					]);
					$this->setProductEmulgator(ProductEmulgator::SOURCE_VISCOKUPUJES_CZ, $emulgator);
				}
			}

			// Palm oil.
			if (isset($res->po)) {
				$this->setProductProperty(ProductEmulgator::SOURCE_VISCOKUPUJES_CZ, 'isPalmOil', $res->po);
			}

			// HFCS.
			if (isset($res->gf)) {
				$this->setProductProperty(ProductEmulgator::SOURCE_VISCOKUPUJES_CZ, 'isHfcs', $res->gf);
			}
		}

		/***************************************************************************
		 * Load by EAN.
		 */
		if ($this->ean) {
			$viscokupujesCzProduct = $this->getViscokupujesCzProduct();
			if ($viscokupujesCzProduct) {
				$isViscokupujesCzValid = true;

				// Allergens.
				$productAllergens = $viscokupujesCzProduct->getProductAllergens();
				foreach ($productAllergens as $productAllergen) {
					$this->setProductAllergen(ProductAllergen::SOURCE_VISCOKUPUJES_CZ, $productAllergen->allergenCode);
				}

				// Emulgators.
				$productEmulgators = $viscokupujesCzProduct->getProductEmulgators();
				foreach ($productEmulgators as $productEmulgator) {
					$this->setProductEmulgator(ProductEmulgator::SOURCE_VISCOKUPUJES_CZ, Emulgator::get($productEmulgator->emulgatorId));
				}
			}
		}

		$this->update('timeLoadedFromViscokupujesCz', new \Katu\Tools\DateTime\DateTime);
		$this->update('isViscokupujesCzValid', $isViscokupujesCzValid ? 1 : 0);
		$this->save();

		return true;
	}

	/****************************************************************************
	 * Unit.
	 */
	public function getAmountWithUnit()
	{
		return \Deli\Classes\AmountWithUnit::createFromString($this->getName());
	}

	/****************************************************************************
	 * Unit.
	 */
	public function getProductUnitAbbr()
	{
		$amountWithUnit = $this->getAmountWithUnit();
		if ($amountWithUnit) {
			return $amountWithUnit->unit;
		}

		$sql = SX::select()
			->select(ProductNutrient::getColumn('ingredientUnit'))
			->select(SX::aka(SX::fncCount([
				ProductNutrient::getIdColumn(),
			]), SX::a('size')))
			->from(ProductNutrient::getTable())
			->where(SX::eq(ProductNutrient::getColumn('productId'), $this->getId()))
			->where(SX::cmpIn(ProductNutrient::getColumn('ingredientUnit'), ['g', 'ml']))
			->groupBy(ProductNutrient::getColumn('ingredientUnit'))
			->orderBy(SX::orderBy(SX::a('size'), SX::kw('desc')))
			;

		$res = ProductNutrient::getConnection()->createQuery($sql)->getResult();

		return $res[0]['ingredientUnit'] ?? false;
	}
}
