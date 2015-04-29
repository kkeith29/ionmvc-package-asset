<?php

namespace ionmvc\packages\asset\classes;

use ionmvc\classes\app;
use ionmvc\classes\cache;
use ionmvc\classes\config;
use ionmvc\classes\hook;
use ionmvc\classes\http;
use ionmvc\classes\file;
use ionmvc\classes\output;
use ionmvc\classes\package;
use ionmvc\classes\path;
use ionmvc\classes\time;
use ionmvc\classes\uri;
use ionmvc\exceptions\app as app_exception;

class response extends \ionmvc\classes\response {

	public function __construct() {
		parent::__construct();
	}

	protected function setup() {
		$this->registry->add( \ionmvc\CLASS_TYPE_DEFAULT,'hook',new hook(['http','output']) );
	}

	private function handle_css( $production ) {
		switch( uri::segment('action') ) {
			case 'output-single':
				if ( !uri::is_set('file') ) {
					throw new app_exception('No file segment found in url');
				}
				$files = [ uri::base64_decode( uri::segment('file') ) ];
				break;
			case 'output-multi':
				if ( !uri::is_set('files') ) {
					throw new app_exception('No files segment found in url');
				}
				$files = $mtimes = array_unique( array_filter( explode( '<|>',uri::base64_decode( uri::segment('files') ) ) ) );
				break;
			default:
				throw new app_exception('Invalid action');
				break;
		}
		$min_time = 0;
		foreach( $files as &$file ) {
			if ( ( $file = path::get( $file,'css' ) ) === false ) {
				throw new app_exception('CSS file not found');
			}
			$mtime = filemtime( $file );
			if ( $mtime > $min_time ) {
				$min_time = $mtime;
			}
		}
		if ( $production && config::get('asset.css.caching.enabled') ) {
			$cache = new cache('storage-cache-css');
			$cache->id( implode( '|',$files ) )->serialize(false)->min_time( $min_time );
			$data = $cache->fetch( config::get('asset.css.caching.days'),cache::day,function() use( $files ) {
				$data = '';
				foreach( $files as $path ) {
					$data .= css::handle_urls( file::get_data( $path ),dirname( $path ) );
				}
				if ( config::get('asset.css.minify.enabled') === true ) {
					$data = css::minify( $data );
				}
				return $data;
			} );
		}
		else {
			$data = '';
			foreach( $files as $path ) {
				$data .= css::handle_urls( file::get_data( $path ),dirname( $path ) );
			}
		}
		http::content_type('text/css',config::get('asset.css.charset'));
		if ( $production ) {
			http::cache( time::now(),time::future( config::get('asset.css.caching.days'),time::day ) );
		}
		output::set_data( $data );
	}

	private function handle_font( $production ) {
		$file = uri::base64_decode( uri::get('file') );
		if ( ( $path = path::get( $file,'font' ) ) === false ) {
			throw new app_exception('Font not found');
		}
		$extn = file::get_extension( $file );
		if ( ( $mime_type = http::mime_type( $extn ) ) === false ) {
			throw new app_exception('Unable to find proper mime type');
		}
		output::compression(false);
		http::content_type( $mime_type );
		if ( $production ) {
			http::cache( time::now(),time::future( config::get('asset.font.caching.days'),time::day ) );
		}
		output::set_data( $path,output::file );
	}

	private function handle_js( $production ) {
		switch( uri::segment('action') ) {
			case 'output-single':
				if ( !uri::is_set('file') ) {
					throw new app_exception('No file segment found in url');
				}
				$files = [ uri::base64_decode( uri::segment('file') ) ];
			break;
			case 'output-multi':
				if ( !uri::is_set('files') ) {
					throw new app_exception('No files segment found in url');
				}
				$files = $mtimes = array_unique( array_filter( explode( '<|>',uri::base64_decode( uri::segment('files') ) ) ) );
			break;
			default:
				throw new app_exception('Invalid action');
			break;
		}
		$min_time = 0;
		foreach( $files as &$file ) {
			if ( ( $file = path::get( $file,'js' ) ) === false ) {
				throw new app_exception('JS file not found');
			}
			$mtime = filemtime( $file );
			if ( $mtime > $min_time ) {
				$min_time = $mtime;
			}
		}
		if ( $production && config::get('asset.js.caching.enabled') ) {
			$cache = new cache('storage-cache-javascript');
			$cache->id( implode( '|',$files ) )->serialize(false)->min_time( $min_time );
			$data = $cache->fetch( config::get('asset.js.caching.days'),cache::day,function() use( $files ) {
				$data = '';
				foreach( $files as $path ) {
					$data .= file::get_data( $path );
				}
				return $data;
			} );
		}
		else {
			$data = '';
			foreach( $files as $path ) {
				$data .= file::get_data( $path );
			}
		}
		http::content_type('text/javascript',config::get('asset.js.charset'));
		if ( $production ) {
			http::cache( time::now(),time::future( config::get('asset.js.caching.days'),time::day ) );
		}
		output::set_data( $data );
	}

	private function handle_image( $production ) {
		if ( !package::loaded('image') ) {
			throw new app_exception('Package \'image\' is required to manipulate images');
		}
		$file = uri::base64_decode( uri::get('file') );
		if ( ( $path = path::get( $file,'image' ) ) === false ) {
			throw new app_exception('Image not found');
		}
		$extn = file::get_extension( $file );
		if ( ( $mime_type = http::mime_type( $extn ) ) === false ) {
			throw new app_exception('Unable to find proper mime type');
		}
		if ( uri::is_set('resize') || uri::is_set('crop') ) {
			if ( $production && config::get('asset.image.caching.enabled') ) {
				$cache = new cache('storage-cache-image');
				$id = $group = $file;
				foreach( ['resize','crop'] as $action ) {
					if ( uri::is_set( $action ) ) {
						$id .= ':' . uri::segment( $action );
					}
				}
				$cache->id( $id )->group( $group )->serialize(false)->min_time( filemtime( $path ) );
				if ( !$cache->expired( config::get('asset.image.caching.days'),cache::day ) ) {
					$output_path = $cache->get_path();
				}
			}
			if ( !isset( $output_path ) ) {
				$image = library::image();
				$image->load_file( $path );
				$i = 0;
				if ( uri::is_set('resize') ) {
					$parts = explode( '-',uri::segment('resize') );
					if ( count( $parts ) == 4 ) {
						list( $width,$height,$prop,$box ) = $parts;
						$image->resize( (int) $width,(int) $height,( $prop === 'true' ? true : false ),( $box === 'true' ? true : false ) );
						$i++;
					}
				}
				if ( uri::is_set('crop') ) {
					$parts = explode( '-',uri::segment('crop') );
					if ( count( $parts ) == 4 ) {
						list( $from_x,$from_y,$to_x,$to_y ) = $parts;
						$image->crop( (int) $from_x,(int) $from_y,(int) $to_x,(int) $to_y );
						$i++;
					}
				}
				if ( $i > 0 ) {
					if ( isset( $cache ) ) {
						$image->save_image( ( $output_path = $cache->get_path() ),file::get_extension( $file ) );
					}
					else {
						$output_data = $image->data();
					}
				}
			}
		}
		else {
			$output_path = $path;
		}
		output::compression(false);
		http::content_type( $mime_type );
		if ( $production ) {
			http::cache( time::now(),time::future( config::get('asset.image.caching.days'),time::day ) );
		}
		if ( isset( $output_path ) ) {
			output::set_data( $output_path,output::file );
		}
		elseif ( isset( $output_data ) ) {
			output::set_data( $output_data );
		}
		else {
			throw new app_exception('An error has occurred while getting image data');
		}
	}

	public function handle() {
		$production = app::env( \ionmvc\ENV_PRODUCTION );
		try {
			if ( !uri::validate_csm() ) {
				throw new app_exception('Checksum failed');
			}
			switch( uri::segment('type') ) {
				case 'css':
					$this->handle_css( $production );
					break;
				case 'font':
					$this->handle_font( $production );
					break;
				case 'js':
					$this->handle_js( $production );
					break;
				case 'image':
					$this->handle_image( $production );
					break;
				default:
					throw new app_exception('Invalid type');
					break;
			}
		}
		catch( app_exception $e ) { //maybe use asset exception and log error and output status code. have app exceptions always pass through
			if ( !$production ) {
				throw $e;
			}
			http::status_code(404,'Not Found');
		}
		parent::handle();
	}

}

?>