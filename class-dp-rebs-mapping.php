<?php

abstract class DP_REBS_Mapping {
	protected $raw_data = array();
	protected $data = array();
	protected $mapping = array();
	protected $saved_fields = array();

	public function __construct( $data = array(), $mapping_data = array() ) {
		$this->set_data( $data, $mapping_data );
	}

	public function set_data( $data, $mapping_data = array() ) {
		$this->raw_data = $data;
		$this->mapping = $mapping_data;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_saved_fields() {
		return $this->saved_fields;
	}

	abstract public function map();
}