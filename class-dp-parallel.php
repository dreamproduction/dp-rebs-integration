<?php

class DP_Parallel {
	var $url = '';
	var $args = array();
	var $target;
	var $listen = 'dp_rebs_get_parallel';

	function __construct() {
		$this->receive_action = 'dp-parallel-action-receive';
		$this->queue = get_option( 'dp_later', array() );

		/*
		 * init
		 * queue?
		 * send request
		 *
		 */

		add_action( 'parse_request', array( $this, 'add_listner' ), 6 );
		add_action( 'parse_request', array( $this, 'send_stuff' ), 299 );
	}

	function add_listner( $query ) {

		if ( $query->request == $this->listen ) {

			if ( isset( $_REQUEST['job'] ) && $_REQUEST['dp_action'] == $this->receive_action ) {
				$call = $_REQUEST['job'];

				$this->log( 'Running parallel save for :' .  $call[3]['key'] );

				// instantiate a new object with params for __construct & call method
				$obj = new $call[0]( $call[2] );
				$obj->{$call[1]}( $call[3] );

				_e('Saved stuff');
				echo $call[3]['key'];
				die;

			} else {
				_e('No parallel job received');
				die;
			}
		}
	}

	function send_stuff() {
		if ( ! $this->queue || defined('DOING_AJAX') )
			return;

		// init stuff
		$count = 1;
		$limit = 5;

		$request = array();
		$request['blocking'] = true;
		$request['timeout'] = 10;

		// remove actions that will be processed now, save modified array
		$left_queue = array_slice( $this->queue, $limit, null, true );
		update_option( 'dp_later', $left_queue );

		while ( $this->queue && $count <= $limit ) {
			$current_job = array_shift( $this->queue );

//			$obj = new $current_job[0]( $current_job[2] );
//			$obj->{$current_job[1]}( $current_job[3] );


			$request['body'] = array(
				'job' => $current_job,
				'dp_action' => $this->receive_action
			);

			$req = wp_remote_post( home_url( $this->listen ), $request );

			if ( is_wp_error($req) ) {
				// put back to queue
				$left_queue[$current_job[3]['key']] = $current_job;
				update_option( 'dp_later', $left_queue );
			}


			$count++;
		}
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