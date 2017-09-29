<?php

namespace Deli\Models\Custom;

class Product extends \Deli\Models\Product {

	const TABLE = 'deli_custom_products';
	const SOURCE = 'custom';
	const SOURCE_LABEL = 'Vlastní';

	public function setProductProperty($property, $value) {
		return \Deli\Models\Custom\ProductProperty::upsert([
			'productId' => $this->getId(),
			'property' => trim($property),
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'value' => trim($value) ?: null,
		]);
	}

	public function getProductProperty($property) {
		return \Deli\Models\Custom\ProductProperty::getOneBy([
			'productId' => $this->getId(),
			'property' => trim($property),
		]);
	}

	public function getUrl() {
		$productProperty = $this->getProductProperty('url');
		if ($productProperty) {
			return $productProperty->value;
		}

		return false;
	}

	public function load() {
		$this->loadNutrients();
		$this->loadAllergens();

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	public function loadNutrients() {
		$property = $this->getProductProperty('nutrients');
		if ($property) {

			$array = \Katu\Utils\JSON::decodeAsArray($property->value);
			if ($array) {

				foreach ($array as $nutrientCode => $rawNutrientUnitWithAmount) {
					$nutrientAmountWithUnit = new \Effekt\AmountWithUnit($rawNutrientUnitWithAmount['amount'], $rawNutrientUnitWithAmount['unit']);
					$this->setProductNutrientIfEmpty($nutrientCode, $nutrientAmountWithUnit);
				}

			}

		}

		return true;
	}

	public function loadAllergens() {
		$property = $this->getProductProperty('allergens');
		if ($property) {

			$array = \Katu\Utils\JSON::decodeAsArray($property->value);
			if ($array) {

				foreach ($array as $allergenCode) {
					$this->setProductAllergen($allergenCode);
				}

			}

		}

		return true;
	}

}
