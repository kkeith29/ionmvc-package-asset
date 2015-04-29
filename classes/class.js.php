<?php

namespace ionmvc\packages\asset\classes;

class js {

	public static function to_object( $data ) {
		$obj = [];
		foreach( $data as $key => $val ) {
			$quote = true;
			if ( is_array( $val ) ) {
				if ( isset( $val['_array'] ) ) {
					unset( $val['_array'] );
					$val = self::to_array( $val );
				}
				else {
					$val = self::to_object( $val );
				}
				$quote = false;
			}
			elseif ( is_bool( $val ) ) {
				$val = ( $val ? 'true' : 'false' );
			}
			$obj[] = $key . ':' . ( !$quote ? $val : "'{$val}'" );
		}
		return '{' . implode( ',',$obj ) . '}';
	}

	public static function to_array( $data ) {
		$obj = [];
		foreach( $data as $key => $val ) {
			$is_obj = false;
			if ( is_array( $val ) ) {
				$val = self::to_array( $val );
				$is_obj = true;
			}
			elseif ( is_bool( $val ) ) {
				$val = ( $val ? 'true' : 'false' );
			}
			$obj[] = ( $is_obj ? $val : "'{$val}'" );
		}
		return '[' . implode( ',',$obj ) . ']';
	}

}

?>