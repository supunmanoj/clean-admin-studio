/* Clean Admin Studio - keeps the Title column readable on content lists.
   When a list has more columns than fit, the table scrolls sideways instead
   of squeezing the title into a sliver. */
( function () {
	'use strict';

	var MIN = 180;
	var table = document.querySelector( '#posts-filter .wp-list-table' );
	var wrap = null;
	var timer = null;

	if ( ! table ) {
		return;
	}

	function fit() {
		table.style.minWidth = '';
		if ( window.innerWidth <= 782 ) {
			return;
		}
		var th = table.querySelector( 'thead .column-primary' );
		if ( ! th ) {
			return;
		}
		var width = th.getBoundingClientRect().width;
		if ( width >= MIN ) {
			return;
		}
		if ( ! wrap ) {
			wrap = document.createElement( 'div' );
			wrap.className = 'cas-table-scroll';
			table.parentNode.insertBefore( wrap, table );
			wrap.appendChild( table );
		}
		// Columns sized in percent grow with the table, so widen in steps
		// until the title really has its room.
		var target = table.getBoundingClientRect().width;
		for ( var i = 0; i < 10 && width < MIN - 1; i++ ) {
			target += Math.ceil( ( MIN - width ) * 1.4 );
			table.style.minWidth = target + 'px';
			width = th.getBoundingClientRect().width;
		}
	}

	function later() {
		window.clearTimeout( timer );
		timer = window.setTimeout( fit, 120 );
	}

	fit();
	window.addEventListener( 'resize', later );
	// Screen Options: columns being shown or hidden.
	document.addEventListener( 'click', function ( event ) {
		if ( event.target && event.target.classList && event.target.classList.contains( 'hide-column-tog' ) ) {
			later();
		}
	} );
}() );
