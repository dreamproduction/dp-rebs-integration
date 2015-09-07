<?php

class DP_Parallel {
	var $url = '';
	var $args = array();
	var $target;
	var $save_listen = 'dp_parallel_save';
	var $general_listen = 'dp_parallel_general';

	function __construct() {
		$this->general_action = 'dp-parallel-action-general';
		$this->save_action = 'dp-parallel-action-receive';
		$this->queue = get_option( 'dp_later', array() );

		/*
		 * init
		 * queue?
		 * send request
		 *
		 */

		add_action( 'parse_request', array( $this, 'save_stuff' ), 6 );
		add_action( 'parse_request', array( $this, 'send_multiple_stuff' ), 299 );
		add_action( 'parse_request', array( $this, 'send_general_stuff' ), 399 );
	}

	function save_stuff( $query ) {

		if ( $query->request == $this->save_listen ) {

			if ( isset( $_REQUEST['job'] ) && $_REQUEST['dp_action'] == $this->save_action ) {
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

	function send_general_stuff() {
		if ( ! $this->queue || defined('DOING_AJAX') )
			return;

		$request = array();
		$request['blocking'] = false;
		$request['timeout'] = 10;

		$request['body'] = array(
			'dp_action' => $this->general_action
		);

		$req = wp_remote_post( home_url( $this->general_listen ), $request );

		if ( is_wp_error($req) ) {
			$this->log( 'Failed general request: ' . $req->get_error_message() );
		}

	}

	function send_multiple_stuff( $query ) {
		if ( $query->request == $this->general_listen ) {
			if ( isset( $_REQUEST['dp_action'] ) && $_REQUEST['dp_action'] == $this->general_action ) {
				// init stuff
				$count = 1;
				$limit = 10;

				$request = array();
				$request['blocking'] = true;
				$request['timeout'] = 10;

				// remove actions that will be processed now, save modified array
				$left_queue = array_slice( $this->queue, $limit, null, true );
				update_option( 'dp_later', $left_queue );

				while ( $this->queue && $count <= $limit ) {
					$current_job = array_shift( $this->queue );


					$request['body'] = array(
						'job' => $current_job,
						'dp_action' => $this->save_action
					);

					$req = wp_remote_post( home_url( $this->save_listen ), $request );

					if ( is_wp_error($req) ) {
						// put back to queue
						$left_queue[$current_job[3]['key']] = $current_job;
						update_option( 'dp_later', $left_queue );
					}


					$count++;
				}

				// actions sent, terminate execution
				_e('Jobs sent for saving');
				die;
			}
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