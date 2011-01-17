<?php

/**
 *	ShibbolethSubject
 *	
 *	Extends SilverStripe Member for Shibboleth
 *	
 *	@package shibboleth
 **/

class ShibbolethSubject extends DataObjectDecorator {
	
	// Add the unique ID field
	function extraStatics() {
		return array(
			'db' => array(
				'UniqueIdentifier' => 'Varchar(256)',
			),
		);
	}

	// make sure the unique ID is set on every member
	function onBeforeWrite() {
		parent::onBeforeWrite();
		if(empty($this->owner->UniqueIdentifier)) $this->owner->UniqueIdentifier = uniqid('NERSC_', true);
	}
}