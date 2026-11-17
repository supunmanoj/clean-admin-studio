/* Clean Admin Studio - settings screen */
( function ( $ ) {
	'use strict';

	var ICONS = [ 'admin-home', 'dashboard', 'admin-site', 'admin-post', 'admin-page', 'admin-media', 'admin-comments', 'admin-appearance', 'admin-plugins', 'admin-users', 'admin-tools', 'admin-settings', 'admin-generic', 'admin-network', 'admin-links', 'admin-customizer', 'admin-multisite', 'menu', 'menu-alt', 'edit', 'welcome-write-blog', 'welcome-add-page', 'welcome-view-site', 'welcome-widgets-menus', 'welcome-learn-more', 'format-image', 'format-gallery', 'format-video', 'format-audio', 'format-quote', 'format-chat', 'format-status', 'format-aside', 'camera', 'images-alt', 'images-alt2', 'video-alt2', 'video-alt3', 'media-document', 'media-text', 'media-spreadsheet', 'media-archive', 'media-code', 'playlist-audio', 'playlist-video', 'text-page', 'cover-image', 'editor-table', 'editor-code', 'editor-help', 'lock', 'unlock', 'shield', 'shield-alt', 'vault', 'privacy', 'visibility', 'hidden', 'calendar', 'calendar-alt', 'clock', 'schedule', 'hourglass', 'sticky', 'external', 'share', 'share-alt', 'rss', 'email', 'email-alt', 'megaphone', 'bell', 'feedback', 'testimonial', 'format-chat', 'phone', 'smartphone', 'tablet', 'laptop', 'desktop', 'networking', 'cloud', 'database', 'backup', 'download', 'upload', 'update', 'migrate', 'performance', 'analytics', 'chart-pie', 'chart-bar', 'chart-line', 'chart-area', 'groups', 'businessman', 'businesswoman', 'businessperson', 'id', 'id-alt', 'nametag', 'universal-access', 'cart', 'products', 'store', 'money', 'money-alt', 'bank', 'calculator', 'tickets', 'tickets-alt', 'awards', 'star-filled', 'star-empty', 'heart', 'thumbs-up', 'smiley', 'flag', 'tag', 'category', 'archive', 'portfolio', 'book', 'book-alt', 'clipboard', 'index-card', 'forms', 'list-view', 'grid-view', 'excerpt-view', 'layout', 'screenoptions', 'slides', 'art', 'color-picker', 'hammer', 'lightbulb', 'superhero', 'sos', 'info', 'warning', 'yes-alt', 'plus-alt', 'search', 'filter', 'sort', 'randomize', 'location', 'location-alt', 'building', 'airplane', 'car', 'palmtree', 'food', 'coffee', 'carrot', 'pets', 'games', 'album', 'microphone', 'translation', 'html', 'rest-api', 'code-standards', 'pdf', 'printer', 'open-folder', 'paperclip', 'saved', 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'whatsapp', 'pinterest' ];

	var PRESETS = [
		{ name: 'Indigo Night', c: { c_accent: '#4f46e5', c_link: '#4f46e5', c_menu_bg: '#111827', c_menu_text: '#d1d5db', c_bar_bg: '#111827', c_bar_text: '#e5e7eb', c_body_bg: '#f3f4f6', c_text: '#374151', c_heading: '#111827' } },
		{ name: 'Ocean', c: { c_accent: '#0284c7', c_link: '#0369a1', c_menu_bg: '#0c2d48', c_menu_text: '#c7dbea', c_bar_bg: '#0c2d48', c_bar_text: '#e0edf6', c_body_bg: '#f1f5f9', c_text: '#334155', c_heading: '#0f172a' } },
		{ name: 'Forest', c: { c_accent: '#15803d', c_link: '#166534', c_menu_bg: '#14261c', c_menu_text: '#cfe0d4', c_bar_bg: '#14261c', c_bar_text: '#e3efe6', c_body_bg: '#f4f6f3', c_text: '#374137', c_heading: '#14261c' } },
		{ name: 'Clean Light', c: { c_accent: '#2563eb', c_link: '#1d4ed8', c_menu_bg: '#ffffff', c_menu_text: '#475569', c_bar_bg: '#ffffff', c_bar_text: '#334155', c_body_bg: '#f8fafc', c_text: '#334155', c_heading: '#0f172a' } },
		{ name: 'Rose', c: { c_accent: '#e11d48', c_link: '#be123c', c_menu_bg: '#1f1720', c_menu_text: '#e2d5dc', c_bar_bg: '#1f1720', c_bar_text: '#f1e7ec', c_body_bg: '#faf7f8', c_text: '#44363d', c_heading: '#1f1720' } },
		{ name: 'Graphite', c: { c_accent: '#d97706', c_link: '#b45309', c_menu_bg: '#1c1917', c_menu_text: '#d6d3d1', c_bar_bg: '#1c1917', c_bar_text: '#e7e5e4', c_body_bg: '#f5f5f4', c_text: '#44403c', c_heading: '#1c1917' } }
	];

	$( function () {
		var $tabs = $( '.cas-tab' ), $panels = $( '.cas-panel' );

		/* Tabs ------------------------------------------------------------ */
		function show( id, remember ) {
			if ( ! id || ! $panels.filter( '[data-panel="' + id + '"]' ).length ) {
				id = 'start';
			}
			$tabs.removeClass( 'is-active' ).removeAttr( 'aria-current' ).filter( '[data-tab="' + id + '"]' ).addClass( 'is-active' ).attr( 'aria-current', 'page' );
			$panels.removeClass( 'is-active' ).filter( '[data-panel="' + id + '"]' ).addClass( 'is-active' );
			$( '.cas-save' ).toggle( id !== 'tools' );
			// On narrow screens the tabs are a strip that scrolls sideways: bring the open one into view.
			var tab = $tabs.filter( '.is-active' )[0], strip = tab ? tab.parentNode : null;
			if ( strip && strip.scrollWidth > strip.clientWidth + 1 ) {
				strip.scrollLeft += tab.getBoundingClientRect().left - strip.getBoundingClientRect().left - ( strip.clientWidth - tab.offsetWidth ) / 2;
			}
			if ( remember ) {
				try { window.localStorage.setItem( 'casTab', id ); } catch ( e ) {}
				if ( window.history.replaceState ) {
					window.history.replaceState( null, '', '#' + id );
				}
			}
		}
		var initial = window.location.hash.replace( '#', '' );
		if ( ! initial ) {
			try { initial = window.localStorage.getItem( 'casTab' ); } catch ( e ) {}
		}
		show( initial, false );
		$( window ).on( 'hashchange', function () {
			show( window.location.hash.replace( '#', '' ), false );
		} );
		$( document ).on( 'click', '.cas-tab, .cas-goto', function ( e ) {
			e.preventDefault();
			show( $( this ).data( 'tab' ), true );
			window.scrollTo( 0, 0 );
		} );

		/* Colors and presets ---------------------------------------------- */
		/* Content boxes: the custom fields show only when asked for, and the
		   preview follows every change before saving. */
		var preview = document.querySelector( '.cas-preview' );
		var boxVars = function () {
			var custom = $( '#cas-ed_style' ).val() === 'custom';
			$( '#cas-box-custom' ).prop( 'hidden', ! custom );
			if ( ! preview ) {
				return;
			}
			var hex = function ( key ) {
				var value = $( '#cas-' + key ).val() || '';
				return custom && /^#[0-9a-f]{6}$/i.test( value ) ? value : '';
			};
			var set = function ( name, value ) {
				preview.style.setProperty( name, value || 'initial' );
			};
			var accent = hex( 'ed_accent' ), bg = hex( 'ed_bg' ), on = '';
			if ( accent ) {
				var n = parseInt( accent.slice( 1 ), 16 );
				on = ( 0.299 * ( n >> 16 ) + 0.587 * ( ( n >> 8 ) & 255 ) + 0.114 * ( n & 255 ) ) / 255 > 0.6 ? '#111827' : '#ffffff';
			}
			set( '--cas-box-accent', accent );
			set( '--cas-box-on-accent', on );
			set( '--cas-box-bg', bg );
			set( '--cas-box-tint', bg ? '0%' : '' );
			set( '--cas-box-text', hex( 'ed_text' ) );
			set( '--cas-box-border', hex( 'ed_border' ) );
			set( '--cas-box-radius', custom ? Math.max( 0, Math.min( 28, parseInt( $( '#cas-ed_radius' ).val(), 10 ) || 0 ) ) + 'px' : '' );
		};
		var boxVarsSoon = function () {
			window.setTimeout( boxVars, 0 );
		};
		$( '#cas-ed_style, #cas-ed_radius' ).on( 'input change', boxVars );
		$( '#cas-ed_quick_label' ).on( 'input', function () {
			if ( preview ) {
				preview.style.setProperty( '--cas-quick-label', "'" + this.value.replace( /['"\\<>]/g, ' ' ) + "'" );
			}
		} );
		$( '.cas-color' ).wpColorPicker( { change: boxVarsSoon, clear: boxVarsSoon } );
		boxVars();

		var $presets = $( '#cas-presets' );
		$.each( PRESETS, function ( i, p ) {
			var $b = $( '<button type="button" class="cas-preset"></button>' );
			$b.append( $( '<span class="cas-preset-swatch"></span>' ).append(
				$( '<i></i>' ).css( 'background', p.c.c_menu_bg ),
				$( '<i></i>' ).css( 'background', p.c.c_accent ),
				$( '<i></i>' ).css( 'background', p.c.c_body_bg )
			) );
			$b.append( $( '<span></span>' ).text( p.name ) );
			$b.on( 'click', function () {
				$.each( p.c, function ( key, value ) {
					$( '#cas-' + key ).wpColorPicker( 'color', value );
				} );
				$presets.find( '.cas-preset' ).removeClass( 'is-active' );
				$b.addClass( 'is-active' );
			} );
			$presets.append( $b );
		} );

		/* Match the content-box accent to the theme's link colour ---------- */
		$( '#cas-match-theme' ).on( 'click', function () {
			var $btn = $( this ), $out = $( '#cas-match-result' ), frame = document.createElement( 'iframe' );
			frame.setAttribute( 'aria-hidden', 'true' );
			frame.tabIndex = -1;
			frame.style.cssText = 'position:absolute;left:-9999px;top:0;width:1200px;height:800px;visibility:hidden';
			var done = function ( text ) {
				$out.text( text );
				$btn.prop( 'disabled', false );
				if ( frame.parentNode ) {
					frame.parentNode.removeChild( frame );
				}
			};
			$btn.prop( 'disabled', true );
			$out.text( '' );
			frame.onload = function () {
				try {
					// A fresh link placed in the article area shows the theme's plain
					// link colour (existing links may be buttons or menu items).
					var doc = frame.contentDocument;
					var view = frame.contentWindow;
					var home = doc.querySelector( '.entry-content, .post-content, article, main, #content' ) || doc.body;
					var probe = doc.createElement( 'a' );
					probe.href = '#';
					home.appendChild( probe );
					var colour = view.getComputedStyle( probe ).color;
					var text = view.getComputedStyle( home ).color;
					home.removeChild( probe );
					var parts = /^rgba?\(/.test( colour ) ? ( colour.match( /[\d.]+/g ) || [] ) : [];
					if ( parts.length < 3 || colour === text ) {
						done( CAS_DATA.themeFailed );
						return;
					}
					var hex = '#' + parts.slice( 0, 3 ).map( function ( n ) {
						return ( '0' + parseInt( n, 10 ).toString( 16 ) ).slice( -2 );
					} ).join( '' );
					$( '#cas-ed_accent' ).wpColorPicker( 'color', hex );
					done( CAS_DATA.themeFound + ' ' + hex );
				} catch ( e ) {
					done( CAS_DATA.themeFailed );
				}
			};
			frame.onerror = function () { done( CAS_DATA.themeFailed ); };
			frame.src = $btn.data( 'url' );
			document.body.appendChild( frame );
		} );

		/* Images ---------------------------------------------------------- */
		function setImage( $wrap, url ) {
			$wrap.find( 'input' ).val( url );
			$wrap.find( '.cas-image-preview' ).html( url ? $( '<img alt="" />' ).attr( 'src', url ) : '' );
		}
		$( document ).on( 'click', '.cas-image-pick', function () {
			var $wrap = $( this ).closest( '.cas-image' );
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			var frame = wp.media( { title: CAS_DATA.chooseImage, button: { text: CAS_DATA.useImage }, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				setImage( $wrap, frame.state().get( 'selection' ).first().toJSON().url );
			} );
			frame.open();
		} );
		$( document ).on( 'click', '.cas-image-clear', function () {
			setImage( $( this ).closest( '.cas-image' ), '' );
		} );
		$( document ).on( 'change', '.cas-image input', function () {
			setImage( $( this ).closest( '.cas-image' ), $.trim( $( this ).val() ) );
		} );

		/* Dashboard widgets ----------------------------------------------- */
		$( '#cas-widgets-all, #cas-widgets-none' ).on( 'click', function () {
			$( '#cas-widgets input' ).prop( 'checked', this.id === 'cas-widgets-all' );
		} );

		/* Menu editor ----------------------------------------------------- */
		var $list = $( '#cas-menu-list' ), $json = $( '#cas-menu-json' ), saved = {}, topReordered = false;
		try { saved = JSON.parse( $json.val() || '{}' ) || {}; } catch ( e ) { saved = {}; }
		topReordered = !! ( saved.order && saved.order.length );
		$list.find( '.cas-mi[data-pkey]' ).each( function () {
			var s = saved.sub && saved.sub[ $( this ).attr( 'data-pkey' ) ];
			if ( s && s.order && s.order.length ) {
				$( this ).attr( 'data-reordered', '1' );
			}
		} );

		if ( $list.length && $.fn.sortable ) {
			$list.sortable( { handle: '> .cas-mi-row .cas-handle', axis: 'y', placeholder: 'cas-placeholder', forcePlaceholderSize: true, update: function () { topReordered = true; } } );
			$list.find( '.cas-sub-list' ).sortable( { handle: '.cas-handle', axis: 'y', placeholder: 'cas-placeholder', forcePlaceholderSize: true, update: function () { $( this ).closest( '.cas-mi' ).attr( 'data-reordered', '1' ); } } );
		}

		$list.on( 'click', '.cas-mi-toggle', function () {
			var $li = $( this ).closest( '.cas-mi' ).toggleClass( 'is-open' );
			$( this ).attr( 'aria-expanded', $li.hasClass( 'is-open' ) ? 'true' : 'false' );
		} );
		$( '#cas-menu-expand' ).on( 'click', function () {
			var open = ! $list.hasClass( 'all-open' );
			$list.toggleClass( 'all-open', open ).find( '.cas-mi[data-pkey]' ).toggleClass( 'is-open', open ).find( '.cas-mi-toggle' ).attr( 'aria-expanded', open ? 'true' : 'false' );
		} );
		$list.on( 'change', '.cas-mi-hide', function () {
			$( this ).closest( '.cas-mi' ).toggleClass( 'is-hidden', this.checked );
		} );
		$list.on( 'change', '.cas-si-hide', function () {
			$( this ).closest( '.cas-si' ).toggleClass( 'is-hidden', this.checked );
		} );

		function renderIcon( $btn, icon ) {
			var $el;
			if ( /^dashicons-/.test( icon ) ) {
				$el = $( '<span class="dashicons"></span>' ).addClass( icon );
			} else if ( /^(https?:)?\/\/|^data:image/i.test( icon ) ) {
				$el = $( '<img alt="" />' ).attr( 'src', icon );
			} else {
				$el = $( '<span class="dashicons dashicons-admin-generic cas-icon-fallback"></span>' );
			}
			$btn.empty().append( $el );
		}

		$( '#cas-menu-clear' ).on( 'click', function () {
			$list.find( 'input[type=text]' ).val( '' );
			$list.find( 'input[type=checkbox]' ).prop( 'checked', false );
			$list.find( '.is-hidden' ).removeClass( 'is-hidden' );
			$list.find( '.cas-icon-btn' ).each( function () {
				$( this ).attr( 'data-icon', '' );
				renderIcon( $( this ), $( this ).attr( 'data-orig' ) || '' );
			} );
			$list.find( '[data-reordered]' ).removeAttr( 'data-reordered' );
			topReordered = false;
			$list.addClass( 'cas-cleared' );
		} );

		/* Icon picker */
		var $picker = $( '#cas-icon-picker' ), $grid = $( '#cas-ip-grid' ), $target = null;
		$.each( ICONS, function ( i, name ) {
			if ( ICONS.indexOf( name ) !== i ) {
				return;
			}
			$grid.append( $( '<button type="button" class="cas-ip-icon"></button>' ).attr( { 'data-icon': 'dashicons-' + name, title: name } ).append( $( '<span class="dashicons"></span>' ).addClass( 'dashicons-' + name ) ) );
		} );
		function closePicker() {
			$picker.prop( 'hidden', true );
			if ( $target ) {
				$target.trigger( 'focus' );
			}
			$target = null;
		}
		function choose( icon ) {
			if ( $target ) {
				$target.attr( 'data-icon', icon );
				renderIcon( $target, icon || $target.attr( 'data-orig' ) || '' );
			}
			closePicker();
		}
		$list.on( 'click', '.cas-icon-btn', function () {
			$target = $( this );
			$( '#cas-ip-search' ).val( '' );
			$( '#cas-ip-url' ).val( /^https?:/i.test( $target.attr( 'data-icon' ) || '' ) ? $target.attr( 'data-icon' ) : '' );
			$grid.children().show();
			$picker.prop( 'hidden', false );
			$( '#cas-ip-search' ).trigger( 'focus' );
		} );
		$grid.on( 'click', '.cas-ip-icon', function () { choose( $( this ).attr( 'data-icon' ) ); } );
		$( '#cas-ip-reset' ).on( 'click', function () { choose( '' ); } );
		$( '#cas-ip-close' ).on( 'click', closePicker );
		$( '#cas-ip-use-url' ).on( 'click', function () {
			var url = $.trim( $( '#cas-ip-url' ).val() );
			if ( /^https?:\/\//i.test( url ) ) {
				choose( url );
			}
		} );
		$picker.on( 'click', function ( e ) {
			if ( e.target === this ) {
				closePicker();
			}
		} );
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! $picker.prop( 'hidden' ) ) {
				closePicker();
			}
		} );
		$( '#cas-ip-search' ).on( 'input', function () {
			var q = $.trim( $( this ).val() ).toLowerCase();
			$grid.children().each( function () {
				$( this ).toggle( ! q || this.title.indexOf( q ) !== -1 );
			} );
		} ).on( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
			}
		} );

		/* Serialise the editor into the hidden field on save */
		function collect() {
			var data = { order: [], items: {}, sub: {} };
			$list.children( '.cas-mi' ).each( function () {
				var $li = $( this ), key = $li.attr( 'data-key' ), $row = $li.children( '.cas-mi-row' ), item = {};
				if ( topReordered ) {
					data.order.push( key );
				}
				var title = $.trim( $row.find( '.cas-mi-title' ).val() || '' );
				var icon = $row.find( '.cas-icon-btn' ).attr( 'data-icon' );
				if ( title ) { item.title = title; }
				if ( icon ) { item.icon = icon; }
				if ( $row.find( '.cas-mi-hide' ).prop( 'checked' ) ) { item.hidden = 1; }
				if ( ! $.isEmptyObject( item ) ) {
					data.items[ key ] = item;
				}
				var pkey = $li.attr( 'data-pkey' );
				if ( pkey ) {
					var sub = { order: [], items: {} }, reordered = $li.attr( 'data-reordered' ) === '1';
					$li.find( '.cas-si' ).each( function () {
						var $si = $( this ), skey = $si.attr( 'data-key' ), s = {};
						if ( reordered ) {
							sub.order.push( skey );
						}
						var st = $.trim( $si.find( '.cas-si-title' ).val() || '' );
						if ( st ) { s.title = st; }
						if ( $si.find( '.cas-si-hide' ).prop( 'checked' ) ) { s.hidden = 1; }
						if ( ! $.isEmptyObject( s ) ) {
							sub.items[ skey ] = s;
						}
					} );
					if ( sub.order.length || ! $.isEmptyObject( sub.items ) ) {
						data.sub[ pkey ] = sub;
					}
				}
			} );
			return data;
		}
		$( '#cas-form' ).on( 'submit', function () {
			if ( $list.children( '.cas-mi' ).length ) {
				$json.val( JSON.stringify( collect() ) );
			}
		} );

		/* Reset confirmation is the required checkbox; nothing else needed. */
	} );
}( jQuery ) );
