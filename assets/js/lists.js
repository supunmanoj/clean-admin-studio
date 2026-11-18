/* Clean Admin Studio - keeps the Title column readable on content lists.
   When a list has more columns than fit, the Author, Categories and Tags
   columns give up some of their width first. If that is not enough, the
   table scrolls sideways instead of squeezing the title into a sliver. */
( function () {
	'use strict';

	var MIN = 180;
	var FLOOR = 108; // Narrowest a column that gives up room may become.
	var GIVERS = [ 'author', 'categories', 'tags' ];
	var table = document.querySelector( '#posts-filter .wp-list-table' );
	var wrap = null;
	var timer = null;

	if ( ! table ) {
		return;
	}

	function givers() {
		var found = [];
		GIVERS.forEach( function ( key ) {
			var cell = table.querySelector( 'thead .column-' + key );
			if ( cell ) {
				found.push( cell );
			}
		} );
		return found;
	}

	// Takes the missing width from the columns that can spare it, each in
	// proportion to what it has above the floor.
	function slim( missing ) {
		var cells = givers(), spare = [], total = 0;
		cells.forEach( function ( cell, i ) {
			spare[ i ] = Math.max( 0, cell.getBoundingClientRect().width - FLOOR );
			total += spare[ i ];
		} );
		if ( total < 1 ) {
			return;
		}
		var take = Math.min( missing, total );
		cells.forEach( function ( cell, i ) {
			if ( spare[ i ] > 0 ) {
				var inner = parseFloat( window.getComputedStyle( cell ).width ) || 0;
				// "important" because some plugins force these widths that way.
				cell.style.setProperty( 'width', Math.max( 40, Math.floor( inner - take * spare[ i ] / total ) ) + 'px', 'important' );
				cell.setAttribute( 'data-cas-width', '1' );
			}
		} );
	}

	// Fixes every other column at the width it has now, so that when the
	// table is widened all of the extra room goes to the title.
	function pin( title ) {
		var cells = table.querySelectorAll( 'thead tr > *' ), widths = [], i;
		for ( i = 0; i < cells.length; i++ ) {
			widths[ i ] = ( cells[ i ] === title || ! cells[ i ].getBoundingClientRect().width ) ? null : ( parseFloat( window.getComputedStyle( cells[ i ] ).width ) || null );
		}
		for ( i = 0; i < cells.length; i++ ) {
			if ( null !== widths[ i ] ) {
				cells[ i ].style.setProperty( 'width', widths[ i ] + 'px', 'important' );
				cells[ i ].setAttribute( 'data-cas-width', '1' );
			}
		}
	}

	function release() {
		var cells = table.querySelectorAll( 'thead [data-cas-width]' ), i;
		for ( i = 0; i < cells.length; i++ ) {
			cells[ i ].style.removeProperty( 'width' );
			cells[ i ].removeAttribute( 'data-cas-width' );
		}
	}

	function fit() {
		table.style.minWidth = '';
		release();
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
		slim( Math.ceil( MIN - width ) + 1 );
		width = th.getBoundingClientRect().width;
		if ( width >= MIN - 1 ) {
			return;
		}
		if ( ! wrap ) {
			wrap = document.createElement( 'div' );
			wrap.className = 'cas-table-scroll';
			table.parentNode.insertBefore( wrap, table );
			wrap.appendChild( table );
		}
		// Widen in steps until the title really has its room.
		pin( th );
		var target = table.getBoundingClientRect().width;
		for ( var i = 0; i < 10 && width < MIN - 1; i++ ) {
			target += Math.ceil( MIN - width ) + 1;
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
