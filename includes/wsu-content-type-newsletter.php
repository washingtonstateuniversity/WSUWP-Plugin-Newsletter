<?php
/**
 * Class WSU_Content_Type_Newsletter
 *
 * Provides functionality to create and send email newsletters for WSU announcements.
 */
class WSU_Content_Type_Newsletter {

	/**
	 * @var string The slug used for the newsletter content type.
	 */
	var $post_type = 'wsu_newsletter';

	/**
	 * @var string The official name of the newsletter content type.
	 */
	var $post_type_name = 'Newsletters';

	/**
	 * @var string The slug for the newsletter taxonomy type.
	 */
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

		add_filter( 'wp_insert_post_data',                array( $this, 'wp_insert_post_data'               ), 10, 1 );
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
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => false,
			'capability_type'    => 'post',
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
			'name'          => 'Newsletter Types',
			'singular_name' => 'Newsletter Type',
			'parent_item'   => 'Parent Newsletter Type',
			'edit_item'     => 'Edit Newsletter Type',
			'update_item'   => 'Update Newsletter Type',
			'add_new_item'  => 'Add Newsletter Type',
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
		/*$newsletter_types = get_terms( $this->tax_newsletter_type );
		foreach ( $newsletter_types as $newsletter_type ) {
			echo '<input type="button" value="' . esc_html( $newsletter_type->name ) . '" id="' . esc_attr( $newsletter_type->slug ) . '" class="button button-large button-secondary newsletter-type" /> ';
		}*/

		if ( $newsletter_date = get_post_meta( $post->ID, '_newsletter_date', true ) ) {
			$n_year  = substr( $newsletter_date, 0, 4 );
			$n_month = substr( $newsletter_date, 4, 2 );
			$n_day   = substr( $newsletter_date, 6, 2 );
			$newsletter_date = $n_month . '/' . $n_day . '/' . $n_year;
		} else {
			$newsletter_date = date( 'm/d/Y', current_time( 'timestamp' ) );
		}
		?>
		<label for="newsletter-date">Newsletter Date:</label>
		<input type="text" id="newsletter-date" name="newsletter_date" value="<?php echo $newsletter_date; ?>" />
		<input type="button" value="Announcements" id="announcements" class="button button-large button-secondary newsletter-type" />
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
	 */
	public function display_newsletter_send_meta_box() {
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
			wp_enqueue_script( 'wsu-newsletter-admin', plugins_url( 'js/wsu-newsletter-admin.js',   dirname( __FILE__ ) ), array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-datepicker' ), false, true );
			wp_enqueue_style( 'jquery-ui-core', 'http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css' );
			wp_enqueue_style(  'wsu-newsletter-admin', plugins_url( 'css/wsu-newsletter-admin.css', dirname( __FILE__ ) ) );
		}
	}

	/**
	 * Build the list of items that should be included in a newsletter.
	 *
	 * @param array        $post_ids  List of specific post IDs to include. Defaults to an empty array.
	 * @param null|string  $post_date Post date to assign to the newsletter. A null default indicates the current date.
	 *
	 * @return array Containing information on each newsletter item.
	 */
	private function _build_announcements_newsletter_response( $post_ids = array(), $post_date = null ) {
		// @global WSU_Content_Type_Announcement $wsu_content_type_announcement
		global $wsu_content_type_announcement;

		$query_args = array(
			'post_type'       => $wsu_content_type_announcement->post_type,
			'posts_per_page'  => 100,
		);

		if ( $post_date ) {
			$query_args['meta_query'] = array(
				array(
					'key'     => '_announcement_date_' . $post_date,
					'value'   => 1,
					'compare' => '=',
					'type'    => 'numeric',
				),
			);
		}

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

	/**
	 * Handle the ajax callback too push a list of newsletter items to a newsletter.
	 */
	public function ajax_callback() {
		if ( ! DOING_AJAX || ! isset( $_POST['action'] ) || 'set_newsletter_type' !== $_POST['action'] )
			die();

		if ( 'announcements' === $_POST['newsletter_type'] ) {
			if ( isset( $_POST['post_date'] ) )
				$post_date = $_POST['post_date'];
			else
				$post_date = false;

			if ( $post_date ) {
				$post_date = explode( '/', $post_date );
				$post_date = array_map( 'absint', $post_date );

				if ( 3 === count( $post_date ) )
					$post_date = $post_date[2] . zeroise( $post_date[0], 2 ) . zeroise( $post_date[1], 2 );
				else
					$post_date = false;
			}

			if ( false === $post_date )
				$post_date = date( 'Ymd', current_time( 'timestamp' ) );

			echo json_encode( $this->_build_announcements_newsletter_response( array(), $post_date ) );
		} elseif ( 'news' === $_POST['newsletter_type'] ) {
			echo 'news';
		}

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

	/**
	 * Modify the default email address for email sent by WordPress.
	 *
	 * @return string The email address to use with the email.
	 */
	public function set_mail_from() {
		return 'wordpress@ur-web1.wsu.edu';
	}

	/**
	 * Modify the default email from name for email sent by WordPress.
	 *
	 * @return string The from name used in the email.
	 */
	public function set_mail_from_name() {
		return 'WSU Announcements';
	}

	/**
	 * Modify the default charset for email sent by WordPress.
	 *
	 * @return string The charset to use with the email.
	 */
	public function set_mail_charset() {
		return 'utf-8';
	}

	/**
	 * Receive and process an ajax request to send an email for an individual newsletter.
	 */
	public function ajax_send_newsletter() {
		if ( ! DOING_AJAX || ! isset( $_POST['action'] ) || 'send_newsletter' !== $_POST['action'] )
			die();

		$post_id = absint( $_POST['post_id'] );

		if ( ! $post_ids = get_post_meta( $post_id, '_newsletter_item_order', true ) ) {
			echo $post_id . 'No items to send...';
			exit;
		}

		$email_html = $this->_generate_html_email( $post_id, $post_ids );

		add_filter( 'wp_mail_from_name',    array( $this, 'set_mail_from_name'    ) );
		add_filter( 'wp_mail_from',         array( $this, 'set_mail_from'         ) );
		add_filter( 'wp_mail_content_type', array( $this, 'set_mail_content_type' ) );
		add_filter( 'wp_mail_charset',      array( $this, 'set_mail_charset'      ) );

		wp_mail( sanitize_email( $_POST['email'] ), get_the_title( $post_id ), $email_html );

		remove_filter( 'wp_mail_charset',      array( $this, 'set_mail_charset'      ) );
		remove_filter( 'wp_mail_content_type', array( $this, 'set_mail_content_type' ) );
		remove_filter( 'wp_mail_from',         array( $this, 'set_mail_from'         ) );
		remove_filter( 'wp_mail_from_name',    array( $this, 'set_mail_from_name'    ) );

		echo 'Emailed ' . esc_html( $_POST['email'] ) . '...';
		exit;
	}

	/**
	 * Alter the data being passed for a newsletter save. We specifically want to make sure
	 * that a proper title is being assigned.
	 *
	 * @param array $post_data Current data being processed for a post save.
	 *
	 * @return array Modified data for the post save.
	 */
	public function wp_insert_post_data( $post_data ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_data;

		if ( 'auto-draft' === $post_data['post_status'] || $this->post_type !== $post_data['post_type'] )
			return $post_data;

		if ( empty( $_POST['newsletter_date'] ) )
			return $post_data;

		$newsletter_date = explode( '/', $_POST['newsletter_date'] );
		$newsletter_date = array_map( 'absint', $newsletter_date );

		if ( 3 === count( $newsletter_date ) ) {
			$title_date  = date( 'l, F j, Y', strtotime( $newsletter_date[2] . '-' . zeroise( $newsletter_date[0], 2 ) . '-' . zeroise( $newsletter_date[1], 2 ) ) . ' 00:00:00' );
		} else {
			$title_date      = date( 'l, F j, Y', current_time( 'timestamp' ) );
		}

		$post_data['post_title'] = 'WSU Announcements for ' . $title_date;

		return $post_data;
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

		if ( 'auto-draft' === $post->post_status )
			return;

		if ( empty( $_POST['newsletter_item_order'] ) && empty( $_POST['newsletter_date'] ) )
			return;

		if ( ! empty( $_POST['newsletter_item_order'] ) ) {
			$newsletter_item_order = explode( ',', $_POST['newsletter_item_order'] );
			$newsletter_item_order = array_map( 'absint', $newsletter_item_order );
			update_post_meta( $post_id, '_newsletter_item_order', $newsletter_item_order );
		}

		if ( ! empty( $_POST['newsletter_date'] ) ) {
			$newsletter_date = explode( '/', $_POST['newsletter_date'] );
			$newsletter_date = array_map( 'absint', $newsletter_date );

			if ( 3 === count( $newsletter_date ) ) {
				$newsletter_date = $newsletter_date[2] . zeroise( $newsletter_date[0], 2 ) . zeroise( $newsletter_date[1], 2 );
			} else {
				$newsletter_date = date( 'Ymd', current_time( 'timestamp' ) );
			}

			update_post_meta( $post_id, '_newsletter_date', $newsletter_date );
		}
	}

	/**
	 * Build the HTML email to be sent out for a newsletter.
	 *
	 * @param int   $post_id  Post ID of the newsletter.
	 * @param array $post_ids List of post IDs to be used in the newsletter.
	 *
	 * @return string The full HTML email to be sent.
	 */
	private function _generate_html_email( $post_id, $post_ids ) {
		$email_title = esc_html( get_the_title( $post_id ) );
		$newsletter_items = $this->_build_announcements_newsletter_response( $post_ids );

		$html_email = <<<EMAIL
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width" />
    <title>$email_title</title>
  </head>
  <body style="width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; color: #222222; display: block; font-family: 'Helvetica', 'Arial', sans-serif; font-weight: normal; text-align: left; line-height: 19px; font-size: 14px; background: #fff; margin: 0; padding: 0;" bgcolor="#fff"><style type="text/css">
a:hover {
color: #ac0404 !important;
}
a:active {
color: #ac0404 !important;
}
a:visited {
color: #ac0404 !important;
}
h1 a:active {
color: #ac0404 !important;
}
h2 a:active {
color: #ac0404 !important;
}
h3 a:active {
color: #ac0404 !important;
}
h4 a:active {
color: #ac0404 !important;
}
h5 a:active {
color: #ac0404 !important;
}
h6 a:active {
color: #ac0404 !important;
}
h1 a:visited {
color: #ac0404 !important;
}
h2 a:visited {
color: #ac0404 !important;
}
h3 a:visited {
color: #ac0404 !important;
}
h4 a:visited {
color: #ac0404 !important;
}
h5 a:visited {
color: #ac0404 !important;
}
h6 a:visited {
color: #ac0404 !important;
}
@media only screen and (max-width: 600px) {
  table[class="body"] img {
    width: auto !important; height: auto !important;
  }
  table[class="body"] .container {
    width: 95% !important;
  }
  table[class="body"] .row {
    width: 100% !important; display: block !important;
  }
  table[class="body"] .wrapper {
    display: block !important; padding-right: 0 !important;
  }
  table[class="body"] .columns {
    table-layout: fixed !important; float: none !important; width: 100% !important; padding-right: 0px !important; padding-left: 0px !important; display: block !important;
  }
  table[class="body"] .column {
    table-layout: fixed !important; float: none !important; width: 100% !important; padding-right: 0px !important; padding-left: 0px !important; display: block !important;
  }
  table[class="body"] .wrapper.first .columns {
    display: table !important;
  }
  table[class="body"] .wrapper.first .column {
    display: table !important;
  }
  table[class="body"] table.columns td {
    width: 100%;
  }
  table[class="body"] table.column td {
    width: 100%;
  }
  table[class="body"] .expander {
    width: 9999px !important;
  }
  table[class="body"] center {
    min-width: 0 !important;
  }
}
</style>
	<table class="body" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; height: 100%; width: 100%; background: #fff; padding: 0;" bgcolor="#fff"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td class="center" align="center" valign="top" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: center; padding: 0;">
				<center style="width: 100%; min-width: 580px;">
					<table class="row header" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 100%; position: relative; padding: 0px;"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td class="center" align="center" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: center; padding: 0;" valign="top">
								<center style="width: 100%; min-width: 580px;">
									<table class="container" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: inherit; width: 580px; background: #fff; margin: 0 auto; padding: 0;" bgcolor="#fff"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td class="wrapper last" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; position: relative; padding: 10px 0px 0px;" align="left" valign="top">
												<table class="twelve columns" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 580px; margin: 0 auto; padding: 0;"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; padding: 0px 0px 10px;" align="left" valign="top"><img src="https://raw.github.com/washingtonstateuniversity/WSU-News-Announcements/master/images/wsu-announcements-banner.png" style="outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; width: auto; max-width: 100%; float: left; clear: both; display: block;" align="left" /></td>
													</tr></table></td>
										</tr></table></center>
							</td>
						</tr></table><table class="container" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: inherit; width: 580px; background: #fff; margin: 0 auto; padding: 0;" bgcolor="#fff"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; padding: 0;" align="left" valign="top">
								<table class="row" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 100%; position: relative; display: block; padding: 0px;"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td class="wrapper last" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; position: relative; padding: 10px 0px 0px;" align="left" valign="top">
											<table class="twelve columns" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 580px; margin: 0 auto; padding: 0;"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; padding: 0px 0px 0px 0px;" align="left" valign="top"><p style="color: #222222; display: block; font-style: italic; font-family: 'Lucida Grande', 'Lucida Sans Unicode', Arial, sans-serif; font-weight: normal; text-align: left; line-height: 19px; font-size: .9em; margin: 0 0 5px 0; padding: 0 0 0 0;" align="left">Submit announcements online at <a href="http://news.wsu.edu/announcements/" style="color: blue; text-decoration: none !important;">http://news.wsu.edu/announcements</a></p></td>
													<td class="expander" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; visibility: hidden; width: 0px; padding: 0;" align="left" valign="top"></td>
												</tr></table></td>
									</tr></table><table class="row callout" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 100%; position: relative; display: block; padding: 0px;"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td class="wrapper last" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; position: relative; padding: 10px 0px 0px;" align="left" valign="top">
											<table class="twelve columns" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 580px; margin: 0 auto; padding: 0;"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; padding: 0px 0px 5px;" align="left" valign="top">
EMAIL;

		foreach ( $newsletter_items as $item ) {
			$item_excerpt   = wp_kses_post( $item['excerpt']   );
			$item_permalink = esc_url_raw(  $item['permalink'] );
			$item_title     = esc_html(     $item['title']     );

			$html_email .= <<<EMAIL
<h3 style="color: #222222; display: block; font-family: 'Open Sans Condensed', 'Lucida Grande', 'Lucida Sans Unicode', arial, sans-serif; font-weight: 700; text-align: left; line-height: 1.3; word-break: normal; font-size: 15px; margin: 0; padding: 0;"
    align="left"><a href="$item_permalink" style="color: #ac0404 !important; text-decoration: none !important;">$item_title</a></h3>
<p style="color: #222222; display: block; font-family: 'Lucida Grande', 'Lucida Sans Unicode', Arial, sans-serif; font-weight: normal; text-align: left; line-height: 19px; font-size: 12px; margin: 10px 0 25px 0; padding: 0 25px 0 0;"
    align="left">$item_excerpt <a href="$item_permalink"
    style="color: #ac0404; text-decoration: none !important;">Continue reading&hellip;</a></p>
EMAIL;

		}

		$html_email .= <<<EMAIL
													</td>
													<td class="expander" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; visibility: hidden; width: 0px; padding: 0;" align="left" valign="top"></td>
												</tr></table></td>
									</tr></table><table class="row footer" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 100%; position: relative; display: block; padding: 0px;"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td class="wrapper" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; position: relative; padding: 10px 20px 0px 0px;" align="left" valign="top">
											<table class="twelve columns" style="border-spacing: 0; border-collapse: collapse; vertical-align: top; text-align: left; width: 580px; margin: 0 auto; padding: 0;"><tr style="vertical-align: top; text-align: left; padding: 0;" align="left"><td style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; padding: 0px 0px 10px;" align="left" valign="top">
														<p style="color: #222222; display: block; font-style: italic; font-family: 'Lucida Grande', 'Lucida Sans Unicode', Arial, sans-serif; font-weight: normal; text-align: left; line-height: 19px; font-size: .9em; margin: 10px 0 25px; padding: 0;" align="left">The Announcement newsletter will continue to be sent once a day at 10 a.m. Submissions made after 9 a.m. each day will appear in the next days’ newsletter. Any edits will be still be made by Brenda Campbell at <a style="color: blue; text-decoration: none !important;" href="mailto:bcampbell@wsu.edu">bcampbell@wsu.edu</a>.</p>
														<p style="color: #222222; display: block; font-style: italic; font-family: 'Lucida Grande', 'Lucida Sans Unicode', Arial, sans-serif; font-weight: normal; text-align: left; line-height: 19px; font-size: .9em; margin: 10px 0 25px; padding: 0;" align="left">If you are having difficulty reading the announcements, try unsubscribing and then resubscribe. Click <a style="color: blue; text-decoration: none !important;" href="http://lists.wsu.edu/leave.php">here</a> to unsubscribe and <a style="color: blue; text-decoration: none !important;" href="http://lists.wsu.edu/join.php">here</a> to subscribe</p>
													</td>
													<td class="expander" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; visibility: hidden; width: 0px; padding: 0;" align="left" valign="top"></td>
												</tr></table></td>
									</tr></table><!-- container end below --></td>
						</tr></table></center>
			</td>
		</tr></table></body>
</html>
EMAIL;

		return $html_email;
	}
}
$wsu_content_type_newsletter = new WSU_Content_Type_Newsletter();
