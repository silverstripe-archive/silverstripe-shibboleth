<?php

/**
 *	ShibbolethSimpleSAMLSP
 *	
 *	@package shibboleth
 **/

class ShibbolethSimpleSAMLSP implements ShibbolethSP, TestOnly {

	/* Exception codes */
	const EX_FAILCREATEAUTH = 100;

	protected $authSource = null;

	protected function getInstance() {
		if ($this->authSource) {
			return $this->authSource;
		}
		$authSource = new SimpleSAML_Auth_Simple('default-sp');
		if (!$authSource) {
			throw new ShibbolethSimpleSAMLSP_Exception("Failed to create auth source", self::EX_FAILCREATEAUTH);
		}
		$this->authSource = $authSource;
		return $authSource;
	}

	public function __construct() {
	}

	public function isAuthenticated() {
		return $this->getInstance()->isAuthenticated();
	}

	public function requireAuth(array $params = array()) {
		return $this->getInstance()->requireAuth($params);
	}

	public function login(array $params = array()) {
		return $this->getInstance()->login($params);
	}

	public function logout($url = null) {
		return $this->getInstance()->logout($url);
	}

	public function getAttributes() {
		return $this->getInstance()->getAttributes();
	}

	public function getLoginURL($returnTo = null) {
		return $this->getInstance()->getLoginURL();
	}

	public function getLogoutURL($returnTo = null) {
		return $this->getInstance()->getLogoutURL();
	}

	public function getSources() {
		$auth = $this->getInstance();
		$ssamlconfig  = SimpleSAML_Configuration::getInstance();
		$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$metadataSet = $metadata->getList('saml20-idp-remote');
		return $metadataSet;
	}

}