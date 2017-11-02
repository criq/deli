<?php

namespace Deli\Models;

abstract class ProductProperty extends \Deli\Model {

	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOJIS_CZ = 'viscojis_cz';

	public function setValue($value) {
		$value = \Katu\Utils\JSON::encodeInline($value);
		$this->update('value', in_array($value, ['null', 'false', '""', '[]']) ? null : $value);

		return true;
	}

	public function getValue() {
		return \Katu\Utils\JSON::decodeAsArray($this->value);
	}

}
