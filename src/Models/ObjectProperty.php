<?php

namespace App\Classes\Filters\Properties;

class ObjectProperty extends \App\Classes\Filters\Property {

	public function getTitle() {
		return implode(static::TITLE_SEPARATOR, [
			$this->getSetting('model'),
			$this->getSetting('property'),
		]);
	}

	public function getComparisonColumn() {
		$class = $this->getSetting('model');

		return $class::getColumn($this->getSetting('property'));
	}

}
