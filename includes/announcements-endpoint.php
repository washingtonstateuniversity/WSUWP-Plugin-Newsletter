<?php

namespace WSU\News\Internal\Announcements;

add_action( 'rest_api_init', 'WSU\News\Internal\Announcements\register_rest_endpoint' );

/**
 * Register a custom endpoint to handle lookups for announcements.
 *
 * @since 1.10.0
 */
function register_rest_endpoint() {
	\register_rest_route( 'insider/v1', '/announcements', array(
		'methods'  => 'GET',
		'callback' => 'WSU\News\Internal\Announcements\rest_announcements',
	) );
}

/**
 * Return the day's announcements.
 *
 * @since 1.10.0
 *
 * @param \WP_REST_Request $request
 *
 * @return array
 */
function rest_announcements() {
	$query_date = date( 'Ymd', time() - ( 8 * HOUR_IN_SECONDS ) );

	$results = new \WP_Query( array(
		'post_type' => 'wsu_announcement',
		'post_status' => 'publish',
		'meta_query' => array(
			array(
				'key' => '_announcement_date_' . $query_date,
				'value' => 1,
				'compare' => '=',
				'type' => 'numeric',
			),
		),
		'posts_per_page' => 50,
	) );

	$posts = array();
	foreach ( $results->posts as $post ) {
		if ( ! $post->ID ) {
			continue;
		}

		$posts[] = array(
			'title' => $post->post_title,
			'url' => get_permalink( $post->ID ),
			'date' => $post->post_date,
			'excerpt' => $post->post_content,
		);
	}

	return $posts;
}
