<?php

class WSU_News_Announcement_Calendar_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'classname' => 'widget_calendar wsu_widget_calendar', 'description' => 'A calendar of announcements' );
		parent::__construct( 'wsu_calendar', 'Announcement Calendar', $widget_ops );
	}

	function widget( $args, $instance ) {
		/* @var WSU_News_Announcements $wsu_news_announcements */
		global $wsu_news_announcements;
		extract($args);
		$title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		echo '<div id="calendar_wrap">';
		$wsu_news_announcements->get_calendar();
		echo '</div>';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title = strip_tags($instance['title']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
	<?php
	}
}