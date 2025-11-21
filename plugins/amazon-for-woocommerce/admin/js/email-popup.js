(function ( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	(function ($) {
		$( document ).ready(
			function () {

				if ($( '.ced-popup-form-wrapper' ).length > 0) {
					function getCookie(name) {
						let match = document.cookie.match( new RegExp( '(^| )' + name + '=([^;]+)' ) );
						if (match) {
							return match[2];
						}
					}

					function setCookie(name, value, hours) {
						let date = new Date();
						date.setTime( date.getTime() + (hours * 60 * 60 * 1000) );
						let expires     = "expires=" + date.toUTCString();
						document.cookie = name + "=" + value + ";" + expires + ";path=/";
					}

					if ( ! getCookie( 'emailPopupShown' )) {
						$( '.ced-popup-form-wrapper' ).fadeIn();
						setCookie( 'emailPopupShown', 'yes', 48 );
					}

					$( '.ced-popup-close' ).click(
						function () {
							$( '.ced-popup-form-wrapper' ).fadeOut();
						}
						);
				}
			}
			);
	})( jQuery );

})( jQuery );