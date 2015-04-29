<?php

namespace ionmvc\packages\asset\classes;

use ionmvc\classes\config;
use ionmvc\classes\func;
use ionmvc\classes\path;

class css {

	public static function minify( $css ) {
		$preserve_urls = config::get('css.minify.preserve_urls');
		if ( $preserve_urls ) {
			$css = preg_replace_callback( '/url\s*\((.*)\)/siU',__CLASS__ . '::encode_url',$css );
		}
		$css = preg_replace( '/\/\*[\d\D]*?\*\/|\t+/','',$css );
		$css = str_replace( ["\n","\r","\t"],'',$css );
		$css = preg_replace( '/\s\s+/','',$css );
		$css = preg_replace( '/\s*({|}|\[|\]|=|~|\+|>|\||;|:|,)\s*/','$1',$css );
		if ( config::get('css.minify.remove_last_semicolon') ) {
			$css = str_replace( ';}','}',$css );
		}
		$css = trim( $css );
		if ( $preserve_urls ) {
			$css = preg_replace_callback( '/url\s*\((.*)\)/siU',__CLASS__ . '::decode_url',$css );
		}
		return $css;
	}

	public static function encode_url( $match ) {
		return 'url(' . base64_encode( trim( $match[1] ) ) . ')';
	}

	public static function decode_url( $match ) {
		return 'url(' . base64_decode( $match[1] ) . ')';
	}

	public static function to_array( $css,$options='' ) {
		$r = [];
		$css = self::minify( $css,$options );
		preg_match_all( '/(.+){(.+:.+);}/U',$css,$items );
		if ( count( $items[0] ) > 0 ) {
			$c = count( $items[0] );
			for( $i=0;$i<$c;$i++ ) {
				$keys = explode( ',',$items[1][$i] );
				$styles_tmp = explode( ';',$items[2][$i] );
				$styles = [];
				foreach( $styles_tmp as $style ) {
					$style_tmp = explode( ':',$style );
					$styles[$style_tmp[0]] = $style_tmp[1];
				}
				$r[] = [
					'keys'   => self::array_clean( $keys ),
					'styles' => self::array_clean( $styles )
				];
			}
		}
		return $r;
	}

	public static function to_string( $array ) {
		$r = '';
		foreach( $array as $item ) {
			$r .= implode( ',',$item['keys'] ) . '{';
			foreach( $item['styles'] as $key => $value ) {
				$r . "{$key}:{$value};";
			}
			$r .= '}';
		}
		return $r;
	}

	public static function array_clean( $array ) {
		$r = [];
		if ( func::array_is_assoc( $array ) ) {
			foreach( $array as $key => $value ) {
				$r[$key] = trim( $value );
			}
		}
		else {
			foreach( $array as $value ) {
				$value = trim( $value );
				if ( $value !== '' ) {
					$r[] = $value;
				}
			}
		}
		return $r;
	}

	public static function handle_urls( $data,$workdir ) {
		$old_workdir = getcwd();
		chdir( $workdir );
		$urls = [];
		$data = preg_replace_callback( '#url\(([^\)]+)\)#is',function( $match ) use ( &$urls ) {
			$i = count( $urls );
			$urls[$i] = trim( $match[1],'\'"' );
			return "url('{ionmvc_url:{$i}}')";
		},$data );
		//get paths based on groups
		$groups = path::get_group(['css','image','font']);
		$paths = [];
		foreach( $groups as $group ) {
			if ( isset( $paths[$group] ) ) {
				continue;
			}
			$path = path::get( $group );
			if ( $path === false ) {
				continue;
			}
			$paths[$group] = $path;
		}
		foreach( $urls as $i => &$url ) {
			if ( !func::is_url( $url ) ) {
				if ( strpos( $url,'/' ) === 0 ) {
					$url = uri::base() . ltrim( $url,'/' );
				}
				else {
					$_url = parse_url( $url );
					if ( $_url === false || !isset( $_url['path'] ) ) {
						$url = false;
					}
					else {
						$url = realpath( $_url['path'] );
						if ( $url !== false ) {
							foreach( $paths as $path ) {
								if ( strpos( $url,$path ) !== 0 ) {
									continue;
								}
								$url = ltrim( str_replace( $path,'',$url ),'/' );
								if ( isset( $_url['query'] ) ) {
									$url .= "?{$_url['query']}";
								}
								break;
							}
							$uri_config = [];
							$url = asset::uri( $url );
						}
					}
				}
			}
			$data = str_replace( "{ionmvc_url:{$i}}",( $url !== false ? $url : '/* not found */' ),$data );
		}
		chdir( $old_workdir );
		return $data;
	}

}

?>