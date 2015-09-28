<?php

class DP_Save_Images {

	protected $data = array();

	public function add( $url, $parent_id, $index = null ) {
		// put key the filename for easier array_unique
		$parts = parse_url( $url );
		$key = basename( $parts['path'] );
		$this->data[$key] = array( 'key' => $key, 'url' => $url, 'parent_id' => $parent_id, 'index' => $index );
		return $this;
	}

	public function remove( $key ) {
		unset( $this->data[$key] );

		return $this;
	}

	public function get( $key ) {
		return $this->data[$key];
	}

	public function save( $element ) {

		if ( ! $element || ! isset( $this->data[ $element['key'] ] ) ) {
			self::log('bail early: ' . $element['key']);
			return $this;
		}

		$image_id = self::import_external_image( $element['url'], $element['parent_id'] );

		if ( ! $image_id ) {
			self::log( 'no image id, image not imported' );
		}

		$this->data[ $element['key'] ]['id'] = $image_id;

		self::log( sprintf( "Save image %s to parent %d", $element['url'], $element['parent_id'] ) );

		return $this;
	}

	public function save_all() {
		foreach ( $this->data as $element ) {
			set_time_limit(60);
			$this->save( $element );
		}
		return $this;
	}

	public function get_ids() {
		$ids = array();
		foreach ( $this->data as $element ) {
			if ( isset($element['id']) && $element['id'] )
				$ids[$element['index']] = $element['id'];
		}
		return $ids;
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

			if ( $id = self::is_stored_image( $file_array['name'] ) ) {
				self::log('Image already storred: ' . $id . ' for parent: ' . $post_id);
				return $id;
			}


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
				return 0;
			}

			return $id;
		}

		return 0;
	}

	/**
	 * @param string $message
	 */
	static function log( $message ) {
		$upload_dir = wp_upload_dir();
		$date = date_i18n( 'Y-m-d H:i:s' ) . " | ";
		error_log( $date . $message . "\r\n", 3, trailingslashit( $upload_dir['basedir'] ) . __CLASS__ .  '.log' );
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
