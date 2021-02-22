<?php

namespace Deli\Classes;

class Translation
{
	public function __construct($sourceLanguage, $targetLanguage, $string)
	{
		$this->sourceLanguage = $sourceLanguage;
		$this->targetLanguage = $targetLanguage;
		$this->string = $string;
	}

	public function translate()
	{
		$url = \Katu\Types\TUrl::make('https://www.googleapis.com/language/translate/v2', [
			'key' => \Katu\Config\Config::get('google', 'api', 'key'),
			'source' => $this->sourceLanguage,
			'target' => $this->targetLanguage,
			'q' => $this->string,
		]);

		$res = \Katu\Cache\General::getUrl($url);
		if (isset($res->data->translations[0]->translatedText)) {
			return $res->data->translations[0]->translatedText;
		}

		return null;
	}
}
