<?php
/**
 * Clean-up: help tabs, screen options, notices, update nags, dashboard
 * widgets and the modern dashboard welcome area.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Cleanup {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'nags' ) );
		add_action( 'admin_head', array( __CLASS__, 'help' ), 1 );
		add_filter( 'screen_options_show_screen', array( __CLASS__, 'screen_options' ), 9999 );
		add_filter( 'admin_email_check_interval', array( __CLASS__, 'email_check' ), 9999 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );

		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard' ), 9999 );
		add_action( 'all_admin_notices', array( __CLASS__, 'hero' ), 1 );

		add_action( 'admin_bar_menu', array( __CLASS__, 'bell' ), 9990 );
		add_action( 'admin_footer', array( __CLASS__, 'panel' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/* ---------------------------------------------------------------------
	 * Help, screen options, nags
	 * ------------------------------------------------------------------ */

	public static function help() {
		if ( ! CAS::opt( 'hide_help' ) || ! CAS::restrict() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen ) {
			$screen->remove_help_tabs();
		}
		echo '<style>#contextual-help-link-wrap{display:none!important}</style>';
	}

	public static function screen_options( $show ) {
		return ( CAS::opt( 'hide_screen_options' ) && CAS::restrict() ) ? false : $show;
	}

	public static function nags() {
		if ( CAS::opt( 'hide_update_nags' ) && CAS::restrict() ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
			remove_action( 'network_admin_notices', 'update_nag', 3 );
			remove_action( 'admin_notices', 'maintenance_nag', 10 );
		}
	}

	public static function email_check( $interval ) {
		return ( CAS::opt( 'disable_email_check' ) && ! CAS::safe_mode() ) ? false : $interval;
	}

	/* ---------------------------------------------------------------------
	 * Notices
	 * ------------------------------------------------------------------ */

	/**
	 * show | collect | hide. People who see the full admin never lose
	 * notices completely: for them "hide" becomes "collect".
	 */
	public static function notice_mode() {
		if ( CAS::safe_mode() ) {
			return 'show';
		}
		$mode = CAS::opt( 'notices' );
		if ( 'hide' === $mode && ! CAS::restrict() ) {
			return 'collect';
		}
		return $mode;
	}

	public static function body_class( $classes ) {
		$mode = self::notice_mode();
		if ( 'collect' === $mode ) {
			$classes .= ' cas-nc';
		} elseif ( 'hide' === $mode ) {
			$classes .= ' cas-nh';
		}
		if ( self::hero_on() ) {
			$classes .= ' cas-has-hero';
		}
		return $classes;
	}

	public static function bell( $bar ) {
		if ( ! is_admin() || 'collect' !== self::notice_mode() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'     => 'cas-notices',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons dashicons-bell"></span><span class="cas-count">0</span><span class="screen-reader-text">' . esc_html__( 'Notifications', 'clean-admin-studio' ) . '</span>',
				'href'   => '#cas-notice-panel',
				'meta'   => array( 'class' => 'cas-bell cas-bell-empty' ),
			)
		);
	}

	public static function panel() {
		if ( 'collect' !== self::notice_mode() ) {
			return;
		}
		echo '<button type="button" class="cas-bell-fab" aria-controls="cas-notice-panel"><span class="dashicons dashicons-bell" aria-hidden="true"></span><span class="cas-count">0</span><span class="screen-reader-text">' . esc_html__( 'Notifications', 'clean-admin-studio' ) . '</span></button>';
		echo '<div id="cas-notice-panel" class="cas-notice-panel" aria-hidden="true">'
			. '<div class="cas-np-head"><strong>' . esc_html__( 'Notifications', 'clean-admin-studio' ) . '</strong>'
			. '<button type="button" class="cas-np-close" aria-label="' . esc_attr__( 'Close', 'clean-admin-studio' ) . '"><span class="dashicons dashicons-no-alt"></span></button></div>'
			. '<div class="cas-np-body"></div>'
			. '<p class="cas-np-empty">' . esc_html__( 'Nothing needs your attention.', 'clean-admin-studio' ) . '</p>'
			. '</div><div class="cas-np-backdrop"></div>';
	}

	public static function assets() {
		if ( 'collect' === self::notice_mode() ) {
			wp_enqueue_script( 'cas-notices', CAS_URL . 'assets/js/notices.js', array( 'jquery', 'common' ), CAS_VERSION, true );
		}
	}

	/* ---------------------------------------------------------------------
	 * Dashboard
	 * ------------------------------------------------------------------ */

	public static function dashboard() {
		global $wp_meta_boxes;

		// Remember which widgets exist so the settings screen can list them.
		$found = array();
		if ( isset( $wp_meta_boxes['dashboard'] ) && is_array( $wp_meta_boxes['dashboard'] ) ) {
			foreach ( $wp_meta_boxes['dashboard'] as $priorities ) {
				foreach ( (array) $priorities as $boxes ) {
					foreach ( (array) $boxes as $id => $box ) {
						if ( is_array( $box ) && isset( $box['title'] ) && 0 !== strpos( (string) $id, 'cas_' ) ) {
							// Some core titles carry a second, script-off variant: keep the main one.
							$title        = preg_replace( '#<span class="hide-if-js">.*?</span>#s', '', (string) $box['title'] );
							$found[ $id ] = trim( wp_strip_all_tags( $title ) );
						}
					}
				}
			}
		}
		if ( $found && current_user_can( 'manage_options' ) ) {
			$known  = get_option( 'cas_dash_widgets', array() );
			$known  = is_array( $known ) ? $known : array();
			$merged = $found + $known;
			if ( $merged != $known ) { // phpcs:ignore Universal.Operators.StrictComparisons -- order does not matter.
				update_option( 'cas_dash_widgets', $merged, false );
			}
		}

		if ( CAS::safe_mode() ) {
			return;
		}
		foreach ( (array) CAS::opt( 'dash_hidden' ) as $id ) {
			foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $context ) {
				remove_meta_box( $id, 'dashboard', $context );
			}
		}
		if ( CAS::opt( 'dash_hide_welcome' ) || CAS::opt( 'dash_hero' ) ) {
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
		}
	}

	private static function hero_on() {
		if ( ! CAS::opt( 'dash_hero' ) || CAS::safe_mode() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && 'dashboard' === $screen->id;
	}

	public static function hero() {
		if ( ! self::hero_on() ) {
			return;
		}
		$user  = wp_get_current_user();
		$name  = $user->first_name ? $user->first_name : $user->display_name;
		$title = str_replace(
			array( '{name}', '{site}' ),
			array( $name, get_bloginfo( 'name' ) ),
			(string) CAS::opt( 'dash_hero_title' )
		);

		$actions = array();
		if ( CAS::opt( 'dash_quick' ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				$actions[] = array( admin_url( 'post-new.php' ), 'edit', __( 'Write a post', 'clean-admin-studio' ) );
			}
			if ( current_user_can( 'edit_pages' ) ) {
				$actions[] = array( admin_url( 'post-new.php?post_type=page' ), 'admin-page', __( 'Add a page', 'clean-admin-studio' ) );
			}
			if ( current_user_can( 'upload_files' ) ) {
				$actions[] = array( admin_url( 'upload.php' ), 'admin-media', __( 'Media library', 'clean-admin-studio' ) );
			}
			$actions[] = array( home_url( '/' ), 'external', __( 'View site', 'clean-admin-studio' ) );
		}

		$stats = array();
		if ( CAS::opt( 'dash_stats' ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				$stats[] = array( admin_url( 'edit.php' ), 'admin-post', (int) wp_count_posts( 'post' )->publish, __( 'Posts', 'clean-admin-studio' ) );
			}
			if ( current_user_can( 'edit_pages' ) ) {
				$stats[] = array( admin_url( 'edit.php?post_type=page' ), 'admin-page', (int) wp_count_posts( 'page' )->publish, __( 'Pages', 'clean-admin-studio' ) );
			}
			if ( current_user_can( 'moderate_comments' ) ) {
				$c = wp_count_comments();
				// With comments switched off for visitors, an empty Comments total is only noise.
				if ( $c->approved || ! CAS::opt( 'comments_off' ) ) {
					$stats[] = array( admin_url( 'edit-comments.php' ), 'admin-comments', (int) $c->approved, __( 'Comments', 'clean-admin-studio' ) );
				}
				if ( $c->moderated ) {
					$stats[] = array( admin_url( 'edit-comments.php?comment_status=moderated' ), 'clock', (int) $c->moderated, __( 'Awaiting review', 'clean-admin-studio' ) );
				}
			}
			$stats = array_merge( $stats, self::glance_stats() );
		}

		echo '<div class="cas-hero"><div class="cas-hero-main">';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== CAS::opt( 'dash_hero_text' ) ) {
			echo '<div class="cas-hero-text">' . wp_kses_post( wpautop( CAS::opt( 'dash_hero_text' ) ) ) . '</div>';
		}
		if ( $actions || CAS::opt( 'support_url' ) ) {
			echo '<div class="cas-hero-actions">';
			foreach ( $actions as $a ) {
				echo '<a class="cas-hero-btn" href="' . esc_url( $a[0] ) . '"><span class="dashicons dashicons-' . esc_attr( $a[1] ) . '"></span>' . esc_html( $a[2] ) . '</a>';
			}
			if ( CAS::opt( 'support_url' ) ) {
				$label = CAS::opt( 'support_label' ) ? CAS::opt( 'support_label' ) : __( 'Get support', 'clean-admin-studio' );
				echo '<a class="cas-hero-btn cas-hero-btn-alt" href="' . esc_url( CAS::opt( 'support_url' ) ) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-sos"></span>' . esc_html( $label ) . '</a>';
			}
			echo '</div>';
		}
		echo '</div>';
		if ( $stats ) {
			echo '<div class="cas-stats">';
			foreach ( $stats as $s ) {
				$tag = $s[0] ? 'a' : 'span';
				echo '<' . $tag . ' class="cas-stat"' . ( $s[0] ? ' href="' . esc_url( $s[0] ) . '"' : '' ) . '><span class="dashicons dashicons-' . esc_attr( $s[1] ) . '"></span><strong>' . esc_html( is_int( $s[2] ) ? number_format_i18n( $s[2] ) : $s[2] ) . '</strong><em>' . esc_html( $s[3] ) . '</em></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- tag is a or span.
			}
			echo '</div>';
		}
		echo '</div>';
		CAS_Insights::render();
	}

	/**
	 * Totals that other plugins add to WordPress's own "At a Glance" box
	 * (forums, topics, products and so on), so they can sit with ours.
	 * Each comes as a short piece of HTML such as "<a href=...>4 Forums</a>".
	 */
	private static function glance_stats() {
		$out   = array();
		$items = apply_filters( 'dashboard_glance_items', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WordPress's own hook.
		foreach ( (array) $items as $item ) {
			if ( ! is_string( $item ) || count( $out ) >= 6 ) {
				continue;
			}
			$text = trim( html_entity_decode( wp_strip_all_tags( $item ), ENT_QUOTES, 'UTF-8' ) );
			if ( ! preg_match( '/^(\d[\d.,\x{00a0}\x{202f} ]*)\s+(\S.{0,40})$/u', $text, $m ) ) {
				continue;
			}
			$href  = preg_match( '/href=["\']([^"\']+)["\']/', $item, $h ) ? html_entity_decode( $h[1], ENT_QUOTES, 'UTF-8' ) : '';
			$class = preg_match( '/class=["\']([^"\']+)["\']/', $item, $c ) ? $c[1] : '';
			// Users are left out on request, and posts, pages and comments are already listed.
			if ( preg_match( '#(users\.php|edit-comments\.php|edit\.php(\?post_type=(post|page))?)$#', $href ) || false !== stripos( $class, 'user' ) ) {
				continue;
			}
			$hint  = strtolower( $class . ' ' . $m[2] );
			$icon  = 'marker';
			foreach ( array(
				'tag'     => 'tag',
				'forum'   => 'buddicons-forums',
				'topic'   => 'buddicons-topics',
				'repl'    => 'buddicons-replies',
				'product' => 'cart',
				'order'   => 'clipboard',
				'event'   => 'calendar-alt',
				'form'    => 'feedback',
			) as $word => $dashicon ) {
				if ( false !== strpos( $hint, $word ) ) {
					$icon = $dashicon;
					break;
				}
			}
			$out[] = array( $href, $icon, trim( $m[1] ), $m[2] );
		}
		return $out;
	}
}
