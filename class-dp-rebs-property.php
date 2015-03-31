<?php

class DP_REBS_Property {
	protected $fields = array();
	protected $schema = array();
	protected $data;

	public $object;
	public $meta;
	public $taxonomy;
	public $images;
	public $sketches;
	public $id;

	public function __construct( $schema ) {
		$this->set_schema( $schema )->set_fields_options();
	}

	public function set_data( $data ) {
		$this->data = $data;
		return $this;
	}

	protected function set_schema( $schema ) {
		$this->schema = $schema;
		return $this;
	}

	protected function set_fields_options() {
		$this->fields = array();

		foreach ( $this->schema as $field => $options ) {
			if ( isset( $options['choices'] ) ) {
				foreach ( $options['choices'] as $value ) {
					$this->fields[$field][$value[0]] = $value[1];
				}
			}
		}

		return $this;
	}

	protected function get_field_option( $name, $value ) {
		return isset( $this->fields[$name][$value] ) ? $this->fields[$name][$value] : '';
	}

	public function get_fields_options() {
		return $this->fields;
	}

	public function clear_data() {
		$this->data = array();
		return $this;
	}

	/**
	 *
	 */
	public function map_fields() {
		foreach ( $this->data as $key => $value ) {
			if ( ! $value )
				continue;

			switch ( $key ) {
				case 'for_rent' :
					$this->taxonomy['property-status'][] = 'rent';
					break;
				case 'for_sale' :
					$this->taxonomy['property-status'][] = 'sale';
					break;
				case 'city':
					$this->taxonomy['property-location'][] = $value;
					break;
				case 'region':
					$this->taxonomy['property-location'][] = $value;
					break;
				case 'property_type':
					$this->taxonomy['property-type'] = $this->get_field_option( (string) $key, (string) $value );
					break;
				case 'tags_en' :
					$this->taxonomy['property-features'] = $value;
					break;
				case 'title':
					$this->object['post_title'] = $value;
					break;
				case 'description':
					$this->object['post_content'] = $value;
					break;
				case 'id':
					$this->object['post_name'] = (string) $value;
					$this->meta['estate_property_id'] = (string) $value;
					break;
				case 'price_sale' :
					$this->meta['estate_property_price'] = (string) $value;
					break;
				case 'surface_built' :
					$this->meta['estate_property_size'] = (string) $value;
					break;
				case 'partitioning' :
				case 'apartment_type' :
				case 'building_structure' :
				case 'comfort' :
				case 'construction_status' :
				case 'floor' :
				case 'house_type' :
					$this->meta['estate_property_' . $key] = $this->get_field_option( (string) $key, (string) $value );
					break;
				case 'date_added':
					$this->object['post_date'] = date('Y-m-d H:i:s', strtotime($value) );
					break;
				case 'full_images' :
					$this->images = $value;
					break;
				case 'sketches' :
					$this->sketches = $value;
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
					$this->meta[ 'estate_property_' . $key] = (string) $value;
			}
		}


		$this->object['post_type'] = 'property';
		$this->object['post_status'] = 'publish';
		$this->meta['estate_property_size_unit'] = 'mp';


		if ( $this->data['lat'] && $this->data['lng'] ) {
			$this->meta['estate_property_location'] = sprintf( '%s,%s', $this->data['lat'], $this->data['lng'] );
		}
		$this->meta['estate_property_address'] = implode( ', ', array( $this->data['street'], $this->data['zone'], $this->data['city'] ) );

		return $this;
	}

	public function save_object() {
		$exists = get_posts(
			array(
				'post_type' => 'property',
				'suppress_filters' => false,
				'meta_key' => 'estate_property_id',
				'meta_value' => $this->meta['estate_property_id'],
				'posts_per_page' => 1
			)
		);

		if ( $exists )
			$this->object['ID'] = $exists[0]->ID;

		$this->id = wp_insert_post( $this->object );


		return $this;
	}

	public function save_taxonomy() {

		foreach ( $this->taxonomy as $taxonomy => $terms ) {
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
					wp_set_object_terms( $this->id, $to_insert, $taxonomy );
				}

			} else {
				wp_set_object_terms( $this->id, $terms, $taxonomy );
			}
		}

		return $this;
	}

	public function save_meta() {
		foreach ( $this->meta as $key => $meta_value ) {
			update_post_meta( $this->id, $key, $meta_value );
		}
		return $this;
	}

	public function save_images() {
		foreach ( $this->images as $image ) {
			$image_id = $this->external_image_sideload( $image, $this->id );
			if ( $image_id )
				add_post_meta( $this->id, 'estate_property_images', $image_id, false );
		}
		return $this;
	}

	public function save_sketches() {
		foreach ( $this->sketches as $image ) {
			$image_id = $this->external_image_sideload( $image, $this->id );
			if ( $image_id )
				add_post_meta( $this->id, 'estate_property_sketches', $image_id, false );
		}
		return $this;
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

	public function __toString() {
		return var_export( $this );
	}
}