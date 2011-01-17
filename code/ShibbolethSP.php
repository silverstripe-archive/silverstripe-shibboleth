<?php

/**
 *	ShibbolethSP
 *	
 *	@package shibboleth
 **/

interface ShibbolethSP {

	public function isAuthenticated();

	public function requireAuth(array $params = array());

	public function login(array $params = array());

	public function logout($url = null);

	public function getAttributes();

	public function getLoginURL($returnTo = null);

	public function getLogoutURL($returnTo = null);

}

?>
