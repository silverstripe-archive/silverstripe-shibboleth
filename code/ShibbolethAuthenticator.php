<?php

/**
 *	ShibbolethAuthenticator
 *	
 *	@package shibboleth
 **/

class ShibbolethAuthenticator extends Authenticator {
	
	
	const EX_NOATTRIBUTES = 100;
	const EX_NOTFOUND = 101;

	protected static $auth_object = null;

	protected static $group_code = 'nersc'; /* Code for group to put new members into */


	/**
	 *	Setter for $group_code
	 **/
	public static function set_group_code($code) {
		self::$group_code = $code;
	}

	/**
	 * 'Singleton' method to retrieve/create an instance of the authentication backend.
	 */
	protected function get_auth_object() {
		if (!self::$auth_object) {
			self::$auth_object = ShibbolethAuthFactory::instance()->create();
		}
		if (!self::$auth_object) {
			throw new ShibbolethAuthenticator_Exception("Failed to instantiate authsource using ShibbolethAuthFactory");
		}
		return self::$auth_object;
	}

	/**
	 * Authenticate the user given the form submission data.
	 * @param array $rawData
	 * @param Form $form
	 * @return mixed (Member, or false on error)
	 */
	public static function authenticate($rawData, Form $form = null) {
		$authSource = self::get_auth_object();
		$authSource->requireAuth();
		$attributes = $authSource->getAttributes();
		
		if (!is_array($attributes)) {
			throw new ShibbolethAuthenticator_Exception("No attributes array returned", self::EX_NOATTRIBUTES);
		}
		if (!isset($attributes['eduPersonTargetedID']) || !is_array($attributes['eduPersonTargetedID'])) {
			throw new ShibbolethAuthenticator_Exception("No eduPersonTargetedID attribute found", self::EX_NOTFOUND);
		}
		
		$uid = $attributes['eduPersonTargetedID'][0];
		$user = DataObject::get_one('Member', "\"Member\".\"UniqueIdentifier\" LIKE '" . Convert::raw2sql($uid) . "'");
		if ($user) {
			$user->login();
		} else {
			$user = Object::create('Member');
			$user->FirstName	= Convert::raw2sql($attributes['givenName'][0]);
			$user->Surname		= Convert::raw2sql($attributes['sn'][0]);
			$user->Email		= Convert::raw2sql($attributes['mail'][0]);
			$user->UniqueIdentifier	= $uid;
			$user->write();
			$user->login();
			$user->addToGroupByCode(self::$group_code);
		}
		return $user != null ? $user : false;
	}

	/**
	 * method that creates the login form for this authentication method
	 * @param controller the parent controller, necessary to create the appropriate form action tag
	 * @return form returns the login form to use with this authentication method
	 */
	public static function get_login_form(Controller $controller) {
		$fields = new FieldSet();
		$actions = new FieldSet(
			new FormAction('dologin', 'Log in')
		);
		$form = Object::create("ShibbolethLoginForm", $controller, "LoginForm", $fields, $actions);
		return $form;
	}

	/**
	 * get the name of the authentication method
	 * @return string returns the name of the authentication method.
	 */
	public static function get_name() {
		return _t('ShibbolethAuthenticator.Title', "Shibboleth");
	}

}


/**
 *	ShibbolethAuthenticator_Exception
 *	
 *	@package shibboleth
 */

class ShibbolethAuthenticator_Exception extends Exception {

}

?>