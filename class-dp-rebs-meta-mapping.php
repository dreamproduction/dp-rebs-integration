<?php

class DP_REBS_Meta_Mapping extends DP_REBS_Mapping {
	public $excluded = array( 'closed_transaction_type', 'cut', 'availability', 'date_modified', 'date_validated', 'images', 'internal_id', 'pot', 'promote_carousel', 'promote_commission_rent', 'promote_commission_sale', 'promote_custom_fields', 'promote_external', 'promote_featured', 'promote_flags', 'resource_uri', 'similar_properties', 'vat', 'vat_rent', 'vat_sale', 'verbose_price', 'tags_en', 'residential_complex', 'is_available', 'description_en', 'title_en', 'thumbnail', 'agency_site_url', 'tracking_code' );
	public $special = array('apartment_type' ,'building_structure' ,'comfort' ,'commercial_building_type' ,'construction_status' ,'floor' ,'house_type' ,'land_classification' ,'office_class','partitioning' ,'pedestrian_traffic' );

	public function set_data( $data, $mapping_data, $exclude = array() ) {
		$this->raw_data = $data;
		$this->mapping = $mapping_data;

		$this->excluded = array_merge( $this->excluded, (array) $exclude );
	}

	public function map() {
		$this->set_custom();
		$this->set_maps();
		$this->set_special();
		$this->set_destination();
		$this->set_common();

	}

	protected function set_custom() {
		$this->data['id'] = 'CP' . (string) $this->raw_data['id'];
		$this->data['price'] = (string) $this->raw_data['price_sale'];
		$this->data['size'] = (string) $this->raw_data['surface_built'];
		$this->data['size_unit'] = 'mp';

		$this->excluded = array_merge( $this->excluded, array( 'id', 'price_save', 'surface_built' ) );
		return $this;
	}
	protected function set_maps() {
		if ( $this->raw_data['lat'] && $this->raw_data['lng'] ) {
			$this->data['google_maps'] = array(
				'lat' => $this->raw_data['lat'],
				'lng' => $this->raw_data['lng'],
				'address' => implode( ', ', array_filter( array( $this->raw_data['city'], $this->raw_data['street'] ) ) )
			);

			$this->excluded = array_merge( $this->excluded, array( 'city', 'street', 'lat', 'lng' ) );
		}
		return $this;
	}

	protected function set_special() {
		foreach ( $this->special as $key ) {
			$this->excluded = array_merge( $this->excluded, array( $key ) );

			if ( $this->mapping[$key][$this->raw_data[$key]] )
				$this->data[$key] = $this->mapping[$key][$this->raw_data[$key]];
		}
		return $this;
	}

	protected function set_destination() {
		// for some reason destination can have multiple values
		foreach( $this->raw_data['destination'] as $v ) {
			$this->data['destination'][] = $v;
		}

		$this->excluded = array_merge( $this->excluded, array( 'destintion' ) );
		return $this;
	}

	protected function set_common() {
		// now save all the remaining meta
		foreach ( $this->raw_data as $key => $value ) {
			if ( ! $value )
				continue;

			if ( in_array( $key, $this->excluded ) )
				continue;

			if ( is_string( $value ) || is_scalar( $value ) || is_bool( $value ) ) {
				$this->data[ $key ] = (string) $value;
			}
		}
		return $this;
	}

}