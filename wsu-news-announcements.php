<?php
/*
Plugin Name: WSU News - Announcements
Plugin URI: http://news.wsu.edu/
Description: Creates an handles an Announcements content type for WSU News
Author: washingtonstateuniversity, jeremyfelt
Version: 0.3
*/

class WSU_News_Announcements {

	/**
	 * @var string The slug to register the announcement post type under.
	 */
	var $post_type = 'wsu_announcement';

	/**
	 * @var string The URL slug to use for a single announcement.
	 */
	var $post_type_slug = 'announcement';

	/**
	 * @var string The slug to use for announcement archives.
	 */
	var $post_type_archive = 'announcements';

	/**
	 * Set up the hooks used by WSU_News_Announcements
	 */
	public function __construct() {
		add_action( 'init',                               array( $this, 'register_post_type'       ) );
		add_action( 'wp_ajax_submit_announcement',        array( $this, 'ajax_callback'            ) );
		add_action( 'wp_ajax_nopriv_submit_announcement', array( $this, 'ajax_callback'            ) );
		add_action( 'generate_rewrite_rules',             array( $this, 'rewrite_rules'            ) );
		add_action( 'pre_get_posts',                      array( $this, 'modify_post_query'        ) );
		add_action( 'add_meta_boxes',                     array( $this, 'add_meta_boxes'           ) );

		add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'manage_list_table_email_column'              ), 10, 2 );
		add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'manage_list_table_announcement_dates_column' ), 10, 2 );

		add_filter( 'post_type_archive_title',                      array( $this, 'post_type_archive_title'   ), 10, 1 );
		add_filter( 'manage_edit-' . $this->post_type . '_columns', array( $this, 'manage_list_table_columns' ), 10, 1 );

		add_shortcode( 'wsu_announcement_form',           array( $this, 'output_announcement_form' ) );
	}

	/**
	 * Register the Announcement post type for the WSU News system.
	 *
	 * Single announcement item: http://news.wsu.edu/announcement/single-title-slug/
	 * Announcement archives:    http://news.wsu.edu/announcements/
	 */
	function register_post_type() {
		$labels = array(
			'name'               => 'Announcements',
			'singular_name'      => 'Announcement',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Announcement',
			'edit_item'          => 'Edit Announcement',
			'new_item'           => 'New Announcement',
			'all_items'          => 'All Announcements',
			'view_item'          => 'View Announcement',
			'search_items'       => 'Search Announcements',
			'not_found'          => 'No announcements found',
			'not_found_in_trash' => 'No announcements found in Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Announcements',
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
			'supports'           => array( 'title', 'editor', 'categories' ),
			'taxonomies'         => array( 'category', 'post_tag' ),
		);

		register_post_type( $this->post_type, $args );
	}

	/**
	 * Add meta boxes used in the announcement edit screen.
	 */
	function add_meta_boxes() {
		add_meta_box( 'wsu_announcement_email', 'Announcement Submitted By:', array( $this, 'display_email_meta_box' ), $this->post_type, 'side' );
		add_meta_box( 'wsu_announcement_dates', 'Announcement Dates:',        array( $this, 'display_dates_meta_box' ), $this->post_type, 'side' );
	}

	/**
	 * Display the email associated with the announcement submission.
	 *
	 * @param WP_Post $post Current post object.
	 */
	function display_email_meta_box( $post ) {
		$email = get_post_meta( $post->ID, '_announcement_contact_email', true );

		if ( ! $email )
			echo '<strong>No email submitted with announcement.';
		else
			echo esc_html( $email );
	}

	function display_dates_meta_box( $post ) {
		$results = $this->_get_announcement_date_meta( $post->ID );
		?>
		<p>This announcement will be published on the following announcement archive pages:</p>
		<ul>
		<?php
		foreach ( $results as $result ) {
			$date = str_replace( '_announcement_date_', '', $result->meta_key );

			if ( 4 == strlen( $date ) ) {
				echo '<li>Yearly: <a href="' . esc_url( site_url( $this->post_type_archive . '/' . $date ) ) . '" >' . $date . '</a></li>';
			}

			if ( 6 == strlen( $date ) ) {
				$date_url = substr( $date, 0, 4 ) . '/' . substr( $date, 4, 2 );
				$date_display = substr( $date, 4, 2 ) . '/' . substr( $date, 0, 4 );
				echo '<li>Monthly: <a href="' . esc_url( site_url( $this->post_type_archive . '/' . $date_url ) ) . '" >' . $date_display . '</a></li>';
			}

			if ( 8 == strlen( $date ) ) {
				$date_url = substr( $date, 0, 4 ) . '/' . substr( $date, 4, 2 ) . '/' . substr( $date, 6, 2 );
				$date_display = substr( $date, 4, 2 ) . '/' . substr( $date, 6, 2 ) . '/' . substr( $date, 0, 4 );
				echo '<li>Daily: <a href="' . esc_url( site_url( $this->post_type_archive . '/' . $date_url ) ) . '" >' . $date_display . '</a></li>';
			}
		}
		echo '</ul>';
	}

	/**
	 * Modify rewrite rules to include support for additional requirements.
	 *
	 * We primarily want to add support for date based archives to announcements. This may
	 * involve some trickery as our true date information is stored in post meta and will
	 * not use the standard day/month/year data passed to us.
	 *
	 * @param WP_Rewrite $wp_rewrite Existing rewrite rules.
	 *
	 * @return WP_Rewrite Modified set of rewrite rules.
	 */
	function rewrite_rules( $wp_rewrite ) {
		$rules = array();

		$dates = array(
			array(
				'rule' => "([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})",
				'vars' => array( 'year', 'monthnum', 'day' ) ),
			array(
				'rule' => "([0-9]{4})/([0-9]{1,2})",
				'vars' => array( 'year', 'monthnum' ) ),
			array(
				'rule' => "([0-9]{4})",
				'vars' => array( 'year' ) ),
		);

		foreach ( $dates as $data ) {
			$query = 'index.php?post_type=' . $this->post_type;
			$rule = $this->post_type_archive . '/' . $data['rule'];

			$i = 1;
			foreach ( $data['vars'] as $var ) {
				$query .= '&' . $var . '=' . $wp_rewrite->preg_index( $i );
				$i++;
			}

			$rules[ $rule . "/?$"                               ] = $query;
			$rules[ $rule . "/feed/(feed|rdf|rss|rss2|atom)/?$" ] = $query . "&feed="  . $wp_rewrite->preg_index( $i );
			$rules[ $rule . "/(feed|rdf|rss|rss2|atom)/?$"      ] = $query . "&feed="  . $wp_rewrite->preg_index( $i );
			$rules[ $rule . "/page/([0-9]{1,})/?$"              ] = $query . "&paged=" . $wp_rewrite->preg_index( $i );
		}

		$wp_rewrite->rules = $rules + $wp_rewrite->rules;

		return $wp_rewrite;
	}

	/**
	 * Modify the post query to load posts based on our custom date meta.
	 *
	 * @param WP_Query $query The query object currently in progress.
	 */
	function modify_post_query( $query ) {

		if ( is_admin() || ! is_post_type_archive( $this->post_type ) )
			return;

		if ( ! $query->is_main_query() )
			return;

		// Not to much of an archive if we don't have the year.
		if ( ! isset( $query->query['year'] ) )
			return;

		$query_date = $query->query['year'];
		$query->set( 'year', '' );

		if ( isset( $query->query['monthnum'] ) ) {
			$query_date .= $query->query['monthnum'];
			$query->set( 'monthnum', '' );
		}

		if ( isset( $query->query['day'] ) ) {
			$query_date .= $query->query['day'];
			$query->set( 'day', '' );
		}

		$query->set( 'meta_query', array(
				array(
					'key' => '_announcement_date_' . $query_date,
					'value' => 1,
					'compare' => '=',
					'type' => 'numeric'
				)
			)
		);

	}

	/**
	 * Setup the announcement form for output when the shortcode is used.
	 *
	 * @return string Contains form to be output.
	 */
	function output_announcement_form() {
		// Enqueue jQuery UI's datepicker to provide an interface for the publish date(s).
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'jquery-ui-core', 'http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css' );

		// Enqueue the Javascript needed to handle the form submission properly.
		wp_enqueue_script( 'wsu-news-announcement-form', plugins_url( 'wsu-news-announcements/js/announcements-form.js' ), array(), false, true );

		// Provide a global variable containing the ajax URL that we can access
		wp_localize_script( 'wsu-news-announcement-form', 'announcementSubmission', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
		wp_enqueue_style( 'wsu-news-announcement-form', plugins_url( 'wsu-news-announcements/css/announcements-form.css' ) );

		// Build the output to return for use by the shortcode.
		ob_start();
		?>
		<div id="announcement-submission-form" class="announcement-form" action="">
			<form action="#">
				<label for="announcement-form-title">Announcement Title:</label>
				<input type="text" id="announcement-form-title" class="announcement-form-input" name="announcement-title" value="" />
				<label for="announcement-form-text">Announcement Text:</label>
				<?php
				$editor_settings = array(
					'wpautop'       => true,
					'media_buttons' => false,
					'textarea_name' => 'announcement-text',
					'textarea_rows' => 15,
					'editor_class'  => 'announcement-form-input',
					'teeny'         => false,
					'dfw'           => false,
					'tinymce'       => array(
						'theme_advanced_disable' => 'wp_more, fullscreen, wp_help',
					),
					'quicktags'     => false,
				);
				wp_editor( '', 'announcement-form-text', $editor_settings );
				?>
				<label for="announcement-form-date">What date(s) should this announcement be published on?</label><br>
				<input type="text" id="announcement-form-date1" class="announcement-form-input announcement-form-date-input" name="announcement-date[]" value="" />
				<input type="text" id="announcement-form-date2" class="announcement-form-input announcement-form-date-input" name="announcement-date[]" value="" />
				<input type="text" id="announcement-form-date3" class="announcement-form-input announcement-form-date-input" name="announcement-date[]" value="" />
				<br>
				<br>
				<label for="announcement-form-email">Your Email Address:</label><br>
				<input type="text" id="announcement-form-email" class="announcement-form-input" name="announcement-email" value="" />
				<div id="announcement-other-wrap">
					If you see the following input box, please leave it empty.
					<label for="announcement-form-other">Other Input:</label>
					<input type="text" id="announcement-form-other" class="announcement-form-input" name="announcement-other" value="" />
				</div>
				<input type="submit" id="announcement-form-submit" class="announcement-form-input" value="Submit Announcement" />
			</form>
		</div>
		<?php
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}

	/**
	 * Handle the ajax submission of the announcement form.
	 */
	function ajax_callback() {
		if ( ! DOING_AJAX || ! isset( $_POST['action'] ) || 'submit_announcement' !== $_POST['action'] )
			die();

		// If the honeypot input has anything filled in, we can bail.
		if ( isset( $_POST['other'] ) && '' !== $_POST['other'] )
			die();

		$title = $_POST['title'];
		$text  = wp_kses_post( $_POST['text'] );
		$email = sanitize_email( $_POST['email'] );

		// If a websubmission user exists, we'll use that user ID.
		$user = get_user_by( 'slug', 'websubmission' );
		if ( is_wp_error( $user ) )
			$user_id = 0;
		else
			$user_id = $user->ID;

		$formatted_dates = array();
		foreach( $_POST['dates'] as $date ) {
			$formatted_dates[] = strtotime( $date );
		}
		sort( $formatted_dates );
		$post_date = date( 'Y-m-d H:i:s', $formatted_dates[0] );

		$post_data = array(
			'comment_status' => 'closed',
			'pint_status'    => 'closed',
			'post_author'    => $user_id,
			'post_content'   => $text,    // Sanitized with wp_kses_post(), probably overly so.
			'post_title'     => $title,   // Sanitized in wp_insert_post().
			'post_type'      => 'wsu_announcement',
			'post_status'    => 'pending',
			'post_date'      => $post_date,
		);
		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			echo 'error';
			exit;
		}

		update_post_meta( $post_id, '_announcement_contact_email', $email );

		// Capture the various days, months, and years on which this announcement should appear
		// and update post meta accordingly so that we can perform custom queries as needed.
		foreach( $formatted_dates as $date ) {
			$date_formatted  = date( 'Ymd', $date );
			$month_formatted = date( 'Ym',  $date );
			$year_formatted  = date( 'Y',   $date );

			update_post_meta( $post_id, '_announcement_date_'  . $date_formatted,  1 );
			update_post_meta( $post_id, '_announcement_date_'  . $month_formatted, 1 );
			update_post_meta( $post_id, '_announcement_date_'  . $year_formatted,  1 );
		}

		echo 'success';
		exit;
	}

	/**
	 * Modify the WSU Announcements post type archive title to properly show date information.
	 *
	 * @param string $name Current title for the archive.
	 *
	 * @return string Modified title for the archive.
	 */
	public function post_type_archive_title( $name ) {

		if ( 'Announcements' !== $name )
			return $name;

		if ( is_day() ) :
			return get_the_date( 'F jS, Y ' ) . $name;
		elseif ( is_month() ) :
			return get_the_date( 'F Y ' ) . $name;
		elseif ( is_year() ) :
			return get_the_date( 'Y ' ) . $name;
		else :
			return $name;
		endif;
	}

	/**
	 * Modify the columns in the post type list table.
	 *
	 * @param array $columns Current list of columns and their names.
	 *
	 * @return array Modified list of columns.
	 */
	public function manage_list_table_columns( $columns ) {
		// We may use categories and tags, but we don't need them on this screen.
		unset( $columns['categories'] );
		unset( $columns['tags'] );
		unset( $columns['date'] );

		$columns['contact_email'] = 'Contact Email';
		$columns['announce_dates'] = 'Announcement Dates';
		$columns['date'] = 'Publish Date';

		return $columns;
	}

	private function _get_announcement_date_meta( $post_id ) {
		global $wpdb;

		$announcement_date = '_announcement_date_%';
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE post_id = %d and meta_key LIKE %s GROUP BY meta_key", $post_id, $announcement_date ) );

		return $results;
	}

	public function manage_list_table_email_column( $column_name, $post_id ) {
		if ( 'contact_email' !== $column_name )
			return;

		if ( $contact_email = get_post_meta( $post_id, '_announcement_contact_email', true ) )
			echo esc_html( $contact_email );
	}

	public function manage_list_table_announcement_dates_column( $column_name, $post_id ) {
		if ( 'announce_dates' !== $column_name )
			return;

	}
}
new WSU_News_Announcements();
