<?php

class DP_Save_Images {

	protected $data = array();

	public function __construct( $name, $later_name = 'dp_later' ) {
		$this->name = $name;
		$this->later_name = $later_name;
		$this->later_actions = array();

		$this->set_data();
	}

	public function add( $url, $parent_id, $index = null ) {
		// put key the filename for easier array_unique
		$parts = parse_url( $url );
		$key = basename( $parts['path'] );
		$this->data[$key] = array( 'key' => $key, 'url' => $url, 'parent_id' => $parent_id, 'index' => $index );
		$this->later_actions[$key] = array( __CLASS__, 'save', $this->name, $this->data[$key] );
		return $this;
	}

	public function remove( $key ) {
		unset( $this->data[$key] );
		$this->store_data();

		return $this;
	}

	public function save( $element ) {

		$this->set_data();

		if ( ! $element || ! isset( $this->data[ $element['key'] ] ) ) {
			return $this;
		}

		$this->remove( $element['key'] );
		$this->store_data();

		$image_id = self::import_external_image( $element['url'], $element['parent_id'] );

        if ( $image_id && ! has_post_thumbnail( $element['parent_id'] ) ) {
            set_post_thumbnail( $element['parent_id'], $image_id );
        }

		if ( ! $image_id )
			return $this;

		// will always return array
        $previous_images = get_post_meta( $element['parent_id'], $this->name, true );
		// make a copy for extra checks
		$new_images = $previous_images;
		// use array_search instead of in_array for indexing later
		$pos_old_images = array_search( $image_id, $previous_images );

		// just position is changed, unset old pos
        if ( $pos_old_images !== false ) {
	        unset( $new_images[$pos_old_images] );
        }

		// save with position if needed
		if ( $element['index'] )
			$new_images[$element['index']] = $image_id;
		else
			$new_images[] = $image_id;

		update_post_meta( $element['parent_id'], $this->name, $new_images );


/*		if ( class_exists('Sitepress') ) {
			global $sitepress;

			$translated_ids = $sitepress->get_element_translations( $element['parent_id'], 'post_property' );
			foreach ( $translated_ids as $translated_id ) {
				$translated_prev_images = get_post_meta( $translated_id, $this->name, false );
				if ( ! in_array( $image_id, $translated_prev_images ) ) {
					if ( $image_id )
						add_post_meta( $translated_id, $this->name, $image_id, false );
				}
			}
		}*/





		$this->log( sprintf( "Save image %s to parent %d", $element['url'], $element['parent_id'] ) );

		return $this;
	}

	function save_later() {
		$later_actions = get_option( $this->later_name, array() );
		foreach ( $this->later_actions as $key => $action ) {
			$later_actions[$key] = $action;
		}
		update_option( $this->later_name, $later_actions );

		return $this;
	}

	public function store_data() {
		// prefix option
		update_option( 'dp' . $this->name, $this->data );
		return $this;
	}

	public function set_data() {
		// prefix option
		$this->data = get_option( 'dp' . $this->name, array() );
		return $this;
	}

	public function get_data() {
		return $this->data;
	}

	/**
	 * Handle importing of external image.
	 * Most of this taken from WordPress function 'media_sideload_image'.
	 *
	 * @param string $url The URL of the image to download
	 * @param int $post_id The post ID the media is to be associated with
	 *
	 * @return string|bool Image id on success, false on failure
	 */
	static function import_external_image( $url , $post_id ) {

		if ( ! function_exists( 'download_url' ) )
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		if ( ! function_exists( 'media_handle_sideload' ) )
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
		if ( ! function_exists( 'wp_read_image_metadata' ) )
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

		if ( ! empty($url) && self::is_valid_image( $url ) ) {

			$file_array = array();

			// Set variables for storage
			// fix file filename for query strings
			preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
			$file_array['name'] = basename($matches[0]);

			if ( $id = self::is_stored_image( $file_array['name'] ) )
				return $id;

			// Download file to temp location
			$file_array['tmp_name'] = download_url( $url );

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

	/**
	 * @param string $message
	 */
	function log( $message ) {
		$upload_dir = wp_upload_dir();
		$date = date_i18n( 'Y-m-d H:i:s' ) . " | ";
		error_log( $message . "\r\n", 3, trailingslashit( $upload_dir['basedir'] ) . __CLASS__ .  '.log' );
	}

	static function is_valid_image( $filename ) {

		$allowed = array( '.jpg' , '.png', '.bmp' , '.gif' );

		$ext = substr( $filename , -4 );

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
	static function is_stored_image( $filename ) {
		/** @var wpdb $wpdb */
		global $wpdb;
		return  $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='attachment'", $filename ));
	}
}
