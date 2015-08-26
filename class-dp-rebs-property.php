<?php

class DP_REBS_Property {
	protected $fields = array();
	protected $schema = array();
	protected $data;

	public $object = array();
	public $meta = array();
	public $taxonomy = array();
	public $images = array();
    public $agent = array();
	public $sketches = array();
	public $id = 0;
	protected $old_id = 0;
	protected $old_date = '000-00-00 00:00:00';

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
					$this->taxonomy['property-status'][] = 'De inchiriat';
					break;
				case 'for_sale' :
					$this->taxonomy['property-status'][] = 'De vanzare';
					break;
				case 'city':
					$this->taxonomy['property-location'][] = $value;
					break;
				case 'region':
					$this->taxonomy['property-location'][] = $value;
					break;
				case 'property_type':
					if ( $value != 6 ) {
						/* 6 == Teren */
						$this->taxonomy['property-type'] = $this->get_field_option( (string) $key, (string) $value );
					}
					break;
				case 'tags':
					$this->taxonomy['property-features'] = $value;
					break;
				case 'title':
					$this->object['post_title'] = $value;
					break;
				case 'description':
					$this->object['post_content'] = $value;
					break;
				case 'id':
					$this->meta['estate_property_id'] = 'CP' . (string) $value;
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
				case 'commercial_building_type' :
				case 'office_class':
				case 'pedestrian_traffic' :
				case 'land_classification' :
				case 'house_type' :
					$this->meta['estate_property_' . $key] = $this->get_field_option( (string) $key, (string) $value );
					break;
				case 'date_modified_by_user':
					$this->object['post_date'] = date('Y-m-d H:i:s', strtotime($value) );
					break;
				case 'destination':
					if( $this->data['property_type'] == 6 ) {
						/* 6 == Teren */
						foreach( $value as $v ) {
							$this->taxonomy['property-type'][] = 'Teren ' . $v;
						}
					} else {
						foreach( $value as $v ) {
							$this->meta['estate_property_' . $key] = $v;
						}
					}
					break;
				case 'full_images' :
					$this->images = $value;
					break;
				case 'sketches' :
					$this->sketches = $value;
					break;
                case 'agent' :
                    $this->agent = $value;
                    break;
				case 'closed_transaction_type' :
				case 'cut' :
				case 'availability' :
				case 'date_modified' :
				case 'date_added' :
				case 'date_validated' :
				case 'exclusive' :
				case 'images' :
				case 'internal_id' :
				case 'pot' :
				case 'promote_carousel' :
				case 'promote_commission_rent' :
				case 'promote_commission_sale' :
				case 'promote_custom_fields' :
				case 'promote_external' :
				case 'promote_featured' :
				case 'promote_flags' :
				case 'print_url' :
				case 'resource_uri' :
				case 'similar_properties' :
				case 'vat' :
				case 'vat_rent' :
				case 'vat_sale' :
				case 'verbose_floor' :
				case 'verbose_price' :
				case 'tags_en' :
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
					if ( is_string( $value ) || is_scalar( $value ) || is_bool( $value ) ) {
						$this->meta[ 'estate_property_' . $key ] = (string) $value;
					} else {
						// ignore extra arrays added to API
						$this->log( 'Unhandled array ' . $key );
					}
			}
		}


		$this->object['post_type'] = 'property';
		$this->object['post_status'] = 'publish';
		$this->meta['estate_property_size_unit'] = 'mp';


		if ( $this->data['lat'] && $this->data['lng'] ) {
			$this->meta['estate_property_google_maps'] = array(
				'lat' => $this->data['lat'],
				'lng' => $this->data['lng']
			);
		}
		$this->meta['estate_property_address'] = implode( ', ', array_filter( array( $this->data['zone'], $this->data['city'] ) ) );

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), 'map fields' );
		$this->log( $message );

		return $this;
	}

	public function delete_object() {
		$this->check_existing();

		if ( $this->old_id ) {
			wp_delete_post( $this->old_id, true );

			$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), 'delete_post' );
			$this->log( $message );
		} else {
			$message = sprintf( '%s, Time - %s, Objects - %s, Failed, no post id', __METHOD__, timer_stop(), 'delete_post' );
			$this->log( $message );
		}

	}

	public function save_object() {
		if ( $this->needs_update() ) {
			$this->_insert();
		}

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), 'insert_post' );
		$this->log( $message );

		return $this;
	}

	public function force_save_object() {
		$this->check_existing();

		if ( $this->old_id ) {
			// wp_insert_post will update if ID present
			$this->object['ID'] = $this->old_id;
		}

		$this->_insert();

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), 'insert_post' );
		$this->log( $message );

		return $this;
	}

	protected function _insert() {
		// actual insert. returns 0 on failure
		$this->id = wp_insert_post( $this->object, false );

		// translate in all other languages if WPML active
		if ( function_exists( 'icl_makes_duplicates' ) ) {
			// icl_makes_duplicates( $this->id );
		}

		// save ID as early as possible to avoid duplicates
		add_post_meta( $this->id, 'estate_property_id', $this->meta['estate_property_id'], true );

		return $this;
	}

	public function maybe_translate() {
		// no property id? bail
		if ( $this->id == 0 ) {
			return $this;
		}

		// translate in all other languages if WPML active
		if ( function_exists( 'icl_makes_duplicates' ) ) {
			// icl_makes_duplicates( $this->id );
		}

		return $this;
	}

    public function save_agent() {
        // no property id? bail
        if ( $this->id == 0 ) {
            return $this;
        }

        // find if agent exists
        $user_exists = new WP_User_Query(
              array(
                  'meta_key' => 'rebs_id',
                  'meta_value' => $this->agent['id']
              )
        );

        if ( $user_exists->get_results() ) {
            $results = $user_exists->get_results();
            $user = array_pop($results);
            $user_id = $user->ID;

            //check if agent changed his email address
            $actual_user = get_user_by('id', $user_id);

            if ( $actual_user->user_email !=  $this->agent['email'] ) {
                wp_update_user(
                    array(
                        'ID' => $user_id,
                        'user_email' => $this->agent['email']
                    )
                );
            }
        } else {
            // if agent doesn't exist --- the agent don't have rebs_id
            //check if email agent exists
            $actual_user = get_user_by( 'email', $this->agent['email'] );

            if ( $actual_user ) {
                $user_id = $actual_user->ID;

                update_user_meta( $user_id, 'rebs_id', $this->agent['id'] );

            } else {
                //new agent
                $user_id = wp_insert_user(
                    array(
                        'user_login' => $this->agent['first_name'] . " " . $this->agent['last_name'],
                        'user_email' => $this->agent['email'],
                        'first_name' => $this->agent['first_name'],
                        'last_name' => $this->agent['last_name'],
                        'role' => 'agent'
                    )
                );

                update_user_meta( $user_id, 'rebs_id', $this->agent['id'] );
            }
        }

		update_user_meta( $user_id, 'office_phone_number', $this->agent['phone'] );
		update_user_meta( $user_id, 'company_name', $this->agent['position'] );
		$user_image_id = DP_Save_Images::import_external_image( $this->agent['avatar'], 0 );
		$user_image = wp_get_attachment_image_src( $user_image_id, 'full' );
		update_user_meta( $user_id, 'user_image', $user_image[0] );

        // associate agent with the property
        update_post_meta( $this->id, 'estate_property_custom_agent', $user_id );

        $message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), count( $this->agent ) );
        $this->log( $message );

        return $this;
    }

	protected function needs_update() {
		$this->check_existing();

		if ( $this->old_id ) {
			// wp_insert_post will update if ID present
			$this->object['ID'] = $this->old_id;

			// bail if no update is necessary
			if ( $this->object['post_date'] == $this->old_date )
				return false;
		}

		return true;
	}

	protected function check_existing() {
		$exists = get_posts(
			array(
				'post_type' => 'property',
				'suppress_filters' => false,
				'meta_key' => 'estate_property_id',
				'meta_value' => $this->meta['estate_property_id'],
				'posts_per_page' => 1,
				'post_status' => 'any'
			)
		);

		if ( $exists ) {
			$this->old_id = $exists[0]->ID;
			$this->old_date = $exists[0]->post_date;
		}

		return $this;
	}

	public function clean_taxonomy() {
		if ( $this->id == 0 ) {
			return $this;
		}

		$taxonomies = array_keys( $this->taxonomy );
		wp_delete_object_term_relationships( $this->id, $taxonomies );

		return $this;
	}

	public function save_taxonomy() {
		if ( $this->id == 0 ) {
			return $this;
		}

		foreach ( $this->taxonomy as $taxonomy => $terms ) {
			if ( $taxonomy == 'property-features' ) {
				// $terms should be an array of arrays
				if ( is_array( $terms )) {
					$to_insert = array();
					foreach ( $terms as $parent => $term_array ) {
						if ( !$parent_result = term_exists($parent, $taxonomy) ) {
							$this->log( 'tax: insert parent slug:' . $parent );
							$parent_result = wp_insert_term( $parent, $taxonomy);
						}


						$to_insert[] = $parent_id = absint($parent_result['term_id']);

						$this->log( 'tax: parent ok. slug:' . $parent . ' id:' . $parent_id );


						foreach ( $term_array as $term ) {

							$this->log( 'tax: check child term slug:' . $term );

							// try slug-parent-slug first, as many terms are used under multiple parents
							if ( $alt_result = term_exists( $term, $taxonomy, $parent_id ) ) {
								// slug-parent-slug
								$term_to_insert = absint($alt_result['term_id']);

								$this->log( 'tax: child term exists:' . $term . ' id: ' . $term_to_insert );
							} else {
								// verify if exists, add it if not
								$result = wp_insert_term( $term, $taxonomy, array( 'parent' => $parent_id ));
								$term_to_insert = absint($result['term_id']);

								$this->log( 'tax: child term inserted:' . $term . ' id: ' . $term_to_insert );
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

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), count( $this->taxonomy ) );
		$this->log( $message );

		return $this;
	}

	public function clean_meta() {
		if ( $this->id == 0 ) {
			return $this;
		}

		foreach ( $this->meta as $key => $meta_value ) {
			delete_post_meta( $this->id, $key );
		}

		// also clear images
		delete_post_meta( $this->id, 'estate_property_gallery' );

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), count( $this->meta ) );
		$this->log( $message );

		return $this;
	}

	public function save_meta() {
		if ( $this->id == 0 ) {
			return $this;
		}

		foreach ( $this->meta as $key => $meta_value ) {
			update_post_meta( $this->id, $key, $meta_value );
		}

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), count( $this->meta ) );
		$this->log( $message );

		return $this;
	}

	public function save_images() {
		if ( $this->id == 0 ) {
			return $this;
		}

		if ( ! $this->images ) {
			return $this;
		}

		$images = new DP_Save_Images( 'estate_property_gallery' );

		foreach ( $this->images as $index => $image_url ) {
			$images->add( $image_url, $this->id, $index );
		}

		$images->store_data()->save_later();

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), count( $this->images ) );
		$this->log( $message );

		return $this;
	}

	public function save_sketches() {
		if ( $this->id == 0 ) {
			return $this;
		}

		if ( ! $this->sketches ) {
			return $this;
		}

		$images = new DP_Save_Images( 'estate_property_sketches' );

		foreach ( $this->sketches as $image_url ) {
			$images->add( $image_url, $this->id );
		}

		$images->store_data()->save_later();

		$message = sprintf( '%s, Time - %s, Objects - %s, Exit', __METHOD__, timer_stop(), count( $this->sketches ) );
		$this->log( $message );

		return $this;
	}



	public function __toString() {
		return var_export( $this );
	}

	/**
	 * @param string $message
	 */
	function log( $message ) {
		return;

		if ( defined('WP_DEBUG') && WP_DEBUG == true ) {
			$upload_dir = wp_upload_dir();
			$date       = date_i18n( 'Y-m-d H:i:s' ) . " | ";
			error_log( $date . $message . "\r\n", 3, trailingslashit( $upload_dir['basedir'] ) . __CLASS__ . '.log' );
		}
	}
}