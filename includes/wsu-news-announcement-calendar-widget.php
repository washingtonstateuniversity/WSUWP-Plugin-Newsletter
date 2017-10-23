<?php
/**
 * Class WSU_News_Announcement_Calendar_Widget
 *
 * Provide a widget which displays a monthly calendar with links to daily announcement
 * archives.
 */
class WSU_News_Announcement_Calendar_Widget extends WP_Widget {

	/**
	 * Build the WSU News Announcement Calendar widget.
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'widget_calendar wsu_widget_calendar',
			'description' => 'A monthly calendar with links to daily announcement archives.',
		);
		parent::__construct( 'wsu_calendar', 'Announcement Calendar', $widget_ops );
	}

	/**
	 * Display the widget.
	 *
	 * @param array $args     General arguments passed to the widget for display.
	 * @param array $instance Arguments specific to this instance of the widget.
	 */
	function widget( $args, $instance ) {
		/* @var WSU_Content_Type_Announcement $wsu_news_announcements */
		global $wsu_content_type_announcement;

		$title = '';
		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		}

		echo $args['before_widget']; // @codingStandardsIgnoreLine
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // @codingStandardsIgnoreLine
		}

		echo '<div id="calendar_wrap">';
		$wsu_content_type_announcement->get_calendar();
		echo '</div>';
		echo $args['after_widget']; // @codingStandardsIgnoreLine
	}

	/**
	 * Update the widget's settings.
	 *
	 * @param array $new_instance The newly submitted instance of the widget.
	 * @param array $old_instance The version of the widget being updated.
	 *
	 * @return array The updated widget.
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}

	/**
	 * Display a form for updating the widget.
	 *
	 * @param array $instance The current state of the widget.
	 */
	function form( $instance ) {
		$instance = wp_parse_args(
			(array) $instance, array(
				'title' => '',
			)
		);
		$title = strip_tags( $instance['title'] );
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Title</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></p>
	<?php
	}
}
