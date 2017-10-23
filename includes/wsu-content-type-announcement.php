<?php
/**
 * Provide a content type to handle announcements separately from news
 *
 * Class WSU_Content_Type_Announcement
 */
class WSU_Content_Type_Announcement {

	/**
	 * @var string The slug to register the announcement post type under.
	 */
	var $post_type = 'wsu_announcement';

	/**
	 * @var string The URL slug to use for a single announcement.
	 */
	var $post_type_slug = 'announcement';

	/**
	 * @var string The general name used for the post type.
	 */
	var $post_type_name = 'Announcements';

	/**
	 * @var string The slug to use for announcement archives.
	 */
	var $post_type_archive = 'announcements';

	/**
	 * @var string Key used for storing the announcement calendar in cache.
	 */
	var $calendar_cache_key = 'wsu_announcement_calendar';

	/**
	 * Set up the hooks used by WSU_Content_Type_Announcement
	 */
	public function __construct() {
		add_action( 'init',                               array( $this, 'register_post_type' ) );
		add_action( 'wp_ajax_submit_announcement',        array( $this, 'ajax_callback' ) );
		add_action( 'wp_ajax_nopriv_submit_announcement', array( $this, 'ajax_callback' ) );
		add_action( 'generate_rewrite_rules',             array( $this, 'rewrite_rules' ) );
		add_action( 'pre_get_posts',                      array( $this, 'modify_post_query' ) );
		add_action( 'add_meta_boxes',                     array( $this, 'add_meta_boxes' ) );
		add_action( 'widgets_init',                       array( $this, 'register_widget' ) );
		add_action( 'save_post',                          array( $this, 'delete_calendar_cache' ), 20, 1 );
		add_action( 'delete_post',                        array( $this, 'delete_calendar_cache' ), 20, 1 );
		add_action( 'update_option_start_of_week',        array( $this, 'delete_calendar_cache' ) );
		add_action( 'update_option_gmt_offset',           array( $this, 'delete_calendar_cache' ) );
		add_action( 'save_post',                          array( $this, 'save_announcement_dates' ), 10, 2 );
		add_action( 'save_post',                          array( $this, 'save_announcement_url' ), 10, 2 );
		add_action( 'admin_enqueue_scripts',              array( $this, 'enqueue_admin_scripts' ) );

		add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'manage_list_table_email_column' ), 10, 2 );
		add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'manage_list_table_announcement_dates_column' ), 10, 2 );

		add_filter( 'wpseo_title',                                  array( $this, 'post_type_archive_wpseo_title' ), 10, 1 );
		add_filter( 'post_type_archive_title',                      array( $this, 'post_type_archive_title' ), 10, 1 );
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
			'name'               => $this->post_type_name,
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
			'rewrite'            => array(
				'slug' => $this->post_type_slug,
			),
			'capability_type'    => 'post',
			'has_archive'        => $this->post_type_archive,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'supports'           => array( 'title', 'editor', 'categories' ),
			'taxonomies'         => array( 'category', 'post_tag' ),
			'show_in_rest'       => true,
			'rest_base'          => 'announcements',
		);

		register_post_type( $this->post_type, $args );
	}

	/**
	 * Add meta boxes used in the announcement edit screen.
	 */
	function add_meta_boxes() {
		add_meta_box( 'wsu_announcement_url', 'Announcement URL:', array( $this, 'display_url_meta_box' ), $this->post_type, 'normal' );
		add_meta_box( 'wsu_announcement_email', 'Announcement Submitted By:', array( $this, 'display_email_meta_box' ), $this->post_type, 'side' );
		add_meta_box( 'wsu_announcement_dates', 'Announcement Dates:',        array( $this, 'display_dates_meta_box' ), $this->post_type, 'side' );
	}

	/**
	 * Enqueue scripts and styles required in the admin.
	 */
	public function enqueue_admin_scripts() {
		if ( $this->post_type === get_current_screen()->id ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style( 'jquery-ui-core', 'https://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css' );
			wp_enqueue_script( 'wsu-news-announcement-admin', plugins_url( '../js/announcements-admin.js', __FILE__ ), array(), false, true );
		}
	}

	/**
	 * Display the meta box used to capture an external URL for
	 * an announcement.
	 *
	 * @since 1.9.0
	 *
	 * @param \WP_Post $post
	 */
	public function display_url_meta_box( $post ) {
		$url = get_post_meta( $post->ID, '_announcement_url', true );

		if ( $url ) {
			$url_parts = wp_parse_url( $url );

			if ( false === $url_parts ) {
				$url = '';
			} elseif ( ! isset( $url_parts['scheme'] ) ) {
				$url = esc_url( 'http://' . $url_parts['host'] . $url_parts['path'] );
			} else {
				$url = esc_url( $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] );
			}
		} else {
			$url = '';
		}

		?>
		<label for="announcement-form-url">Announcement URL:</label>
		<input type="text" id="announcement-form-url" name="announcement_url" value="<?php echo $url; // @codingStandardsIgnoreLine ?>" />
		<?php
	}

	/**
	 * Display the email associated with the announcement submission.
	 *
	 * @param WP_Post $post Current post object.
	 */
	function display_email_meta_box( $post ) {
		$email = get_post_meta( $post->ID, '_announcement_contact_email', true );

		if ( ! $email ) {
			echo '<strong>No email submitted with announcement.</strong>';
		} else {
			echo esc_html( $email );
		}
	}

	/**
	 * Display the contact dates associated with the announcement submission.
	 *
	 * @param WP_Post $post Post object to display meta for.
	 */
	function display_dates_meta_box( $post ) {
		$results = $this->_get_announcement_date_meta( $post->ID );
		$date_input = '';
		$date_input_count = 1;
		$archive_dates = array(
			'daily' => array(),
			'monthly' => array(),
			'yearly' => array(),
		);

		foreach ( $results as $result ) {
			$date = str_replace( '_announcement_date_', '', $result->meta_key );

			if ( 4 == strlen( $date ) ) {
				$archive_dates['yearly'][] = '<a href="' . esc_url( site_url( $this->post_type_archive . '/' . $date ) ) . '" >' . $date . '</a>';
			}

			if ( 6 == strlen( $date ) ) {
				$date_url = substr( $date, 0, 4 ) . '/' . substr( $date, 4, 2 );
				$date_display = substr( $date, 4, 2 ) . '/' . substr( $date, 0, 4 );
				$archive_dates['monthly'][] = '<a href="' . esc_url( site_url( $this->post_type_archive . '/' . $date_url ) ) . '" >' . $date_display . '</a>';
			}

			if ( 8 == strlen( $date ) ) {
				$date_url = substr( $date, 0, 4 ) . '/' . substr( $date, 4, 2 ) . '/' . substr( $date, 6, 2 );
				$date_display = substr( $date, 4, 2 ) . '/' . substr( $date, 6, 2 ) . '/' . substr( $date, 0, 4 );
				$archive_dates['daily'][] = '<a href="' . esc_url( site_url( $this->post_type_archive . '/' . $date_url ) ) . '" >' . $date_display . '</a>';
				$date_input .= '<input type="text" id="announcement-form-date' . $date_input_count . '" class="announcement-form-input announcement-form-date-input" name="announcement-date[]" value="' . $date_display . '" />';
				$date_input_count++;
			}
		}

		// Ensure we have 3 inputs listed. (This could be expandable...)
		while ( $date_input_count <= 3 ) {
			$date_input .= '<input type="text" id="announcement-form-date' . esc_attr( $date_input_count ) . '" class="announcement-form-input announcement-form-date-input" name="announcement-date[]" value="" />';
			$date_input_count++;
		}
		?>
		<label for="announcement-form-date">This announcement is assigned to the following date(s):</label><br /><br />
		<?php echo $date_input; // @codingStandardsIgnoreLine ?>

		<?php
		if ( 0 === count( $archive_dates['yearly'] ) && 0 === count( $archive_dates['monthly'] ) && 0 === count( $archive_dates['daily'] ) ) {
			echo '<p>Please enter dates for this announcement.</p>';
		} else {
			?>
			<p>It will appear on the following announcement archive pages:</p>
			<ul>
				<li>Yearly: <?php echo esc_html( implode( ', ', $archive_dates['yearly'] ) ); ?></li>
				<li>Monthly: <?php echo esc_html( implode( ', ', $archive_dates['monthly'] ) ); ?></li>
				<li>Daily: <?php echo esc_html( implode( ', ', $archive_dates['daily'] ) ); ?></li>
			</ul>
			<?php
		}

	}

	/**
	 * Save an announcement URL if provided in the announcement admin.
	 *
	 * @since 1.9.0
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_announcement_url( $post_id, $post ) {
		if ( $this->post_type !== $post->post_type ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['announcement_url'] ) ) {
			return;
		}

		$announcement_url = $_POST['announcement_url'];

		if ( empty( $announcement_url ) || false === wp_parse_url( $announcement_url ) ) {
			update_post_meta( $post_id, '_announcement_url', '' );
		} else {
			update_post_meta( $post_id, '_announcement_url', esc_url_raw( $announcement_url ) );
		}
	}

	/**
	 * Save the dates assigned to an announcement whenever an announcement is updated.
	 *
	 * @param $post_id
	 * @param $post
	 */
	public function save_announcement_dates( $post_id, $post ) {
		if ( $this->post_type !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['announcement-date'] ) ) {
			return;
		}

		$formatted_dates = array();
		foreach ( $_POST['announcement-date'] as $date ) {
			$formatted_dates[] = strtotime( $date );
		}
		sort( $formatted_dates );

		$this->_clear_announcement_date_meta( $post_id );
		$this->_save_announcement_date_meta( $post_id, $formatted_dates );

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
				'rule' => '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})',
				'vars' => array( 'year', 'monthnum', 'day' ),
			),
			array(
				'rule' => '([0-9]{4})/([0-9]{1,2})',
				'vars' => array( 'year', 'monthnum' ),
			),
			array(
				'rule' => '([0-9]{4})',
				'vars' => array( 'year' ),
			),
		);

		foreach ( $dates as $data ) {
			$query = 'index.php?post_type=' . $this->post_type;
			$rule = $this->post_type_archive . '/' . $data['rule'];

			$i = 1;
			foreach ( $data['vars'] as $var ) {
				$query .= '&' . $var . '=' . $wp_rewrite->preg_index( $i );
				$i++;
			}

			$rules[ $rule . '/?$'                               ] = $query;
			$rules[ $rule . '/feed/(feed|rdf|rss|rss2|atom)/?$' ] = $query . '&feed=' . $wp_rewrite->preg_index( $i );
			$rules[ $rule . '/(feed|rdf|rss|rss2|atom)/?$'      ] = $query . '&feed=' . $wp_rewrite->preg_index( $i );
			$rules[ $rule . '/page/([0-9]{1,})/?$'              ] = $query . '&paged=' . $wp_rewrite->preg_index( $i );
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

		if ( is_admin() || ! is_post_type_archive( $this->post_type ) ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		// Not to much of an archive if we don't have the year.
		if ( ! isset( $query->query['year'] ) ) {
			return;
		}

		$query_date = $query->query['year'];
		$query->set( 'year', '' );

		if ( isset( $query->query['monthnum'] ) ) {
			$query_date .= $query->query['monthnum'];
			$query->set( 'monthnum', '' );
		}

		if ( isset( $query->query['day'] ) ) {
			$query_date .= zeroise( $query->query['day'], 2 );
			$query->set( 'day', '' );
			$query->set( 'posts_per_page', 50 ); // Try to fit all of one day's announcements on a screen.
		}

		$query->set(
			'meta_query', array(
				array(
					'key' => '_announcement_date_' . $query_date,
					'value' => 1,
					'compare' => '=',
					'type' => 'numeric',
				),
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
		wp_enqueue_style( 'jquery-ui-core', 'https://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css' );

		// Enqueue the Javascript needed to handle the form submission properly.
		wp_enqueue_script( 'wsu-news-announcement-form', plugins_url( 'wsu-news-announcements/js/announcements-form.js' ), array(), false, true );

		// Provide a global variable containing the ajax URL that we can access
		wp_localize_script( 'wsu-news-announcement-form', 'announcementSubmission', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		) );

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
		if ( ! DOING_AJAX || ! isset( $_POST['action'] ) || 'submit_announcement' !== $_POST['action'] ) {
			die();
		}

		// If the honeypot input has anything filled in, we can bail.
		if ( isset( $_POST['other'] ) && '' !== $_POST['other'] ) {
			die();
		}

		$title = $_POST['title'];
		$text  = wp_kses_post( $_POST['text'] );
		$email = sanitize_email( $_POST['email'] );

		// If a websubmission user exists, we'll use that user ID.
		$user = get_user_by( 'slug', 'websubmission' );

		if ( is_wp_error( $user ) || false === $user ) {
			$user_id = 0;
		} else {
			$user_id = $user->ID;
		}

		$formatted_dates = array();
		foreach ( $_POST['dates'] as $date ) {
			$formatted_dates[] = strtotime( $date );
		}
		sort( $formatted_dates );
		$post_date = date( 'Y-m-d H:i:s', $formatted_dates[0] );

		$post_data = array(
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
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

		$this->_save_announcement_date_meta( $post_id, $formatted_dates );

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

		if ( 'Announcements' !== $name ) {
			return $name;
		}

		// Get the date from our URL because we've tricked the query until now.
		$url_dates = explode( '/', trim( $_SERVER['REQUEST_URI'], '/' ) );
		array_shift( $url_dates );

		if ( isset( $url_dates[2] ) ) {
			return date( 'F j, Y ', strtotime( $url_dates[0] . '-' . $url_dates[1] . '-' . $url_dates[2] . ' 00:00:00' ) ) . $name;
		} elseif ( isset( $url_dates[1] ) && 0 !== absint( $url_dates[0] ) ) {
			return date( 'F Y ', strtotime( $url_dates[0] . '-' . $url_dates[1] . '-01 00:00:00' ) ) . $name;
		} elseif ( isset( $url_dates[0] ) && 0 !== absint( $url_dates[0] ) ) {
			return date( 'Y ', strtotime( $url_dates[0] . '-01-01 00:00:00' ) ) . $name;
		} else {
			return $name;
		}

	}

	/**
	 * Filter the WordPress SEO generate post type archive title for Announcements.
	 *
	 * @param string $title Title as previously modified by WordPress SEO
	 *
	 * @return string Our replacement version of the title.
	 */
	public function post_type_archive_wpseo_title( $title ) {
		if ( is_post_type_archive( $this->post_type ) ) {
			return $this->post_type_archive_title( $this->post_type_name ) . ' |';
		}

		return $title;
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

		// Remove all WPSEO added columns as we have no use for them on this screen.
		unset( $columns['wpseo-score'] );
		unset( $columns['wpseo-title'] );
		unset( $columns['wpseo-metadesc'] );
		unset( $columns['wpseo-focuskw'] );

		// Add our custom columns. Move date to the end of the array after we unset it above.
		$columns['contact_email'] = 'Contact Email';
		$columns['announce_dates'] = 'Announcement Dates';
		$columns['date'] = 'Publish Date';

		return $columns;
	}

	/**
	 * Capture the various days, months, and years on which this announcement should appear and
	 * update post meta accordingly so that we can perform custom queries as needed.
	 *
	 * @param int   $post_id         ID of the post to assign the dates to.
	 * @param array $formatted_dates An array of dates the announcement will be shown on.
	 */
	private function _save_announcement_date_meta( $post_id, $formatted_dates ) {
		foreach ( $formatted_dates as $date ) {
			$date_formatted  = date( 'Ymd', $date );
			$month_formatted = date( 'Ym',  $date );
			$year_formatted  = date( 'Y',   $date );

			update_post_meta( $post_id, '_announcement_date_' . $date_formatted,  1 );
			update_post_meta( $post_id, '_announcement_date_' . $month_formatted, 1 );
			update_post_meta( $post_id, '_announcement_date_' . $year_formatted,  1 );
		}
	}

	/**
	 * Retrieve announcement date meta for a post.
	 *
	 * @param int $post_id Post ID to retrieve metadata for.
	 *
	 * @return mixed Results of the post meta query.
	 */
	private function _get_announcement_date_meta( $post_id ) {
		/* @global WPDB $wpdb */
		global $wpdb;

		$announcement_date = '_announcement_date_%';
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE post_id = %d and meta_key LIKE %s GROUP BY meta_key", $post_id, $announcement_date ) );

		return $results;
	}

	/**
	 * Delete any announcement dates associated with an announcement.
	 *
	 * @param int $post_id Post ID of the announcement to clear date data from.
	 */
	private function _clear_announcement_date_meta( $post_id ) {
		global $wpdb;

		$announcement_key = '_announcement_date_%';
		$wpdb->get_results( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key LIKE %s", $post_id, $announcement_key ) );
	}

	/**
	 * Handle output for the contact email column in the announcement list table.
	 *
	 * @param string $column_name Current column being displayed.
	 * @param int    $post_id     Post ID of the current row being displayed.
	 */
	public function manage_list_table_email_column( $column_name, $post_id ) {
		if ( 'contact_email' !== $column_name ) {
			return;
		}

		$contact_email = get_post_meta( $post_id, '_announcement_contact_email', true );
		if ( $contact_email ) {
			echo esc_html( $contact_email );
		}
	}

	/**
	 * Handle output for the announcement dates column in the announcement list table.
	 *
	 * @param string $column_name Current column being displayed.
	 * @param int    $post_id     Post ID of the current row being displayed.
	 */
	public function manage_list_table_announcement_dates_column( $column_name, $post_id ) {
		if ( 'announce_dates' !== $column_name ) {
			return;
		}

		$announcement_meta = $this->_get_announcement_date_meta( $post_id );

		foreach ( $announcement_meta as $meta ) {
			$date = str_replace( '_announcement_date_', '', $meta->meta_key );

			if ( 8 === strlen( $date ) ) {
				$date_display = substr( $date, 4, 2 ) . '/' . substr( $date, 6, 2 ) . '/' . substr( $date, 0, 4 );
				echo esc_html( $date_display ) . '<br>';
			}
		}
	}

	/**
	 * Displays a calendar with links to days that have announcements.
	 *
	 * This was originally copied from the WordPress get_calendar() function, but then
	 * heavily modified to query against a post types post meta rather than the
	 * wp_posts table. The HTML structure of the final calendar is vary close, if not
	 * identical to the built in WordPress functionality.
	 *
	 * @param bool $initial Optional, default is true. Use initial calendar names.
	 * @param bool $echo    Optional, default is true. Set to false for return.
	 *
	 * @return null|string  String when retrieving, null when displaying.
	 */
	public function get_calendar( $initial = true, $echo = true ) {
		// @codingStandardsIgnoreStart
		// This is code copied from WordPress core, so obviously it does not
		// meet current WordPress core standards. /shrug

		/**
		 * @global WPDB      $wpdb
		 * @global WP_Locale $wp_locale
		 */
		global $wpdb, $m, $monthnum, $year, $wp_locale, $posts;

		$key = md5( $m . $monthnum . $year );

		if ( $cache = get_transient( $this->calendar_cache_key ) ) {
			if ( is_array( $cache ) && isset( $cache[ $key ] ) ) {
				if ( $echo ) {
					echo $cache[ $key ];
					return;
				} else {
					return $cache[ $key ];
				}
			}
		}

		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		// Quick check. If we have no posts at all, abort!
		if ( ! $posts ) {
			$gotsome = $wpdb->get_var( $wpdb->prepare( "SELECT 1 as test FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish' LIMIT 1", $this->post_type ) );
			if ( ! $gotsome ) {
				$cache[ $key ] = '';
				set_transient( $this->calendar_cache_key, $cache, 60 * 60 * 24 ); // Will likely be flushed well before then.
				return;
			}
		}

		if ( isset( $_GET['w'] ) ) {
			$w = '' . intval( $_GET['w'] );
		}

		// week_begins = 0 stands for Sunday
		$week_begins = intval( get_option( 'start_of_week' ) );

		// Let's figure out when we are
		if ( ! empty( $monthnum ) && ! empty( $year ) ) {
			$thismonth = '' . zeroise( intval( $monthnum ), 2 );
			$thisyear  = '' . intval( $year );
		} elseif ( ! empty( $w ) ) {
			// We need to get the month from MySQL
			$thisyear  = '' . intval( substr( $m, 0, 4 ) );
			$d = ( ( $w - 1 ) * 7 ) + 6; //it seems MySQL's weeks disagree with PHP's
			$thismonth = $wpdb->get_var( "SELECT DATE_FORMAT((DATE_ADD('{$thisyear}0101', INTERVAL $d DAY) ), '%m')" );
		} elseif ( ! empty( $m ) ) {
			$thisyear = '' . intval( substr( $m, 0, 4 ) );
			if ( strlen( $m ) < 6 ) {
				$thismonth = '01';
			} else {
				$thismonth = '' . zeroise( intval( substr( $m, 4, 2 ) ), 2 );
			}
		} else {
			$thisyear  = gmdate( 'Y', current_time( 'timestamp' ) );
			$thismonth = gmdate( 'm', current_time( 'timestamp' ) );
		}

		$unixmonth = mktime( 0, 0 , 0, $thismonth, 1, $thisyear );
		$last_day  = date( 't', $unixmonth );

		// @todo Get the next and previous month and year with at least one post
		$previous = false;
		$next     = false;

		$calendar_output = '<table id="wp-calendar">
	<caption><a href="' . esc_url( $this->get_month_link( $thisyear, $thismonth ) ) . '" title="Announcements for ' . $thismonth . '/' . $thisyear . '">' . date( 'F Y ' ) . '</a></caption>
	<thead>
	<tr>';

		$myweek = array();

		for ( $wdcount = 0; $wdcount <= 6; $wdcount++ ) {
			$myweek[] = $wp_locale->get_weekday( ( $wdcount + $week_begins ) % 7 );
		}

		foreach ( $myweek as $wd ) {
			$day_name = ( true == $initial ) ? $wp_locale->get_weekday_initial( $wd ) : $wp_locale->get_weekday_abbrev( $wd );
			$wd = esc_attr( $wd );
			$calendar_output .= "\n\t\t<th scope=\"col\" title=\"$wd\">$day_name</th>";
		}

		$calendar_output .= '
	</tr>
	</thead>

	<tfoot>
	<tr>';

		if ( $previous ) {
			$calendar_output .= "\n\t\t" . '<td colspan="3" id="prev"><a href="' . $this->get_month_link( $previous->year, $previous->month ) . '" title="' . esc_attr( sprintf( __( 'View posts for %1$s %2$s' ), $wp_locale->get_month( $previous->month ), date( 'Y', mktime( 0, 0 , 0, $previous->month, 1, $previous->year ) ) ) ) . '">&laquo; ' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $previous->month ) ) . '</a></td>';
		} else {
			$calendar_output .= "\n\t\t" . '<td colspan="3" id="prev" class="pad">&nbsp;</td>';
		}

		$calendar_output .= "\n\t\t" . '<td class="pad">&nbsp;</td>';

		if ( $next ) {
			$calendar_output .= "\n\t\t" . '<td colspan="3" id="next"><a href="' . $this->get_month_link( $next->year, $next->month ) . '" title="' . esc_attr( sprintf( __( 'View posts for %1$s %2$s' ), $wp_locale->get_month( $next->month ), date( 'Y', mktime( 0, 0 , 0, $next->month, 1, $next->year ) ) ) ) . '">' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $next->month ) ) . ' &raquo;</a></td>';
		} else {
			$calendar_output .= "\n\t\t" . '<td colspan="3" id="next" class="pad">&nbsp;</td>';
		}

		$calendar_output .= '
	</tr>
	</tfoot>

	<tbody>
	<tr>';

		// Get days with announcement data for this month stored in post meta.
		$announcement_date_key = '_announcement_date_' . $thisyear . $thismonth . '%';
		$days_post_ids = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key LIKE %s", $announcement_date_key ), ARRAY_N );
		$days_post_ids = wp_list_pluck( $days_post_ids, 0 );
		$days_post_ids = join( ',', $days_post_ids );

		// Now that we have a full list of post IDs, we need to make a query for those that are published.
		$days_post_ids = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE ID IN ( " . $days_post_ids . " ) AND post_status ='publish'", ARRAY_N );
		$days_post_ids = wp_list_pluck( $days_post_ids, 0 );
		$days_post_ids = join( ',', $days_post_ids );

		// No go back and get the distinct dates on which these announcements are to be made.
		$days_results = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT meta_key FROM $wpdb->postmeta WHERE post_id IN ( " . $days_post_ids . ' ) AND meta_key LIKE %s', $announcement_date_key ), ARRAY_N );

		$current_day    = date( 'd' ); // We need this to avoid future announcements.
		$days_with_post = array();     // Ensure at least an empty array.

		if ( $days_results ) {
			foreach ( $days_results as $day_with ) {
				$day_with = str_replace( '_announcement_date_' . $thisyear . $thismonth, '', $day_with );
				if ( '' !== $day_with[0] && $current_day >= $day_with[0] ) {
					$days_with_post[] = $day_with[0];
				}
			}
		}

		// See how much we should pad in the beginning
		$pad = calendar_week_mod( date( 'w', $unixmonth ) - $week_begins );
		if ( 0 != $pad ) {
			$calendar_output .= "\n\t\t" . '<td colspan="' . esc_attr( $pad ) . '" class="pad">&nbsp;</td>';
		}

		$daysinmonth = intval( date( 't', $unixmonth ) );
		for ( $day = 1; $day <= $daysinmonth; ++$day ) {
			if ( isset( $newrow ) && $newrow ) {
				$calendar_output .= "\n\t</tr>\n\t<tr>\n\t\t";
			}
			$newrow = false;

			if ( $day == gmdate( 'j', current_time( 'timestamp' ) ) && $thismonth == gmdate( 'm', current_time( 'timestamp' ) ) && $thisyear == gmdate( 'Y', current_time( 'timestamp' ) ) ) {
				$calendar_output .= '<td id="today">';
			} else {
				$calendar_output .= '<td>';
			}

			if ( in_array( $day, $days_with_post ) ) { // any posts today?
				$calendar_output .= '<a href="' . $this->get_day_link( $thisyear, $thismonth, $day ) . '" title="' . esc_attr( 'Announcements for ' . $thismonth . '/' . $day . '/' . $thisyear ) . " \">$day</a>";
			} else {
				$calendar_output .= $day;
			}
			$calendar_output .= '</td>';

			if ( 6 == calendar_week_mod( date( 'w', mktime( 0, 0 , 0, $thismonth, $day, $thisyear ) ) - $week_begins ) ) {
				$newrow = true;
			}
		}

		$pad = 7 - calendar_week_mod( date( 'w', mktime( 0, 0 , 0, $thismonth, $day, $thisyear ) ) - $week_begins );
		if ( $pad != 0 && $pad != 7 ) {
			$calendar_output .= "\n\t\t" . '<td class="pad" colspan="' . esc_attr( $pad ) . '">&nbsp;</td>';
		}

		$calendar_output .= "\n\t</tr>\n\t</tbody>\n\t</table>";

		$cache[ $key ] = $calendar_output;
		set_transient( $this->calendar_cache_key, $cache, 60 * 60 * 24 ); // Will likely be flushed well before this.

		if ( $echo ) {
			echo $calendar_output;
		} else {
			return $calendar_output;
		}

		// @codingStandardsIgnoreEnd

		return null;
	}

	/**
	 * Purge cached announcement calendar data when an announcement is saved or deleted.
	 *
	 * @param int $post_id Current post being acted on.
	 */
	public function delete_calendar_cache( $post_id ) {
		if ( $this->post_type === get_post_type( $post_id ) ) {
			delete_transient( $this->calendar_cache_key );
		}
	}

	/**
	 * Generate a link to a day's announcement archives.
	 *
	 * @param string $year  Year to be included in the URL.
	 * @param string $month Month to be included in the URL.
	 * @param string $day   Day to be included in the URL.
	 *
	 * @return string Day's announcement URL.
	 */
	public function get_day_link( $year, $month, $day ) {
		return site_url( $this->post_type_archive . '/' . $year . '/' . $month . '/' . $day . '/' );
	}

	/**
	 * Generate a link to a month's announcement archives.
	 *
	 * @param string $year  Year to be included in the URL.
	 * @param string $month Month to be included in the URL.
	 *
	 * @return string Month's announcement URL.
	 */
	public function get_month_link( $year, $month ) {
		return site_url( $this->post_type_archive . '/' . $year . '/' . $month . '/' );
	}

	/**
	 * Register widgets used by announcements.
	 */
	public function register_widget() {
		register_widget( 'WSU_News_Announcement_Calendar_Widget' );
	}
}
$wsu_content_type_announcement = new WSU_Content_Type_Announcement();
