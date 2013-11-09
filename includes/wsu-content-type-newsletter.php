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

		$email_html = $this->generate_html_email( $post_id, $post_ids );
		$headers = "From: WSU Announcements\r\n";
		$headers .= 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

		add_filter( 'wp_mail_content_type', array( $this, 'set_mail_content_type' ) );
		wp_mail( $_POST['email'], 'Announcements Newsletter', $email_html, $headers );
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

	private function generate_html_email( $post_id, $post_ids ) {
		$email_title = esc_html( get_the_title( $post_id ) );
		$newsletter_items = $this->_build_announcements_newsletter_response( $post_ids );

		$html_email = <<<EMAIL
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
	<title>$email_title</title>
	<style type="text/css">
		/* Based on The MailChimp Reset INLINE: Yes. */
		/* Client-specific Styles */
		#outlook a {padding:0;} /* Force Outlook to provide a "view in browser" menu link. */
		body{width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; margin:0; padding:0;}
		/* Prevent Webkit and Windows Mobile platforms from changing default font sizes.*/
		.ExternalClass {width:100%;} /* Force Hotmail to display emails at full width */
		.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {line-height: 100%;}
		/* Forces Hotmail to display normal line spacing.  More on that: http://www.emailonacid.com/forum/viewthread/43/ */
		#background-full {
			margin:0;
			padding:0;
			width:100% !important;
			line-height: 100% !important;
			background: #5e6a71;
		}

		#background-header {
			margin: 0;
			padding: 0;
			width: 100%;
			line-height: 36px;
			background: #981e32;
			color: #fff;
			font-family: 'Lucida Grande', 'Lucida Sans Unicode', arial, helvetica, sans-serif;
			font-size: 26px;
		}

		#background-message {
			margin: 0;
			padding: 0;
			background: #fff;
			font-family: 'Lucida Grande', 'Lucida Sans Unicode', arial, helvetica, sans-serif;
			font-size: 12px;
		}
		/* End reset */

		/* Some sensible defaults for images
		Bring inline: Yes. */
		img {outline:none; text-decoration:none; -ms-interpolation-mode: bicubic;}
		a img {border:none;}
		.image_fix {display:block;}

		/* Yahoo paragraph fix
		Bring inline: Yes. */
		p {margin: 1em 0;}

		/* Hotmail header color reset
		Bring inline: Yes. */
		h1 {
			color: white !important;
			font-size: 26px;
		}

		h2, h3, h4, h5, h6 {color: black !important;}

		h1 a, h2 a, h3 a, h4 a, h5 a, h6 a {color: blue !important;}

		h1 a:active, h2 a:active,  h3 a:active, h4 a:active, h5 a:active, h6 a:active {
			color: red !important; /* Preferably not the same color as the normal header link color.  There is limited support for psuedo classes in email clients, this was added just for good measure. */
		}

		h1 a:visited, h2 a:visited,  h3 a:visited, h4 a:visited, h5 a:visited, h6 a:visited {
			color: purple !important; /* Preferably not the same color as the normal header link color. There is limited support for psuedo classes in email clients, this was added just for good measure. */
		}

		/* Outlook 07, 10 Padding issue fix
		Bring inline: No.*/
		table td {border-collapse: collapse;}

		/* Remove spacing around Outlook 07, 10 tables
		Bring inline: Yes */
		table { border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; }

		/* Styling your links has become much simpler with the new Yahoo.  In fact, it falls in line with the main credo of styling in email and make sure to bring your styles inline.  Your link colors will be uniform across clients when brought inline.
		Bring inline: Yes. */
		a {color: orange;}


		/***************************************************
		****************************************************
		MOBILE TARGETING
		****************************************************
		***************************************************/
		@media only screen and (max-device-width: 480px) {
			/* Part one of controlling phone number linking for mobile. */
			a[href^="tel"], a[href^="sms"] {
				text-decoration: none;
				color: blue; /* or whatever your want */
				pointer-events: none;
				cursor: default;
			}

			.mobile_link a[href^="tel"], .mobile_link a[href^="sms"] {
				text-decoration: default;
				color: orange !important;
				pointer-events: auto;
				cursor: default;
			}

		}

		/* More Specific Targeting */

		@media only screen and (min-device-width: 768px) and (max-device-width: 1024px) {
			/* You guessed it, ipad (tablets, smaller screens, etc) */
			/* repeating for the ipad */
			a[href^="tel"], a[href^="sms"] {
				text-decoration: none;
				color: blue; /* or whatever your want */
				pointer-events: none;
				cursor: default;
			}

			.mobile_link a[href^="tel"], .mobile_link a[href^="sms"] {
				text-decoration: default;
				color: orange !important;
				pointer-events: auto;
				cursor: default;
			}
		}

		@media only screen and (-webkit-min-device-pixel-ratio: 2) {
			/* Put your iPhone 4g styles in here */
		}

		/* Android targeting */
		@media only screen and (-webkit-device-pixel-ratio:.75){
			/* Put CSS for low density (ldpi) Android layouts in here */
		}
		@media only screen and (-webkit-device-pixel-ratio:1){
			/* Put CSS for medium density (mdpi) Android layouts in here */
		}
		@media only screen and (-webkit-device-pixel-ratio:1.5){
			/* Put CSS for high density (hdpi) Android layouts in here */
		}
		/* end Android targeting */

	</style>

	<!-- Targeting Windows Mobile -->
	<!--[if IEMobile 7]>
	<style type="text/css">

	</style>
	<![endif]-->

	<!-- ***********************************************
	****************************************************
	END MOBILE TARGETING
	****************************************************
	************************************************ -->

	<!--[if gte mso 9]>
	<style>
		/* Target Outlook 2007 and 2010 */
	</style>
	<![endif]-->
</head>
<body>
<table cellpadding="0" cellspacing="0" border="0" id="background-full">
	<tr>
		<td valign="top">
			<!-- Establish a header section at the top and center the title in a specific area -->
			<table cellpadding="0" cellspacing="0" border="0" align="center" id="background-header">
				<tr>
					<td>
						<table align="center">
							<tr>
								<td width="600" valign="top"><h1><?php the_title(); ?></h1></td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td valign="top">
			<table cellpadding="0" cellspacing="0" border="0" align="center">
				<tr><td>&nbsp;</td></tr>
				<tr>
					<td>
						<table cellpadding="0" cellspacing="0" border="0" align="center" id="background-message">
							<tr>
								<td width="600" valign="top">
								<p>Submit announcements online at <a href="http://news.wsu.edu/announcements/">http://news.wsu.edu/announcements</a></p>
EMAIL;

		foreach ( $newsletter_items as $item ) {
			$html_email .= '<h3><a href="' . esc_url( $item['permalink'] ) . '">' . esc_html( $item['title'] ) . '</a></h3>';
			$html_email .= '<p>' . esc_html( $item['excerpt'] ) . ' <a href="' . esc_url( $item['permalink'] ) . '">Continue reading&hellip;</a></p>';
		}

		$html_email .= <<<EMAIL
									<p>The Announcement newsletter will continue to be sent once a day at 10 a.m. Submissions made after 9 a.m. each day will appear in the next days’ newsletter. Any edits will be still be made by Brenda Campbell at <a href="mailto:bcampbell@wsu.edu">bcampbell@wsu.edu</a>.</p>
									<p>If you are having difficulty reading the announcements, try unsubscribing and then resubscribe. Click <a href="http://lists.wsu.edu/leave.php">here</a> to unsubscribe and <a href="http://lists.wsu.edu/join.php">here</a> to subscribe</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr><td>&nbsp;</td></tr>
			</table>
		</td>
	</tr>
</table>
<!-- End of wrapper table -->
</body>
</html>
EMAIL;
		return $html_email;
	}
}
$wsu_content_type_newsletter = new WSU_Content_Type_Newsletter();
