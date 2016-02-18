/*!
 * Scripts for action=markmajorchange at domready
 */
( function ( mw, $ ) {
	'use strict';

	$( function () {
		// Make sure reason text does not exceed byte limit
		$( '#mw-input-wpreason' ).byteLimit( 255 );
	} );
}( mediaWiki, jQuery ) );
