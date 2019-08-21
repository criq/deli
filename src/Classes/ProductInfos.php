<?php

namespace Deli\Classes;

class ProductInfos {

	public $productInfos = [];

	public function __construct(array $productInfos = null) {
		$this->productInfos = $productInfos;
	}

	public function add(ProductInfo $productInfo) {
		$this->productInfos[] = $productInfo;
	}

	public function filterByTitle($title) {
		return array_values(array_filter($this->productInfos, function($i) use($title) {
			return $title == $i->title;
		}));
	}

}
