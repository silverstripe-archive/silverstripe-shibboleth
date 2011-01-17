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
			'indexes' => array(
				'UniqueIdentifier' => '(UniqueIdentifier)',
			),
		);
	}

	// make sure the unique ID is set on every member
	function onBeforeWrite() {
		parent::onBeforeWrite();
		if(empty($this->owner->UniqueIdentifier)) $this->owner->UniqueIdentifier = $this->owner->Email ? $this->owner->Email : uniqid('NERSC_', true);
	}
	
	function augmentDatabase() {
		if(in_array("Member", DB::tableList())) DB::query("UPDATE \"Member\" SET \"Member\".\"UniqueIdentifier\" = \"Member\".\"Email\" WHERE \"Member\".\"UniqueIdentifier\" IS NULL");
	}
}