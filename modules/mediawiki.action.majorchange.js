/*!
 * Scripts for action=markmajorchange at domready
 */
( function ( $ ) {
	'use strict';

	$( function () {
		// Make sure reason text does not exceed byte limit
		$( '#mw-input-wpreason' ).byteLimit( 255 );
	} );
}( jQuery ) );
