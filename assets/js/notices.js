/* Clean Admin Studio - notification center: gathers admin notices behind a bell. */
( function ( $ ) {
	'use strict';

	$( function () {
		var $body = $( document.body );
		try {
			var $panel = $( '#cas-notice-panel' ), $list = $panel.find( '.cas-np-body' ), $bell = $( '#wp-admin-bar-cas-notices' );
			if ( ! $panel.length || ! $bell.length ) {
				return;
			}

			var $found = $( '#wpbody-content' ).find( '.notice, div.updated, div.error, .update-nag' ).filter( function () {
				var $n = $( this );
				// Keep save confirmations, inline messages and anything inside tables or widgets in place.
				if ( $n.is( '.settings-error, #message, .inline, .hidden, .hide-if-js, .cas-keep, .below-h2' ) ) {
					return false;
				}
				if ( $n.closest( 'table, .postbox, .cas-notice-panel, .media-modal, .plugin-card, .theme-browser, #cas-form' ).length ) {
					return false;
				}
				return $n.parent().closest( '.notice, div.updated, div.error' ).length === 0;
			} );

			$found.each( function () {
				$( this ).removeClass( 'is-dismissible' ).addClass( 'cas-collected' ).appendTo( $list ).find( '> .notice-dismiss' ).remove();
			} );

			var count = $list.children().length;
			$bell.add( '.cas-bell-fab' ).find( '.cas-count' ).text( count );
			$( '.cas-bell-fab' ).toggleClass( 'has-items', count > 0 );
			$bell.toggleClass( 'cas-bell-empty', count === 0 );
			$panel.toggleClass( 'is-empty', count === 0 );

			var toggle = function ( open ) {
				$body.toggleClass( 'cas-np-open', open );
				$panel.attr( 'aria-hidden', open ? 'false' : 'true' );
			};
			$bell.on( 'click', 'a', function ( e ) {
				e.preventDefault();
				toggle( ! $body.hasClass( 'cas-np-open' ) );
			} );
			$( '.cas-bell-fab' ).on( 'click', function () { toggle( ! $body.hasClass( 'cas-np-open' ) ); } );
			$( '.cas-np-close, .cas-np-backdrop' ).on( 'click', function () { toggle( false ); } );
			$( document ).on( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					toggle( false );
				}
			} );
		} finally {
			// Whatever happens, never leave notices hidden.
			$body.addClass( 'cas-nc-ready' );
		}
	} );
}( jQuery ) );
