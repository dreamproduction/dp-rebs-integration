<?php

/*
Plugin Name: DP REBS Integration
Plugin URI: http://dreamproduction.com/wordpress-plugins/dp-rebs-integration
Description: Get properties and values from REBS API.
Version: 1.0
Author: Dream Production
Author URI: http://dreamproduction.com
License: GPL2
*/

if ( ! class_exists( 'DP_Plugin' ) ) {
	include( 'inc/dp-plugin/dp-plugin.php' );
}

class DP_REBS extends DP_Plugin {

	/**
	 * A sub class must override this parameter to allow all subclasses to have their own instance
	 * @var DP_Plugin The single instance of the class
	 */
	protected static $_instance = null;
	/**
	 * A sub class must override this parameter for the autoloader to work
	 * @var String
	 */
	static $file;

	public $date_format = 'Y-m-d H:i:s';
	public $last_modified;
	/**
	 * @var DP_REBS_API
	 */
	public $api;
	public $api_data = array();
	public $virtual_page = 'rebs-hook';

	/**
	 * Main plugin method. Called right after auto-loader.
	 */
	function plugin_init() {
		$this->prefix = 'dp_rebs';

		$url = get_option( 'rebs_api_url', '' );
		$this->api = new DP_REBS_API( $url );

		// An option in general settings
		add_action( 'admin_init', array( $this, 'setup_plugin_options' ), 11 );

		// Hook update from webhook
		add_action( 'parse_request', array( $this, 'add_listner' ), 5 );

		new DP_REBS_Endpoint();
		new DP_REBS_AllImport_Compat();
	}

	/**
	 * Register fields for general options page.
	 */
	function setup_plugin_options() {
		add_settings_field( 'rebs_api_url' , __( 'REBS API URL', 'dp' ), array( $this, 'display_options' ) , 'general' , 'default' );
		register_setting( 'general', 'rebs_api_url' );
	}

	/**
	 * Display markup for options.
	 */
	function display_options() {
		include( 'views/options.php' );
	}

	function add_listner( $query ) {
		if ( $query->request == $this->virtual_page ) {
			$this->log( 'Update hook hit' );

			$property_id =  filter_input( INPUT_REQUEST, 'property_id', FILTER_SANITIZE_NUMBER_INT );

			if ( $property_id !== false && $property_id !== null ) {
				$this->log( 'Update hook hit with id:' .  $property_id );

				$this->set_api_data_single( $property_id );
				// send ID in case data is false (deleted property)
				$this->save_api_data( $property_id );
				wp_die( __('Saved'), __('OK') );
			} else {
				wp_die( __('No property id') );
			}
		}
	}

	protected function set_api_data_single( $api_id ) {
		$this->api_data = $this->api->set_url( 'single', $api_id )->call()->store()->return_data();
	}

	protected function save_api_data( $api_id ) {
		foreach( $this->api_data as $data ) {
			$property = new DP_REBS_Property( $this->get_schema( 'property' ) );
			if ( $data === false ) {
				// set id for mapping
				$data['id'] = $api_id;
				$property->set_data($data)->delete_object();
			} else {
				$property->set_data($data)->save_object()->save_agent()->save_meta()->save_taxonomy()->save_images()->save_sketches();

			}
		}
	}

	protected function get_schema( $type = 'property' ) {
		$name = $this->name( 'schema_' . $type );
		$expire = current_time( 'timestamp' ) + DAY_IN_SECONDS;
		$data = get_transient( $name );

		if ( $data === false ) {
			$api_schema = new DP_REBS_API();
			$data = $api_schema->set_url( 'schema', $type )->call()->store()->return_data();
			set_transient( $name, $data, $expire );
		}

		return $data;
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



/**
 * Instantiate the plugin class if not already done so.
 *
 * @return DP_REBS
 */
function dp_rebs() {
	$instance_object = apply_filters( 'dp_rebs_integration_object', 'DP_REBS' );
	return $instance_object::instance();
}

// fire plugin after theme
add_action( 'after_setup_theme', 'dp_rebs', 5 );