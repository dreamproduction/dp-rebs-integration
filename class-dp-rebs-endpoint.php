<?php

class DP_REBS_Endpoint {
	protected $endpoint = 'rebs-id';

	public function __construct() {
		// Handle redirect endpoints
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_action( 'template_redirect', array( $this, 'redirect' ) );
	}

	/**
	 * Add endpoint to rewrite rules
	 */
	public function add_rewrite() {
		// add endpoint for root domain
		add_rewrite_endpoint( $this->endpoint, EP_ROOT );
	}

	/**
	 * Handle redirects on endpoint
	 */
	public function redirect() {
		global $wp_query;

		// if this is not our request then bail
		if ( ! isset( $wp_query->query_vars[ $this->endpoint ] ) )
			return;

		$property_id = str_ireplace( 'cp', '', $wp_query->query_vars[ $this->endpoint ] );
		$meta_id = 'CP' . $property_id;

		// search a property with queried ID
		$exists = get_posts(
			array(
				'post_type' => 'property',
				'suppress_filters' => false,
				'posts_per_page' => 1,
				'post_status' => 'publish',
				'meta_value' => $meta_id,
			)
		);

		if ( $exists ) {
			// redirect to the property permalink
			wp_redirect( get_permalink( $exists[0]->ID ), 301 );
			exit;
		}

		// show 404, hook late
		add_filter( 'template_include', 'get_404_template', 99 );

	}

}