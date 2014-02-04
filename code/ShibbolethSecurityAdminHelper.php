<?php

/**
 *	ShibbolethSecurityAdminHelper
 *	
 *	Extends SilverStripe MemberAdmin for Shibboleth
 *	
 *	@package shibboleth
 **/

class ShibbolethSecurityAdminHelper extends LeftAndMainDecorator {

	public function init() {
		##$this->owner->init();
		Requirements::javascript('shibboleth/javascript/afterautocomplete.js');
	}

	/**
	 * Ajax autocompletion
	 * This takes into account that this module changes the unique ID from Email to UniqueIdentifier
	 */
	public function autocomplete() {
		$fieldName = $this->urlParams['ID'];
		$fieldVal = $_REQUEST[$fieldName];
		$result = '';

		// Make sure we only autocomplete on keys that actually exist, and that we don't autocomplete on password
		if(!singleton($this->stat('subitem_class'))->hasDatabaseField($fieldName)  || $fieldName == 'Password') return;

		$matches = DataObject::get($this->stat('subitem_class'),"\"$fieldName\" LIKE '" . Convert::raw2sql($fieldVal) . "%'");
		if($matches) {
			$result .= "<ul>";
			foreach($matches as $match) {
				// If the current user doesnt have permissions on the target user,
				// he's not allowed to add it to a group either: Don't include it in the suggestions.
				if(!$match->canView() || !$match->canEdit()) continue;

                $data = $match->FirstName;
                $data .= ",$match->Surname";
                $data .= ",$match->UniqueIdentifier";
                $result
                    .= "<li>"
                    . $match->$fieldName
                    . "<span class=\"informal\">($match->FirstName $match->Surname, $match->UniqueIdentifier)</span><span class=\"informal data\">$data</span></li>";
			}
			$result .= "</ul>";
			return $result;
		}
	}

}