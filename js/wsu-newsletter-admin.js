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
			// Use this data to setup the remainder.
			console.log( response );
		});
	});
}( jQuery, window ) );
