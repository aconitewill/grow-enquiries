/**
 * Grow Enquiries: the person checks and a little help.
 *
 * - Writes the JS token and the seconds spent on the page into hidden
 *   fields. A bot that doesn't run JavaScript, or posts too fast, is held.
 * - Pre-selects a field from the URL (data-gre-prefill="topic" reads
 *   ?topic=...). Done here, not in PHP, because the page is cached.
 * - Moves focus to the thank-you or error after the redirect.
 * - Fires "gre:ready" on each form so a theme can add to it.
 */
( function () {
	'use strict';

	var cfg = window.GRE || {};
	var t0  = Date.now();
	var params;
	try { params = new URLSearchParams( window.location.search ); } catch ( e ) { params = null; }

	document.querySelectorAll( '[data-gre-form]' ).forEach( function ( form ) {
		var js = form.querySelector( '[data-gre-js]' );
		if ( js ) { js.value = cfg.token || ''; }

		form.addEventListener( 'submit', function () {
			var el = form.querySelector( '[data-gre-elapsed]' );
			if ( el ) { el.value = String( Math.round( ( Date.now() - t0 ) / 1000 ) ); }
		} );

		if ( params ) {
			form.querySelectorAll( '[data-gre-prefill]' ).forEach( function ( field ) {
				var v = params.get( field.getAttribute( 'data-gre-prefill' ) );
				if ( ! v || field.value ) { return; }
				if ( field.tagName === 'SELECT' ) {
					for ( var i = 0; i < field.options.length; i++ ) {
						if ( field.options[ i ].value === v ) { field.value = v; break; }
					}
				} else {
					field.value = v.slice( 0, 200 );
				}
			} );
		}

		try {
			form.dispatchEvent( new CustomEvent( 'gre:ready', { bubbles: true } ) );
		} catch ( e ) { /* very old browser */ }
	} );

	var note = document.querySelector( '[data-gre-focus]' );
	if ( note ) {
		note.focus( { preventScroll: false } );
	}
} )();
