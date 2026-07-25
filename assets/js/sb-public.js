/**
 * SmartBook public scripts.
 *
 * Relies on the `sbPublic` object localized by
 * SmartBook\Assets\FrontendAssetLoader (ajaxUrl, restUrl, nonce).
 */
( function ( window, document ) {
	'use strict';

	if ( typeof window.sbPublic === 'undefined' ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.dispatchEvent( new CustomEvent( 'sb:public:ready', { detail: window.sbPublic } ) );
	} );
} )( window, document );
