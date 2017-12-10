<?php

namespace Deli\Models\itesco_cz;

class ProductAllergenTag extends \Deli\Model {

	const TABLE = 'deli_tesco_product_allergen_tags';

	static function create($allergenName) {
		return static::insert([
			'timeCreated'  => (string) (new \Katu\Utils\DateTime),
			'allergenName' => (string) (trim($allergenName)),
		]);
	}

	static function make($allergenName) {
		return static::getOneOrCreateWithList([
			'allergenName' => (string) (trim($allergenName)),
		], $allergenName);
	}

	static function checkCrudParams($allergenName) {
		if (!trim($allergenName)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid allergen name."))
				->addErrorName('allergenName')
				;
		}

		return true;
	}

	public function setTag($tag) {
		if ($tag && !($tag instanceof \App\Models\Tag)) {
			throw (new \Katu\Exceptions\InputErrorException("Invalid tag."))
				->addErrorName('tag')
				;
		}

		$this->update('tagId', $tag->getId());

		return true;
	}

	public function userCanEdit($user) {
		if (!$user) {
			return false;
		}

		return $user->hasPermission('scrapedTescoProductAllergenTags.edit');
	}

}
