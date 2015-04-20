<?php

class DP_Parallel {
	var $url = '';
	var $args = array();
	var $target;

	function __construct() {
		$this->receive_page = 'dp-parallel-page-receive';
		$this->send_page = 'dp-parallel-page-send';
		$this->receive_action = 'dp-parallel-action-receive';
		$this->send_action = 'dp-parallel-action-send';
		$this->queue = get_option( 'dp_later', array() );

		// TODO: replace dummy code

		//set_cron( 'minutly', 'my_hook' );
//		add_action( 'my_hook', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'init' ), 121 );

		add_action( 'init', array( $this, 'send' ), 205 );
		add_action( 'init', array( $this, 'receive' ), 204 );

	}

	function init() {
		$this->log( 'do nothing' );
		return;

		// check queue on every init
		if ( ! $this->queue ) {
			$message = sprintf( '%s, Time - %s, Objects - %s', __METHOD__, timer_stop(), 'Nothing in queue' );
			$this->log( $message );
			return;
		}

		// don't run on our POST
		if ( $this->is_current_action( $this->receive_action ) || $this->is_current_action( $this->send_action ) ) {
			$this->log( 'Should skip on ' . $_POST['dp_action']  );
			return;
		}

		$request = array();
		$request['blocking'] = false;
		$request['timeout'] = 1;
		$request['body'] = array( 'dp_action' => $this->send_action );

		wp_remote_post( home_url( $this->send_page ), $request );

		$message = sprintf( '%s, Time - %s, Objects - %s', __METHOD__, timer_stop(), 'Run request' );
		$this->log( $message );
	}

	function send() {

		if ( $this->is_current_action( $this->send_action ) ) {
			// init stuff
			$count = 1;
			$limit = 20;

			$request = array();
			$request['blocking'] = true;
			$request['timeout'] = 1;

			$message = sprintf( '%s, Time - %s, Objects - %s, Start', __METHOD__, timer_stop(), count( $this->queue ) );
			$this->log( $message );

			while ( $this->queue && $count <= $limit ) {
				$request['body'] = array(
					'job' => array_shift( $this->queue ),
					'dp_action' => $this->receive_action
				);

				wp_remote_post( home_url( $this->receive_page ), $request );

				$count++;
			}

			$message = sprintf( '%s, Time - %s, Objects - %s, Start', __METHOD__, timer_stop(), count( $this->queue ) );
			$this->log( $message );

			update_option( 'dp_later', $this->queue );
			// all good, stop the wp execution
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