<?php


if (!array_key_exists('PATH_INFO', $_SERVER)) {
	throw new SimpleSAML_Error_BadRequest('Missing authentication source id in metadata URL');
}

$config = SimpleSAML_Configuration::getInstance();
$sourceId = substr($_SERVER['PATH_INFO'], 1);
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

if (array_key_exists('output', $_REQUEST) && $_REQUEST['output'] == 'xhtml') {

	$t = new SimpleSAML_XHTML_Template($config, 'metadata.php', 'admin');

	$t->data['header'] = 'saml20-sp';
	$t->data['metadata'] = htmlspecialchars($xml);
	$t->data['metadataflat'] = '$metadata[' . var_export($entityId, TRUE) . '] = ' . var_export($metaArray20, TRUE) . ';';
	$t->data['metaurl'] = $source->getMetadataURL();

	$t->data['idpsend'] = array();
	$t->data['sentok'] = FALSE;
	$t->data['adminok'] = FALSE;
	$t->data['adminlogin'] = NULL;

	$t->data['techemail'] = $config->getString('technicalcontact_email', NULL);

	$t->show();
} else {
	header('Content-Type: application/samlmetadata+xml');
	echo($xml);
}

?>
