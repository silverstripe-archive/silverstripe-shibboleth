<?php

if (!isset($_REQUEST['id'])) {
	throw new SimpleSAML_Error_BadRequest('Missing required parameter: id');
}
$id = (string)$_REQUEST['id'];

if (isset($_REQUEST['type'])) {
	$type = (string)$_REQUEST['type'];
	if (!in_array($type, array('init', 'js', 'nojs', 'embed', 'async'), TRUE)) {
		throw new SimpleSAML_Error_BadRequest('Invalid value for type.');
	}
} else {
	$type = 'init';
}

if (isset($_REQUEST['timeout'])) {
	$timeout = (int)$_REQUEST['timeout'];
} else {
	$timeout = time() + 10;
}

if ($type !== 'embed' && $type !== 'async') {
	SimpleSAML_Logger::stats('slo-iframe ' . $type);
}

$state = SimpleSAML_Auth_State::loadState($id, 'core:Logout-IFrame');
$idp = SimpleSAML_IdP::getByState($state);

if ($type !== 'init') {
	/* Update association state. */

	$associations = $idp->getAssociations();

	foreach ($state['core:Logout-IFrame:Associations'] as $assocId => &$sp) {

		$spId = sha1($assocId);

		/* Move SPs from 'onhold' to 'inprogress'. */
		if ($sp['core:Logout-IFrame:State'] === 'onhold') {
			$sp['core:Logout-IFrame:State'] = 'inprogress';
		}

		/* Check for update by cookie. */
		$cookieId = 'logout-iframe-' . $spId;
		if (isset($_COOKIE[$cookieId])) {
			$cookie = $_COOKIE[$cookieId];
			if ($cookie == 'completed' || $cookie == 'failed') {
				$sp['core:Logout-IFrame:State'] = $cookie;
			}
		}

		/* Check for update through request. */
		if (isset($_REQUEST[$spId])) {
			$s = $_REQUEST[$spId];
			if ($s == 'completed' || $s == 'failed') {
				$sp['core:Logout-IFrame:State'] = $s;
			}
		}

		/* In case we are refreshing a page. */
		if (!isset($associations[$assocId])) {
			$sp['core:Logout-IFrame:State'] = 'completed';
		}

		/* Update the IdP. */
		if ($sp['core:Logout-IFrame:State'] === 'completed') {
			$idp->terminateAssociation($assocId);
		}
	}
}

if ($type === 'js' || $type === 'nojs') {
	foreach ($state['core:Logout-IFrame:Associations'] as $assocId => &$sp) {

		if ($sp['core:Logout-IFrame:State'] !== 'inprogress') {
			/* This SP isn't logging out. */
			continue;
		}

		try {
			$assocIdP = SimpleSAML_IdP::getByState($sp);
			$url = call_user_func(array($sp['Handler'], 'getLogoutURL'), $assocIdP, $sp, NULL);
			$sp['core:Logout-IFrame:URL'] = $url;
		} catch (Exception $e) {
			$sp['core:Logout-IFrame:State'] = 'failed';
		}
	}
}

$id = SimpleSAML_Auth_State::saveState($state, 'core:Logout-IFrame');

$globalConfig = SimpleSAML_Configuration::getInstance();

if ($type === 'nojs') {
	$t = new SimpleSAML_XHTML_Template($globalConfig, 'core:logout-iframe-wrapper.php');
	$t->data['id'] = $id;
	$t->data['SPs'] = $state['core:Logout-IFrame:Associations'];
	$t->data['timeout'] = $timeout;
	$t->show();
	exit(0);

} elseif ($type == 'async') {
	header('Content-Type: application/json');
	$res = array();
	foreach ($state['core:Logout-IFrame:Associations'] as $assocId => $sp) {
		if ($sp['core:Logout-IFrame:State'] !== 'completed') {
			continue;
		}
		$res[sha1($assocId)] = 'completed';
	}
	echo(json_encode($res));
	exit(0);
}

$t = new SimpleSAML_XHTML_Template($globalConfig, 'core:logout-iframe.php');
$t->data['id'] = $id;
$t->data['type'] = $type;
$t->data['from'] = $state['core:Logout-IFrame:From'];
$t->data['SPs'] = $state['core:Logout-IFrame:Associations'];
$t->data['timeout'] = $timeout;
$t->show();
exit(0);
