/**
 * Handle various features required in creation of newsletters in the admin.
 */
(function( $, window ) {

	/**
	 * Selector cache of the container holding all of the items for an
	 * individual newsletter.
	 *
	 * @type {*|HTMLElement}
	 */
	var $newsletter_build = $( '#newsletter-build-items' );

	/**
	 * Holds the current IDs and order of the newsletter items for an
	 * individual newsletter.
	 *
	 * @type {Array}
	 */
	var sorted_data = [];

	if ( window.wsu_newsletter.items instanceof Array ) {
		load_newsletter_items( window.wsu_newsletter.items );
	}

	/**
	 * Process an existing list of newsletter items and add them to the front end
	 * view of the newsletter build.
	 *
	 * @param raw_data
	 */
	function load_newsletter_items( raw_data ) {
		var data = '';

		var checked = $('#newsletter-type').prop('checked');

		// Append the results to the existing build of newsletter items.
		$.each( raw_data, function( index, val ) {
			data += '<div id="newsletter-item-' + val.id + '" class="newsletter-item">' +
				'<h3><a href="' + val.permalink + '">' + val.title + '</a></h3>';

			if ( checked ) {
				data += val.content;
			} else {
				data += '<p>' + val.excerpt + ' <a href="' + val.permalink + '" >Continue reading&hellip;</a></p>';
			}

			data += '<span class="newsletter-item-remove">Remove</span>' +
				'</div>';
		} );

		$newsletter_build.html( data );

		// Use jQuery UI Sortable to add sorting functionality to newsletter items.
		$newsletter_build.sortable( { axis: "y", opacity: 0.6, items: ".newsletter-item" } );
	}

	/**
	 * As newsletter items are sorted in the newsletter build container, process
	 * the associated post IDs into something that we can pass to the back end.
	 */
	function process_sorted_data() {
		var new_val = '';

		sorted_data = $newsletter_build.sortable( 'toArray' );

		// Strip `newsletter-item-` from the beginning of each newsletter item ID
		$.each( sorted_data, function( index, val ) {
			new_val = val.replace( /newsletter-item-/g, '' );
			sorted_data[index] = new_val;
		} );

		$( '#newsletter-item-order' ).val( sorted_data );
	}

	$( '.newsletter-type' ).on( 'click', function( e ) {
		// Don't do anything rash.
		e.preventDefault();

		var post_date = $( '#newsletter-date' ).val();

		// Cache the newsletter build area for future use.
		var data = {
			action: 'set_newsletter_type',
			newsletter_type: this.id,
			post_id: window.wsu_newsletter.post_id,
			post_date: post_date
		};

		// Make the ajax call
		$.post( window.ajaxurl, data, function( response ) {
			var data = '',
				response_data = $.parseJSON( response );

			load_newsletter_items( response_data );
			process_sorted_data();
		} );
	} );

	$( '#newsletter-send' ).on( 'click', function( e ) {
		// Not entirely sure this button has a default, but if it does...
		e.preventDefault();

		var data = {
			action: 'send_newsletter',
			post_id: window.wsu_newsletter.post_id,
			email: $( '#newsletter-email' ).val()
		};

		$.post( window.ajaxurl, data, function( response ) {
			$( '#newsletter-send-response' ).html( response );
		} )
	} );

	$newsletter_build.on( 'click', '.newsletter-item-remove', function( e ) {
		// Doesn't hurt.
		e.preventDefault();

		$( this ).parent().remove();
		process_sorted_data();
	} );

	$( '#newsletter-date' ).datepicker();

	// Fire an event any time sorting has stopped after a move.
	$newsletter_build.on( "sortupdate", process_sorted_data );
}( jQuery, window ));
