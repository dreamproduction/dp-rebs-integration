<?php

class DP_REBS_Meta_Mapping {
	protected $raw_data = array();
	protected $data = array();
	protected $mapping = array();
	protected $saved_fields = array();
	public $excluded = array( 'closed_transaction_type', 'cut', 'availability', 'date_modified', 'date_validated', 'images', 'internal_id', 'pot', 'promote_carousel', 'promote_commission_rent', 'promote_commission_sale', 'promote_custom_fields', 'promote_external', 'promote_featured', 'promote_flags', 'resource_uri', 'similar_properties', 'vat', 'vat_rent', 'vat_sale', 'verbose_price', 'tags_en', 'residential_complex', 'is_available', 'description_en', 'title_en', 'thumbnail', );
	public $special = array('apartment_type' ,'building_structure' ,'comfort' ,'commercial_building_type' ,'construction_status' ,'floor' ,'house_type' ,'land_classification' ,'office_class','partitioning' ,'pedestrian_traffic' );

	public function __construct( $data, $mapping_data, $exclude ) {
		$this->raw_data = $data;
		$this->mapping = $mapping_data;

		$this->excluded = $this->excluded + (array) $exclude;

		$this->set_custom()->set_maps()->set_special()->set_destination()->set_common();
	}

	public function get_data() {
		return $this->data;
	}

	public function get_saved_fields() {
		return $this->saved_fields;
	}

	protected function set_custom() {
		$this->data['estate_property_id'] = 'CP' . (string) $this->raw_data['id'];
		$this->data['estate_property_price'] = (string) $this->raw_data['price_sale'];
		$this->data['estate_property_size'] = (string) $this->raw_data['surface_built'];
		$this->data['estate_property_size_unit'] = 'mp';

		$this->excluded = $this->excluded + array( 'id', 'price_save', 'surface_built' );
		return $this;
	}
	protected function set_maps() {
		if ( $this->raw_data['lat'] && $this->raw_data['lng'] ) {
			$this->data['estate_property_google_maps'] = array(
				'lat' => $this->raw_data['lat'],
				'lng' => $this->raw_data['lng'],
				'address' => implode( ', ', array_filter( array( $this->raw_data['city'], $this->raw_data['street'] ) ) )
			);

			$this->excluded = $this->excluded + array( 'city', 'street', 'lat', 'lng' );
		}
		return $this;
	}

	protected function set_special() {
		foreach ( $this->special as $key ) {
			$this->data['estate_property_' . $key] = $this->mapping[$key][$this->raw_data[$key]];

			$this->excluded = $this->excluded + array( $key );
		}
		return $this;
	}

	protected function set_destination() {
		// for some reason destination can have multiple values
		foreach( $this->raw_data['destination'] as $v ) {
			$this->data['estate_property_destination'] = $v;
		}

		$this->excluded = $this->excluded + array( 'destintion' );
		return $this;
	}

	protected function set_common() {
		// now save all the remaining meta
		foreach ( $this->raw_data as $key => $value ) {
			if ( in_array( $key, $this->excluded ) )
				continue;

			if ( is_string( $value ) || is_scalar( $value ) || is_bool( $value ) ) {
				$this->data[ 'estate_property_' . $key ] = (string) $value;
			}
		}
		return $this;
	}

}