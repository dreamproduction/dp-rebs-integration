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

	/**
	 * Main plugin method. Called right after auto-loader.
	 */
	function plugin_init() {
		$this->prefix = 'dp_rebs';
		// defaults to 01.01.2015 in unix time
		//$this->last_modified = get_option( $this->name( 'last_modified' ), date_i18n( $this->date_format, 1420070400 ) );
		$this->last_modified = date_i18n( $this->date_format, 1420070400 );

		$url = get_option( 'rebs_api_url', '' );
		$this->api = new DP_REBS_API( $url );

		// set cron via activation
		// set cron action
		add_action( 'dp_hourly', array( $this, 'update_from_api' ) );
		add_action( 'admin_bar_menu', array( $this, 'menu_buttons' ) );
		add_action( 'init', array( $this, 'handle_menu_actions' ), 99 );
		add_action( 'admin_init', array( $this, 'property_meta' ), 12 );

		// An option in general settings
		add_action( 'admin_init', array( $this, 'setup_plugin_options' ), 11 );

		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'endoint_redirect' ) );

		new DP_Parallel();
	}

	function add_endpoint() {
		//
		add_rewrite_endpoint( $this->endpoint, EP_ROOT );
	}


	function property_meta() {
		add_post_type_support( 'property', 'custom-fields' );
	}

	function endoint_redirect() {
		global $wp_query;

		// if this is not our request then bail
		if ( ! isset( $wp_query->query_vars[ $this->endpoint ] ) )
			return;

		// search a property with queried ID
		$exists = get_posts(
			array(
				'post_type' => 'property',
				'suppress_filters' => false,
				'meta_key' => 'estate_property_id',
				'meta_value' => $wp_query->query_vars[ $this->endpoint ],
				'posts_per_page' => 1
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

	function menu_buttons( $wp_admin_bar ) {
		// Template name
		$wp_admin_bar->add_node(
			array(
				'id'		=> $this->name( 'update' ),
				'title'		=> __( 'REBS: Update all', 'dp' ),
				'href'		=> add_query_arg( $this->name( 'update' ), 'everything', admin_url( 'index.php' ) ),
			)
		);

		if ( is_singular( 'property' ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'		=> $this->name( 'update' ),
					'title'		=> __( 'REBS: Update this', 'dp' ),
					'href'		=> add_query_arg( $this->name( 'update' ), get_the_ID(), $this->get_current_url() ),
				)
			);
		}

		if ( is_admin() ) {
			$screen = get_current_screen();
			if ( $screen->base == 'post'  && $screen->post_type == 'property' ) {
				$wp_admin_bar->add_node(
					array(
						'id'		=> $this->name( 'update' ),
						'title'		=> __( 'REBS: Update this', 'dp' ),
						'href'		=> add_query_arg( $this->name( 'update' ), get_the_ID(), get_edit_post_link( get_the_ID() ) ),
					)
				);
			}
		}

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

	function handle_menu_actions() {

		if ( ! isset( $_GET[ $this->name('update') ] ) )
			return;

		$what = $_GET[ $this->name('update') ];

		if ( $what == 'everything' ) {
			$this->set_api_data_everything();
		}

		if ( is_numeric($what) ) {
			$this->set_api_data_single( $what );
		}

		$this->save_api_data();
	}

	function set_api_data_everything() {
		$this->api_data = $this->api->set_url( 'list_since', $this->last_modified )->call()->store()->walk()->return_data();
	}

	function set_api_data_single( $id ) {
		$property_id = get_post_meta( $id, 'estate_property_id', true );
		$this->api_data = $this->api->set_url( 'single', $property_id )->call()->store()->walk()->return_data();
	}

	function save_api_data() {
		foreach( $this->api_data as $data ) {
			$property = new DP_REBS_Property( $this->get_schema( 'property' ) );
			$property->set_data($data)->map_fields()->save_object()->save_agent()->save_meta()->save_taxonomy()->save_images()->save_sketches();
		}
//		$this->last_modified = date_i18n( $this->date_format, current_time( 'timestamp' ) );
//		update_option( $this->name( 'last_modified' ), $this->last_modified );
	}

	function get_schema( $type = 'property' ) {
		$name = $this->name( 'schema_' . $type );
		$expire = current_time( 'timestamp' ) + DAY_IN_SECONDS;
		$data = get_transient( $name );

		if ( $data == false ) {
			$api_schema = new DP_REBS_API();
			$data = $api_schema->set_url( 'schema', $type )->call()->store()->return_data();
			set_transient( $name, $data, $expire );
		}

		return $data;
	}

	function update_from_api() {
		$this->set_api_data_everything();
		$this->save_api_data();
	}

	static function set_cron() {
		wp_schedule_event( time(), 'hourly', 'dp_hourly' );
	}

	static function clear_cron() {
		wp_clear_scheduled_hook( 'dp_hourly' );
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
register_activation_hook( __FILE__, array( dp_rebs(), 'set_cron' ) );
register_deactivation_hook( __FILE__, array( dp_rebs(), 'clear_cron' ) );