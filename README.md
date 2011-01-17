Shibboleth module
=================

This module is a wrapper around `SimpleSAML`, providing an authentication method and login form for *SilverStripe*.


Warning
--------
This module replaces Sapphire's __autoload function, as SimpleSAML is based
around its own __autoload.  So, the Sapphire __autoload function has been
copied into  thirdparty/simplesaml/lib/_autoload.php and is called as a
fallback, if the SimpleSAML one fails.

This is awkward, but effective.

Usage
-----
Add this module to your *SilverStripe* installation.

You will also need to ensure the following condition is added to the standard SilverStripe .htaccess, before the final RewriteRule.

	RewriteCond %{REQUEST_URI} !shibboleth/thirdparty/simplesaml/www/module.php/saml/disco.php

This ensures SimpleSAML can be accessed as needed from the web.

Then, add this at the end of your .htaccess:

	AcceptPathInfo On

Configuration
-------------

You will have to configure the SimpleSAML installation to suit your needs.
This can be done through the SimpleSAML control panel.
Add this to your .htaccess (before the RewriteRule) to enable the control panel.

	RewriteCond %{REQUEST_URI} !shibboleth/thirdparty/simplesaml

Create the following file called _ssp_environment.php in your app root or one folder below:

<?php

/**
 *
 *	Environment file can be located in app root or one level below.
 *
 **/

// use one of the 3 presetup configurations
$useDiscoService = 'scifed'; // set to 'scifed' / 'incommon' / null

$env = array(
	'config/config.php' => array(
		'config' => array(
			'auth.adminpassword' => 'simplesamlpassword',
			'secretsalt' => 'somesecretsalt',
			'timezone' => 'Pacific/Auckland',	
			'useDiscoService' => $useDiscoService,
			'metadata.sources' => array(
				array('type' => 'flatfile'),
				array('type' => 'flatfile', 'directory' => 'metadata/metadata-' . $useDiscoService)
			),
			'technicalcontact_name' => 'Administrator',
			'technicalcontact_email' => 'some@email.com',
		),
	),
	'config/authsources.php' => array(
		'config' => array(
			'default-sp' => array(
				'entityID' => null,
				'useDiscoService' => $useDiscoService,
				'privatekey' => 'saml.pem',
				'certificate' => 'saml.crt',
			),
		),
	),
	'config/module_discopower.php' => array(
		'useDiscoService' => $useDiscoService,
	),
);
