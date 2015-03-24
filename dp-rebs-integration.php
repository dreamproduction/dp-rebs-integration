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
			if ( $taxonomy == 'property-features' ) {
				// $terms should be an array of arrays
				if ( is_array( $terms )) {
					$to_insert = array();
					foreach ( $terms as $parent => $term_array ) {
						$term_to_insert = $parent;
						if ( !$term_info = term_exists($parent, $taxonomy) ) {
							$result = wp_insert_term( $parent, $taxonomy);
							$term_info = $term_to_insert = $result['term_id'];
						}
						$to_insert[] = $term_to_insert;

						foreach ( $term_array as $term ) {
							$term_to_insert = $term;
							if ( ! term_exists($term, $taxonomy) ) {
								$result = wp_insert_term( $term, $taxonomy, array( 'parent' => $term_info ));
								$term_to_insert = $result['term_id'];
							}

							$to_insert[] = $term_to_insert;
						}

					}
					// set everything at once, this way one tag won't replace the previous one set
					wp_set_object_terms( $id, $to_insert, $taxonomy );
				}

 			} else {
				wp_set_object_terms( $id, $terms, $taxonomy );
			}
		}

		foreach ( $data['meta'] as $key => $meta_value ) {
			update_post_meta( $id, $key, $meta_value );
		}

		foreach ( $data['images'] as $image ) {
			$image_id = $this->external_image_sideload( $image, $id );
			if ( $image_id )
				add_post_meta( $id, 'estate_property_images', $image_id, false );
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
				case 'tags_en' :
					$return['taxonomy']['property-features'] = $value;
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
				case 'surface_built' :
					$return['meta']['estate_property_size'] = (string) $value;
					break;
				case 'partitioning' :
					$return['meta']['estate_property_' . $key] = $this->{'get_meta_' . $key}( (string) $value );
					break;
				case 'apartment_type' :
					$return['meta']['estate_property_' . $key] = $this->{'get_meta_' . $key}( (string) $value );
					break;
				case 'building_structure' :
					$return['meta']['estate_property_' . $key] = $this->{'get_meta_' . $key}( (string) $value );
					break;
				case 'comfort' :
					$return['meta']['estate_property_' . $key] = $this->{'get_meta_' . $key}( (string) $value );
					break;
				case 'construction_status' :
					$return['meta']['estate_property_' . $key] = $this->{'get_meta_' . $key}( (string) $value );
					break;
				case 'floor' :
					$return['meta']['estate_property_' . $key] = $this->{'get_meta_' . $key}( (string) $value );
					break;
				case 'house_type' :
					$return['meta']['estate_property_' . $key] = $this->{'get_meta_' . $key}( (string) $value );
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
		$return['meta']['estate_property_size_unit'] = 'mp';


		if ( $data['lat'] && $data['lng'] ) {
			$return['meta']['estate_property_location'] = sprintf( '%s,%s', $data['lat'], $data['lng'] );
		}
		$return['meta']['estate_property_address'] = implode( ', ', array( $data['street'], $data['zone'], $data['city'] ) );


		return $return;
	}

	function get_property_type( $value ) {
		switch ( $value ) {

			case '3':
				$return = 'house';
				break;
			case '4':
				$return = 'office';
				break;
			case '5':
				$return = 'commercial';
				break;
			case '6':
				$return = 'field';
				break;
			case '7':
				$return = 'industrial';
				break;
			case '1':
			default:
				$return = 'apartment';
				break;
		}

		return $return;
	}

	function get_meta_partitioning( $value ) {
		switch ( $value ) {
			case '1':
				$return = 'Detached';
				break;
			case '2':
				$return = 'Semi-detached';
				break;
			case '3':
				$return = 'Un-detached';
				break;
			case '4':
				$return = 'Circular';
				break;
			case '5':
				$return = 'Wagon';
				break;
			default:
				$return = '';
				break;
		}

		return $return;
	}

	function get_meta_apartment_type( $value ) {
		switch ( $value ) {
			case '1':
				$return = 'Studio';
				break;
			case '2':
				$return = 'Penthouse';
				break;
			case '5':
				$return = 'Duplex';
				break;
			case '6':
				$return = 'Apartment';
				break;
			default:
				$return = '';
				break;
		}

		return $return;
	}

	function get_meta_building_structure( $value ) {
		switch ( $value ) {
			case '2':
				$return = 'Brick';
				break;
			case '3':
				$return = 'ACB';
				break;
			case '4':
				$return = 'Wood';
				break;
			case '5':
				$return = 'Metal';
				break;
			case '6':
				$return = 'Other';
				break;
			case '7':
				$return = 'Frame';
				break;
			case '8':
				$return = 'Prefabricated';
				break;
			case '9':
				$return = 'Monolith';
				break;
			default:
				$return = '';
				break;
		}
		return $return;
	}

	function get_meta_comfort( $value ) {
		switch ( $value ) {
			case '1':
				$return = '1';
				break;
			case '2':
				$return = '2';
				break;
			case '3':
				$return = '3';
				break;
			case '4':
				$return = 'Luxury';
				break;
			default:
				$return = '';
				break;
		}
		return $return;
	}

	function get_meta_construction_status( $value ) {
		switch ( $value ) {
			case '1':
				$return = 'Completed';
				break;
			case '2':
				$return = 'Finishings completed';
				break;
			case '3':
				$return = 'In construction';
				break;
			case '4':
				$return = 'Unfinished';
				break;
			case '5':
				$return = 'Requires renovation';
				break;
			case '6':
				$return = 'Requires demolition';
				break;
			case '7':
				$return = 'Structure';
				break;
			default:
				$return = '';
				break;
		}
		return $return;
	}

	function get_meta_floor($value) {
		switch ($value) {
			case '1':
				$return = 'Semi-basement';
				break;
			case '2':
				$return = 'Ground floor';
				break;
			case '102':
				$return = 'Mezzanine';
				break;
			case '3':
				$return = '1';
				break;
			case '4':
				$return = '2';
				break;
			case '5':
				$return = '3';
				break;
			case '6':
				$return = '4';
				break;
			case '7':
				$return = '5';
				break;
			case '8':
				$return = '6';
				break;
			case '9':
				$return = '7';
				break;
			case '10':
				$return = '8';
				break;
			case '11':
				$return = '9';
				break;
			case '12':
				$return = '10';
				break;
			case '13':
				$return = '11';
				break;
			case '14':
				$return = '12';
				break;
			case '15':
				$return = '13';
				break;
			case '16':
				$return = '14';
				break;
			case '17':
				$return = '15';
				break;
			case '18':
				$return = '16';
				break;
			case '100':
				$return = 'Loft';
				break;
			case '101':
				$return = 'Last 2 floors';
				break;
			default:
				$return = '';
				break;
		}
		return $return;
	}

	function get_meta_house_type( $value ) {
		switch ( $value ) {
			case '1':
				$return = 'Individual';
				break;
			case '2':
				$return = 'Narrow lot';
				break;
			case '3':
				$return = 'Duplex';
				break;
			default:
				$return = '';
				break;
		}
		return $return;
	}

	/**
     * Handle importing of external image.
     * Most of this taken from WordPress function 'media_sideload_image'.
	 *
     * @param string $file The URL of the image to download
     * @param int $post_id The post ID the media is to be associated with
     *
	 * @return string - just the image url on success, false on failure
     */
	function external_image_sideload( $file , $post_id ) {

		if ( ! function_exists( 'download_url' ) )
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		if ( ! function_exists( 'media_handle_sideload' ) )
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
		if ( ! function_exists( 'wp_read_image_metadata' ) )
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

		if ( ! empty($file) && $this->is_valid_image( $file ) ) {

			$file_array = array();

			// Set variables for storage
			// fix file filename for query strings
			preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $file, $matches);
			$file_array['name'] = basename($matches[0]);

			if ( $id = $this->file_imported( $file_array['name'] ) )
				return $id;

			// Download file to temp location
			$file_array['tmp_name'] = download_url( $file );

			// If error storing temporarily, unlink
			if ( is_wp_error( $file_array['tmp_name'] ) ) {
				@unlink($file_array['tmp_name']);
				$file_array['tmp_name'] = '';
				return false;
			}

			// do the validation and storage stuff
			$id = media_handle_sideload( $file_array, $post_id, $file_array['name'] );
			// If error storing permanently, unlink
			if ( is_wp_error($id) ) {
				@unlink($file_array['tmp_name']);
				return false;
			}

			return $id;
		}

		return false;
	}

	function is_valid_image( $file ) {

		$allowed = array( '.jpg' , '.png', '.bmp' , '.gif' );

		$ext = substr( $file , -4 );

		return in_array( strtolower($ext) , $allowed );
	}

	/**
	 * Retrieve a post given its title.
	 *
	 * @param string $filename Page title
	 * @global wpdb $wpdb       WordPress Database Access Abstraction Object
	 *
	 * @return mixed
	 */
	function file_imported( $filename ) {
		global $wpdb;
		return  $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='attachment'", $filename ));
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