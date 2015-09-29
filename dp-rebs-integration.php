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
	public $endpoint = 'rebs-id';
	/**
	 * @var DP_REBS_API
	 */
	public $api;
	public $api_data = array();
	public $api_id;
	public $virtual_page = 'rebs-hook';

	/**
	 * Main plugin method. Called right after auto-loader.
	 */
	function plugin_init() {
		$this->prefix = 'dp_rebs';

		$url = get_option( 'rebs_api_url', '' );
		$this->api = new DP_REBS_API( $url );

		// enable custom fields for debug
		add_action( 'admin_init', array( $this, 'property_meta' ), 12 );

		// An option in general settings
		add_action( 'admin_init', array( $this, 'setup_plugin_options' ), 11 );

		// Handle redirect endpoints
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'endoint_redirect' ) );

		add_action( 'parse_request', array( $this, 'add_listner' ), 5 );

		// wp all import compatibility
		add_action( 'pmxi_gallery_image', array( $this, 'save_images_from_import' ), 10, 2 );

		add_action( 'pmxi_update_post_meta', array( $this, 'save_agent_from_import' ), 10, 3 );

	}

	function save_images_from_import( $post_id, $image_id ) {
		$previous_images = get_post_meta( $post_id, 'estate_property_gallery', true );
		if ( empty( $previous_images ) )
			$previous_images = array();

		if ( ! in_array( $image_id, $previous_images ) ) {
			if ( $image_id ) {
				$new_images = $previous_images;
				$new_images[] = $image_id;
				update_post_meta( $post_id, 'estate_property_gallery', $new_images, $previous_images );
			}
		}
	}

	function save_agent_from_import( $post_id, $meta_key, $meta_value ) {
		if ( $meta_key == 'estate_property_custom_agent' ) {
			$user = get_user_by( 'email', $meta_value );
			if ( !is_wp_error( $user ) )
				update_post_meta( $post_id, $meta_key, $user->ID );
		}
	}

	/**
	 * Add custom fields for properties in debug mode
	 */
	function property_meta() {
		if ( defined('WP_DEBUG') && WP_DEBUG )
			add_post_type_support( 'property', 'custom-fields' );
	}


	/**
	 * Add endpoint to rewrite rules
	 */
	function add_endpoint() {
		// add endpoint for root domain
		add_rewrite_endpoint( $this->endpoint, EP_ROOT );
	}

	/**
	 * Handle redirects on endpoint
	 */
	function endoint_redirect() {
		global $wp_query;

		// if this is not our request then bail
		if ( ! isset( $wp_query->query_vars[ $this->endpoint ] ) )
			return;

		$p_id = str_ireplace( 'cp', '', $wp_query->query_vars[ $this->endpoint ] );
		$m_id = 'CP' . $p_id;

		// search a property with queried ID
		$exists = get_posts(
			array(
				'post_type' => 'property',
				'suppress_filters' => false,
				'posts_per_page' => 1,
				'post_status' => 'publish',
				'meta_value' => $m_id,
			)
		);

		if ( $exists ) {
			// redirect to the property permalink
			wp_redirect( get_permalink( $exists[0]->ID ), 301 );
			exit;
		}

		// display a 404 if no property with that ID is available
		$template = get_404_template();

		// filter the path before including, as WordPress does
		if ( $template = apply_filters( 'template_include', $template ) )
			include( $template );

		// stop WordPress execution after include
		exit;
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

			if ( isset( $_REQUEST['property_id'] ) ) {
				$this->api_id = absint( $_REQUEST['property_id'] );
				$this->log( 'Update hook hit with id:' .  $this->api_id );

				$this->set_api_data_single( $this->api_id );
				$this->save_api_data();
				wp_die( __('Saved'), __('OK') );
				die;
			} else {
				wp_die( __('No property id') );
			}
		}
	}

	function set_api_data_single( $api_id ) {
		$this->api_data = $this->api->set_url( 'single', $api_id )->call()->store()->return_data();
	}

	function save_api_data() {
		foreach( $this->api_data as $data ) {
			$property = new DP_REBS_Property( $this->get_schema( 'property' ) );
			if ( $data === false ) {
				// set id for mapping
				$data['id'] = $this->api_id;
				$property->set_data($data)->delete_object();
			} else {
				$property->set_data($data)->save_object()->save_agent()->save_meta()->save_taxonomy()->save_images()->save_sketches();

			}
		}
	}

	function get_schema( $type = 'property' ) {
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