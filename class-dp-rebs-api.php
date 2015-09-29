<?php
/**
 * Created by PhpStorm.
 * User: Dan
 * Date: 26.03.2015
 * Time: 16:20
 */

class DP_REBS_API {
	private $base = 'http://demo.rebs-group.com/';
	private $url = '';
	private $data = array();
	private $current_data;

	function __construct( $url = '' ) {
		$this->base = $url ? trailingslashit( $url ) : $this->base ;
	}

	function set_url( $mode, $data = '' ) {
		switch ( $mode ) {
			case 'single' :
				$this->set_single_url( $data );
				break;
			case 'agent' :
				$this->set_agent_url( $data );
				break;
			case 'list_since' :
				$this->set_since_url( $data );
				break;
			case 'schema' :
				$this->set_schema_url( $data );
				break;
			default :
				$this->url = $mode;
				break;
		}

		$this->url = add_query_arg( 'format', 'json', $this->url	);

		return $this;
	}

	protected function set_single_url( $data = 0 ) {
		$url_part = absint( $data );
		$this->url = $this->base . 'api/public/property/';
		$this->url .= $url_part;
	}

	protected function set_agent_url( $data = 0 ) {
		$url_part = absint( $data );
		$this->url = $this->base . 'api/public/agent/';
		$this->url .= $url_part;
	}

	protected function set_since_url( $data = '' ) {
		$url_args = ! empty( $data ) ? array( 'date_modified_by_user__gte' => $data ) : array();
		$this->url = $this->base . 'api/public/property/';
		$this->url = add_query_arg( $url_args, $this->url );
	}

	protected function set_schema_url( $data = '' ) {
		$this->url = $this->base . 'api/public/';
		$this->url .= $data;
		$this->url .= '/schema/';
	}

	function store() {
		if ( isset( $this->current_data['objects'] ) && $this->current_data['objects'] ) {
			// regular call
			$this->data = $this->data + $this->current_data['objects'];
		} elseif ( isset( $this->current_data['fields'] ) && $this->current_data['fields'] ) {
			// schema call
			$this->data = $this->data + $this->current_data['fields'];
		} else {
			// single call
			$this->data[] = $this->current_data;
		}

		return $this;
	}

	function walk() {
		$walk_url = isset( $this->current_data['meta']['next'] ) ? $this->base . $this->current_data['meta']['next'] : '';

		while ( $walk_url ) {
			$this->set_url( $walk_url )->call()->store();
		}

		return $this;
	}

	function call() {
		$this->current_data = array();

		$response = wp_remote_get( $this->url );

		if ( is_wp_error( $response ) ) {
			wp_die( $response->get_error_message() );
		}

		if ( $response['response']['code'] == 200 ) {
			$this->current_data = json_decode( $response['body'], true );
		} else {
			// false for 404 and anything else
			$this->current_data = false;
		}

		return $this;
	}

	function return_data() {
		return $this->data;
	}
}
