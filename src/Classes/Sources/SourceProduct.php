<?php

namespace Deli\Classes\Sources;

abstract class SourceProduct
{
	public $product;

	public function __construct($product)
	{
		$this->product = $product;
	}

	public function getProduct()
	{
		return $this->product;
	}

	public function getSource()
	{
		return $this->getProduct()->getSource();
	}

	public function getURL()
	{
		return $this->getProduct()->uri;
	}

	public function getSrc()
	{
		return \Katu\Cache::get([__CLASS__, __FUNCTION__, __LINE__], $this->product->getSource()::CACHE_TIMEOUT, function ($url) {
			$curl = new \Curl\Curl;
			$curl->setOpt(CURLOPT_TIMEOUT, 30);
			$curl->setOpt(CURLOPT_CONNECTTIMEOUT, 30);

			return $curl->get($url);
		}, $this->getURL());
	}

	public function getDOM()
	{
		return \Katu\Utils\DOM::crawlHtml($this->getSrc());
	}
}
