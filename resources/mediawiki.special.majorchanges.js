/*!
 * Replace the "handled" tag checkbox with a static check mark on
 * Special:MajorChangesLog.
 */
'use strict';

function init() {
	Array.prototype.forEach.call(
		document.querySelectorAll( '.mw-tag-שינוי_מהותי_טופל' ),
		( el ) => {
			const checkbox = el.querySelector( 'input[type="checkbox"]' );
			if ( checkbox ) {
				const check = document.createElement( 'span' );
				check.className = 'fa fa-check';
				check.setAttribute( 'aria-hidden', 'true' );

				const srText = document.createElement( 'span' );
				srText.className = 'sr-only';
				srText.textContent = 'טופל';

				checkbox.replaceWith( check, srText );
			}

			const markers = el.querySelector( '.mw-tag-markers' );
			if ( markers ) {
				markers.style.display = 'none';
			}
		}
	);
}

if ( document.readyState !== 'loading' ) {
	init();
} else {
	document.addEventListener( 'DOMContentLoaded', init );
}
