# Shibboleth module

This module is a wrapper around the [`SimpleSAML` thirdparty library](http://simplesamlphp.org/), providing federated authentication.
It uses the `Authenticator` interface to expose a new `ShibbolethAuthenticator` that is integrated into the login screen.

**Caution: This module is just a light wrapper, and requires installation and configuration of the fairly complex thirdparty library (see [docs](http://simplesamlphp.org/docs/1.7/simplesamlphp-install)).**

## Requirements ##

 * PHP 5.2.0+
 * SilverStripe 2.4.3+

**Warning**: This module replaces Sapphire's `__autoload` function, as SimpleSAML is based
around its own `__autoload`.  So, the Sapphire `__autoload` function has been
copied into  `thirdparty/simplesaml/lib/_autoload.php` and is called as a
fallback, if the SimpleSAML one fails. This is awkward, but effective.

 * Modified SimpleSAML library 1.6.1 (packaged in `thirdparty/simplesaml`)
 * Unix operating system (hardcoded `/tmp` paths, `install.sh` script)
 * Apache webserver (uses rewrite rules and `.htaccess`)
 * The project containing this module has to be named `nersc/` (see "Issues Tracking" below)

## Installation ##

### Customizations to simplesaml ###

The simplesaml login form shows in as a new tab in the default `Security/login` screen.
In order to display it "inline" rather than as a full webpage, the default template has
been modified in the form of a new module: `simplesaml-custom/modules/silverstripe/`.

In order to copy the customizations into the `thirdparty/simplesaml` folder,
run the following script:

	install.sh
	
### Customizations to thirdparty/shibbolet/config/config.php ###

When copying the `thirdparty/shibboleth/config-templates/` folder to `thirdparty/shibboleth/config/`,
you need to adjust some settings:

	$config = array(
		// ...
		// Module is not in webroot, but rather a subfolder
		'baseurlpath' => '<projectname>/shibboleth/thirdparty/simplesaml/www/',
		// Use a custom theme
		'theme.use' => 'silverstripe:silverstripe',
	)


The module includes a method to configure the thirdparty library through
a `SspConfigLoader::get_env_conf()` call. This is an optional enhancement,
used to keep the actual configuration outside of version control.
You are free to write configuration without this helper.

	include_once('../../../code/sspConfigLoader.php');
	
	$config = array(
		// ...
		// Example use of sspConfigLoader class
		'auth.adminpassword' => SspConfigLoader::get_env_conf('config', 'auth.adminpassword'),
	)

### Apache rewriting

You will also need to ensure the following condition is added to the standard SilverStripe `.htaccess`, before the final `RewriteRule`.

	RewriteCond %{REQUEST_URI} !shibboleth/thirdparty/simplesaml

This ensures SimpleSAML can be accessed as needed from the web.

Then, add this at the end of your `.htaccess`:

	AcceptPathInfo On

### Unique identifer in SilverStripe Member database

The `shibboleth` module changes the unique identifier for members from the `Email` field to the `UniqueIdentifier` field. You will notice
that the login form now asks for a different value. Newly created members should have the `UniqueIdentifier` field be set. Existing
members don't have the field set. That is why they can not log in. Copying the Email value into this new column should fix the issue.

## Configuration

You will have to configure the SimpleSAML installation to suit your needs.
This can be done through the SimpleSAML control panel.

The module comes with defaults for certain Identity Providers.

Create the following file called `_ssp_environment.php` in your app root or one folder above. Note: This is different from the `_ss_environment.php` file used for generic SilverStripe environment management.

	<?php
	// use one of the thre predefined configurations
	$useDiscoService = 'scifed'; // set to 'scifed' / 'incommon' / null

	$env = array(
		'config/config.php' => array(
			'config' => array(
				'baseurlpath' => 'yourprojectfolder/shibboleth/thirdparty/simplesaml/www/',
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
	
The usage of `_ssp_environment.php` allows loading of values into the `thirdparty/simplesamml/config/config.php` file via a custom class call to `SspConfigLoader::get_env_conf()`.

IMPORTANT: Data in `thirdparty/metadata/` might change based on cronjobs and manual refreshes triggered by the thirdparty module code.

## SimpleSAML thirdparty UI administration ##

The SimpleSAML library comes with its own UI, available at `http://localhost/shibboleth/thirdparty/simplesaml/www/module.php/core/frontpage_welcome.php`.
It is authenticated (see `_ssp_environment.php`).

## Issue Tracking ##

Bugs are tracked on [github.com](https://github.com/silverstripe-labs/silverstripe-shibboleth/issues).

## Internal Subversion History ##

The module was migrated from an internal, access protected svn repository.
The module path has since been removed, so needs to list a specific revision.
In case you have access, the module history is available through the following command:

	svn log http://svn.silverstripe.com/modules/shibboleth@115000