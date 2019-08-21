<?php

namespace Deli\Classes\Sources\rohlik_cz;

class Source extends \Deli\Classes\Sources\Source {

	const HAS_PRODUCT_LOADING    = true;
	const HAS_PRODUCT_DETAILS    = false;
	const HAS_PRODUCT_ALLERGENS  = false;
	const HAS_PRODUCT_EMULGATORS = false;
	const HAS_PRODUCT_NUTRIENTS  = false;
	const HAS_PRODUCT_PRICES     = false;

	const XML_URL = 'https://www.rohlik.cz/heureka.xml';












	public function getJsonUrl() {
		return new \Katu\Types\TUrl('https://www.rohlik.cz/services/frontend-service/product/' . $this->remoteId . '/full');
	}

	public function getJson($timeout = null) {
		if (is_null($timeout)) {
			$timeout = static::TIMEOUT;
		}

		return \Katu\Cache\Url::get($this->getJsonUrl(), $timeout);
	}

	public function load() {
		try {

			$this->loadNutrients();
			$this->loadAllergens();
			$this->loadEmulgators();

			$this->update('isAvailable', 1);

		} catch (\Exception $e) {

			$this->update('isAvailable', 0);

		}

		$this->update('timeLoaded', new \Katu\Utils\DateTime);
		$this->save();

		return true;
	}

	static function loadNutrients() {
		$json = $this->getJson();

		if (isset($json->data->product->composition->nutritionalValues->dose) && preg_match('/^(?<amount>[0-9]+)\s?(?<unit>[a-z]+)$/', $json->data->product->composition->nutritionalValues->dose, $match)) {

			$productAmountWithUnit = new \Deli\Classes\AmountWithUnit($match['amount'], $match['unit']);

			if (isset($json->data->product->composition->nutritionalValues->energyValueKJ)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'energy', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->energyValueKJ, 'kJ'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->energyValueKcal)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'calories', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->energyValueKcal, 'kcal'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->fats)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fats', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->fats, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->saturatedFattyAcids)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'saturatedFattyAcids', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->saturatedFattyAcids, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->carbohydrates)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'carbs', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->carbohydrates, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->sugars)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'sugar', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->sugars, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->proteins)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'proteins', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->proteins, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->salt)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'salt', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->salt, 'g'), $productAmountWithUnit);
			}
			if (isset($json->data->product->composition->nutritionalValues->fiber)) {
				$this->setProductNutrient(ProductNutrient::SOURCE_ORIGIN, 'fiber', new \Deli\Classes\AmountWithUnit($json->data->product->composition->nutritionalValues->fiber, 'g'), $productAmountWithUnit);
			}

		}

		return true;
	}

	static function loadAllergens() {
		$json = $this->getJson();

		$map = [
			'Obiloviny obsahující lepek' => 'gluten',         //  1
			'Korýši'                     => 'crustaceans',    //  2
			'Vejce'                      => 'eggs',           //  3
			'Ryby'                       => 'fish',           //  4
			'Podzemnice olejná'          => 'peanuts',        //  5
			'Sójové boby'                => 'soybeans',       //  6
			'Mléko'                      => 'lactose',        //  7
			'Skořápkové plody'           => 'nuts',           //  8
			'Celer'                      => 'celery',         //  9
			'Hořčice'                    => 'mustard',        // 10
			'Sezamová semena'            => 'sesame',         // 11
			'Oxid siřičitý a siřičitany' => 'sulphurDioxide', // 12
			'Měkkýši'                    => 'molluscs',       // 14
		];

		$allergenCodes = [];

		if (isset($json->data->product->composition->allergens->contained)) {
			foreach ($json->data->product->composition->allergens->contained as $allergenString) {
				if (isset($map[$allergenString])) {
					$allergenCodes[] = $map[$allergenString];
				} else {
					var_dump($allergenString);die;
				}
			}
		}

		foreach ($allergenCodes as $allergenCode) {
			$this->setProductAllergen(ProductAllergen::SOURCE_ORIGIN, $allergenCode);
		}

		return true;
	}

	static function loadEmulgators() {
		$json = $this->getJson();

		$emulgatorCodes = [];

		if (isset($json->data->product->composition->ingredients)) {

			foreach ($json->data->product->composition->ingredients as $ingredient) {
				if (isset($ingredient->code) && preg_match('/^E[0-9]+/i', $ingredient->code)) {
					$emulgatorCodes[] = strtolower(preg_replace('/[^a-z0-9]/i', null, $ingredient->code));
				}
			}

		}

		foreach ($emulgatorCodes as $emulgatorCode) {

			$emulgator = \Deli\Models\Emulgator::upsert([
				'code' => $emulgatorCode,
			], [
				'timeCreated' => new \Katu\Utils\DateTime,
			]);

			$this->setProductEmulgator(ProductEmulgator::SOURCE_ORIGIN, $emulgator);

		}

		return true;
	}

	public function loadPrice() {
		$this->update('timeAttemptedPrice', new \Katu\Utils\DateTime);
		$this->save();

		try {

			$json = $this->getJson();

			$currency = $json->data->product->currency;
			$pricePerProduct = (new \Katu\Types\TString($json->data->product->price))->getAsFloat();
			$pricePerUnit = $unitAmount = $unitCode = null;

			try {

				$amountWithUnit = \Deli\Models\ProductPrice::createFromString($json->data->product->textualAmount);
				if (!$amountWithUnit) {
					$amountWithUnit = \Deli\Models\ProductPrice::createFromString($json->data->product->productName);
				}

				if ($amountWithUnit) {
					$pricePerUnit = $pricePerProduct;
					$unitAmount = $amountWithUnit->amount;
					$unitCode = $amountWithUnit->unit;
				}

			} catch (\Exception $e) {
				// Nevermind.
			}

			$this->setProductPrice($currency, $pricePerProduct, $pricePerUnit, $unitAmount, $unitCode);

		} catch (\Exception $e) {
			// Nevermind.
		}

		return true;
	}

}
