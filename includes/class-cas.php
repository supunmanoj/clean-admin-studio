<?php
/**
 * Core: settings storage, sanitising, "who sees what" logic and color helpers.
 */

defined( 'ABSPATH' ) || exit;

final class CAS {

	const OPT      = 'cas_settings';
	const MENU_OPT = 'cas_menu';
	const VIEW_KEY = 'cas_client_view';

	private static $cache = null;

	public static function init() {
		add_action( 'update_option_' . self::OPT, array( __CLASS__, 'flush' ) );
		add_action( 'add_option_' . self::OPT, array( __CLASS__, 'flush' ) );
		add_action( 'delete_option_' . self::OPT, array( __CLASS__, 'flush' ) );
	}

	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Setting key => array( type, default, extra ).
	 */
	public static function schema() {
		return array(
			// Who is affected.
			'exempt_roles'        => array( 'roles', array( 'administrator' ) ),
			'exempt_users'        => array( 'ids', array() ),
			'plugin_name'         => array( 'text', 'Clean Admin Studio' ),
			'hide_plugin'         => array( 'bool', 0 ),

			// Branding.
			'brand_name'          => array( 'text', '' ),
			'brand_url'           => array( 'url', '' ),
			'logo_url'            => array( 'url', '' ),
			'icon_url'            => array( 'url', '' ),
			'menu_logo'           => array( 'bool', 0 ),
			'footer_text'         => array( 'html', '' ),
			'hide_footer_version' => array( 'bool', 0 ),
			'howdy'               => array( 'text', '' ),
			'strip_wp_title'      => array( 'bool', 0 ),

			// Admin bar.
			'bar_brand'           => array( 'bool', 0 ),
			'bar_hide_wp_logo'    => array( 'bool', 0 ),
			'bar_hide_comments'   => array( 'bool', 0 ),
			'bar_hide_new'        => array( 'bool', 0 ),
			'bar_hide_updates'    => array( 'bool', 0 ),
			'bar_hide_customize'  => array( 'bool', 0 ),
			'bar_hide_search'     => array( 'bool', 0 ),
			'hide_front_bar'      => array( 'bool', 0 ),

			// Clean-up.
			'hide_help'           => array( 'bool', 0 ),
			'hide_screen_options' => array( 'bool', 0 ),
			'notices'             => array( 'choice', 'show', array( 'show', 'collect', 'hide' ) ),
			'hide_update_nags'    => array( 'bool', 0 ),
			'disable_email_check' => array( 'bool', 0 ),

			// Version and URLs.
			'remove_generator'    => array( 'bool', 0 ),
			'mask_asset_version'  => array( 'bool', 0 ),
			'clean_head'          => array( 'bool', 0 ),
			'login_slug'          => array( 'slug', '' ),

			// Dashboard.
			'dash_hidden'         => array( 'list', array() ),
			'dash_hide_welcome'   => array( 'bool', 0 ),
			'dash_hero'           => array( 'bool', 0 ),
			'dash_hero_title'     => array( 'text', 'Welcome back, {name}' ),
			'dash_hero_text'      => array( 'html', '' ),
			'dash_stats'          => array( 'bool', 1 ),
			'dash_insights'       => array( 'bool', 1 ),
			'dash_quick'          => array( 'bool', 1 ),
			'support_label'       => array( 'text', '' ),
			'support_url'         => array( 'url', '' ),

			// Extra dashboard widgets and Google Analytics.
			'w_analytics'         => array( 'bool', 0 ),
			'w_recent'            => array( 'bool', 0 ),
			'w_health'            => array( 'bool', 0 ),
			'w_links'             => array( 'bool', 0 ),
			'w_links_title'       => array( 'text', 'Quick links' ),
			'w_links_items'       => array( 'area', '' ),
			'w_note'              => array( 'bool', 0 ),
			'w_note_title'        => array( 'text', 'A note from your web team' ),
			'w_note_body'         => array( 'html', '' ),
			// Email.
			'mail_from_on'        => array( 'bool', 1 ),
			'mail_from_name'      => array( 'text', '' ),
			'mail_from_email'     => array( 'email', '' ),
			'mail_force'          => array( 'bool', 0 ),
			'mail_reply_to'       => array( 'email', '' ),
			'mail_return_path'    => array( 'bool', 0 ),
			'mail_log'            => array( 'bool', 1 ),
			'mail_log_days'       => array( 'int', 30, 1, 365 ),
			'mail_no_user_admin'  => array( 'bool', 0 ),
			'mail_no_pass_admin'  => array( 'bool', 0 ),
			'mail_no_update_ok'   => array( 'bool', 0 ),

			'ga_id'               => array( 'gaid', '' ),
			'ga_mode'             => array( 'choice', 'auto', array( 'auto', 'force', 'off' ) ),
			'ga_skip_staff'       => array( 'bool', 1 ),
			'ga_property'         => array( 'digits', '' ),
			'ga_relay'            => array( 'url', '' ),

			// Content lists and comments.
			'col_words'           => array( 'bool', 0 ),
			'col_views'           => array( 'bool', 0 ),
			'col_views_period'    => array( 'choice', '28', array( '28', '90', '365', 'all' ) ),
			'col_modified'        => array( 'bool', 0 ),
			'col_thumb'           => array( 'bool', 0 ),
			'dup_link'            => array( 'bool', 0 ),
			'comments_off'        => array( 'bool', 0 ),
			'comments_keep_reviews' => array( 'bool', 1 ),

			// Classic Editor.
			'ed_blocks'           => array( 'bool', 0 ),
			'ed_style'            => array( 'choice', 'theme', array( 'theme', 'custom' ) ),
			'ed_accent'           => array( 'coloropt', '' ),
			'ed_bg'               => array( 'coloropt', '' ),
			'ed_text'             => array( 'coloropt', '' ),
			'ed_border'           => array( 'coloropt', '' ),
			'ed_radius'           => array( 'int', 14, 0, 28 ),
			'ed_quick_label'      => array( 'text', 'Quick answer' ),
			'ed_toolbar'          => array( 'bool', 0 ),
			'ed_buttons'          => array( 'bool', 0 ),
			'ed_paste_plain'      => array( 'bool', 0 ),
			'ed_comfort'          => array( 'bool', 0 ),

			// Colors and typography.
			'style_enabled'       => array( 'bool', 0 ),
			'modern_skin'         => array( 'bool', 1 ),
			'c_accent'            => array( 'color', '#4f46e5' ),
			'c_menu_bg'           => array( 'color', '#111827' ),
			'c_menu_text'         => array( 'color', '#d1d5db' ),
			'c_bar_bg'            => array( 'color', '#111827' ),
			'c_bar_text'          => array( 'color', '#e5e7eb' ),
			'c_body_bg'           => array( 'color', '#f3f4f6' ),
			'c_text'              => array( 'color', '#374151' ),
			'c_heading'           => array( 'color', '#111827' ),
			'c_link'              => array( 'color', '#4f46e5' ),
			'font_family'         => array( 'choice', 'system', array_keys( self::fonts() ) ),
			'font_custom'         => array( 'text', '' ),
			'font_size'           => array( 'int', 13, 11, 18 ),
			'menu_font_size'      => array( 'int', 14, 11, 18 ),
			'icon_size'           => array( 'int', 20, 14, 28 ),
			'custom_css'          => array( 'css', '' ),

			// Login page.
			'login_enabled'       => array( 'bool', 0 ),
			'login_logo_url'      => array( 'url', '' ),
			'login_logo_width'    => array( 'int', 180, 40, 320 ),
			'login_bg_color'      => array( 'color', '#f3f4f6' ),
			'login_bg_image'      => array( 'url', '' ),
			'login_card_bg'       => array( 'color', '#ffffff' ),
			'login_text'          => array( 'color', '#374151' ),
			'login_btn'           => array( 'color', '#4f46e5' ),
			'login_radius'        => array( 'int', 14, 0, 32 ),
			'login_message'       => array( 'html', '' ),
			'login_hide_back'     => array( 'bool', 0 ),
			'login_hide_lost'     => array( 'bool', 0 ),
			'login_hide_lang'     => array( 'bool', 0 ),
			'login_generic_error' => array( 'bool', 0 ),
			'login_custom_css'    => array( 'css', '' ),
		);
	}

	/**
	 * Font key => array( label, CSS stack, Google Fonts family parameter ).
	 */
	public static function fonts() {
		$sys = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
		return array(
			'system'       => array( 'System default', $sys, '' ),
			'inter'        => array( 'Inter', '"Inter", ' . $sys, 'Inter:wght@400;500;600;700' ),
			'poppins'      => array( 'Poppins', '"Poppins", ' . $sys, 'Poppins:wght@400;500;600;700' ),
			'roboto'       => array( 'Roboto', '"Roboto", ' . $sys, 'Roboto:wght@400;500;700' ),
			'open-sans'    => array( 'Open Sans', '"Open Sans", ' . $sys, 'Open+Sans:wght@400;500;600;700' ),
			'lato'         => array( 'Lato', '"Lato", ' . $sys, 'Lato:wght@400;700' ),
			'montserrat'   => array( 'Montserrat', '"Montserrat", ' . $sys, 'Montserrat:wght@400;500;600;700' ),
			'nunito'       => array( 'Nunito', '"Nunito", ' . $sys, 'Nunito:wght@400;600;700' ),
			'dm-sans'      => array( 'DM Sans', '"DM Sans", ' . $sys, 'DM+Sans:wght@400;500;700' ),
			'jakarta'      => array( 'Plus Jakarta Sans', '"Plus Jakarta Sans", ' . $sys, 'Plus+Jakarta+Sans:wght@400;500;600;700' ),
			'source-sans'  => array( 'Source Sans 3', '"Source Sans 3", ' . $sys, 'Source+Sans+3:wght@400;600;700' ),
			'work-sans'    => array( 'Work Sans', '"Work Sans", ' . $sys, 'Work+Sans:wght@400;500;600;700' ),
			'custom'       => array( 'Custom font stack', $sys, '' ),
		);
	}

	public static function defaults() {
		static $d = null;
		if ( null === $d ) {
			$d = array();
			foreach ( self::schema() as $k => $def ) {
				$d[ $k ] = $def[1];
			}
		}
		return $d;
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPT, array() );
			$d           = self::defaults();
			self::$cache = array_merge( $d, is_array( $saved ) ? array_intersect_key( $saved, $d ) : array() );
		}
		return self::$cache;
	}

	public static function opt( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitise a full settings array. Missing booleans become off (form posts).
	 */
	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$out = array();
		foreach ( self::schema() as $k => $def ) {
			$v = isset( $in[ $k ] ) ? $in[ $k ] : null;
			switch ( $def[0] ) {
				case 'bool':
					$out[ $k ] = empty( $v ) ? 0 : 1;
					break;
				case 'text':
					$out[ $k ] = null === $v ? $def[1] : sanitize_text_field( $v );
					break;
				case 'html':
					$out[ $k ] = null === $v ? $def[1] : wp_kses_post( $v );
					break;
				case 'css':
					$out[ $k ] = null === $v ? '' : trim( str_ireplace( array( '</style', '<style' ), '', wp_strip_all_tags( $v ) ) );
					break;
				case 'url':
					$out[ $k ] = null === $v ? '' : esc_url_raw( trim( $v ) );
					break;
				case 'color':
					$c         = is_string( $v ) ? sanitize_hex_color( trim( $v ) ) : '';
					$out[ $k ] = $c ? $c : $def[1];
					break;
				case 'coloropt':
					$c         = is_string( $v ) ? sanitize_hex_color( trim( $v ) ) : '';
					$out[ $k ] = $c ? $c : '';
					break;
				case 'int':
					$out[ $k ] = null === $v || '' === $v ? $def[1] : max( $def[2], min( $def[3], (int) $v ) );
					break;
				case 'choice':
					$out[ $k ] = in_array( $v, $def[2], true ) ? $v : $def[1];
					break;
				case 'roles':
					$out[ $k ] = array_values( array_intersect( array_map( 'sanitize_key', (array) $v ), array_keys( wp_roles()->roles ) ) );
					break;
				case 'ids':
					$out[ $k ] = array_values( array_unique( array_filter( array_map( 'absint', (array) $v ) ) ) );
					break;
				case 'list':
					$out[ $k ] = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_id' ), (array) $v ) ) ) );
					break;
				case 'area':
					$out[ $k ] = null === $v ? $def[1] : sanitize_textarea_field( $v );
					break;
				case 'email':
					$e         = is_string( $v ) ? sanitize_email( trim( $v ) ) : '';
					$out[ $k ] = ( $e && is_email( $e ) ) ? $e : '';
					break;
				case 'gaid':
					$g         = strtoupper( trim( (string) $v ) );
					$out[ $k ] = preg_match( '/^G-[A-Z0-9]{4,20}$/', $g ) ? $g : '';
					break;
				case 'digits':
					$out[ $k ] = preg_replace( '/\D/', '', (string) $v );
					break;
				case 'slug':
					$s         = null === $v ? '' : sanitize_title( $v );
					$reserved  = array( 'wp-admin', 'wp-login', 'wp-login-php', 'admin', 'login', 'dashboard', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'embed' );
					$out[ $k ] = in_array( $s, $reserved, true ) ? '' : $s;
					break;
			}
		}

		// Never leave the site without someone who can see the unmodified admin.
		if ( empty( $out['exempt_roles'] ) && empty( $out['exempt_users'] ) ) {
			$uid = get_current_user_id();
			if ( $uid ) {
				$out['exempt_users'] = array( $uid );
			} else {
				$out['exempt_roles'] = array( 'administrator' );
			}
		}
		if ( '' === $out['plugin_name'] ) {
			$out['plugin_name'] = 'Clean Admin Studio';
		}
		return $out;
	}

	public static function clean_id( $v ) {
		return is_scalar( $v ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $v ) : '';
	}

	/* ---------------------------------------------------------------------
	 * Who sees what
	 * ------------------------------------------------------------------ */

	/**
	 * Safe mode switches every customization off.
	 */
	public static function safe_mode() {
		if ( defined( 'CAS_SAFE_MODE' ) && CAS_SAFE_MODE ) {
			return true;
		}
		return isset( $_GET['cas_safe_mode'] ) && function_exists( 'wp_get_current_user' ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Is this user in the "sees the full, unmodified admin" group?
	 */
	public static function can_be_exempt( $user = null ) {
		$user = $user ? $user : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return true;
		}
		if ( in_array( (int) $user->ID, array_map( 'intval', (array) self::opt( 'exempt_users' ) ), true ) ) {
			return true;
		}
		return (bool) array_intersect( (array) $user->roles, (array) self::opt( 'exempt_roles' ) );
	}

	/**
	 * Exempt users can switch on "client view" to preview what clients see.
	 */
	public static function is_exempt() {
		if ( ! self::can_be_exempt() ) {
			return false;
		}
		return ! get_user_meta( get_current_user_id(), self::VIEW_KEY, true );
	}

	/**
	 * Should hide-type features (hidden menus, notices, help...) apply now?
	 */
	public static function restrict() {
		return is_user_logged_in() && ! self::safe_mode() && ! self::is_exempt();
	}

	public static function page_url( $tab = '' ) {
		return admin_url( 'options-general.php?page=' . CAS_SLUG ) . ( $tab ? '#' . $tab : '' );
	}

	/* ---------------------------------------------------------------------
	 * Color helpers
	 * ------------------------------------------------------------------ */

	public static function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			$hex = '000000';
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * Blend color $a towards $b by $t (0..1).
	 */
	public static function mix( $a, $b, $t ) {
		$a = self::rgb( $a );
		$b = self::rgb( $b );
		$o = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$o .= str_pad( dechex( (int) round( $a[ $i ] + ( $b[ $i ] - $a[ $i ] ) * $t ) ), 2, '0', STR_PAD_LEFT );
		}
		return $o;
	}

	public static function luminance( $hex ) {
		$c = array();
		foreach ( self::rgb( $hex ) as $v ) {
			$v   = $v / 255;
			$c[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	public static function is_dark( $hex ) {
		return self::luminance( $hex ) < 0.4;
	}

	/**
	 * A text color that stays readable on top of $bg.
	 */
	public static function on( $bg ) {
		return self::luminance( $bg ) > 0.45 ? '#111827' : '#ffffff';
	}
}
