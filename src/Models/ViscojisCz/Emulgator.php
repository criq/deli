<?php

namespace Deli\Models\ViscojisCz;

class Emulgator extends \Deli\Model {

	const TABLE = 'deli_viscojis_cz_emulgators';

	static function buildEmulgatorList() {
		try {

			\Katu\Utils\Lock::run(['deli', Product::SOURCE, __FUNCTION__], 120, function() {

				@ini_set('memory_limit', '512M');

				$array = \Katu\Utils\Cache::get(function() {

					$curl = new \Curl\Curl;
					$curl->setBasicAuthentication('jidelniplan', 'modraKarkulka55');
					$res = $curl->get('https://viscokupujes.cz/export/e.json');

					return $res;

				}, Product::TIMEOUT);

				foreach ($array as $code => $item) {

					$emulgator = static::upsert([
						'emulgatorId' => \Deli\Models\Emulgator::upsert([
							'code' => $code,
						], [
							'timeCreated' => new \Katu\Utils\DateTime,
						])->getId(),
					], [
						'timeCreated' => new \Katu\Utils\DateTime,
					], [
						'timeLoaded' => new \Katu\Utils\DateTime,
						'rating' => $item->rating,
						'description' => $item->desc ?: null,
					]);

					foreach ($item->names as $name) {

						EmulgatorName::upsert([
							'emulgatorId' => $emulgator->getId(),
							'name' => $name,
						], [
							'timeCreated' => new \Katu\Utils\DateTime,
						]);

					}

				}

			}, !in_array(\Katu\Env::getPlatform(), ['dev']));

		} catch (\Exception $e) {
			// Nevermind.
		}
	}

	public function getEmulgatorName() {
		return EmulgatorName::getOneBy([
			'emulgatorId' => $this->getId(),
		]);
	}

}
