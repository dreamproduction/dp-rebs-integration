<?php
/**
 * Created by PhpStorm.
 * User: Dan
 * Date: 26.03.2015
 * Time: 16:20
 */

class DP_REBS_Property {
	var $api_url = 'http://demo.rebs-group.com/api/public/property/';
	var $url;
	var $data;

	function get_remote() {
		return $this;
	}

	function save_data() {
		return $this;
	}

	function get_url( $mode = 'global', $id = 0 ) {

		switch ( $mode ) {
			case 'single' :
				$this->url =  trailingslashit( $this->api_url . '/' . $id );
				add_query_arg(
					array(
						'format' => 'json',
					),
					$this->url
				);
				break;
			default :
				$this->url = add_query_arg(
					array(
						'format' => 'json',
						'date_modified_by_user__gte' => $this->last_modified
					),
					$this->api_url
				);
				break;
		}

		return $this;
	}
} 