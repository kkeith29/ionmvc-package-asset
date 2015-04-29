<?php

use ionmvc\classes\router;
use ionmvc\packages\asset\classes\response;

$config = [
	'asset' => [
		'jquery' => [
			'path'    => 'jquery.js',
			'ui_path' => 'jquery-ui'
		],
		'css' => [
			'charset' => 'UTF-8',
			'caching' => [
				'enabled' => true,
				'days'    => 5
			],
			'minify' => [
				'enabled'               => true,
				'remove_last_semicolon' => true,
				'preserve_urls'         => false
			]
		],
		'js' => [
			'charset' => 'UTF-8',
			'caching' => [
				'enabled' => true,
				'days'    => 5
			]
		],
		'font' => [
			'caching' => [
				'enabled' => true,
				'days'    => 5
			]
		],
		'image' => [
			'caching' => [
				'enabled' => true,
				'days'    => 5
			]
		]
	]
];

router::to_closure('app/asset',function() {
	return new response;
});

?>