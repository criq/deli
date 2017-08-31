<?php

namespace Deli\Models;

abstract class Product extends \Deli\Model {

	#abstract public function scrape();
	#abstract public function import();
	#abstract public function getName();

	public function setCategory($source) {
		$this->update('category', \Katu\Utils\JSON::encodeInline(array_values(array_filter(array_map('trim', (array)$source)))));

		return true;
	}

	public function getOrCreateScrapedIngredent() {
		return \App\Models\ScrapedIngredient::make(static::SOURCE, $this->getName());
	}

}
