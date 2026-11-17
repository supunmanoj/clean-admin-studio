/* Clean Admin Studio - "Content boxes" button for the Classic Editor. */
( function () {
	'use strict';

	if ( ! window.tinymce ) {
		return;
	}

	tinymce.PluginManager.add( 'cas_blocks', function ( editor ) {
		var data = window.CAS_BLOCKS || { title: 'Content boxes', items: [] };
		var BOX = '.cas-box, .cas-steps, .cas-sources';

		function currentBox() {
			return editor.dom.getParent( editor.selection.getNode(), BOX );
		}

		function insert( item ) {
			var selected = editor.selection.getContent( { format: 'html' } );
			var plain = editor.selection.getContent( { format: 'text' } );
			var html = item.html;

			// Wrap whatever is selected instead of the sample text.
			if ( plain && plain.replace( /\s/g, '' ).length ) {
				if ( item.key === 'button' ) {
					html = '<p><a class="cas-button" href="#">' + editor.dom.encode( plain ) + '</a></p>';
				} else if ( item.key === 'sources' ) {
					html = '<p class="cas-sources">' + editor.dom.encode( plain ) + '</p>';
				} else if ( item.key === 'quick' || item.key === 'tip' || item.key === 'info' || item.key === 'warning' ) {
					var wrap = /<(p|ul|ol|h[1-6]|div|blockquote)[\s>]/i.test( selected ) ? selected : '<p>' + selected + '</p>';
					html = html.replace( /<p>[\s\S]*<\/p>/, wrap );
				}
			}

			// Never nest one box inside another: place the new one after it.
			var box = currentBox();
			editor.undoManager.transact( function () {
				if ( box ) {
					var holder = editor.dom.create( 'p', {}, '<br data-mce-bogus="1">' );
					editor.dom.insertAfter( holder, box );
					editor.selection.setCursorLocation( holder, 0 );
				}
				editor.insertContent( html + '<p>&nbsp;</p>' );
			} );
			editor.focus();
		}

		function paragraphBelow() {
			var box = currentBox();
			if ( ! box ) {
				return;
			}
			var p = editor.dom.create( 'p', {}, '<br data-mce-bogus="1">' );
			editor.undoManager.transact( function () {
				editor.dom.insertAfter( p, box );
			} );
			editor.selection.setCursorLocation( p, 0 );
			editor.focus();
		}

		function removeBox() {
			var box = currentBox();
			if ( ! box ) {
				return;
			}
			editor.undoManager.transact( function () {
				if ( box.nodeName === 'OL' || box.nodeName === 'P' ) {
					editor.dom.removeClass( box, 'cas-steps' );
					editor.dom.removeClass( box, 'cas-sources' );
					if ( ! box.className ) {
						box.removeAttribute( 'class' );
					}
				} else {
					editor.dom.remove( box, true );
				}
			} );
			editor.focus();
		}

		var menu = [];
		for ( var i = 0; i < data.items.length; i++ ) {
			( function ( item ) {
				menu.push( { text: item.text, onclick: function () { insert( item ); } } );
			}( data.items[ i ] ) );
		}
		menu.push( { text: '-' } );
		menu.push( { text: data.below || 'New paragraph below this box', onclick: paragraphBelow } );
		menu.push( { text: data.remove || 'Remove this box (keep the text)', onclick: removeBox } );

		editor.addButton( 'cas_blocks', {
			type: 'menubutton',
			text: data.title,
			tooltip: data.title,
			icon: false,
			menu: menu
		} );
	} );
}() );
