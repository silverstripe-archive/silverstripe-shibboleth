<?php
/* 
 * Configuration for the DiscoPower module.
 * 
 * $Id: $
 */

$config = array (

	// Which tab should be set as default. 0 is the first tab.
	'defaulttab' => 0,
	
	/*
	 * List a set of tags (Tabs) that should be listed in a specific order.
	 * All other available tabs will be listed after the ones specified below.
	 */
	'taborder' => array(SspConfigLoader::get_default_or_conf('incommon', 'useDiscoService')),
	/*
	 * the 'tab' parameter allows you to limit the tabs to a specific list. (excluding unlisted tags)
	 *
	 * 'tabs' => array('norway', 'finland'),
	 */
	 'tabs' => array(SspConfigLoader::get_default_or_conf('incommon', 'useDiscoService')),
	
	 /**
	  * If you want to change the scoring algorithm to a more google suggest like one
	  * (filters by start of words) uncomment this ... 
	  *
	  * 'score' => 'suggest', 
	  */

);

?>
