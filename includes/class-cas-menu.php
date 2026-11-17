<?php
/**
 * Admin menu editor: rename, change icons, reorder and hide menu items.
 *
 * Everything is applied on admin_head - after WordPress has finished its
 * permission checks and before the menu is printed - so the changes are
 * purely visual. Hidden pages keep working when opened by URL, and no
 * capability or page registration is ever touched.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Menu {

	/** Unmodified menu, kept for the editor screen. */
	private static $orig = null;

	public static function init() {
		add_action( 'admin_head', array( __CLASS__, 'apply' ), 9999 );
	}

	public static function data() {
		$d = get_option( CAS::MENU_OPT, array() );
		$d = is_array( $d ) ? $d : array();
		return array(
			'order' => isset( $d['order'] ) && is_array( $d['order'] ) ? $d['order'] : array(),
			'items' => isset( $d['items'] ) && is_array( $d['items'] ) ? $d['items'] : array(),
			'sub'   => isset( $d['sub'] ) && is_array( $d['sub'] ) ? $d['sub'] : array(),
		);
	}

	/**
	 * Index menu rows by a stable key (hash of the slug), keeping their order.
	 */
	private static function keyed( $items ) {
		$out = array();
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
				continue;
			}
			$key = md5( (string) $item[2] );
			$n   = 1;
			while ( isset( $out[ $key ] ) ) {
				$key = md5( $item[2] . '#' . ( ++$n ) );
			}
			$out[ $key ] = $item;
		}
		return $out;
	}

	private static function ordered( $keyed, $order ) {
		$out = array();
		foreach ( (array) $order as $k ) {
			if ( isset( $keyed[ $k ] ) ) {
				$out[ $k ] = $keyed[ $k ];
			}
		}
		// Anything new (e.g. a plugin installed later) keeps its place at the end.
		return $out + $keyed;
	}

	private static function is_sep( $item ) {
		return isset( $item[4] ) && false !== strpos( $item[4], 'wp-menu-separator' );
	}

	/** Keep update/comment count bubbles when a title is replaced. */
	private static function bubble( $title ) {
		$p = stripos( (string) $title, '<span' );
		return false === $p ? '' : ' ' . substr( $title, $p );
	}

	private static function plain( $title ) {
		$p = stripos( (string) $title, '<span' );
		return trim( wp_strip_all_tags( false === $p ? $title : substr( $title, 0, $p ) ) );
	}

	public static function apply() {
		global $menu, $submenu;

		if ( ! is_array( $menu ) || is_network_admin() || is_user_admin() ) {
			return;
		}
		self::$orig = array(
			'menu'    => $menu,
			'submenu' => is_array( $submenu ) ? $submenu : array(),
		);
		if ( CAS::safe_mode() ) {
			return;
		}
		$d = self::data();
		if ( ! $d['order'] && ! $d['items'] && ! $d['sub'] ) {
			return;
		}
		$restrict = CAS::restrict();

		// Top level.
		$out = array();
		foreach ( self::ordered( self::keyed( $menu ), $d['order'] ) as $k => $item ) {
			$c = isset( $d['items'][ $k ] ) ? $d['items'][ $k ] : array();
			if ( $restrict && ! empty( $c['hidden'] ) ) {
				continue;
			}
			if ( ! self::is_sep( $item ) ) {
				if ( ! empty( $c['title'] ) ) {
					$item[0] = esc_html( $c['title'] ) . self::bubble( $item[0] );
				}
				if ( ! empty( $c['icon'] ) ) {
					$item[6] = $c['icon'];
					// Core items carry an icon class that would win over the new icon.
					$item[4] = isset( $item[4] ) ? trim( preg_replace( '/\bmenu-icon-\S+/', '', $item[4] ) ) : '';
				}
			}
			$out[] = $item;
		}

		// Tidy separators: none doubled, none first or last.
		$clean    = array();
		$prev_sep = true;
		foreach ( $out as $item ) {
			$sep = self::is_sep( $item );
			if ( $sep && $prev_sep ) {
				continue;
			}
			$clean[]  = $item;
			$prev_sep = $sep;
		}
		while ( $clean && self::is_sep( end( $clean ) ) ) {
			array_pop( $clean );
		}
		foreach ( $clean as $i => $item ) {
			if ( isset( $item[4] ) ) {
				$clean[ $i ][4] = trim( str_replace( array( 'menu-top-first', 'menu-top-last' ), '', $item[4] ) );
			}
		}
		if ( $clean ) {
			$menu = function_exists( 'add_menu_classes' ) ? add_menu_classes( $clean ) : $clean;
		}

		// Submenus.
		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent => $items ) {
				$pk = md5( (string) $parent );
				if ( empty( $d['sub'][ $pk ] ) || ! is_array( $items ) ) {
					continue;
				}
				$c     = $d['sub'][ $pk ];
				$order = isset( $c['order'] ) ? $c['order'] : array();
				$new   = array();
				foreach ( self::ordered( self::keyed( $items ), $order ) as $k => $item ) {
					$ic = isset( $c['items'][ $k ] ) ? $c['items'][ $k ] : array();
					if ( $restrict && ! empty( $ic['hidden'] ) ) {
						continue;
					}
					if ( ! empty( $ic['title'] ) ) {
						$item[0] = esc_html( $ic['title'] ) . self::bubble( $item[0] );
					}
					$new[] = $item;
				}
				if ( $new ) {
					$submenu[ $parent ] = $new;
				} else {
					unset( $submenu[ $parent ] );
				}
			}
		}
	}

	/**
	 * Rows for the editor screen, in the currently saved order.
	 */
	public static function editor_items() {
		if ( ! self::$orig ) {
			return array();
		}
		$d    = self::data();
		$rows = array();
		foreach ( self::ordered( self::keyed( self::$orig['menu'] ), $d['order'] ) as $k => $item ) {
			$c   = isset( $d['items'][ $k ] ) ? $d['items'][ $k ] : array();
			$sep = self::is_sep( $item );
			$row = array(
				'key'       => $k,
				'sep'       => $sep,
				'title'     => $sep ? '' : self::plain( $item[0] ),
				'custom'    => isset( $c['title'] ) ? $c['title'] : '',
				'icon'      => isset( $item[6] ) ? (string) $item[6] : '',
				'new_icon'  => isset( $c['icon'] ) ? $c['icon'] : '',
				'hidden'    => ! empty( $c['hidden'] ),
				'subs'      => array(),
			);
			if ( ! $sep && ! empty( self::$orig['submenu'][ $item[2] ] ) ) {
				$pk    = md5( (string) $item[2] );
				$sc    = isset( $d['sub'][ $pk ] ) ? $d['sub'][ $pk ] : array();
				$order = isset( $sc['order'] ) ? $sc['order'] : array();
				foreach ( self::ordered( self::keyed( self::$orig['submenu'][ $item[2] ] ), $order ) as $sk => $sub ) {
					$ic            = isset( $sc['items'][ $sk ] ) ? $sc['items'][ $sk ] : array();
					$row['subs'][] = array(
						'key'    => $sk,
						'title'  => self::plain( $sub[0] ),
						'custom' => isset( $ic['title'] ) ? $ic['title'] : '',
						'hidden' => ! empty( $ic['hidden'] ),
					);
				}
				$row['pkey'] = $pk;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/* ---------------------------------------------------------------------
	 * Sanitising
	 * ------------------------------------------------------------------ */

	private static function is_key( $k ) {
		return is_string( $k ) && 1 === preg_match( '/^[a-f0-9]{32}$/', $k );
	}

	public static function clean_icon( $icon ) {
		$icon = is_string( $icon ) ? trim( $icon ) : '';
		if ( preg_match( '/^dashicons-[a-z0-9\-]+$/', $icon ) ) {
			return $icon;
		}
		if ( preg_match( '#^https?://#i', $icon ) ) {
			return esc_url_raw( $icon );
		}
		return '';
	}

	private static function clean_items( $items, $with_icon ) {
		$out = array();
		foreach ( (array) $items as $k => $c ) {
			if ( ! self::is_key( $k ) || ! is_array( $c ) ) {
				continue;
			}
			$row = array();
			if ( isset( $c['title'] ) && '' !== trim( (string) $c['title'] ) ) {
				$row['title'] = sanitize_text_field( $c['title'] );
			}
			if ( $with_icon && ! empty( $c['icon'] ) && self::clean_icon( $c['icon'] ) ) {
				$row['icon'] = self::clean_icon( $c['icon'] );
			}
			if ( ! empty( $c['hidden'] ) ) {
				$row['hidden'] = 1;
			}
			if ( $row ) {
				$out[ $k ] = $row;
			}
		}
		return $out;
	}

	/**
	 * Accepts the JSON string posted by the editor, or an array (import).
	 */
	public static function sanitize( $in ) {
		if ( is_string( $in ) ) {
			$in = json_decode( $in, true );
		}
		$out = array(
			'order' => array(),
			'items' => array(),
			'sub'   => array(),
		);
		if ( ! is_array( $in ) ) {
			return $out;
		}
		if ( isset( $in['order'] ) ) {
			$out['order'] = array_values( array_filter( (array) $in['order'], array( __CLASS__, 'is_key' ) ) );
		}
		if ( isset( $in['items'] ) ) {
			$out['items'] = self::clean_items( $in['items'], true );
		}
		if ( isset( $in['sub'] ) && is_array( $in['sub'] ) ) {
			foreach ( $in['sub'] as $pk => $c ) {
				if ( ! self::is_key( $pk ) || ! is_array( $c ) ) {
					continue;
				}
				$row = array(
					'order' => isset( $c['order'] ) ? array_values( array_filter( (array) $c['order'], array( __CLASS__, 'is_key' ) ) ) : array(),
					'items' => isset( $c['items'] ) ? self::clean_items( $c['items'], false ) : array(),
				);
				if ( $row['order'] || $row['items'] ) {
					$out['sub'][ $pk ] = $row;
				}
			}
		}
		return $out;
	}
}
