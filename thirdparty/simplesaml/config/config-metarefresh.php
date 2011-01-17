<?php

$config = array(
	'sets' => array(
		'incommon' => array(
			'cron'      => array('hourly'),
			'sources'   => array(
				array(
					// For DEBUGGING, no signature validation; local copy.
					'src' => 'http://wayf.incommonfederation.org/InCommon/InCommon-metadata.xml',
					// TODO: Confirm the following for live deploy.  Currently failing to validate?
					//'src' => 'http://wayf.incommonfederation.org/InCommon/InCommon-metadata.xml',
					//'validateFingerprint' => '74278f967cf1bfcaaa1b41afb6336448a2150eb4',
					'template' => array(
						'tags' => array('incommon'),
						/** BELOW may be needed if attributes are coming in as alphanumeric codes,
		 						 * instead of readable names.
		 						 * see here: http://simplesamlphp.org/docs/1.6/simplesamlphp-authproc
						 */
						//'authproc' => array(
						//	10 => array('class' => 'core:AttributeMap', 'oid3name'),
						//	),
					),
				),
			),
			'expireAfter'       => 60*60*24*4, // Maximum 4 days cache time.
			'outputDir'     => 'metadata/metadata-incommon/',
			// 'outputFormat' => 'serialize',
			'outputFormat' => 'flatfile',
		),
		'scifed' => array(
			'cron'      => array('hourly'),
			'sources'   => array(
				array(
					'src' => 'https://confluence.scifed.org/download/attachments/10715161/scifed.xml',
					'template' => array(
						'tags' => array('scifed'),
					),
				),
			),
			'expireAfter'       => 60*60*24*4, // Maximum 4 days cache time.
			'outputDir'     => 'metadata/metadata-scifed/',
			'outputFormat' => 'flatfile',
		),
	)
);