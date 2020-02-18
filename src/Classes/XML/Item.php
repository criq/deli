<?php

namespace Deli\Classes\XML;

class Item extends \SimpleXMLElement
{

	public function getSource()
	{
		$code = explode('\\', get_called_class())[3];
		$class = "\\Deli\\Classes\\Sources\\" . $code . "\\Source";

		return new $class;
	}

	public function getId()
	{
		return (string)($this->ITEM_ID ?? null) ?: null;
	}

	public function getURL()
	{
		return (string)($this->URL ?? null) ?: null;
	}

	public function getEAN()
	{
		return (string)($this->EAN ?? null) ?: null;
	}

	public function getName()
	{
		return (string)($this->PRODUCTNAME ?? null) ?: (string)($this->PRODUCT ?? null) ?: null;
	}

	public function getCategory()
	{
		return (string)($this->CATEGORYTEXT ?? null) ?: null;
	}

	public function isAvailable()
	{
		return (bool)((string)($this->AVAILABILITY ?? null) ?: true);
	}

	public function getPrice()
	{
		return (string)($this->PRICE_VAT ?? null) ?: (string)($this->PRICE ?? null) ?: null;
	}

	public function getPrices()
	{
		return new \Deli\Classes\Prices([
			new \Deli\Classes\Price($this->getPrice(), $this->getName()),
		]);
	}

	public function getParams()
	{
		$params = [];

		try {
			foreach ($this->PARAM as $param) {
				$params[(string)$param->PARAM_NAME] = trim((string)$param->VAL);
			}
		} catch (\Throwable $e) {
			// Nevermind.
		}

		return $params;
	}

	public function getParam($param)
	{
		$params = $this->getParams();

		return $params[$param] ?? null;
	}

	/****************************************************************************
	 * Get or create product from XML.
	 */
	public function getOrCreateProduct()
	{
		$product = \Deli\Models\Product::upsert([
			'remoteId' => $this->getId(),
		], [
			'timeCreated' => new \Katu\Utils\DateTime,
		], [
			'source' => $this->getSource(),
			'uri' => $this->getURL(),
			'ean' => trim($this->getEAN()) ?: null,
			'name' => $this->getName(),
			'originalName' => $this->getName(),
			'remoteCategory' => $this->getSource()->getRemoteCategoryJSON($this->getCategory()),
			'originalRemoteCategory' => $this->getSource()->getRemoteCategoryJSON((string)$this->getCategory()),
			'isAvailable' => $this->isAvailable() ? 1 : 0,
		]);

		$product->setProductProperties(\Deli\Models\ProductProperty::SOURCE_ORIGIN, $this->getProperties());
		$product->setTimeLoadedDetails();

		return $product;
	}

	/****************************************************************************
	 * Load product properties from XML.
	 */
	public function getProperties()
	{
		$properties = [];

		$properties['description']  = (string)($this->DESCRIPTION ?? null);
		$properties['imageUrl']     = (string)($this->IMGURL ?? null);
		$properties['manufacturer'] = (string)($this->MANUFACTURER ?? null);

		return $properties;
	}
}
