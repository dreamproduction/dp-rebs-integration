<?php

class DP_Parallel {
	var $url = '';
	var $args = array();
	var $target;

	function __construct() {

		// TODO: replace dummy code
		set_cron( 'minutly', 'my_hook' );
		add_action( 'my_hook', array( $this, 'init' ) );
	}

	/**
	 * @param $what string DP_Save_Images
	 */
	function init( ) {
		// init stuff
		$count = 1;
		$limit = 50;


		$request = array();
		$request['blocking'] = false;


		$import_jobs = get_option( 'dp_later', array() );

		while ( $import_jobs && $count <= $limit ) {
			$request['body'] = array(
				'job' => array_shift( $import_jobs )
			);

			// TODO: generate real url
			wp_remote_post( $this->url, $request );

			$count++;
		}

		update_option( 'dp_later', $import_jobs );
	}

	function intercept_post() {
		// TODO: add real validation
		if ( $our_post && $call ) {

			if ( class_exists( $call[0] ) ) {
				// instantiate a new object with params for __construct & call method
				$obj = new $call[0]( $call[2] );
				$obj->{$call[1]}();
			}

		}
	}
}