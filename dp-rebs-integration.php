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

include( 'inc/dp-plugin/dp-plugin.php' );

class DP_REBS extends DP_Plugin {
	public $api_url;
	public $last_modified;
	public $api_data;
	public $data;

	/**
	 * Main plugin method. Called right after auto-loader.
	 */
	function plugin_init() {
		$this->prefix = 'dp_rebs';
		$this->api_url = 'http://demo.rebs-group.com/api/public/property/';

		// set cron via activation
		// set cron action
//		add_action( 'init', array( $this, 'update_from_api' ) );
		add_action( 'admin_bar_menu', array( $this, 'menu_buttons' ) );
		add_action( 'init', array( $this, 'handle_menu_actions' ), 99 );
		add_action( 'admin_init', array( $this, 'property_meta' ), 12 );

				// get last modified
			// do request
			// save last modified
			// map fields
			// save data

		// verify data

		// crud if the case


	}

	function property_meta() {
		add_post_type_support( 'property', 'custom-fields' );
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

	function handle_menu_actions() {
		header("Content-Type: text/html; charset=utf-8");

		// testing

		//add_action( 'custom_hehe', 'DP_Save_Images::save' );
	//	do_action( 'custom_hehe', 'trei' );

		$call = array( 'DP_Save_Images', 'save', 'dp_images' );


		//call_user_func_array(array( 'DP_Save_Images', 'save' ), array('dp_images') );

//		$reflect  = new ReflectionClass( $obj[0] );
//		$instance = $reflect->newInstanceArgs( array( $obj[2] ) );
//		$instance->{$obj[1]}();

		$obj = new $call[0]( $call[2] );
		$obj->{$call[1]}();

		//
		die('final');

		$api = new DP_REBS_API();
		$api_data = $api->set_url( 'single', '4976' )->call()->store()->walk()->return_data();

		foreach( $api_data as $data ) {
			$property = new DP_REBS_Property( $this->get_schema( 'property' ) );
			echo $property->set_data($data)->map_fields()->save_object()->save_taxonomy()->save_meta()->save_images()->save_sketches();
		}


		die( "coz I'm an albatraoz" );


		if ( ! isset( $_GET[ $this->name('update') ] ) )
			return;

		$what = $_GET[ $this->name('update') ];

		if ( $what == 'everything' ) {
			$this->get_data();
			$this->save_data();
		}

		if ( is_numeric($what) ) {

			$property_id = get_post_meta( $what, 'estate_property_id', true );

			$this->get_data( 'single', $property_id );
			$this->save_data( 'single' );
		}
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
		/*
		$this->last_modified = get_option( $this->name( 'last_modified' ), date_i18n( 'Y-m-d', time() - WEEK_IN_SECONDS ) );

		$api = new DP_REBS_API();
		$api_data = $api->set_url( 'list_since', $this->last_modified )->call()->store()->walk()->return_data();




		$this->get_data();
		$this->save_data();

		update_option( $this->name( 'last_modified' ), $this->last_modified );
		*/
	}

	static function set_cron() {
		//wp_schedule_event( time(), 'hourly', 'dp_hourly' );
	}

	static function clear_cron() {
		//wp_clear_scheduled_hook( 'dp_hourly' );
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