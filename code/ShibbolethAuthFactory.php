<?php

/**
 *	ShibbolethAuthenticator
 *	
 *	Singleton Factory class to construct the ShibbolethAuthenticator auth source
 *
 *	@package shibboleth
 **/

class ShibbolethAuthFactory {

	protected static $instance = null;
	protected $instanceClass = 'ShibbolethSimpleSAMLSP';

	/**
	 *	Singleton method
	 **/
	public static function &instance() {
		if (!self::$instance) {
			self::$instance = new ShibbolethAuthFactory();
		}
		return self::$instance;
	}

	/**
	 *	Getter for ShibbolethAuthFactory::$instanceClass
	 **/
	public function getInstanceClass() {
		return $this->instanceClass;
	}

	/**
	 *	Setter for ShibbolethAuthFactory::$instanceClass
	 **/
	public function setInstanceClass($className) {
		$this->instanceClass = $className;
	}

	public function __construct() {
		if (Director::isTest() || SapphireTest::is_running_test()) {
			// default to Mock in test mode.
			$this->setInstanceClass('MockSimpleSAML');
		}
	}

	public function create() {
		//	require_once dirname(dirname(__FILE__)) . '/tests/MockAuthFactory.php';
		return Object::create($this->instanceClass);
	}
}


?>
