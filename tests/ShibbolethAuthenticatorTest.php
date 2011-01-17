<?php

class ShibbolethAuthenticatorTest extends SapphireTest {
	static $fixture_file = 'shibboleth/tests/ShibbolethAuthenticatorTest.yml';

	public function setUp() {
		parent::setUp();

		// Better not run with real SimpleSAML, make sure of this here.
		$this->assertEquals('MockSimpleSAML', ShibbolethAuthFactory::instance()->getInstanceClass());
	}

	public function testAuthenticateOK() {
		$rawData = array();
		$member = $this->objFromFixture('Member', 'shibmember');
		MockSimpleSAML::set_auth_user($member);
		$form = ShibbolethAuthenticator::get_login_form(Controller::curr());
		$authmember = ShibbolethAuthenticator::authenticate($rawData, $form);
		$this->assertEquals($member->ID, $authmember->ID, 'Member was not authenticated from data ' . var_export($rawData,2));
	}

	public function testAuthenticateAndCreateMember() {
		$rawData = array();
		$member = $this->objFromFixture('Member', 'shibmember');
		$member->FirstName = 'newuser';
		$member->UniqueIdentifier = 'newuser@User';
		$member->ID = null;
		MockSimpleSAML::set_auth_user($member);
		$form = ShibbolethAuthenticator::get_login_form(Controller::curr());
		$authmember = ShibbolethAuthenticator::authenticate($rawData, $form);
		$this->assertEquals($member->UniqueIdentifier, $authmember->UniqueIdentifier, 'Member was not authenticated from data ' . var_export($rawData,2));
		$this->assertTrue(is_numeric($authmember->ID),  'Member was not created');
	}

	public function testAuthenticateFail() {
		MockSimpleSAML::set_auth_user(null);
		$rawData = array();
		$form = ShibbolethAuthenticator::get_login_form(Controller::curr());

		try {
			$member = ShibbolethAuthenticator::authenticate($rawData, $form);
			$this->assertTrue(MockSimpleSAML::has_died(), 'Should have died due to failed authentication');
		} catch(ShibbolethAuthenticator_Exception $ex) {
			$this->assertEquals(ShibbolethAuthenticator::EX_NOATTRIBUTES, $ex->getCode(), "Expected EX_NOATTRIBUTES, got " . $ex->getCode());
		}
	}
}

