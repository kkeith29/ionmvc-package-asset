<?php

namespace ionmvc\packages;

use ionmvc\classes\app;
use ionmvc\classes\hook;
use ionmvc\classes\path;
use ionmvc\classes\response;

class asset extends \ionmvc\classes\package {

	const version = '1.0.0';

	public static function setup() {
		app::hook()->attach('response.create',function() {
			if ( !response::hook()->exists('view') ) {
				return;
			}
			response::hook()->add('asset',[
				'position' => hook::position_after,
				'hook'     => 'view'
			]);
		});
		path::add([
			'asset'                    => '{public}/assets',
			'asset-css'                => '{asset}/css',
			'asset-font'               => '{asset}/fonts',
			'asset-js'                 => '{asset}/javascript',
			'asset-image'              => '{asset}/images',
			'asset-third-party'        => '{asset}/third-party',
			'storage-cache-css'        => '{storage-cache}/css',
			'storage-cache-image'      => '{storage-cache}/images',
			'storage-cache-javascript' => '{storage-cache}/javascript',
		]);
		path::group( 'css',['asset-css','asset-third-party'],path::overwrite );
		path::group( 'font',['asset-font','asset-third-party'],path::overwrite );
		path::group( 'image',['asset-image','asset-third-party'],path::overwrite );
		path::group( 'js',['asset-js','asset-third-party'],path::overwrite );
	}

	public static function package_info() {
		return [
			'author'      => 'Kyle Keith',
			'version'     => self::version,
			'description' => 'Asset handler'
		];
	}

}

?>