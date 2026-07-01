/*!
 * Scripts for action=markmajorchange at domready
 */
'use strict';

/**
 * @param {string} str
 * @return {number} UTF-8 byte length of the string
 */
function byteLength( str ) {
	return new Blob( [ str ] ).size;
}

/**
 * Trim an input's value so it never exceeds a byte limit (the "reason"
 * column is 255 bytes wide).
 *
 * @param {HTMLInputElement|HTMLTextAreaElement} input
 * @param {number} limit
 */
function enforceByteLimit( input, limit ) {
	input.addEventListener( 'input', () => {
		let value = input.value;
		while ( byteLength( value ) > limit ) {
			value = value.slice( 0, -1 );
		}
		if ( value !== input.value ) {
			input.value = value;
		}
	} );
}

function init() {
	const input = document.getElementById( 'mw-input-wpreason' );
	// Make sure reason text does not exceed byte limit
	if ( input && 'value' in input ) {
		enforceByteLimit( input, 255 );
	}
}

if ( document.readyState !== 'loading' ) {
	init();
} else {
	document.addEventListener( 'DOMContentLoaded', init );
}
