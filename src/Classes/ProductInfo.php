<?php

namespace Deli\Classes;

class ProductInfo {

	public $title;
	public $text;

	public function __construct(string $title, string $text) {
		$this->title = trim($title);
		$this->text = trim($text);
	}

}
