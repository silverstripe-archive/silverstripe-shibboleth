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

    /*
     * updateSummaryFields
     * Ensure that the UniqueIdentifier field appears on the MemberTableField's   
     * Add line when editing security groups. Failure to do so causes addtogroup()
     * to add a new member record rather than adding the existing member to the   
     * group being edited. QI bug 3898. This is necessary, but not sufficient.    
     * Core patches are also needed to MemberTableField.js and SecurityAdmin::autocomplete().
     * 
     */
    function updateSummaryFields(&$fields) {
        $fields['UniqueIdentifier'] = 'Unique ID (shibboleth)';
    }

	// make sure the unique ID is set on every member
	function onBeforeWrite() {
		parent::onBeforeWrite();
		if(empty($this->owner->UniqueIdentifier)) $this->owner->UniqueIdentifier = $this->owner->Email ? $this->owner->Email : uniqid('NERSC_', true);
	}
	
	function augmentDatabase() {
		
		// don't update if Member table is not yet ready
		if(!in_array("Member", DB::tableList())) return;

		// don't update if identifier has not yet been created
		$fields = DB::getConn()->fieldList('Member');
		if(!isset($fields['UniqueIdentifier'])) return;
		
		// don't update in test context
		if(SapphireTest::using_temp_db()) return;
		
		// do update
		DB::query("UPDATE \"Member\" SET \"Member\".\"UniqueIdentifier\" = \"Member\".\"Email\" WHERE \"Member\".\"UniqueIdentifier\" IS NULL");
	}
}