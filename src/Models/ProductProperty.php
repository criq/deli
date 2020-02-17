<?php

namespace Deli\Models;

class ProductProperty extends \Deli\Model {

	const TABLE = 'deli_product_properties';

	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOKUPUJES_CZ = 'viscokupujes_cz';

	public function setValue($value) {
		$value = \Katu\Utils\JSON::encodeInline($value);
		$this->update('value', in_array($value, ['null', '""', '[]']) ? null : $value);

		return true;
	}

	public function getValue() {
		return \Katu\Utils\JSON::decodeAsArray($this->value);
	}

}
