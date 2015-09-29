<?php

class DP_REBS_Post_Mapping extends DP_REBS_Mapping {

	public function __construct( $data = array(), $mapping_data = array() ) {
		parent::__construct( $data, $mapping_data );
		$this->saved_fields = array_merge( array( 'title' ), array( 'description' ), array( 'date_added', 'date_modified_by_user' ) );
	}

	public function map() {
		$this->set_title();
		$this->set_content();
		$this->set_time();
		$this->set_status();
		$this->set_old_id();
	}

	protected function set_title() {
		$this->data['post_title'] = $this->raw_data['title'];
	}
	protected function set_content() {
		$this->data['post_content'] = $this->raw_data['description'];
	}

	protected function set_time() {
		$this->data['post_date'] = date('Y-m-d H:i:s', strtotime($this->raw_data['date_added']) );
		$this->data['post_modified'] = date('Y-m-d H:i:s', strtotime($this->raw_data['date_modified_by_user']) );
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
				'post_status' => 'publish',
				'meta_value' => 'CP' . $this->raw_data['id'],
			)
		);

		if ( $old->found_posts == '1' )
			$this->data['ID'] = $old->post->ID;

		return $this;
	}
}