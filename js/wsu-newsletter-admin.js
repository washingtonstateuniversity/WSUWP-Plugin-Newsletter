/**
 * Handle various features required in creation of newsletters in the admin.
 */
( function( $, window ) {

	$( '.newsletter-type').on( 'click', function( e ) {
		// Don't do anything rash.
		e.preventDefault();

		// Pass this.id to window.ajaxurl
		var data = {
			action: 'set_newsletter_type',
			newsletter_type: this.id
		};

		// Make the ajax call
		$.post( window.ajaxurl, data, function( response ) {

			// Append the results to the existing build of newsletter items.
			$('#newsletter-build').append( response );
		});
	});
}( jQuery, window ) );
