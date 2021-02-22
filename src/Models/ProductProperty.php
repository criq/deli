<?php

namespace Deli\Models;

class ProductProperty extends \Deli\Model
{
	const SOURCE_ORIGIN = 'origin';
	const SOURCE_VISCOKUPUJES_CZ = 'viscokupujes_cz';
	const TABLE = 'deli_product_properties';

	public function setValue($value)
	{
		$value = \Katu\Files\Formats\JSON::encodeInline($value);
		$this->update('value', in_array($value, ['null', '""', '[]']) ? null : $value);

		return true;
	}

	public function getValue()
	{
		return \Katu\Files\Formats\JSON::decodeAsArray($this->value);
	}
}
