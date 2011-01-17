<?php

require_once dirname(__FILE__) . '/MockSimpleSAML.php';

class MockAuthFactory { //implements TestOnly {

	public static function create() {
		return new MockSimpleSAML();
	}
}

?>
