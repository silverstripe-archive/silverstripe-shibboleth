<?php
/**
 * silverstripe/modules/shibboleth/code/DevTasks.php
 *
 * Generates a new certificate public/private keypair for the shib module
 * and displays the SP metadata that needs to be shared with IdPs and Federations
 *
 * See the SimpleSAMLPHP docs at this URL
 * http://simplesamlphp.org/docs/1.6/simplesamlphp-sp
 *
 * And the notes + PHP code on generating a self-signed cert here:
 * http://foaf.me/Tests/Using_PHP_to_create_X.509_Client_Certificates.php
 *
 * The code for displaying the metadata is copied from the SSP codebase
 * simplesaml/modules/saml/www/sp/metadata.php
 *
 * @author Steve Chan sychan@lbl.gov
 * @package NERSC-ESnet
 */


class GenerateShibCertTask extends BuildTask {

    protected $title       = 'Generate New Shibboleth Certificate';
    protected $description = 'Uses openssl to generate a new self-signed certificate for the shibboleth SP module. Run this task before registering SAML SP metadata to IdPs and Federations. Note that if you re-run this task after already registering with trust partners, you will have to reregister!';

    /**
     *
     *
     * @param HttpRequest $request
     */
    function run($request) {

      echo "<pre>\n";
      try {

	$env =  SspConfigLoader::env();
	$defaultSP = $env['config/authsources.php']['config']['default-sp'];
	$baseSAMLPath = realpath(dirname(__FILE__) . '/../thirdparty/simplesaml');
	$cert = $baseSAMLPath . "/cert/" . $defaultSP['certificate'];
	$key = $baseSAMLPath . "/cert/" . $defaultSP['privatekey'];
	echo "Certificate file for default-sp in " . $cert . " \n";
	echo "Private key for default-sp in " . $key . " \n";
	if (! is_writable($cert) || ! is_writable($key)) { 
	  throw new Exception("Unable to write to certificate and/or private keyfiles");
	}
	// try to find an openssl.cnf file
	$possible = array("/etc/ssl/openssl.cnf","/usr/share/ssl/openssl.cnf","/usr/local/ssl/lib/openssl.cnf");
	foreach ( $possible  as $cnf ) {
	  if (is_readable($cnf)) {
	    $config = array('config'=>$cnf);
	  }
	}
	if (! $config['config']) {
	  throw new Exception("Unable to find openssl.cnf");
	}
	  
	echo "Generating new x509 certificate....\n";
	$dn = array(
		    "countryName" => "US",
		    "stateOrProvinceName" => "CA",
		    "organizationName" => "Silverstripe CMS",
		    "organizationalUnitName" => "Shibboleth Auth Module",
		    "commonName" => Director::absoluteBaseURL(),
		    "emailAddress" => Email::getAdminEmail() ? Email::getAdminEmail() : $_SERVER['SERVER_ADMIN'],
		    );
	// Generate a new private (and public) key pair
	$privkey = openssl_pkey_new($config);
	
	if ($privkey==FALSE) {
	  while (($e = openssl_error_string()) !== false) {
	    echo $e . " \n";
	  }
	  throw new Exception("Could not generate a public/private keypair");
	}
	
	// Generate a certificate signing request
	$csr = openssl_csr_new($dn, $privkey, $config);
	
	if (!$csr) {
	  while (($e = openssl_error_string()) !== false) {
	    echo $e . " \n";
	  }
	  throw new Exception("Could not generate a signing request");
	}
	// Self-sign the cert for 3 years
	$sscert = openssl_csr_sign($csr, null, $privkey, 365*3, $config);
	
	if ($sscert==FALSE) {
	  while (($e = openssl_error_string()) !== false) {
	    echo $e . " \n";
	  }
	  throw new Exception("Unable to self-sign certificate");

	}
	// Assuming we got here, write it out
	if (openssl_x509_export_to_file($sscert, $cert)==FALSE) {
	  // Show any errors that occurred here
	  while (($e = openssl_error_string()) !== false) {
	    echo $e . " \n";
	  }
	  throw new Exception("Unable to write certificate to $cert");
	}
	openssl_x509_export( $sscert, $x509, FALSE);
	echo "Successfully wrote the following cert to $cert \n$x509\n";
	
	if (openssl_pkey_export_to_file($privkey, $key)==FALSE) {
	  // Show any errors that occurred here
	  while (($e = openssl_error_string()) !== false) {
	    echo $e . " \n";
	  }
	  throw new Exception("Unable to write private key to $key");
	}
	echo "Successfully exported private key to $key.\n\n";
	echo "IMPORTANT: Now that your certificate has been changed, you will need to update the metadata that IdPs and Federations use to communicate with this host. Please use run ShibSPMetadata task and send the new metadata to your IdPs and/or trust federations.\n";
      } catch (Exception $ex) {
	echo "Error: ", $ex->getMessage(), "\n";
      }
      echo "</pre>";
    }
}

class ShibSPMetadataTask extends BuildTask {

    protected $title       = 'Display Shibboleth SP Metadata';
    protected $description = "Display Service Provider metadata for SAML/Shibboleth authentication that can be sent to IdPs and trust federations.";

    /**
     *
     *
     * @param HttpRequest $request
     */
    function run($request) {

      try {

	$config = SimpleSAML_Configuration::getInstance();
	$sourceId = "default-sp";
	$source = SimpleSAML_Auth_Source::getById($sourceId);
	if ($source === NULL) {
	  throw new SimpleSAML_Error_NotFound('Could not find authentication source with id ' . $sourceId);
	}

	if (!($source instanceof sspmod_saml_Auth_Source_SP)) {
	  throw new SimpleSAML_Error_NotFound('Source isn\'t a SAML SP: ' . var_export($sourceId, TRUE));
	}
	
	$entityId = $source->getEntityId();
	$spconfig = $source->getMetadata();
	
	$ed = new SAML2_XML_md_EntityDescriptor();
	$ed->entityID = $entityId;
	
	$sp = new SAML2_XML_md_SPSSODescriptor();
	$ed->RoleDescriptor[] = $sp;
	$sp->protocolSupportEnumeration = array(
						'urn:oasis:names:tc:SAML:1.1:protocol',
						'urn:oasis:names:tc:SAML:2.0:protocol'
						);
	
	$slo = new SAML2_XML_md_EndpointType();
	$slo->Binding = SAML2_Const::BINDING_HTTP_REDIRECT;
	$slo->Location = SimpleSAML_Module::getModuleURL('saml/sp/saml2-logout.php/' . $sourceId);
	$sp->SingleLogoutService[] = $slo;
	
	
	$acs = new SAML2_XML_md_IndexedEndpointType();
	$acs->index = 0;
	$acs->Binding = SAML2_Const::BINDING_HTTP_POST;
	$acs->Location = SimpleSAML_Module::getModuleURL('saml/sp/saml2-acs.php/' . $sourceId);
	$sp->AssertionConsumerService[] = $acs;
	
	$acs = new SAML2_XML_md_IndexedEndpointType();
	$acs->index = 1;
	$acs->Binding = 'urn:oasis:names:tc:SAML:1.0:profiles:browser-post';
	$acs->Location = SimpleSAML_Module::getModuleURL('saml/sp/saml1-acs.php/' . $sourceId);
	$sp->AssertionConsumerService[] = $acs;
	
	$acs = new SAML2_XML_md_IndexedEndpointType();
	$acs->index = 2;
	$acs->Binding = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact';
	$acs->Location = SimpleSAML_Module::getModuleURL('saml/sp/saml2-acs.php/' . $sourceId);
	$sp->AssertionConsumerService[] = $acs;
	
	$acs = new SAML2_XML_md_IndexedEndpointType();
	$acs->index = 3;
	$acs->Binding = 'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01';
	$acs->Location = SimpleSAML_Module::getModuleURL('saml/sp/saml1-acs.php/' . $sourceId . '/artifact');
	$sp->AssertionConsumerService[] = $acs;
	
	$certInfo = SimpleSAML_Utilities::loadPublicKey($spconfig);
	if ($certInfo !== NULL && array_key_exists('certData', $certInfo)) {
	  $certData = $certInfo['certData'];
	  $kd = SAML2_Utils::createKeyDescriptor($certData);
	  $kd->use = 'signing';
	  $sp->KeyDescriptor[] = $kd;
	  
	  $kd = SAML2_Utils::createKeyDescriptor($certData);
	  $kd->use = 'encryption';
	  $sp->KeyDescriptor[] = $kd;
	} else {
	  $certData = NULL;
	}
	
	$name = $spconfig->getLocalizedString('name', NULL);
	$attributes = $spconfig->getArray('attributes', array());
	if ($name !== NULL && !empty($attributes)) {
	  /* We have everything necessary to add an AttributeConsumingService. */
	  $acs = new SAML2_XML_md_AttributeConsumingService();
	  $sp->AttributeConsumingService[] = $acs;
	  
	  $acs->index = 0;
	  $acs->ServiceName = $name;
	  
	  $description = $spconfig->getLocalizedString('description', NULL);
	  if ($description !== NULL) {
	    $acs->ServiceDescription = $description;
	  }
	  
	  $nameFormat = $spconfig->getString('attributes.NameFormat', NULL);
	  foreach ($attributes as $attribute) {
	    $a = new SAML2_XML_md_RequestedAttribute();
	    $a->Name = $attribute;
	    $a->NameFormat = $nameFormat;
	    $acs->RequestedAttribute[] = $a;
	  }
	  
	}
	
	$orgName = $spconfig->getLocalizedString('OrganizationName', NULL);
	if ($orgName !== NULL) {
	  $o = new SAML2_XML_md_Organization();
	  $o->OrganizationName = $orgName;
	  
	  $o->OrganizationDisplayName = $spconfig->getLocalizedString('OrganizationDisplayName', NULL);
	  if ($o->OrganizationDisplayName === NULL) {
	    $o->OrganizationDisplayName = $orgName;
	  }
	  
	  $o->OrganizationURL = $spconfig->getLocalizedString('OrganizationURL', NULL);
	  if ($o->OrganizationURL === NULL) {
	    throw new SimpleSAML_Error_Exception('If OrganizationName is set, OrganizationURL must also be set.');
	  }
	  
	  $ed->Organization = $o;
	}
	
	$c = new SAML2_XML_md_ContactPerson();
	$c->contactType = 'technical';
	
	$email = $config->getString('technicalcontact_email', NULL);
	if ($email !== NULL) {
	  $c->EmailAddress = array($email);
	}
	
	$name = $config->getString('technicalcontact_name', NULL);
	if ($name === NULL) {
	  /* Nothing to do here... */
	} elseif (preg_match('@^(.*?)\s*,\s*(.*)$@D', $name, $matches)) {
	  $c->SurName = $matches[1];
	  $c->GivenName = $matches[2];
	} elseif (preg_match('@^(.*?)\s+(.*)$@D', $name, $matches)) {
	  $c->GivenName = $matches[1];
	  $c->SurName = $matches[2];
	} else {
	  $c->GivenName = $name;
	}
	$ed->ContactPerson[] = $c;
	
	$xml = $ed->toXML();
	SimpleSAML_Utilities::formatDOMElement($xml);
	$xml = $xml->ownerDocument->saveXML($xml);
	
	$metaArray20 = array(
			     'AssertionConsumerService' => SimpleSAML_Module::getModuleURL('saml/sp/saml2-acs.php/' . $sourceId),
			     'SingleLogoutService' => SimpleSAML_Module::getModuleURL('saml/sp/saml2-logout.php/' . $sourceId),
			     );
	if ($certData !== NULL) {
	  $metaArray20['certData'] = $certData;
	}

	// Output goes an html template if we're called from a web server, plain text output
	// otherwise
	if (! preg_match("/^Command-line/",$_SERVER['SERVER_SIGNATURE'])) {
	  $t = new SimpleSAML_XHTML_Template($config, 'metadata-silverstripe.php', 'admin');
	  
	  $t->data['header'] = 'saml20-sp';
	  $t->data['metadata'] = htmlspecialchars($xml);
	  $t->data['metadataflat'] = '$metadata[' . var_export($entityId, TRUE) . '] = ' . var_export($metaArray20, TRUE) . ';';
	  
	  $t->data['idpsend'] = array();
	  $t->data['sentok'] = FALSE;
	  $t->data['adminok'] = FALSE;
	  $t->data['adminlogin'] = NULL;
	  
	  $t->data['techemail'] = $config->getString('technicalcontact_email', NULL);
	  
	  $t->show();
	} else {
	  echo "This is the straight XML for Shib metadata\n";
	  echo $xml,"\n\n";
	  echo "This is the php code that can be included directly into SimpleSAMLPHP setups:\n\n";
	  echo '$metadata[' . var_export($entityId, TRUE) . '] = ' . var_export($metaArray20, TRUE) . ";\n\n";
	}
      } catch (Exception $ex) {
        echo "Error: ", $ex->getMessage(), "\n";
      }
    }

}