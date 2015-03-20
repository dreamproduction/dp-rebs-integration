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
		add_action( 'init', array( $this, 'handle_menu_actions' ) );

				// get last modified
			// do request
			// save last modified
			// map fields
			// save data

		// verify data

		// crud if the case

	//	var_dump( json_decode($str) );
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

	function update_from_api() {
		$this->last_modified = get_option( $this->name( 'last_modified' ), date_i18n( 'Y-m-d', time() - WEEK_IN_SECONDS ) );

		var_dump($this->last_modified);

		$this->get_data();
		$this->save_data();

		update_option( $this->name( 'last_modified' ), $this->last_modified );
	}

	function get_url( $mode = 'global', $id = 0 ) {

		switch ( $mode ) {
			case 'single' :
				$url =  trailingslashit( $this->api_url . '/' . $id );
				add_query_arg(
					array(
						'format' => 'json',
					),
					$url
				);
				break;
			default :
				$url = add_query_arg(
					array(
						'format' => 'json',
						'date_modified_by_user__gte' => $this->last_modified
					),
					$this->api_url
				);
				break;
		}

		return $url;
	}

	function get_data( $mode = 'global', $id = 0 ) {

		$url = $this->get_url( $mode, $id );

		$response = wp_remote_get( $url );

		if ( ! is_wp_error( $response ) ) {
			if ( $response['response']['code'] == 200 ) {
				//var_dump( $response );
				$this->api_data = json_decode( $response['body'], true );

			}
		}

		var_dump( $mode, $id );

		return $this->api_data;
	}

	function save_data( $mode = 'global'  ) {
		$this->setup_data( $mode );

		foreach ( $this->data as $property_data ) {
			$this->save_or_update( $property_data );
		}
	}

	function setup_data( $mode = 'global' ) {
		if ( $mode == 'single' ) {
			$this->data[] = $this->map_fields( $this->api_data );

		} else {
			foreach( $this->api_data['objects'] as $index => $object_data ) {
				$this->data[$index] = $this->map_fields( $object_data );
			}
		}
	}

	function save_or_update( $data ) {

		$exists = get_posts(
			array(
				'post_type' => 'property',
			    'suppress_filters' => false,
			    'meta_key' => 'estate_property_id',
			    'meta_value' => $data['post']['post_name'],
			    'posts_per_page' => 1
			)
		);

		if ( $exists ) {
			$data['post']['ID'] = $exists[0]->ID;
		}

		$id = wp_insert_post( $data['post'] );

		foreach ( $data['taxonomy'] as $taxonomy => $terms ) {
			wp_set_object_terms( $id, $terms, $taxonomy );
		}

		foreach ( $data['meta'] as $key => $meta_value ) {
			update_post_meta( $id, $key, $meta_value );
		}
	}

	static function set_cron() {
		wp_schedule_event( time(), 'hourly', 'dp_hourly' );
	}

	static function clear_cron() {
		wp_clear_scheduled_hook( 'dp_hourly' );
	}

	function property_save( $data ) {

	}

	/**
	 * @param array $data Property data from API
	 *
	 * @return array
	 */
	function map_fields( $data ) {
		$return = array();

		foreach ( $data as $key => $value ) {
			if ( ! $value )
				continue;

			switch ( $key ) {
				case 'for_rent' :
					$return['taxonomy']['property-status'][] = 'rent';
					break;
				case 'for_sale' :
					$return['taxonomy']['property-status'][] = 'sale';
					break;
				case 'city':
					$return['taxonomy']['property-location'][] = $value;
					break;
				case 'region':
					$return['taxonomy']['property-location'][] = $value;
					break;
				case 'property_type':
					$return['taxonomy']['property-type'] = $this->get_property_type( $value );
					break;
				case 'title':
					$return['post']['post_title'] = $value;
					break;
				case 'description':
					$return['post']['post_content'] = $value;
					break;
				case 'id':
					$return['post']['post_name'] = (string) $value;
					$return['meta']['estate_property_id'] = (string) $value;
					break;
				case 'price_sale' :
					$return['meta']['estate_property_price'] = (string) $value;
					break;
				case 'date_added':
					$return['post']['post_date'] = date('Y-m-d H:i:s', strtotime($value) );
					break;
				case 'full_images' :
					$return['images'] = $value;
					break;
				case 'closed_transaction_type' :
				case 'cut' :
				case 'availability' :
				case 'date_modified' :
				case 'date_modified_by_user' :
				case 'date_validated' :
				case 'exclusive' :
				case 'images' :
				case 'internal_id' :
				case 'pot' :
				case 'promote_carousel' :
				case 'promote_commission_rent' :
				case 'promote_commission_sale' :
				case 'promote_external' :
				case 'promote_featured' :
				case 'promote_flags' :
				case 'print_url' :
				case 'resource_uri' :
				case 'similar_properties' :
				case 'vat' :
				case 'vat_rent' :
				case 'vat_sale' :
				case 'zero_commission_rent' :
				case 'zero_commission_sale' :
				case 'verbose_floor' :
				case 'verbose_price' :
				case 'tags' :
				case 'tags_en' :
				case 'agent' :
				case 'residential_complex' :
				case 'sketches' :
				case 'is_available' :
				case 'description_en' :
				case 'title_en' :
				case 'thumbnail' :
				case 'lat' :
				case 'lng' :
				case 'street' :
				case 'zone' :
				// handled differently or not needed
				break;
				default:
					$return['meta'][ 'estate_property_' . $key] = (string) $value;
			}
		}


		$return['post']['post_type'] = 'property';
		$return['post']['post_status'] = 'publish';

		if ( $data['lat'] && $data['lng'] ) {
			$return['meta']['estate_property_location'] = sprintf( '%s,%s', $data['lat'], $data['lng'] );
		}
		$return['meta']['estate_property_address'] = implode( ', ', array( $data['street'], $data['zone'], $data['city'] ) );


		return $return;
	}

	function get_property_type( $value ) {
		switch ( $value ) {

			case 3:
				$return = 'house';
				break;
			case 4:
				$return = 'office';
				break;
			case 5:
				$return = 'commercial';
				break;
			case 6:
				$return = 'field';
				break;
			case 7:
				$return = 'industrial';
				break;
			case 1:
			default:
				$return = 'apartment';
				break;
		}

		return $return;
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