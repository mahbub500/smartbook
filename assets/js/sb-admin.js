/**
 * SmartBook admin scripts.
 *
 * Relies on the `sbAdmin` object localized by
 * SmartBook\Assets\AdminAssetLoader (ajaxUrl, nonce).
 */
( function ( window, document ) {
	'use strict';

	if ( typeof window.sbAdmin === 'undefined' ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.dispatchEvent( new CustomEvent( 'sb:admin:ready', { detail: window.sbAdmin } ) );
	} );
} )( window, document );
