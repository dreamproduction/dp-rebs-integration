<?php

class DP_REBS_Taxonomy_Mapping {
	protected $raw_data = array();
	protected $data = array();
	protected $mapping = array();
	protected $saved_fields = array();


	function __construct( $data, $mapping_data ) {
		$this->raw_data = $data;
		$this->mapping = $mapping_data;

		$this->set_features()->set_location()->set_status()->set_type();

	}

	public function get_data() {
		return $this->data;
	}

	public function get_saved_fields() {
		return $this->saved_fields;
	}

	protected function set_features() {
		$this->data['property-features'] = array();

		foreach ( $this->raw_data['tags'] as $parent_name => $term_array ) {

			$this->data['property-features'][] = $parent_id = self::name_to_id( $parent_name, 'property-features' );

			foreach ( $term_array as $term_name ) {
				$this->data['property-features'][] = self::name_to_id( $term_name, 'property-features', array( 'parent' => $parent_id ) );
			}

		}

		$this->saved_fields = $this->saved_fields + array('tags');

		return $this;
	}

	protected function set_location() {
		$this->data['property-location'] = array();

		$region_name = $this->raw_data['region'];
		$city_name = $this->raw_data['city'];
		$zone_name = $this->raw_data['zone'];

		// hierarchy is: region > city > zone

		$region_id = self::name_to_id( $region_name, 'property-location' );

		// add if not exists with parent in slug, as wp-all-import does it
		$city_id = self::name_to_id( $city_name, 'property-location', array(
			'parent' => $region_id,
			'slug' => sanitize_title( sprintf( '%s %s', $city_name, $region_name ) )
			)
		);

		// add if not exists with parent in slug, as wp-all-import does it
		$zone_id = self::name_to_id( $city_name, 'property-location', array(
				'parent' => $city_id,
				'slug' => sanitize_title( sprintf( '%s %s %s', $zone_name, $city_name, $region_name ) )
			)
		);

		$this->data['property-location'] = array( $zone_id, $city_id, $region_id );

		$this->saved_fields = $this->saved_fields + array('region', 'zone', 'city');

		return $this;
	}

	protected function set_status() {

		if ( $this->raw_data['for_rent'] === true )
			$this->data['property-status'][] = self::name_to_id( 'De închiriat', 'property-status' );

		if ( $this->raw_data['for_sale'] === true )
			$this->data['property-status'][] = self::name_to_id( 'De vânzare', 'property-status' );

		$this->saved_fields = $this->saved_fields + array('for_rent', 'for_sale' );

		return $this;
	}

	protected function set_type() {
		$this->data['property-type'] = array();

		$term_names = array();
		$numeric_type =  $this->raw_data['property_type'];

		/* 6 == Teren */
		if ( $numeric_type == 6 ) {
			foreach( $this->raw_data['destination'] as $v ) {
				$term_names[] = 'Teren ' . $v;
			}
		} else {
			$term_names[] = $this->mapping['property_type'][$numeric_type];

		}

		foreach ( $term_names as $term_name ) {
			$this->data['property-type'][] = self::name_to_id( $term_name, 'property-type' );
		}

		$this->saved_fields = $this->saved_fields + array( 'property_type' );
		return $this;
	}

	private static function name_to_id( $name, $taxonomy, $args = array() ) {
		// if no parent required, we rather search by name, not by slug - with term_exists
		if ( isset( $args['parent'] ) ) {
			$term = term_exists( $name, $taxonomy, $args['parent'] );
		} else {
			$term = get_term_by( 'name', $name, $taxonomy, ARRAY_A );
		}

		if ( !$term || is_wp_error( $term ) )
			$term = wp_insert_term( $name, $taxonomy, $args );

		if ( $term && ! is_wp_error( $term ) )
			return $term['term_id'];

		return 0;
	}

}