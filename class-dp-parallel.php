<?php

class DP_Parallel {
	var $url = '';
	var $args = array();
	var $target;

	function __construct() {
		$this->receive_page = 'dp-parallel';
		$this->send_page = 'dp-parallel-send';
		$this->receive_action = 'dp-parallel';
		$this->send_action = 'dp-parallel';

		// TODO: replace dummy code

		//set_cron( 'minutly', 'my_hook' );
//		add_action( 'my_hook', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'init' ), 121 );

		add_action( 'init', array( $this, 'send' ), 205 );
		add_action( 'init', array( $this, 'receive' ), 210 );

	}

	function init() {
		// don't run on our POST
		if ( $this->is_current_action( $this->receive_action ) || $this->is_current_action( $this->send_action ) )
			return;



		$import_jobs = get_option( 'dp_later', array() );

		$message = sprintf( '%s, Time - %s, Objects - %s, Start', __METHOD__, timer_stop(), count( $import_jobs ) );
		$this->log( $message );

		if ( $import_jobs ) {

			$request = array();
			$request['blocking'] = false;
			$request['timeout'] = 1;
			$request['body'] = array( 'dp_action' => $this->send_action );
			if ( '0' == get_option('blog_public') )
				$request['headers']['Authorization'] = 'Basic ' . base64_encode( 'test:this' );

			wp_remote_post( home_url( $this->send_page ), $request );
		}

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), count( $import_jobs ) );
		$this->log( $message );
	}

	function send() {

		if ( $this->is_current_action( $this->send_action ) ) {
			// init stuff
			$count = 1;
			$limit = 20;

			$request = array();
			$request['blocking'] = false;
			$request['timeout'] = 2;

			$import_jobs = get_option( 'dp_later', array() );

			while ( $import_jobs && $count <= $limit ) {
				$request['body'] = array(
					'job' => array_shift( $import_jobs ),
					'dp_action' => $this->receive_action
				);

				wp_remote_post( home_url( $this->receive_page ), $request );

				$count++;
			}

			update_option( 'dp_later', $import_jobs );
			// all good, stop the wp execution
			die( 'ok' );
		}
	}

	function receive() {

		$call = isset( $_POST['job'] ) ? $_POST['job'] : array();

		if ( $this->is_current_action( $this->receive_action ) && $call ) {
			$message = sprintf( '%s, Time - %s, Objects - %s, Start', __METHOD__, timer_stop(), $call[0] );
			$this->log( $message );

			// instantiate a new object with params for __construct & call method
			$obj = new $call[0]( $call[2] );
			$obj->{$call[1]}();

			$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), $call[0] );
			$this->log( $message );
			// all good, stop the wp execution
			die( 'ok' );
		}
	}

	protected function is_current_action( $action ) {
		return isset( $_POST['dp_action'] ) ? ( $action == $_POST['dp_action'] ) : false;
	}

	/**
	 * @param string $message
	 */
	function log( $message ) {
		$upload_dir = wp_upload_dir();
		$date = date_i18n( 'Y-m-d H:i:s' ) . " | ";
		error_log( $date . $message . "\r\n", 3, trailingslashit( $upload_dir['basedir'] ) . __CLASS__ .  '.log' );
	}
}