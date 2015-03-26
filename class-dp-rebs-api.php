<?php
/**
 * Created by PhpStorm.
 * User: Dan
 * Date: 26.03.2015
 * Time: 16:20
 */

class DP_REBS_API {
	var $url = 'http://demo.rebs-group.com/';
	var $data = array();
	var $current_data;

	function __construct( $url = '' ) {
		$this->url = $url ? trailingslashit( $url ) : $this->url ;
	}

	function set_url( $mode, $data = array() ) {
		switch ( $mode ) {
			case 'single' :
				$url_part = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
				$this->url .= 'api/public/property/';
				$this->url .= $url_part;
				break;
			case 'agent' :
				$url_part = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
				$this->url .= 'api/public/agent/';
				$this->url .= $url_part;
				break;
			case 'list_since' :
				$url_args = isset( $data ) ? array( 'date_modified_by_user__get' => $data ) : array();
				$this->url .= 'api/public/property/';
				$this->url = add_query_arg( $url_args, $this->url );
				break;
			default :
				$this->url = $mode;
				break;
		}

		$this->url = add_query_arg( 'format', 'json', $this->url	);

		return $this;
	}

	function store() {
		if ( $this->current_data['objects'] )
			$this->data = $this->data + $this->current_data['objects'];
		else
			$this->data[] = $this->current_data;

		return $this;
	}

	function walk() {
		$walk_url = isset( $this->current_data['meta']['next'] ) ? $this->current_data['meta']['next'] : '';

		while ( $walk_url ) {
			$this->set_url( $walk_url )->call()->store();
		}

		return $this;
	}

	function call() {
		$response = wp_remote_get( $this->url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( $response['response']['code'] == 200 ) {
			$this->current_data = json_decode( $response['body'], true );
			return $this;
		}

		return false;
	}

	function return_ids() {
		$ids = array();
		foreach ( (array) $this->data as $object ) {
			$ids[] = $object->id;
		}
		return $ids;
	}

}