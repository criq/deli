<?php

namespace App\Controllers\Front;

use \App\Models\User;
use \App\Models\UserPasswordToken;
use \App\Models\EmailAddress;
use \App\Models\Organization;
use \App\Models\File;

class Account extends \Katu\Controller {

	static function signup() {
		$app = \Katu\App::get();

		$user = User::getCurrent();
		if ($user) {
			return static::redirect($user->getAfterLoginUrl());
		}

		if (static::isSubmittedWithToken()) {

			try {

				$ec = new \Katu\Exceptions\ExceptionCollection;

				try {

					$emailAddress = EmailAddress::make($app->request->params('emailAddress'));

					$user = User::getOneBy([
						'emailAddressId' => $emailAddress->getId(),
					]);
					if ($user) {
						$ec->add(
							(new \Katu\Exceptions\InputErrorException("User already exists."))
								->addErrorName('emailAddress')
								->addTranslation('cs', "Užívatel s touto e-mailovou adresou již existuje.")
						);
					}

				} catch (\Katu\Exceptions\Exception $e) {

					switch ($e->getAbbr()) {
						case 'missingEmailAddress' :
							$ec->add(
								(new \Katu\Exceptions\InputErrorException("Missing e-mail address."))
									->addErrorName('emailAddress')
									->addTranslation('cs', "Chybějící e-mailová adresa.")
							);
						break;
						default :
							$ec->add(
								(new \Katu\Exceptions\InputErrorException("Invalid e-mail address."))
									->addErrorName('emailAddress')
									->addTranslation('cs', "Neplatná e-mailová adresa.")
							);
						break;
					}

				}

				if (!$app->request->params('password')) {
					$ec->add(
						(new \Katu\Exceptions\InputErrorException("Missing password."))
							->addErrorName('emailAddress')
							->addTranslation('cs', "Chybějící heslo.")
					);
				}

				if ($ec->has()) {
					throw $ec;
				}

				$user = User::createWithEmailAddress($emailAddress);
				$user->login();

				\Katu\Flash::set('facebookPixelTrack', [
					[
						'event' => 'CompleteRegistration',
					],
				]);

				try {
					$user->getEmailAddress()->sendConfirmationEmail();
				} catch (\Exception $e) {
					/* Nevermind. */
				}

				return static::redirect([
					$app->request->params('returnUrl'),
					$user->getAfterLoginUrl(),
				]);

			} catch (\Katu\Exceptions\Exception $e) { static::addError($e); }

		}

		static::$data['_page']['title'] = \App\Classes\Site::getPageTitle("Registrace");

		return static::render("Front/Account/signup");
	}

	static function login() {
		$app = \Katu\App::get();

		$user = User::getCurrent();
		if ($user) {
			return static::redirect($user->getAfterLoginUrl());
		}

		if (static::isSubmittedWithToken()) {

			try {

				$ec = new \Katu\Exceptions\ExceptionCollection;

				try {

					$emailAddress = EmailAddress::make(trim($app->request->params('emailAddress')));

					$user = User::getOneBy([
						'emailAddressId' => $emailAddress->getId(),
					]);
					if (!$user) {
						$ec->add(
							(new \Katu\Exceptions\InputErrorException("User doesn't exists."))
								->addErrorName('emailAddress')
								->addTranslation('cs', "Užívatel s touto e-mailovou neexistuje.")
						);
					}

				} catch (\Katu\Exceptions\Exception $e) {

					switch ($e->getAbbr()) {
						case 'missingEmailAddress' :
							$ec->add(
								(new \Katu\Exceptions\InputErrorException("Missing e-mail address."))
									->addErrorName('emailAddress')
									->addTranslation('cs', "Chybějící e-mailová adresa.")
							);
						break;
						default :
							$ec->add(
								(new \Katu\Exceptions\InputErrorException("Invalid e-mail address."))
									->addErrorName('emailAddress')
									->addTranslation('cs', "Neplatná e-mailová adresa.")
							);
						break;
					}

				}

				if ($ec->has()) {
					throw $ec;
				}

				if (!\Katu\Utils\Password::verify($app->request->params('password'), $user->password)) {
					throw (new \Katu\Exceptions\InputErrorException("Invalid password."))
						->addErrorName('password')
						->addTranslation('cs', "Neplatné heslo.")
						;
				}

				$user->login();

				return static::redirect([
					$app->request->params('returnUrl'),
					$user->getAfterLoginUrl(),
				]);

			} catch (\Katu\Exceptions\Exception $e) { static::addError($e); }

		}

		static::$data['_page']['title'] = \App\Classes\Site::getPageTitle("Přihlášení");

		return static::render("Front/Account/login");
	}

	static function loginWithToken($token) {
		$app = \Katu\App::get();

		$userLoginToken = \App\Models\UserLoginToken::getOneBy([
			'token' => $token,
		]);
		if (!$userLoginToken) {
			throw new \Katu\Exceptions\NotFoundException;
		}
		if (!$userLoginToken->isValid()) {
			throw new \Katu\Exceptions\NotFoundException;
		}

		$user = $userLoginToken->getUser();

		$user->login();
		$userLoginToken->expire();

		switch ($app->request->params('scenario')) {
			case 'welcome' :

				$organization = Organization::get($app->request->params('organizationId'));
				if (!$organization) {
					throw new \Katu\Exceptions\NotFoundException;
				}
				if (!$organization->isOrganizationMember($user)) {
					throw new \Katu\Exceptions\UnauthorizedException;
				}

				return static::redirect(\Katu\Utils\Url::getFor('account.welcome.password', [
					'organizationSlug' => $organization->getSlug(),
				]));

			break;
			default :

				return static::redirect([
					$user->getAfterLoginUrl(),
				]);

			break;
		}
	}

	static function loginWithFacebook() {
		try {

			$app = \Katu\App::get();

			\Katu\Session::start();
			\Katu\Session::reset('facebook.accessToken');

			$api = new \Facebook\Facebook([
				'app_id' => \Katu\Config::get('facebook', 'api', 'appId'),
				'app_secret' => \Katu\Config::get('facebook', 'api', 'secret'),
			]);
			$helper = $api->getRedirectLoginHelper();
			$oAuth2Client = $api->getOAuth2Client();

			$loginUrl = $helper->getReRequestUrl((string) \Katu\Utils\Url::getFor('account.loginWithFacebook', null, [
				'returnUrl' => $app->request->params('returnUrl'),
			]), [
				'email',
			]);

			// Redirected with error.
			if ($app->request->params('error')) {
				throw new \Katu\Exceptions\ErrorException("Přihlášení přes Facebook se nezdařilo. Zkuste to znovu.");
			}

			// Redirected with code.
			if ($app->request->params('code')) {
				\Katu\Session::set('facebook.accessToken', $helper->getAccessToken());
			}

			$accessToken = \Katu\Session::get('facebook.accessToken');
			if (!$accessToken) {
				throw new \Facebook\Exceptions\FacebookAuthenticationException;
			}

			$tokenMetadata = $oAuth2Client->debugToken($accessToken);
			if (!$tokenMetadata->getIsValid()) {
				throw new \Facebook\Exceptions\FacebookAuthenticationException;
			}

			$tokenMetadata->validateAppId((string) \Katu\Config::get('facebook', 'api', 'appId'));
			$tokenMetadata->validateExpiration();

			try {

				$facebookUser = $api->get('/me?fields=name,email', $accessToken)->getGraphUser();
				$facebookEmailAddress = $facebookUser->getEmail();
				$emailAddress = EmailAddress::make($facebookEmailAddress);

			} catch (\Exception $e) {
				throw new \Katu\Exceptions\ErrorException("Nepodařilo se nám zjistit vaši e-mailovou adresu. Zkuste to znovu.");
			}

			try {

				$user = static::getOrCreateUserByEmailAddress($emailAddress);
				$user->update('name', $facebookUser->getName());
				$user->save();
				$user->login();

				\Katu\Flash::set('success', "Přihlásili jste se pomocí svého Facebook účtu.");

				return static::redirect([
					$app->request->params('returnUrl'),
					$user->getAfterLoginUrl(),
				]);

			} catch (\Exception $e) {
				throw new \Katu\Exceptions\ErrorException("Přihlášení přes Facebook se nezdařilo. Zkuste to znovu.");
			}

		} catch (\Facebook\Exceptions\FacebookAuthenticationException $e) {
			return static::redirect($loginUrl);
		} catch (\Facebook\Exceptions\FacebookResponseException $e) {
			return static::redirect($loginUrl);
		} catch (\Facebook\Exceptions\FacebookSDKException $e) {
			return static::redirect($loginUrl);
		} catch (\Katu\Exceptions\Exception $e) {
			\Katu\Flash::set('error', $e->getMessage());
			return static::redirect(\Katu\Utils\Url::getFor('account.login'));
		}
	}

	static function loginWithGoogle() {
		try {

			$app = \Katu\App::get();

			\Katu\Session::reset('google.accessToken');

			$client = new \Google_Client();
			$client->setClientId(\Katu\Config::get('google', 'api', 'clientId'));
			$client->setClientSecret(\Katu\Config::get('google', 'api', 'secret'));
			$client->setAccessType('offline');
			$client->setIncludeGrantedScopes(true);
			$client->addScope(\Google_Service_Plus::PLUS_ME);
			$client->addScope(\Google_Service_Plus::USERINFO_EMAIL);
			$client->addScope(\Google_Service_Plus::USERINFO_PROFILE);
			$client->setRedirectUri((string) \Katu\Utils\Url::getFor('account.loginWithGoogle', null, [
				//'returnUrl' => $app->request->params('returnUrl'),
			]));

			$loginUrl = $client->createAuthUrl();

			// Redirected with error.
			if ($app->request->params('error')) {
				throw new \Katu\Exceptions\ErrorException("Přihlášení přes Google+ se nezdařilo. Zkuste to znovu.");
			}

			// Redirected with code.
			if ($app->request->params('code')) {
				\Katu\Session::set('google.accessToken', $client->authenticate($app->request->params('code')));
			}

			$accessToken = \Katu\Session::get('google.accessToken');
			if (!$accessToken) {
				throw new \Google_Exception;
			}

			$client->setAccessToken($accessToken);

			try {

				$plus = new \Google_Service_Plus($client);
				$googleUser = $plus->people->get('me');
				$googleEmailAddress = $googleUser->getEmails()[0]->value;
				$emailAddress = EmailAddress::make($googleEmailAddress);

			} catch (\Exception $e) {
				throw new \Katu\Exceptions\ErrorException("Nepodařilo se nám zjistit vaši e-mailovou adresu. Zkuste to znovu.");
			}

			try {

				$user = static::getOrCreateUserByEmailAddress($emailAddress);
				$user->update('name', $googleUser->getDisplayName());
				$user->save();
				$user->login();

				\Katu\Flash::set('success', "Přihlásili jste se pomocí svého Google účtu.");

				return static::redirect([
					$app->request->params('returnUrl'),
					$user->getAfterLoginUrl(),
				]);

			} catch (\Exception $e) {
				throw new \Katu\Exceptions\ErrorException("Přihlášení přes Google+ se nezdařilo. Zkuste to znovu.");
			}

		} catch (\Google_Exception $e) {
			return static::redirect($loginUrl);
		} catch (\Google_Service_Exception $e) {
			return static::redirect($loginUrl);
		} catch (\Katu\Exceptions\Exception $e) {
			\Katu\Flash::set('error', $e->getMessage());
			return static::redirect(\Katu\Utils\Url::getFor('account.login'));
		}
	}

	static function welcome() {
		$app = \Katu\App::get();

		$user = User::getCurrent();
		if (!$user) {
			throw new \Katu\Exceptions\UnauthorizedException;
		}

		$user->setUserSetting('welcome.shown', 1);

		static::$data['_page']['title'] = \App\Classes\Site::getPageTitle("Vítejte");

		return static::render("Front/Account/welcome");
	}

	static function details() {
		$app = \Katu\App::get();

		$user = User::getCurrent();
		if (!$user) {
			throw new \Katu\Exceptions\UnauthorizedException;
		}

		if (static::isSubmittedWithToken()) {

			try {

				User::transaction(function($app, $user) {

					try {
						$user->setNickname($app->request->params('nickname'));
					} catch (\Exception $e) {
						throw new \Katu\Exceptions\InputErrorException("Chybějící přezdívka.");
					}

					try {
						$user->setName($app->request->params('name'));
					} catch (\Exception $e) {
						throw new \Katu\Exceptions\InputErrorException("Chybějící jméno.");
					}

					$user->setUserSetting('details.about', $app->request->params('about'));
					$user->setUserSetting('details.url', \Katu\Types\TUrl::makeValid($app->request->params('url')));
					$user->save();

					$user->refreshFileAttachmentsFromFileIds($user, $app->request->params('fileIds'));

				}, $app, $user);

				\Katu\Flash::set('success', 'Úspešně jste aktualizovali svoje osobní údaje.');

				return static::redirect(\Katu\Utils\Url::getFor('account.details'));

			} catch (\Katu\Exceptions\Exception $e) { static::addError($e); }

		}

		static::$data['_page']['title'] = \App\Classes\Site::getPageTitle("Osobní údaje");

		return static::render("Front/Account/details");
	}

	static function logout() {
		$app = \Katu\App::get();

		User::logout();

		\Katu\Flash::set('success', "Odhlásili jste se.");

		return static::redirect(\Katu\Utils\Url::getFor('homepage'));
	}

	static function forgottenPassword() {
		$app = \Katu\App::get();

		if (static::isSubmittedWithToken()) {

			try {

				if (!trim($app->request->params('emailAddress'))) {
					throw (new \Katu\Exceptions\InputErrorException("Missing e-mail address."))
						->addErrorName('emailAddress')
						->addTranslation('cs', "Chybějící e-mailová adresa.")
						;
				}

				$user = User::getOneBy([
					'emailAddressId' => EmailAddress::make($app->request->params('emailAddress'))->id,
				]);
				if ($user) {
					$user->sendForgottenPasswordEmail();

					\Katu\Flash::set('success', 'Zkontrolujte svou e-mailovou schránku. Najdete v ní e-mail s instrukcemi k obnovení svého hesla.');
				}

				return static::redirect(\Katu\Utils\Url::getFor('homepage'));

			} catch (\Katu\Exceptions\Exception $e) { static::addError($e); }

		}

		static::$data['_page']['title'] = \App\Classes\Site::getPageTitle("Zapomenuté heslo");

		return static::render("Front/Account/forgottenPassword");
	}

	static function passwordRecovery($token) {
		$app = \Katu\App::get();

		$userPasswordToken = UserPasswordToken::getOneBy([
			'token' => $token,
		]);
		if (!$userPasswordToken) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}
		if (!$userPasswordToken->isValid()) {
			throw new \Katu\Exceptions\ModelNotFoundException;
		}

		if (static::isSubmittedWithToken()) {

			try {

				if (!trim($app->request->params('password1'))) {
					throw (new \Katu\Exceptions\InputErrorException("Missing password."))
						->addErrorName('password1')
						->addTranslation('cs', "Chybějící nové heslo.")
						;
				}
				if (!trim($app->request->params('password2'))) {
					throw (new \Katu\Exceptions\InputErrorException("Missing password."))
						->addErrorName('password2')
						->addTranslation('cs', "Chybějící nové heslo pro kontrolu.")
						;
				}
				if ($app->request->params('password1') != $app->request->params('password2')) {
					throw (new \Katu\Exceptions\InputErrorException("Passwords don't match."))
						->addErrorName('password2')
						->addTranslation('cs', "Hesla se neshodují.")
						;
				}

				$userPasswordToken->expire();

				$user = $userPasswordToken->getUser();
				$user->setPassword(\Katu\Utils\Password::encode('sha512', $app->request->params('password1')));
				$user->save();

				$user->login();

				\Katu\Flash::set('success', 'Vaše heslo jsme úspěšně změnili a rovnou vás přihlásili.');

				return static::redirect([
					$user->getAfterLoginUrl(),
				]);

			} catch (\Katu\Exceptions\Exception $e) { static::addError($e); }

		}

		static::$data['_page']['title'] = \App\Classes\Site::getPageTitle("Obnova hesla");

		return static::render("Front/Account/passwordRecovery");
	}

	static function sendEmailAddressConfirmationEmail() {
		$app = \Katu\App::get();

		$user = User::getCurrent();
		if (!$user) {
			throw new \Katu\Exceptions\UnauthorizedException;
		}

		$user->getEmailAddress()->sendConfirmationEmail([
			'returnUrl' => $app->request->params('returnUrl'),
		]);

		\Katu\Flash::set('success', "Zkontrolujte svou e-mailovou schránku. Najdete v ní ověřovací e-mail.");

		return static::redirect(\Katu\Utils\Url::getFor('homepage'));
	}

	static function confirmEmailAddress($emailAddressSecret) {
		$app = \Katu\App::get();

		$user = User::getCurrent();

		$emailAddress = EmailAddress::getOneBy([
			'secret' => $emailAddressSecret,
		]);

		if (!$emailAddress) {
			\Katu\Flash::set('error', "Při ověření e-mailové adresy došlo k chybě.");
		} else {
			$emailAddress->confirm();
			\Katu\Flash::set('success', "Vaše e-mailová adresa byla ověřena.");
		}

		return static::redirect([
			$app->request->params('returnUrl'),
			\Katu\Utils\Url::getFor('homepage'),
		]);
	}

	static private function getOrCreateUserByEmailAddress($emailAddress) {
		$user = User::getOneBy([
			'emailAddressId' => $emailAddress->getId(),
		]);
		if (!$user) {

			$user = User::createWithEmailAddress($emailAddress);

			\Katu\Flash::set('facebookPixelTrack', [
				[
					'event' => 'CompleteRegistration',
				],
			]);

			try {
				$user->getEmailAddress()->sendConfirmationEmail();
			} catch (\Exception $e) {
				/* Nevermind. */
			}

		}

		return $user;
	}

	static function recipes() {
		$user = User::getCurrent();
		if (!$user) {
			throw new \Katu\Exceptions\UnauthorizedException;
		}

		static::$data['recipes'] = $user->getRecipes();

		static::$data['_page']['title'] = \App\Classes\Site::getPageTitle("Moje recepty");

		return static::render("Front/Account/recipes");
	}

}
