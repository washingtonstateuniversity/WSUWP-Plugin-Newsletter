<?php

class WSU_Content_Type_Newsletter {

	var $post_type = 'wsu_newsletter';

	var $post_type_name = 'Newsletters';

	var $post_type_slug = 'newsletter';

	var $post_type_archive = 'newsletters';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type() {
		$labels = array(
			'name'               => $this->post_type_name,
			'singular_name'      => 'Newsletter',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Newsletter',
			'edit_item'          => 'Edit Newsletter',
			'new_item'           => 'New Newsletter',
			'all_items'          => 'All Newsletters',
			'view_item'          => 'View Newsletter',
			'search_items'       => 'Search Newsletters',
			'not_found'          => 'No newsletters found',
			'not_found_in_trash' => 'No newsletters found in Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Newsletters',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => $this->post_type_slug ),
			'capability_type'    => 'post',
			'has_archive'        => $this->post_type_archive,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'supports'           => array( 'title', 'editor' ),
			'taxonomies'         => array(),
		);

		register_post_type( $this->post_type, $args );
	}
}
$wsu_content_type_newsletter = new WSU_Content_Type_Newsletter();
