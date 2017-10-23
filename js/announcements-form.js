/**
 * Handle the submission of the announcements form at news.wsu.edu
 *
 * announcementSubmission.ajaxurl is available to us in the global scope
 */
( function( $ ) {

	/**
	 * Perform very basic validation on the email address.
	 *
	 * @param email string containing email address to test.
	 * @returns {boolean} True if pattern matches. False for failure.
	 */
	validate_email = function( email ) {
		var pattern = /\S+@\S+/;
		return pattern.test( email );
	};

	clean_array = function( data ) {
		new_array = new Array();

		data.each( function( key, val ) {
			if ( "" !== val ) {
				new_array.push( val );
			}
		} );

		return new_array;
	};

	// Add datepicker elements to all date inputs.
	$( "#announcement-form-date1" ).datepicker();
	$( "#announcement-form-date2" ).datepicker();
	$( "#announcement-form-date3" ).datepicker();

	/**
	 * Handle the submission of the form as well as basic validation of the
	 * content before submitting the ajax request.
	 */
	$( "#announcement-form-submit" ).click( function( e ) {
		// Don't actually submit the form.
		e.preventDefault();

		// Trigger the tinyMCE save method on the editor so that the textarea is filled properly.
		tinyMCE.get( "announcement-form-text" ).save();

		/**
		 * The title of the announcement.
		 *
		 * @type {string}
		 */
		var form_title = $( "#announcement-form-title" ).val();

		/**
		 * The email address associated with the announcement.
		 *
		 * @type {string}
		 */
		var form_email = $( "#announcement-form-email" ).val();

		/**
		 * The content of the announcement.
		 *
		 * @type {string}
		 */
		var form_text  = $( "#announcement-form-text" ).val();

		/**
		 * The date(s) on which the announcement should be published.
		 *
		 * @type {array}
		 */
		var form_dates = $( '[name="announcement-date[]"]' ).map( function() { return $( this ).val(); } );

		form_dates = clean_array( form_dates );

		if ( "" === form_title ) {
			alert( "Please provide a title for the announcement." );
			return 0;
		}

		if ( false === validate_email( form_email ) ) {
			alert( "Please provide a contact email for the announcement." );
			return 0;
		}

		if ( "" === form_text ) {
			alert( "Please provide content for the announcement." );
			return 0;
		}

		if ( 0 === form_dates.length ) {
			alert( "Please enter at least one date to publish this announcement on." );
			return 0;
		}

		// Build the data for our ajax call
		var data = {
			action: "submit_announcement",
			title:  form_title,
			text:   form_text,
			email:  form_email,
			dates:  form_dates,
			other:  $( "#announcement-form-other" ).val()
		};

		// Make the ajax call
		$.post( announcementSubmission.ajaxurl, data, function( response ) {
			if ( "success" === response ) {
				$( "#announcement-submission-form" ).html( '<span class="announcement-success">Announcement submitted for approval.</span>' );
			} else {
				alert( "Error submitting form. Please try again." );
			}
		} );
	} );
} )( jQuery );
