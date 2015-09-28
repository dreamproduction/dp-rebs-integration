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

	public function __construct( $schema ) {
		$this->set_schema( $schema )->set_fields_options();
	}

	public function set_data( $data ) {
		$this->data = $data;
		$this->map_fields();
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

	protected function map_fields() {

		$this->images = $this->data['full_images'];
		$this->sketches =  $this->data['sketches'];
		$this->agent = $this->data['agent'];

		$taxonomies = new DP_REBS_Taxonomy_Mapping( $this->data, $this->fields );
		$object = new DP_REBS_Post_Mapping( $this->data );
		$exclude = $taxonomies->get_saved_fields() + $object->get_saved_fields() + array( 'full_images', 'sketches', 'agent' );
		$meta = new DP_REBS_Meta_Mapping( $this->data, $this->fields, $exclude );

		// multidimensional array with taxonomy names and actual term ids
		$this->taxonomy = $taxonomies->get_data();
		// array with data ready to save
		$this->object = $object->get_data();
		// array with data ready to save
		$this->meta = $meta->get_data();

		return $this;
	}

	public function delete_object() {
		wp_delete_post( $this->id, true );
	}

	public function save_object() {
		// actual insert. returns 0 on failure
		$this->id = wp_insert_post( $this->object, false );

		// save ID as early as possible to avoid duplicates
		add_post_meta( $this->id, 'estate_property_id', $this->meta['estate_property_id'], true );

		return $this;
	}

    public function save_agent() {
	    if ( $this->id == 0 ) return $this;

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

		//update_user_meta( $user_id, 'office_phone_number', $this->agent['phone'] );
		update_user_meta( $user_id, 'company_name', $this->agent['position'] );
		$user_image_id = DP_Save_Images::import_external_image( $this->agent['avatar'], 0 );
		$user_image = wp_get_attachment_image_src( $user_image_id, 'full' );
		update_user_meta( $user_id, 'user_image', $user_image[0] );

	    wp_update_post( array( 'ID' => $this->id, 'post_author' => $user_id ) );

        // associate agent with the property
        update_post_meta( $this->id, 'estate_property_custom_agent', $user_id );

        return $this;
    }

	public function save_taxonomy() {
		if ( $this->id == 0 ) return $this;

		$this->clean_taxonomy();

		foreach ( $this->taxonomy as $taxonomy => $terms ) {
			wp_set_object_terms( $this->id, $terms, $taxonomy );
		}

		return $this;
	}

	public function save_meta() {
		if ( $this->id == 0 ) return $this;

		$this->clean_meta();

		foreach ( $this->meta as $key => $meta_value ) {
			update_post_meta( $this->id, $key, $meta_value );
		}

		return $this;
	}

	public function save_images() {
		if ( $this->id == 0 || ! $this->images ) return $this;

		$this->clean_images();

		$images = new DP_Save_Images();
		$count = 1;

		foreach ( $this->images as $index => $image_url ) {
			if ( $count > 25 )
				continue;
			$images->add( $image_url, $this->id, $index );
			$count++;
		}
		$images->save_all();

		$ids = $images->get_ids();

		update_post_meta( $this->id, 'estate_property_gallery', $ids );

		set_post_thumbnail( $this->id, reset( $ids ) );

		return $this;
	}

	public function save_sketches() {
		if ( $this->id == 0 || ! $this->sketches ) return $this;

		$this->clean_sketches();

		$images = new DP_Save_Images();

		foreach ( $this->sketches as $index => $image_url ) {
			$images->add( $image_url, $this->id, $index );
		}
		$ids = $images->save_all()->get_ids();

		update_post_meta( $this->id, 'estate_property_sketches', $ids );

		return $this;
	}

	protected function clean_taxonomy() {
		$taxonomies = array_keys( $this->taxonomy );
		wp_delete_object_term_relationships( $this->id, $taxonomies );
	}

	protected function clean_meta() {
		foreach ( $this->meta as $key => $meta_value ) {
			delete_post_meta( $this->id, $key );
		}
	}

	protected function clean_images() {
		delete_post_meta( $this->id, 'estate_property_gallery' );
		// also clear featured image
		delete_post_meta( $this->id, '_thumbnail_id' );
	}

	protected function clean_sketches() {
		delete_post_meta( $this->id, 'estate_property_sketches' );
	}

	/**
	 * @param string $message
	 */
	function log( $message ) {
		if ( defined('WP_DEBUG') && WP_DEBUG === true ) {
			$upload_dir = wp_upload_dir();
			$date       = date_i18n( 'Y-m-d H:i:s' ) . " | ";
			error_log( $date . $message . "\r\n", 3, trailingslashit( $upload_dir['basedir'] ) . __CLASS__ . '.log' );
		}
	}
}