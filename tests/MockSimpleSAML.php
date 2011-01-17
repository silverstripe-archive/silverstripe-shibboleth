<?php

class MockSimpleSAML implements ShibbolethSP, TestOnly {

	protected static $has_died = false;
	protected static $authed_member = null;

	public static function set_auth_user($member) {
		self::$authed_member = $member;
	}

	public static function has_died() {
		return self::$has_died;
	}

	public function __construct() {
	}

	public function isAuthenticated() {
		return false;
	}

	public function requireAuth(array $params = array()) {
		self::$has_died = false;
		if (!self::$authed_member) {
			self::$has_died = true;
		}
	}

	public function login(array $params = array()) {
	}

	public function logout($url = null) {
		Director::redirect($url);
		exit();
	}

	public function getAttributes() {
		if (self::$authed_member) {
			$member = self::$authed_member;
			return array(
					'mail' => array($member->Email),
					'givenName' => array($member->FirstName),
					'sn' => array($member->Surname),
					'eduPersonTargetedID' => array("{$member->FirstName}@{$member->Surname}"),
				);
		}
		return false;
	}

	public function getLoginURL($returnTo = null) {
		return '';
	}

	public function getLogoutURL($returnTo = null) {
		return '';
	}

}

?>
