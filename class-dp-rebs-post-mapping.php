<?php

class DP_REBS_Post_Mapping {
	protected $raw_data = array();
	protected $data = array();
	protected $saved_fields = array();

	public function __construct( $data ) {
		$this->raw_data = $data;

		$this->set_title()->set_content()->set_time()->set_status()->set_old_id();
	}

	public function get_data() {
		return $this->data;
	}

	public function get_saved_fields() {
		return $this->saved_fields;
	}

	protected function set_title() {
		$this->data['post_title'] = $this->raw_data['title'];
		$this->saved_fields = $this->saved_fields + array( 'title' );
		return $this;
	}
	protected function set_content() {
		$this->data['post_content'] = $this->raw_data['description'];
		$this->saved_fields = $this->saved_fields + array( 'description' );
		return $this;
	}

	protected function set_time() {
		$this->data['post_date'] = date('Y-m-d H:i:s', strtotime($this->raw_data['date_added']) );
		$this->data['post_modified'] = date('Y-m-d H:i:s', strtotime($this->raw_data['date_modified_by_user']) );
		$this->saved_fields = $this->saved_fields + array( 'date_added', 'date_modified_by_user' );
		return $this;
	}

	protected function set_status() {
		$this->data['post_type'] = 'property';
		$this->data['post_status'] = 'publish';

		return $this;
	}

	protected function set_old_id() {

		$old = new WP_Query(
			array(
				'post_type' => 'property',
				'suppress_filters' => false,
				'posts_per_page' => 1,
				'post_status' => 'any',
				'meta_query' => array(
					'value' => 'CP' . $this->raw_data['id'],
					'compare' => '='
				)
			)
		);

		$this->data['ID'] = $old->post->ID;

		return $this;
	}
}