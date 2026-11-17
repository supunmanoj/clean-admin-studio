<?php
/**
 * Login page design and the optional custom login address.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Login {

	/** '' | 'serve' | 'block' for the custom login address. */
	private static $mode = '';

	public static function init() {
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'style' ), 20 );
		add_filter( 'login_headerurl', array( __CLASS__, 'header_url' ), 9999 );
		add_filter( 'login_headertext', array( __CLASS__, 'header_text' ), 9999 );
		add_filter( 'login_message', array( __CLASS__, 'message' ) );
		add_filter( 'login_errors', array( __CLASS__, 'errors' ), 9999 );
		add_filter( 'login_display_language_dropdown', array( __CLASS__, 'language' ), 9999 );

		if ( self::slug_active() ) {
			add_action( 'plugins_loaded', array( __CLASS__, 'detect' ), 1 );
			add_action( 'wp_loaded', array( __CLASS__, 'route' ), 1 );
			add_filter( 'site_url', array( __CLASS__, 'filter_url' ), 9999, 3 );
			add_filter( 'network_site_url', array( __CLASS__, 'filter_url' ), 9999, 3 );
			add_filter( 'wp_redirect', array( __CLASS__, 'filter_redirect' ), 9999 );
			// Stop /login and /admin from redirecting to (and revealing) the address.
			remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
		}
	}

	private static function on() {
		return CAS::opt( 'login_enabled' ) && ! ( defined( 'CAS_SAFE_MODE' ) && CAS_SAFE_MODE );
	}

	/* ---------------------------------------------------------------------
	 * Design
	 * ------------------------------------------------------------------ */

	public static function header_url( $url ) {
		if ( ! self::on() ) {
			return $url;
		}
		return CAS::opt( 'brand_url' ) ? CAS::opt( 'brand_url' ) : home_url( '/' );
	}

	public static function header_text( $text ) {
		return self::on() ? CAS_Branding::brand_name() : $text;
	}

	public static function message( $message ) {
		$custom = CAS::opt( 'login_message' );
		if ( ! self::on() || '' === $custom ) {
			return $message;
		}
		return $message . '<div class="cas-login-message">' . wp_kses_post( wpautop( $custom ) ) . '</div>';
	}

	/** Do not reveal whether the username or the password was wrong. */
	public static function errors( $error ) {
		global $errors;
		if ( ! self::on() || ! CAS::opt( 'login_generic_error' ) || ! is_wp_error( $errors ) ) {
			return $error;
		}
		$codes = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'invalidcombo' );
		if ( array_intersect( $codes, $errors->get_error_codes() ) ) {
			return esc_html__( 'Those login details are not correct. Please try again.', 'clean-admin-studio' );
		}
		return $error;
	}

	public static function language( $show ) {
		return ( self::on() && CAS::opt( 'login_hide_lang' ) ) ? false : $show;
	}

	public static function style() {
		if ( ! self::on() ) {
			return;
		}
		$font_url = CAS_Branding::font_url();
		if ( $font_url ) {
			wp_enqueue_style( 'cas-font', $font_url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}

		$bg     = CAS::opt( 'login_bg_color' );
		$img    = CAS::opt( 'login_bg_image' );
		$card   = CAS::opt( 'login_card_bg' );
		$text   = CAS::opt( 'login_text' );
		$btn    = CAS::opt( 'login_btn' );
		$r      = (int) CAS::opt( 'login_radius' );
		$r_in   = min( $r, 10 );
		$logo   = CAS::opt( 'login_logo_url' ) ? CAS::opt( 'login_logo_url' ) : CAS::opt( 'logo_url' );
		$w      = (int) CAS::opt( 'login_logo_width' );
		$onbg   = $img ? '#ffffff' : CAS::mix( CAS::on( $bg ), $bg, 0.3 );
		$shadow = $img ? 'text-shadow:0 1px 3px rgba(0,0,0,.55);' : '';
		$input  = CAS::is_dark( $card ) ? CAS::mix( $card, '#ffffff', 0.08 ) : '#ffffff';
		$line   = CAS::mix( $card, CAS::on( $card ), 0.2 );

		$css  = 'body.login{background-color:' . $bg . ';color:' . $text . ';font-family:' . CAS_Branding::font_stack() . ';';
		$css .= $img ? 'background-image:url("' . esc_url( $img ) . '");background-size:cover;background-position:center;background-attachment:fixed;' : '';
		$css .= 'display:flex;flex-direction:column;justify-content:center;min-height:100vh;box-sizing:border-box}';
		$css .= '.login #login{width:380px;max-width:92vw;padding:32px 0;margin:0 auto}';
		if ( $logo ) {
			$css .= '.login h1 a{background-image:url("' . esc_url( $logo ) . '");background-size:contain;background-position:center;background-repeat:no-repeat;width:' . $w . 'px;max-width:100%;height:' . (int) round( max( 48, $w * 0.5 ) ) . 'px;margin:0 auto 22px}';
		} else {
			$css .= '.login h1 a{background-image:none;width:auto;height:auto;text-indent:0;overflow:visible;font-size:26px;line-height:1.3;font-weight:700;color:' . $onbg . ';' . $shadow . 'margin:0 auto 22px}';
		}
		$css .= '.login form{background:' . $card . ';border:0;border-radius:' . $r . 'px;box-shadow:0 12px 32px rgba(15,23,42,.10),0 2px 6px rgba(15,23,42,.06);padding:30px 28px 32px;margin-top:0;overflow:hidden}';
		$css .= '.login label,.login form p,.login form .forgetmenot label{color:' . $text . ';font-size:14px}';
		$css .= '.login form .input,.login input[type=text],.login input[type=password],.login input[type=email]{background:' . $input . ';color:' . CAS::on( $input ) . ';border:1px solid ' . $line . ';border-radius:' . $r_in . 'px;box-shadow:none;font-size:16px;line-height:1.4;min-height:46px;padding:8px 14px;margin-top:6px}';
		$css .= '.login form .input:focus,.login input[type=text]:focus,.login input[type=password]:focus,.login input[type=email]:focus,.login input[type=checkbox]:focus{border-color:' . $btn . ';box-shadow:0 0 0 2px ' . CAS::mix( $btn, $card, 0.65 ) . ';outline:0}';
		$css .= '.login .wp-pwd .button.wp-hide-pw{top:6px;min-height:46px;color:' . CAS::mix( $text, $card, 0.25 ) . '}.login .wp-pwd .button.wp-hide-pw:focus{border-color:' . $btn . ';box-shadow:0 0 0 1px ' . $btn . '}';
		$css .= '.login .button-primary,.login.wp-core-ui .button-primary{background:' . $btn . ';border-color:' . $btn . ';color:' . CAS::on( $btn ) . ';border-radius:' . $r_in . 'px;box-shadow:none;text-shadow:none;font-weight:600;min-height:44px;padding:0 22px;line-height:42px}';
		$hover = CAS::mix( $btn, '#000000', 0.14 );
		$css .= '.login.wp-core-ui .button-primary:hover,.login.wp-core-ui .button-primary:focus,.login.wp-core-ui .button-primary:active{background:' . $hover . ';border-color:' . $hover . ';color:' . CAS::on( $hover ) . '}';
		$css .= '.login.wp-core-ui .button-primary:focus{box-shadow:0 0 0 2px ' . $card . ',0 0 0 4px ' . $btn . '}';
		$css .= '.login input[type=checkbox]{border-radius:4px;border-color:' . $line . '}';
		$css .= '.login form .forgetmenot{float:none;margin:2px 0 0}.login form p.submit{float:none;margin:18px 0 0;padding:0}.login form p.submit .button{float:none;width:100%}';
		$css .= '.login #nav,.login #backtoblog{text-align:center;padding:0;margin:18px 0 0}.login #nav a,.login #backtoblog a,.login .privacy-policy-link{color:' . $onbg . ';' . $shadow . '}';
		$css .= '.login #nav a:hover,.login #backtoblog a:hover,.login h1 a:hover{color:' . ( $img ? '#ffffff' : $btn ) . ';text-decoration:underline}';
		$css .= '.login .message,.login .notice,.login .success,.login #login_error{border-radius:' . $r_in . 'px;box-shadow:0 2px 8px rgba(15,23,42,.06);margin-bottom:18px}.login .message,.login .notice-info{border-left-color:' . $btn . '}';
		$css .= '.login .cas-login-message{background:' . $card . ';color:' . $text . ';border-radius:' . $r_in . 'px;padding:12px 16px;margin:0 0 18px;font-size:14px;box-shadow:0 2px 8px rgba(15,23,42,.06)}.login .cas-login-message p{margin:0 0 6px}.login .cas-login-message p:last-child{margin:0}';
		$css .= '.login .language-switcher{margin:18px auto 0;text-align:center}.login a:focus{box-shadow:0 0 0 2px ' . $btn . ';border-radius:3px}';
		$css .= '@media (max-width:480px){.login form{padding:24px 20px 26px}}';
		if ( CAS::opt( 'login_hide_back' ) ) {
			$css .= '.login #backtoblog{display:none}';
		}
		if ( CAS::opt( 'login_hide_lost' ) ) {
			$css .= '.login #nav{display:none}';
		}
		$css .= (string) CAS::opt( 'login_custom_css' );

		wp_register_style( 'cas-login', false, array( 'login' ), CAS_VERSION );
		wp_enqueue_style( 'cas-login' );
		wp_add_inline_style( 'cas-login', $css );
	}

	/* ---------------------------------------------------------------------
	 * Custom login address
	 * ------------------------------------------------------------------ */

	public static function slug() {
		return (string) CAS::opt( 'login_slug' );
	}

	/**
	 * Why the custom address cannot be used here: a short code, or '' when it can.
	 * (No translations here: this runs before WordPress is ready for them.)
	 */
	public static function slug_conflict_code() {
		if ( is_multisite() ) {
			return 'multisite';
		}
		if ( ! get_option( 'permalink_structure' ) ) {
			return 'permalinks';
		}
		if ( 'custom' === get_option( 'sg_security_login_type' ) ) {
			return 'sg';
		}
		if ( in_array( 'wps-hide-login/wps-hide-login.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			return 'wps';
		}
		return '';
	}

	public static function slug_conflict() {
		$messages = array(
			'multisite'  => __( 'Not available on multisite networks.', 'clean-admin-studio' ),
			'permalinks' => __( 'Needs pretty permalinks (Settings > Permalinks).', 'clean-admin-studio' ),
			'sg'         => __( 'Security Optimizer already changes the login address.', 'clean-admin-studio' ),
			'wps'        => __( 'WPS Hide Login already changes the login address.', 'clean-admin-studio' ),
		);
		$code     = self::slug_conflict_code();
		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}

	public static function slug_active() {
		if ( defined( 'CAS_SAFE_MODE' ) && CAS_SAFE_MODE ) {
			return false;
		}
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
			return false;
		}
		return '' !== self::slug() && '' === self::slug_conflict_code();
	}

	public static function login_url() {
		return user_trailingslashit( home_url( '/' . self::slug() ) );
	}

	/** Requests that must keep reaching wp-login.php directly. */
	private static function allowed_direct() {
		// phpcs:disable WordPress.Security.NonceVerification
		$allowed = isset( $_GET['loginSocial'] ) // Nextend Social Login callbacks.
			|| ( isset( $_REQUEST['action'] ) && 'postpass' === $_REQUEST['action'] ); // Password-protected posts.
		// phpcs:enable
		return (bool) apply_filters( 'cas_login_allow_direct', $allowed );
	}

	public static function detect() {
		global $pagenow;
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		$base = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );

		if ( in_array( basename( $path ), array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			if ( ! self::allowed_direct() ) {
				self::$mode = 'block';
			}
		} elseif ( $path === $base . '/' . self::slug() ) {
			self::$mode = 'serve';
			$pagenow    = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}

	public static function route() {
		global $pagenow;

		if ( 'serve' === self::$mode ) {
			// wp-login.php expects these to be globals.
			global $error, $interim_login, $action, $user_login;
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
		if ( 'block' === self::$mode ) {
			self::not_found();
		}
		// Logged-out visitors to /wp-admin would otherwise be redirected to the address.
		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! in_array( $pagenow, array( 'admin-post.php', 'admin-ajax.php' ), true ) ) {
			self::not_found();
		}
	}

	private static function not_found() {
		status_header( 404 );
		nocache_headers();
		wp_die(
			esc_html__( 'The page you are looking for could not be found.', 'clean-admin-studio' ),
			esc_html__( 'Page not found', 'clean-admin-studio' ),
			array( 'response' => 404 )
		);
	}

	private static function swap( $url, $scheme = null ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) ) {
			return $url;
		}
		$parts = explode( '?', $url, 2 );
		$new   = self::login_url();
		if ( $scheme ) {
			$new = set_url_scheme( $new, $scheme );
		}
		return isset( $parts[1] ) && '' !== $parts[1] ? $new . '?' . $parts[1] : $new;
	}

	public static function filter_url( $url, $path = '', $scheme = null ) {
		return self::swap( $url, $scheme );
	}

	public static function filter_redirect( $location ) {
		return self::swap( $location );
	}
}
