<?php

class DP_REBS_AllImport_Compat {
	public function __construct() {
		add_action( 'pmxi_gallery_image', array( $this, 'save_to_gallery' ), 10, 2 );

		add_action( 'pmxi_update_post_meta', array( $this, 'convert_agent' ), 10, 3 );
	}

	/**
	 * Save image id into gallery array
	 * @param $post_id
	 * @param $image_id
	 */
	function save_to_gallery( $post_id, $image_id ) {
		$previous_images = get_post_meta( $post_id, 'estate_property_gallery', true );
		if ( empty( $previous_images ) )
			$previous_images = array();

		if ( ! in_array( $image_id, $previous_images ) ) {
			if ( $image_id ) {
				$new_images = $previous_images;
				$new_images[] = $image_id;
				update_post_meta( $post_id, 'estate_property_gallery', $new_images, $previous_images );
			}
		}
	}

	/**
	 * Convert email into user ID
	 *
	 * @param $post_id
	 * @param $meta_key
	 * @param $meta_value
	 */
	function convert_agent( $post_id, $meta_key, $meta_value ) {
		if ( $meta_key == 'estate_property_custom_agent' ) {
			$user = get_user_by( 'email', $meta_value );
			if ( !is_wp_error( $user ) )
				update_post_meta( $post_id, $meta_key, $user->ID );
		}
	}
}