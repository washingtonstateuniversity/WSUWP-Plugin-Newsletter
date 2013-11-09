<?php

class WSU_Content_Type_Newsletter {

	var $post_type = 'wsu_newsletter';

	var $post_type_name = 'Newsletters';

	var $post_type_slug = 'newsletter';

	var $post_type_archive = 'newsletters';

	var $tax_newsletter_type = 'wsu_newsletter_type';

	/**
	 * Add the hooks that we'll make use of.
	 */
	public function __construct() {
		add_action( 'init',                               array( $this, 'register_post_type'                ), 10    );
		add_action( 'init',                               array( $this, 'register_newsletter_type_taxonomy' ), 10    );
		add_action( 'save_post_' . $this->post_type,      array( $this, 'save_post'                         ), 10, 2 );
		add_action( 'add_meta_boxes',                     array( $this, 'add_meta_boxes'                    ), 10    );
		add_action( 'admin_enqueue_scripts',              array( $this, 'admin_enqueue_scripts'             ), 10    );
		add_action( 'wp_ajax_set_newsletter_type',        array( $this, 'ajax_callback'                     ), 10    );
		add_action( 'wp_ajax_nopriv_set_newsletter_type', array( $this, 'ajax_callback'                     ), 10    );
		add_action( 'wp_ajax_send_newsletter',            array( $this, 'ajax_send_newsletter'              ), 10    );
		add_filter( 'single_template',                    array( $this, 'single_template'                   ), 10, 1 );
	}

	/**
	 * Register a content type specifically for the newsletter.
	 */
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
			'supports'           => array( '' ),
			'taxonomies'         => array(),
		);

		register_post_type( $this->post_type, $args );
	}

	/**
	 * Register a taxonomy for newsletter type.
	 */
	public function register_newsletter_type_taxonomy() {
		$labels = array(
			'name' => 'Newsletter Types',
			'singular_name' => 'Newsletter Type',
			'parent_item' => 'Parent Newsletter Type',
			'edit_item' => 'Edit Newsletter Type',
			'update_item' => 'Update Newsletter Type',
			'add_new_item' => 'Add Newsletter Type',
			'new_item_name' => 'New Newsletter Type',
		);

		$args = array(
			'hierarchical'          => true,
			'labels'                => $labels,
			'show_ui'               => false,
			'show_admin_column'     => true,
			'query_var'             => true,
		);

		register_taxonomy( $this->tax_newsletter_type, array( $this->post_type ), $args );
	}

	/**
	 * Add the meta boxes used by the WSU newsletter content type.
	 */
	public function add_meta_boxes() {
		add_meta_box( 'wsu_newsletter_items', 'Newsletter Items', array( $this, 'display_newsletter_items_meta_box' ), $this->post_type, 'normal' );
		add_meta_box( 'wsu_newsletter_send',  'Send Newsletter',  array( $this, 'display_newsletter_send_meta_box'  ), $this->post_type, 'side'   );
	}

	/**
	 * Display a newsletter form that allows for the automatic creation and drag/drop editing
	 * of an email newsletter.
	 *
	 * @todo NEWS - add subheads, posts, text blurbs
	 * @todo ANNOUNCEMENTS - options for ad-hoc adding of announcements and text blurbs
	 *
	 * @param WP_Post $post Object for the post currently being edited.
	 */
	public function display_newsletter_items_meta_box( $post ) {
		$localized_data = array( 'post_id' => $post->ID );

		// If this newsletter has items assigned already, we want to make them available to our JS
		if ( $post_ids = get_post_meta( $post->ID, '_newsletter_item_order', true ) )
			$localized_data['items'] = $this->_build_announcements_newsletter_response( $post_ids );

		wp_localize_script( 'wsu-newsletter-admin', 'wsu_newsletter', $localized_data );

		// Select Newsletter Type
		$newsletter_types = get_terms( $this->tax_newsletter_type );
		foreach ( $newsletter_types as $newsletter_type ) {
			echo '<input type="button" value="' . esc_html( $newsletter_type->name ) . '" id="' . esc_attr( $newsletter_type->slug ) . '" class="button button-large button-secondary newsletter-type" /> ';
		}

		?>
		<div id="newsletter-build">
			<div class="newsletter-date"><?php echo date( 'l, F j, Y', current_time( 'timestamp' ) ); ?></div>
			<div class="newsletter-head">
				<p>Submit announcements online at <a href="http://news.wsu.edu/announcements/">http://news.wsu.edu/announcements</a></p>
			</div>
			<div id="newsletter-build-items">
				<p class="newsletter-build-tip">Click 'Announcements' above to load in today's announcements.</p>
			</div>
			<div class="newsletter-footer">
				<p>The Announcement newsletter will continue to be sent once a day at 10 a.m. Submissions made after 9 a.m.
				each day will appear in the next days’ newsletter. Any edits will be still be made by Brenda Campbell at <a href="mailto:bcampbell@wsu.edu">bcampbell@wsu.edu</a>.</p>
				<p>If you are having difficulty reading the announcements, try unsubscribing and then resubscribe. Click <a href="http://lists.wsu.edu/leave.php">here</a> to unsubscribe and <a href="http://lists.wsu.edu/join.php">here</a> to subscribe</p>
			</div>
			<div style="clear:left;"> </div>
		</div>
		<input type="hidden" id="newsletter-item-order" name="newsletter_item_order" value="" />
		<?php
	}

	/**
	 * Display a meta box to allow the sending of a newsletter to an email address.
	 *
	 * @param WP_Post $post Post object for the post currently being edited.
	 */
	public function display_newsletter_send_meta_box( $post ) {
		?>
		<label for="newsletter-email">Email Address:</label>
		<input type="text" name="newsletter_email" id="newsletter-email" value="" placeholder="email..." />
		<input type="button" id="newsletter-send" value="Send" class="button button-primary" />
		<br>
		<span id="newsletter-send-response"></span>
		<?php
	}

	/**
	 * Enqueue the scripts used in the WordPress admin for managing newsletter creation.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) )
			return;

		if ( $this->post_type === get_current_screen()->id ) {
			wp_enqueue_script( 'wsu-newsletter-admin', plugins_url( 'js/wsu-newsletter-admin.js',   dirname( __FILE__ ) ), array( 'jquery', 'jquery-ui-sortable' ), false, true );
			wp_enqueue_style(  'wsu-newsletter-admin', plugins_url( 'css/wsu-newsletter-admin.css', dirname( __FILE__ ) ) );
		}
	}

	private function _build_announcements_newsletter_response( $post_ids = array() ) {
		// @global WSU_Content_Type_Announcement $wsu_content_type_announcement
		global $wsu_content_type_announcement;

		$query_date = date( 'Ymd', current_time( 'timestamp' ) );

		$query_args = array(
			'post_type'       => $wsu_content_type_announcement->post_type,
			'posts_per_page'  => 100,
			'meta_query'      => array(
				array(
					'key'     => '_announcement_date_' . $query_date,
					'value'   => 1,
					'compare' => '=',
					'type'    => 'numeric',
				)
			),
		);

		// If an array of post IDs has been passed, use only those.
		if ( ! empty( $post_ids ) ) {
			$query_args['post__in'] = $post_ids;
			$query_args['orderby']  = 'post__in';
		}

		$announcements_query = new WP_Query( $query_args );
		$items = array();
		if ( $announcements_query->have_posts() ) {
			while ( $announcements_query->have_posts() ) {
				$announcements_query->the_post();
				$items[] = array(
					'id'        => get_the_ID(),
					'title'     => get_the_title(),
					'excerpt'   => get_the_excerpt(),
					'permalink' => get_permalink(),
				);
			}
		}
		return $items;
	}

	public function ajax_callback() {
		if ( ! DOING_AJAX || ! isset( $_POST['action'] ) || 'set_newsletter_type' !== $_POST['action'] )
			die();

		if ( 'announcements' === $_POST['newsletter_type'] )
			echo json_encode( $this->_build_announcements_newsletter_response() );
		elseif ( 'news' === $_POST['newsletter_type'] )
			echo 'news';

		exit(); // close the callback
	}

	/**
	 * Modify the default content type for email used by WordPress.
	 *
	 * @return string The content type to use with the email.
	 */
	public function set_mail_content_type() {
			return 'text/html';
	}

	public function ajax_send_newsletter() {
		if ( ! DOING_AJAX || ! isset( $_POST['action'] ) || 'send_newsletter' !== $_POST['action'] )
			die();

		$post_id = absint( $_POST['post_id'] );

		if ( ! $post_ids = get_post_meta( $post_id, '_newsletter_item_order', true ) ) {
			echo $post_id . 'No items to send...';
			exit;
		}

		$email_html = '<html><head><title>Newsletter</title></head><body></body></html>';
		add_filter( 'wp_mail_content_type', array( $this, 'set_mail_content_type' ) );
		wp_mail( $_POST['email'], 'Announcements Newsletter', $email_html );
		remove_filter( 'wp_mail_content_type', array( $this, 'set_mail_content_type' ) );

		echo 'Emailed ' . esc_html( $_POST['email'] ) . '...';
		exit;
	}

	/**
	 * Capture the order of newsletter items on save and store as post meta.
	 *
	 * @param int     $post_id ID of the current post being saved.
	 * @param WP_Post $post    Object of the current post being saved.
	 */
	public function save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		if ( 'auto-draft' === $post->post_status || empty( $_POST['newsletter_item_order'] ) )
			return;

		$newsletter_item_order = explode( ',', $_POST['newsletter_item_order'] );
		$newsletter_item_order = array_map( 'absint', $newsletter_item_order );
		update_post_meta( $post_id, '_newsletter_item_order', $newsletter_item_order );
	}

	public function single_template( $template ) {
		$current_object = get_queried_object();

		if ( isset( $current_object->post_type ) && $this->post_type === $current_object->post_type )
			return dirname( dirname( __FILE__ ) ) . '/templates/single-' . $this->post_type . '.php';
		else
			return $template;
	}
}
$wsu_content_type_newsletter = new WSU_Content_Type_Newsletter();
