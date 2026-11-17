<?php
/**
 * Branding: admin bar, footer, titles, version masking, colors and fonts.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Branding {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'head_cleanup' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );

		add_action( 'admin_bar_menu', array( __CLASS__, 'bar_brand' ), 5 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar_tweaks' ), 9999 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'front_bar' ), 9999 );
		add_action( 'admin_post_cas_toggle_view', array( __CLASS__, 'toggle_view' ) );

		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ), 9999 );
		add_filter( 'update_footer', array( __CLASS__, 'footer_version' ), 9999 );
		add_filter( 'admin_title', array( __CLASS__, 'title' ), 9999 );
		add_filter( 'login_title', array( __CLASS__, 'title' ), 9999 );

		add_filter( 'style_loader_src', array( __CLASS__, 'mask_version' ), 9999 );
		add_filter( 'script_loader_src', array( __CLASS__, 'mask_version' ), 9999 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ), 9999 );
		add_action( 'admin_head', array( __CLASS__, 'favicon' ) );
		add_action( 'admin_head', array( __CLASS__, 'svg_colors' ), 99 );
		add_filter( 'get_user_option_admin_color', array( __CLASS__, 'admin_color' ), 9999 );
		add_action( 'login_head', array( __CLASS__, 'favicon' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'all_plugins', array( __CLASS__, 'plugin_list' ) );
	}

	/* ---------------------------------------------------------------------
	 * Version and <head> clean-up
	 * ------------------------------------------------------------------ */

	public static function head_cleanup() {
		if ( CAS::safe_mode() ) {
			return;
		}
		if ( CAS::opt( 'remove_generator' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
		if ( CAS::opt( 'clean_head' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
			remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}
	}

	/**
	 * Swap the WordPress version in asset URLs for an opaque hash, so the
	 * version is not readable while browser cache-busting keeps working.
	 */
	public static function mask_version( $src ) {
		if ( ! is_string( $src ) || ! CAS::opt( 'mask_asset_version' ) || CAS::safe_mode() ) {
			return $src;
		}
		$v = get_bloginfo( 'version' );
		if ( false === strpos( $src, 'ver=' . $v ) ) {
			return $src;
		}
		$hash = substr( md5( $v . wp_salt( 'nonce' ) ), 0, 10 );
		return preg_replace( '/([?&])ver=' . preg_quote( $v, '/' ) . '(?=&|$)/', '${1}ver=' . $hash, $src );
	}

	public static function admin_init() {
		if ( CAS::safe_mode() ) {
			return;
		}
		// Custom colors replace the per-user color schemes.
		if ( CAS::opt( 'style_enabled' ) ) {
			remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
		}
	}

	/* ---------------------------------------------------------------------
	 * Admin bar
	 * ------------------------------------------------------------------ */

	public static function brand_name() {
		$n = CAS::opt( 'brand_name' );
		return '' !== $n ? $n : get_bloginfo( 'name' );
	}

	public static function bar_brand( $bar ) {
		if ( CAS::safe_mode() || ! CAS::opt( 'bar_brand' ) ) {
			return;
		}
		$icon  = CAS::opt( 'icon_url' ) ? CAS::opt( 'icon_url' ) : CAS::opt( 'logo_url' );
		$title = $icon ? '<img class="cas-bar-logo" src="' . esc_url( $icon ) . '" alt="" />' : '';
		$title .= '<span class="cas-bar-name">' . esc_html( self::brand_name() ) . '</span>';
		$bar->add_node(
			array(
				'id'    => 'cas-brand',
				'title' => $title,
				'href'  => CAS::opt( 'brand_url' ) ? CAS::opt( 'brand_url' ) : admin_url(),
				'meta'  => array( 'class' => 'cas-brand' ),
			)
		);
	}

	public static function bar_tweaks( $bar ) {
		// The client-view switch is always available to exempt users.
		if ( CAS::can_be_exempt() && ! ( defined( 'CAS_SAFE_MODE' ) && CAS_SAFE_MODE ) ) {
			$on = (bool) get_user_meta( get_current_user_id(), CAS::VIEW_KEY, true );
			// The toolbar stays clean: the switch only appears there while client
			// view is on, as the way back. It always lives in the account menu.
			$on && $bar->add_node(
				array(
					'id'     => 'cas-view',
					'parent' => 'top-secondary',
					'title'  => '<span class="ab-icon dashicons dashicons-visibility"></span><span class="ab-label">' . ( $on ? esc_html__( 'Client view: on', 'clean-admin-studio' ) : esc_html__( 'Client view', 'clean-admin-studio' ) ) . '</span>',
					'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=cas_toggle_view' ), 'cas_toggle_view' ),
					'meta'   => array(
						'class' => $on ? 'cas-view cas-view-on' : 'cas-view',
						'title' => $on ? __( 'You are seeing the admin as clients do. Click to return to the full admin.', 'clean-admin-studio' ) : __( 'Preview the admin as clients see it.', 'clean-admin-studio' ),
					),
				)
			);
			// Also in the account menu, which is what phones show.
			$bar->add_node(
				array(
					'id'     => 'cas-view-account',
					'parent' => 'user-actions',
					'title'  => $on ? esc_html__( 'Exit client view', 'clean-admin-studio' ) : esc_html__( 'Client view', 'clean-admin-studio' ),
					'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=cas_toggle_view' ), 'cas_toggle_view' ),
				)
			);
			if ( $on && current_user_can( 'manage_options' ) ) {
				$bar->add_node(
					array(
						'id'     => 'cas-view-settings',
						'parent' => 'cas-view',
						'title'  => esc_html( CAS::opt( 'plugin_name' ) ) . ' ' . esc_html__( 'settings', 'clean-admin-studio' ),
						'href'   => CAS::page_url(),
					)
				);
			}
		}

		if ( CAS::safe_mode() ) {
			return;
		}
		$map = array(
			'bar_hide_wp_logo'   => array( 'wp-logo' ),
			'bar_hide_comments'  => array( 'comments' ),
			'bar_hide_new'       => array( 'new-content' ),
			'bar_hide_updates'   => array( 'updates' ),
			'bar_hide_customize' => array( 'customize' ),
			'bar_hide_search'    => array( 'search' ),
		);
		foreach ( $map as $key => $nodes ) {
			if ( CAS::opt( $key ) ) {
				foreach ( $nodes as $id ) {
					$bar->remove_node( $id );
				}
			}
		}

		$howdy = CAS::opt( 'howdy' );
		$node  = $bar->get_node( 'my-account' );
		if ( '' !== $howdy && $node && is_string( $node->title ) ) {
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string on purpose.
			$prefix = trim( sprintf( __( 'Howdy, %s' ), '' ) );
			if ( '' !== $prefix && false !== strpos( $node->title, $prefix ) ) {
				$node->title = str_replace( $prefix, esc_html( $howdy ), $node->title );
				$bar->add_node( (array) $node );
			}
		}
	}

	public static function front_bar( $show ) {
		if ( $show && ! is_admin() && CAS::opt( 'hide_front_bar' ) && CAS::restrict() ) {
			return false;
		}
		return $show;
	}

	public static function toggle_view() {
		check_admin_referer( 'cas_toggle_view' );
		if ( CAS::can_be_exempt() ) {
			$uid = get_current_user_id();
			if ( get_user_meta( $uid, CAS::VIEW_KEY, true ) ) {
				delete_user_meta( $uid, CAS::VIEW_KEY );
			} else {
				update_user_meta( $uid, CAS::VIEW_KEY, 1 );
			}
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Footer, titles, plugin list
	 * ------------------------------------------------------------------ */

	public static function footer_text( $text ) {
		$custom = CAS::opt( 'footer_text' );
		if ( '' === $custom || CAS::safe_mode() ) {
			return $text;
		}
		$custom = str_replace( array( '{site}', '{year}' ), array( esc_html( get_bloginfo( 'name' ) ), gmdate( 'Y' ) ), $custom );
		return '<span id="footer-thankyou">' . wp_kses_post( $custom ) . '</span>';
	}

	public static function footer_version( $text ) {
		return ( CAS::opt( 'hide_footer_version' ) && ! CAS::safe_mode() ) ? '' : $text;
	}

	public static function title( $title ) {
		if ( ! CAS::opt( 'strip_wp_title' ) || CAS::safe_mode() ) {
			return $title;
		}
		return preg_replace( '/\s*(&#8212;|&mdash;|\x{2014})\s*WordPress\s*$/u', '', (string) $title );
	}

	public static function plugin_list( $plugins ) {
		$base = plugin_basename( CAS_FILE );
		if ( ! isset( $plugins[ $base ] ) || CAS::safe_mode() ) {
			return $plugins;
		}
		if ( CAS::opt( 'hide_plugin' ) && CAS::restrict() ) {
			unset( $plugins[ $base ] );
			return $plugins;
		}
		$name = CAS::opt( 'plugin_name' );
		if ( $name && 'Clean Admin Studio' !== $name ) {
			$plugins[ $base ]['Name']  = $name;
			$plugins[ $base ]['Title'] = $name;
			if ( '' !== CAS::opt( 'brand_name' ) ) {
				$plugins[ $base ]['Author']     = CAS::opt( 'brand_name' );
				$plugins[ $base ]['AuthorName'] = CAS::opt( 'brand_name' );
				$plugins[ $base ]['AuthorURI']  = CAS::opt( 'brand_url' );
			}
		}
		return $plugins;
	}

	public static function favicon() {
		$icon = CAS::opt( 'icon_url' );
		if ( $icon && ! CAS::safe_mode() ) {
			echo '<link rel="icon" href="' . esc_url( $icon ) . '" />' . "\n";
		}
	}

	/* ---------------------------------------------------------------------
	 * Colors, fonts and the modern skin
	 * ------------------------------------------------------------------ */

	/** One theme for everyone: skip the per-user color scheme stylesheet. */
	public static function admin_color( $color ) {
		return ( CAS::opt( 'style_enabled' ) && ! CAS::safe_mode() ) ? 'fresh' : $color;
	}

	/** Tell WordPress which colors to paint plugin (SVG) menu icons with. */
	public static function svg_colors() {
		if ( ! CAS::opt( 'style_enabled' ) || CAS::safe_mode() ) {
			return;
		}
		$menu   = CAS::opt( 'c_menu_bg' );
		$scheme = array(
			'icons' => array(
				'base'    => CAS::mix( CAS::opt( 'c_menu_text' ), $menu, 0.25 ),
				'focus'   => CAS::on( $menu ),
				'current' => CAS::on( CAS::opt( 'c_accent' ) ),
			),
		);
		echo '<script>var _wpColorScheme = ' . wp_json_encode( $scheme ) . ";</script>\n";
	}

	public static function body_class( $classes ) {
		if ( CAS::safe_mode() ) {
			return $classes;
		}
		if ( CAS::opt( 'style_enabled' ) ) {
			$classes .= ' cas-styled';
			if ( CAS::opt( 'modern_skin' ) ) {
				$classes .= ' cas-modern';
			}
			$classes .= CAS::is_dark( CAS::opt( 'c_menu_bg' ) ) ? ' cas-menu-dark' : ' cas-menu-light';
		}
		return $classes;
	}

	public static function font_stack() {
		$fonts = CAS::fonts();
		$key   = CAS::opt( 'font_family' );
		if ( 'custom' === $key ) {
			$custom = preg_replace( '/[^A-Za-z0-9 ,"\'\-_]/', '', (string) CAS::opt( 'font_custom' ) );
			return '' !== trim( $custom ) ? $custom . ', ' . $fonts['system'][1] : $fonts['system'][1];
		}
		return isset( $fonts[ $key ] ) ? $fonts[ $key ][1] : $fonts['system'][1];
	}

	public static function font_url() {
		$fonts = CAS::fonts();
		$key   = CAS::opt( 'font_family' );
		if ( empty( $fonts[ $key ][2] ) ) {
			return '';
		}
		return 'https://fonts.googleapis.com/css2?family=' . $fonts[ $key ][2] . '&display=swap';
	}

	/**
	 * The handful of chosen colors are expanded into a full set of CSS
	 * variables; hover, active and text-on-color shades are derived so the
	 * result stays readable whatever the user picks.
	 */
	public static function vars_css() {
		$accent  = CAS::opt( 'c_accent' );
		$menu    = CAS::opt( 'c_menu_bg' );
		$mtext   = CAS::opt( 'c_menu_text' );
		$bar     = CAS::opt( 'c_bar_bg' );
		$btext   = CAS::opt( 'c_bar_text' );
		$link    = CAS::opt( 'c_link' );
		$dark    = CAS::is_dark( $menu );
		$strong  = CAS::on( $menu );
		$bar_dark = CAS::is_dark( $bar );
		// SVG icons in the toolbar are painted with the menu colors in the admin
		// and are white on the public site: nudge them when that would not show.
		if ( is_admin() ) {
			$bar_svg = $bar_dark === $dark ? 'none' : ( $bar_dark ? 'brightness(2)' : 'brightness(0.45)' );
		} else {
			$bar_svg = $bar_dark ? 'none' : 'brightness(0.45)';
		}

		$vars = array(
			'accent'        => $accent,
			'accent-hover'  => CAS::mix( $accent, '#000000', 0.14 ),
			'accent-soft'   => CAS::mix( $accent, '#ffffff', 0.9 ),
			'on-accent'     => CAS::on( $accent ),
			'menu-bg'       => $menu,
			'menu-text'     => $mtext,
			'menu-strong'   => $strong,
			'menu-icon'     => CAS::mix( $mtext, $menu, 0.25 ),
			'menu-hover-bg' => CAS::mix( $menu, $strong, 0.09 ),
			'submenu-bg'    => $dark ? CAS::mix( $menu, '#000000', 0.22 ) : CAS::mix( $menu, '#000000', 0.04 ),
			'menu-line'     => CAS::mix( $menu, $strong, 0.1 ),
			'bar-bg'        => $bar,
			'bar-text'      => $btext,
			'bar-icon'      => CAS::mix( $btext, $bar, 0.3 ),
			'bar-hover-bg'  => CAS::mix( $bar, CAS::on( $bar ), 0.1 ),
			'bar-sub-bg'    => CAS::mix( $bar, CAS::on( $bar ), 0.05 ),
			'bar-line'      => CAS::mix( $bar, CAS::on( $bar ), 0.12 ),
			'bar-svg'       => $bar_svg,
			'body-bg'       => CAS::opt( 'c_body_bg' ),
			'text'          => CAS::opt( 'c_text' ),
			'heading'       => CAS::opt( 'c_heading' ),
			'link'          => $link,
			'link-hover'    => CAS::mix( $link, '#000000', 0.22 ),
			'font'          => self::font_stack(),
			'fs'            => (int) CAS::opt( 'font_size' ) . 'px',
			'menu-fs'       => (int) CAS::opt( 'menu_font_size' ) . 'px',
			'icon'          => (int) CAS::opt( 'icon_size' ) . 'px',
		);
		$css = ':root{';
		foreach ( $vars as $k => $v ) {
			$css .= '--cas-' . $k . ':' . $v . ';';
		}
		return $css . '}';
	}

	private static function enqueue_theme() {
		$url = self::font_url();
		if ( $url ) {
			wp_enqueue_style( 'cas-font', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		wp_enqueue_style( 'cas-admin-theme', CAS_URL . 'assets/css/admin-theme.css', array(), CAS_VERSION );
		wp_add_inline_style( 'cas-admin-theme', self::vars_css() );
	}

	public static function assets() {
		if ( CAS::safe_mode() ) {
			return;
		}
		// Small always-on layer: hero, notification panel, brand in the admin bar.
		wp_enqueue_style( 'cas-admin-ui', CAS_URL . 'assets/css/admin-ui.css', array(), CAS_VERSION );

		if ( CAS::opt( 'style_enabled' ) ) {
			self::enqueue_theme();
		}

		$extra = '';
		$logo  = CAS::opt( 'logo_url' );
		if ( CAS::opt( 'menu_logo' ) && $logo ) {
			$extra .= '#adminmenuwrap::before{content:"";display:block;height:44px;margin:16px 14px 10px;background:url("' . esc_url( $logo ) . '") left center/contain no-repeat}'
				. '.folded #adminmenuwrap::before{display:none}'
				. '@media screen and (max-width:960px){.auto-fold #adminmenuwrap::before{display:none}}';
		}
		$extra .= (string) CAS::opt( 'custom_css' );
		if ( '' !== $extra ) {
			wp_add_inline_style( 'cas-admin-ui', $extra );
		}
	}

	/** Keep the admin bar on-brand on the front end too. */
	public static function front_assets() {
		if ( CAS::safe_mode() || ! is_admin_bar_showing() ) {
			return;
		}
		wp_enqueue_style( 'cas-admin-ui', CAS_URL . 'assets/css/admin-ui.css', array(), CAS_VERSION );
		if ( CAS::opt( 'style_enabled' ) ) {
			self::enqueue_theme();
		}
	}
}
