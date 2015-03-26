<?php

class DP_REBS_Fields {
	function __construct() {

	}

	function update_schema() {
		return $this;
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


}