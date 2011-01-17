<?php

/**
 *	ShibbolethLoginForm
 *	
 *	@package shibboleth
 **/

class ShibbolethLoginForm extends LoginForm {

	protected $authenticator_class = 'ShibbolethAuthenticator';

	/**
	 * Constructor
	 *
	 * @param Controller $controller The parent controller, necessary to create the appropriate form action tag.
	 * @param string $name The method on the controller that will return this form object.
	 * @param FieldSet|FormField $fields All of the fields in the form - a {@link FieldSet} of {@link FormField} objects.
	 * @param FieldSet|FormAction $actions All of the action buttons in the form - a {@link FieldSet} of {@link FormAction} objects
	 * @param bool $checkCurrentUser If set to TRUE, it will be checked if a the user is currently logged in, and if so, only a logout button will be rendered
	 */
	function __construct($controller, $name, $fields = null, $actions = null, $checkCurrentUser = true) {

		if (!$fields->fieldByName('AuthenticationMethod')) {
			$fields->push(new HiddenField('AuthenticationMethod', null, $this->authenticator_class, $this));
		}

		if (!$actions) {
			$actions = new FieldSet(
				new FormAction(
					'dologin',
					_t('ShibbolethAuthenticator.Login',	'Log in')
				)
			);
		}

		if (isset($backURL)) {
			$fields->push(new HiddenField('BackURL', 'BackURL', $backURL));
		}

		$actions = new FieldSet();
		if(Member::currentUserID()) {
			$fields->push(new LiteralField('iframe', "<iframe style=\"border:0 none; width:100%; height:500px;\" src=\"".Director::absoluteBaseURL()."/shibboleth/thirdparty/simplesaml/www/module.php/core/authenticate.php?as=default-sp\"></iframe>"));
		} else {
			$fields->push(new LiteralField('iframe', "<iframe style=\"border:0 none; width:100%; height:500px;\" src=\"".Director::absoluteBaseURL()."/Security/LoginForm?AuthenticationMethod=ShibbolethAuthenticator&action_dologin=Log+in\"></iframe>"));
		}

		parent::__construct($controller, $name, $fields, $actions);
	}


	/**
	 * Process the login form submission
	 */
	public function dologin($data, $form = null) {
		ShibbolethAuthenticator::authenticate($data, $form);

		// If the authenticator returns then the user is authenticated.
		if(isset($_REQUEST['BackURL']) && $backURL = $_REQUEST['BackURL']) {
			Session::set('BackURL', $backURL);
		}
		Director::redirect(Session::get('BackURL'));

		// the following should only run if there's an error authenticating.
		if (false) {
			if($badLoginURL = Session::get("BadLoginURL")){
				Director::redirect($badLoginURL);
			} else {
				// Show the right tab on failed login
				Director::redirect(Director::absoluteURL(Security::Link("login")) .
						'#' . $this->FormName() .'_tab');
			}
		}
	}
}

