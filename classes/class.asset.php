<?php

namespace ionmvc\packages\asset\classes;

use ionmvc\classes\app;
use ionmvc\classes\cache;
use ionmvc\classes\config;
use ionmvc\classes\file;
use ionmvc\classes\func;
use ionmvc\classes\html;
use ionmvc\classes\http;
use ionmvc\classes\package;
use ionmvc\classes\path;
use ionmvc\classes\output;
use ionmvc\classes\response;
use ionmvc\classes\time;
use ionmvc\classes\uri;
use ionmvc\exceptions\app as app_exception;

class asset {

	const type_internal = 1;
	const type_external = 2;
	const type_function = 3;

	private static $base_uri = null;
	private static $types = [
		'css' => [
			'extns' => ['css'],
			'multi' => true
        ],
		'js' => [
			'extns' => ['js'],
			'multi' => true
		],
		'image' => [
			'extns' => ['png','jpg','jpeg','gif','tiff','svg'],
			'multi' => false
		],
		'font' => [
			'extns' => ['eot','ttf','woff','woff2','svg','otf'],
			'multi' => false
		]
	];

	private $registered_groups = [];
	private $groups = [];

	private $group = false;
	private $assets = [
		'css' => [],
		'js'  => []
	];
	private $order = [
		'css' => [],
		'js'  => []
	];

	public static function __callStatic( $method,$args ) {
		$class = response::asset();
		$method = "_{$method}";
		if ( !method_exists( $class,$method ) ) {
			throw new app_exception( "Method '%s' not found",$method );
		}
		return call_user_func_array( [ $class,$method ],$args );
	}

	public function __construct() {
		response::hook()->attach('asset',function() {
			response::asset()->handle();
		});
	}

	public function _add( $data,$priority=5,$config=[] ) {
		$info = [];
		if ( is_string( $data ) ) {
			$info['url']  = func::is_url( $data );
			$info['extn'] = ( isset( $config['type'] ) ? $config['type'] : file::get_extension( $data ) );
			switch( $info['extn'] ) {
				case 'css':
					if ( $info['url'] === false && !path::test( path::is_file,$data,'css' ) ) {
						throw new app_exception( "CSS file '%s' not found",$data );
					}
				break;
				case 'js':
					if ( $info['url'] === false && !path::test( path::is_file,$data,'js' ) ) {
						throw new app_exception( "JS file '%s' not found",$data );
					}
				break;
			}
			$info['file'] = $data;
			$key = md5( $data );
		}
		elseif ( $data instanceof \Closure ) {
			if ( !isset( $config['type'] ) ) {
				throw new app_exception('Type is required when using a closure');
			}
			if ( !isset( $this->assets[$config['type']] ) ) {
				throw new app_exception('Type is not valid');
			}
			$info['extn'] = $config['type'];
			$info['func'] = $data;
			$key = count( $this->assets[$info['extn']] );
		}
		else {
			throw new app_exception('Invalid data sent to function');
		}
		$info['priority']  = $priority;
		$info['group']     = $this->group;
		$info['allow_php'] = ( isset( $config['allow_php'] ) ? $config['allow_php'] : false );
		if ( $this->group === false ) {
			$this->assets[$info['extn']][$key] = $info;
			$this->order[$info['extn']][] =& $this->assets[$info['extn']][$key];
		}
		elseif ( !isset( $this->registered_groups[$this->group] ) || !in_array( $key,$this->registered_groups[$this->group] ) ) {
			$this->registered_groups[$this->group][$key] = [
				'type'  => $info['extn'],
				'asset' => $info
			];
		}
		if ( isset( $config['return_info'] ) && $config['return_info'] === true ) {
			return $info;
		}
	}

	public function _register( $name,\Closure $function ) {
		$this->group = $name;
		$function();
		$this->group = false;
	}

	public function _group( $name ) {
		if ( !isset( $this->registered_groups[$name] ) ) {
			throw new app_exception( "Unable to find group '%s'",$name );
		}
		if ( isset( $this->groups[$name] ) ) {
			return;
		}
		if ( $this->group === false ) {
			$this->groups[$name] = true;
			foreach( $this->registered_groups[$name] as $data ) {
				switch( $data['type'] ) {
					case 'css':
					case 'js':
						$this->order[$data['asset']['extn']][] =& $data['asset'];
					break;
					case 'group':
						$this->_group( $data['name'] );
					break;
				}
			}
			return;
		}
		$this->registered_groups[$this->group][] = [
			'type' => 'group',
			'name' => $name
		];
	}

	public function handle() {
		$types = [];
		foreach( $this->order as $extn => $order ) {
			foreach( $order as $asset ) {
				if ( !isset( $types[$extn] ) ) {
					$types[$extn] = [];
				}
				$types[$extn][$asset['priority']][] = $asset;
			}
			if ( isset( $types[$extn] ) ) {
				krsort( $types[$extn] );
			}
		}
		$assets = [];
		foreach( $types as $type => $_assets ) {
			if ( !isset( $assets[$type] ) ) {
				$assets[$type] = [];
			}
			foreach( $_assets as $priority => $__assets ) {
				foreach( $__assets as $asset ) {
					$assets[$type][] = $asset;
				}
			}
		}
		$production = app::env( \ionmvc\ENV_PRODUCTION );
		foreach( $assets as $type => $_assets ) {
			$function = "{$type}_external";
			$order = new order( $_assets );
			$groups = $order->reorder();
			foreach( $groups as $group ) {
				switch( $group['type'] ) {
					case self::type_external:
						foreach( $group['assets'] as $asset ) {
							html::$function( $asset['file'] );
						}
					break;
					case self::type_internal:
						if ( $production ) {
							$group['assets'] = array_map( function( $data ) {
								return $data['file'];
							},$group['assets'] );
							//html::$function( uri::create("app/asset/type:{$type}/action:output-multi/files:" . uri::base64_encode( implode( '<|>',$group['assets'] ) ),"extn[{$type}]|csm[yes]") );
							html::$function( self::uri( $group['assets'] ) );
							break;
						}
						foreach( $group['assets'] as $asset ) {
							html::$function( self::uri( $asset['file'] ) );
						}
					break;
					case self::type_function:
						foreach( $group['assets'] as $asset ) {
							call_user_func( $asset['func'] );
						}
					break;
				}
			}
		}
	}

	public static function get_type( $extn ) {
		foreach( self::$types as $type => $info ) {
			if ( !in_array( $extn,$info['extns'] ) ) {
				continue;
			}
			return $type;
		}
		return false;
	}

	public static function get_file_info( $file ) {
		$query_string = false;
    	if ( ( $pos = strpos( $file,'?' ) ) !== false ) {
            $query_string = substr( $file,( $pos + 1 ) );
            $file = substr( $file,0,$pos );
    	}
		$query_string = ( $query_string === false ? [] : http::parse_query_string( $query_string ) );
		$extn = file::get_extension( $file );
		$type = ( isset( $query_string['type'] ) && isset( self::$types[$query_string['type']] ) ? $query_string['type'] : self::get_type( $extn ) );
		if ( $type === false ) {
			return false;
		}
		$info = path::get( $file,$type,[
			'return' => path::full_info
		] );
		if ( $info === false ) {
			return false;
		}
		return [
			'name'         => $file,
			'type'         => $type,
			'extn'         => $extn,
			'query_string' => $query_string,
			'info'         => $info
		];
	}

	public static function uri( $file,$config=[] ) {
		if ( !isset( $config['named_segments'] ) ) {
			$config['named_segments'] = [];
		}
		if ( is_array( $file ) ) {
			$type = null;
			$files = [];
			foreach( $file as $_file ) {
				if ( ( $data = self::get_file_info( $_file ) ) === false ) {
					throw new app_exception( 'Unable to find file: %s',$_file );
				}
				if ( is_null( $type ) ) {
					if ( !self::$types[$data['type']]['multi'] ) {
						throw new app_exception( 'Type \'%s\' is not allowed to be used in a bulk fashion',$data['type'] );
					}
					$type = $data['type'];
				}
				elseif ( $type !== $data['type'] ) {
					throw new app_exception( 'Only a single file type can be passed to this function in bulk' );
				}
				$files[] = $data;
			}
			$files = array_map( function( $file ) {
				return $file['name'];
			},$files );
			$config['named_segments']['type'] = $type;
			$config['named_segments']['action'] = 'output-multi';
			$config['named_segments']['files'] = uri::base64_encode( implode( '<|>',$files ) );
			$config['extn'] = self::$types[$type]['extns'][0];
		}
		else {
			if ( ( $data = self::get_file_info( $file ) ) === false ) {
				throw new app_exception( 'Unable to find file: %s',$file );
			}
			$public_path = path::get('public');
			if ( ( !isset( $config['allow_public'] ) || $config['allow_public'] ) && strpos( $data['info']['full_path'],$public_path ) === 0 ) {
				return uri::base() . ltrim( str_replace( $public_path,'',$data['info']['full_path'] ) );
			}
			if ( isset( $config['allow_public'] ) ) {
				unset( $config['allow_public'] );
			}
			$config['named_segments']['type'] = $data['type'];
			if ( self::$types[$data['type']]['multi'] ) {
				$config['named_segments']['action'] = 'output-single';
			}
			$config['named_segments']['file'] = uri::base64_encode( $data['name'] );
			$config['extn'] = $data['extn'];
		}
		$config['csm'] = true;
		return uri::create( 'app/asset',$config );
	}

	public static function image( $file,$extra=[],$config=[] ) {
		$e = 0;
		if ( isset( $extra['resize'] ) ) {
			if ( !isset( $extra['resize']['width'] ) && !isset( $extra['resize']['height'] ) ) {
				throw new app_exception('Width and/or Height required, neither given');
			}
			$defaults = [
				'width'  => 'auto',
				'height' => 'auto',
				'prop'   => true,
				'box'    => false
			];
			$extra['resize'] = array_merge( $defaults,$extra['resize'] );
			$config['named_segments']['resize'] = "{$extra['resize']['width']}-{$extra['resize']['height']}-" . ( $extra['resize']['prop'] ? 'true' : 'false' ) . '-' . ( $extra['resize']['box'] ? 'true' : 'false' );	
			$e++;
		}
		if ( isset( $extra['crop'] ) ) {
			if ( count( $extra['crop'] ) !== 4 ) {
				throw new app_exception('Not enough info provided for cropping');
			}
			$config['named_segments']['crop'] = "{$extra['crop'][0]}-{$extra['crop'][1]}-{$extra['crop'][2]}-{$extra['crop'][3]}";
			$e++;
		}
		if ( $e > 0 ) {
			$config['allow_public'] = false;
		}
		return self::uri( $file,$config );
	}

	public static function base_uri() {
		if ( is_null( self::$base_uri ) ) {
			self::$base_uri = uri::base() . trim( str_replace( path::get('public'),'',path::get('asset') ),'/' ) . '/';
		}
		return self::$base_uri;
	}

	public static function clear_cache() {
		$cache = new cache('storage-cache-css');
		$cache->clear_all();
		$cache = new cache('storage-cache-javascript');
		$cache->clear_all();
		$cache = new cache('storage-cache-image');
		$cache->clear_all();
	}

}

?>