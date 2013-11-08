/**
 * Handle various features required in creation of newsletters in the admin.
 */
( function( $, window ) {

	var $newsletter_build = $('#newsletter-build'),
		sorted_data = [];

	$( '.newsletter-type').on( 'click', function( e ) {
		// Don't do anything rash.
		e.preventDefault();

		// Cache the newsletter build area for future use.
		var data = {
				action: 'set_newsletter_type',
				newsletter_type: this.id
			};

		// Make the ajax call
		$.post( window.ajaxurl, data, function( response ) {
			var data = '',
				response_data = $.parseJSON( response );

			// Append the results to the existing build of newsletter items.
			$.each( response_data, function( index, val ) {
				data = '<div id="newsletter-item-' + val.id + '" class="newsletter-item">' +
					     '<h3><a href="' + val.permalink + '">' + val.title + '</a></h3>' +
					     '<p>' + val.excerpt + ' <a href="' + val.permalink + '" >Continue reading&hellip;</a></p>' +
					   '</div>';

				$newsletter_build.prepend( data );
				data = '';
			});

			// Use jQuery UI Sortable to add sorting functionality to newsletter items.
			$newsletter_build.sortable( { axis: "y", opacity: 0.6 } );
		});
	});

	// Fire an event any time sorting has stopped after a move.
	$newsletter_build.on( "sortupdate", function( event, ui ) {
		// Store the existing sorted newsletter items as an array.
		sorted_data = $newsletter_build.sortable( 'toArray' );
	} );
}( jQuery, window ) );
