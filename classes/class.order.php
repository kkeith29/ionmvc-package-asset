<?php

namespace ionmvc\packages\asset\classes;

use ionmvc\exceptions\app as app_exception;

class order {

	private $data   = [];
	private $groups = [];
	private $group  = [];

	private $idx = 0;

	public function __construct( $data ) {
		$this->data = $data;
	}

	private function type( $data ) {
		if ( isset( $data['func'] ) ) {
			return asset::type_function;
		}
		return ( $data['url'] ? asset::type_external : asset::type_internal );
	}

	private function next( $idx ) {
		$idx++;
		if ( !isset( $this->data[$idx] ) ) {
			return false;
		}
		return $this->data[$idx];
	}

	private function group_add( $data ) {
		if ( $this->group === false ) {
			throw new app_exception('No group set');
		}
		$this->group[] = $data;
	}

	private function group_clear( $type ) {
		$this->groups[] = [
			'type'   => $type,
			'assets' => $this->group
		];
		$this->group = [];
	}

	public function reorder() {
		foreach( $this->data as $idx => $data ) {
			$type = $this->type( $data );
			$this->group_add( $data );
			if ( ( $next = $this->next( $idx ) ) === false || $this->type( $next ) !== $type ) {
				$this->group_clear( $type );
			}
		}
		return $this->groups;
	}

}

?>