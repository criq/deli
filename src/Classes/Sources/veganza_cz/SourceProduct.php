<?php

namespace Deli\Classes\Sources\veganza_cz;

class SourceProduct extends \Deli\Classes\Sources\SourceProduct
{
	/****************************************************************************
	 * Product details.
	 */
	public function loadDetails()
	{
		try {
			$product = $this->getProduct();

			$name = trim($this->getDOM()->filter('.p-detail-inner-header h1')->text());
			$product->update('name', $name);
			$product->update('originalName', $name);

			$remoteCategory = array_values(array_filter($this->getDOM()->filter('#navigation [itemprop="title"]')->each(function ($e) use ($name) {
				$category = trim($e->text());
				return $category != $name ? $category : null;
			})));

			$product->setRemoteCategory($remoteCategory);
			$product->setOriginalRemoteCategory($remoteCategory);

			$remoteId = $this->getDOM()->filter('[name="productId"]')->attr('value');
			$product->setRemoteId($remoteId);

			$product->update('timeLoadedDetails', new \Katu\Tools\DateTime\DateTime);
			$product->save();

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}
}
