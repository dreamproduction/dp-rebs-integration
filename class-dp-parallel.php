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

		define( 'DP_PARALLEL_SEND', false );

		add_action( 'init', array( $this, 'send' ), 205 );
		add_action( 'init', array( $this, 'receive' ), 204 );

		add_filter( 'cron_schedules', array( $this, 'add_schedule_interval' ) );

		if ( ! wp_next_scheduled( 'dp_parallel_init' ) ) {
			wp_schedule_event( time(), 'minutely', 'dp_parallel_init' );
		}

		add_action( 'dp_parallel_init', array( $this, 'init' ) );

	}

	function add_schedule_interval( $intervals ) {
		$intervals['minutely'] = array( 'interval' => 60, 'display' => __( 'Every minute', 'dp' ) );
		return $intervals;
	}

	function init() {

		$upload_dir = wp_upload_dir();

		$fp = fopen( trailingslashit( $upload_dir['basedir'] ) . "dp_parallel.lock", "w" ); // open it for WRITING ("w")

		// get lock non blocking, return false if lock can not be acquired
		if( ! flock($fp, LOCK_EX | LOCK_NB )) {
			$message = sprintf( '%s, Time - %s, Objects - %s', __METHOD__, timer_stop(), 'Cron already running' );
			$this->log( $message );
			return;
		}

		update_option( 'dp_parallel_cron_running', 'yes' );

		// check queue on every init
		if ( ! $this->queue ) {
			$message = sprintf( '%s, Time - %s, Objects - %s', __METHOD__, timer_stop(), 'Nothing in queue' );
			$this->log( $message );
			flock($fp, LOCK_UN);
			return;
		}

		// don't run on our POST
		if ( $this->is_current_action( $this->receive_action ) || $this->is_current_action( $this->send_action ) ) {
			$this->log( 'Should skip on ' . $_POST['dp_action']  );
			flock($fp, LOCK_UN);
			return;
		}

		$request = array();
		$request['blocking'] = false;
		$request['timeout'] = 1;
		$request['body'] = array( 'dp_action' => $this->send_action );

		wp_remote_post( home_url( $this->send_page ), $request );

		$message = sprintf( '%s, Time - %s, Objects - %s', __METHOD__, timer_stop(), 'Run request' );
		$this->log( $message );

		flock($fp, LOCK_UN);
	}

	function send() {

		if ( $this->is_current_action( $this->send_action ) && ! DP_PARALLEL_SEND ) {

			define( 'DP_PARALLEL_SEND', true );

			// init stuff
			$count = 1;
			$limit = 5;

			$request = array();
			$request['blocking'] = true;
			$request['timeout'] = 1;

			$message = sprintf( '%s, Time - %s, Objects - %s, Start', __METHOD__, timer_stop(), count( $this->queue ) );
			$this->log( $message );

			// get all later actions
			$this->queue = get_option( 'dp_later', array() );
			// remove actions that will be processed now, save modified array
			update_option( 'dp_later', array_slice( $this->queue, $limit, null, true ) );

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
			$obj->{$call[1]}( $call[3] );

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