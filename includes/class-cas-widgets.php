<?php
/**
 * Extra dashboard widgets, the Google Analytics tracking code (added only
 * when the site does not already have one) and the visitor report.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Widgets {

	const KEY_OPT    = 'cas_ga_key';
	const DETECT_OPT = 'cas_ga_detect';
	const OAUTH_OPT  = 'cas_ga_oauth';
	const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
	const SCOPE      = 'https://www.googleapis.com/auth/analytics.readonly';

	/** True while the plugin itself is saving tokens (not a form post). */
	private static $internal = false;

	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'tag' ), 20 );
		add_action( 'admin_post_cas_ga_check', array( __CLASS__, 'check' ) );
		add_action( 'update_option_' . CAS::OPT, array( __CLASS__, 'saved' ), 20 );
		add_action( 'add_option_' . CAS::OPT, array( __CLASS__, 'saved' ), 20 );
		add_action( 'update_option_' . self::KEY_OPT, array( __CLASS__, 'forget_report' ) );
		add_action( 'update_option_' . self::OAUTH_OPT, array( __CLASS__, 'forget_report' ) );
		add_action( 'admin_post_cas_ga_connect', array( __CLASS__, 'connect' ) );
		add_action( 'admin_post_cas_ga_oauth', array( __CLASS__, 'callback' ) );
		add_action( 'admin_post_cas_ga_disconnect', array( __CLASS__, 'disconnect' ) );
	}

	/* ---------------------------------------------------------------------
	 * Is there already a Google tag on the site?
	 * ------------------------------------------------------------------ */

	/** Plugins that normally add the tracking code themselves. */
	private static function tag_plugins() {
		$known = array(
			'google-site-kit/google-site-kit.php'                => 'Site Kit by Google',
			'google-analytics-for-wordpress/googleanalytics.php' => 'MonsterInsights',
			'google-analytics-premium/googleanalytics-premium.php' => 'MonsterInsights Pro',
			'google-analytics-dashboard-for-wp/gadwp.php'        => 'ExactMetrics',
			'ga-google-analytics/ga-google-analytics.php'        => 'GA Google Analytics',
			'wp-analytify/wp-analytify.php'                      => 'Analytify',
			'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' => 'GTM4WP',
			'woocommerce-google-analytics-integration/woocommerce-google-analytics-integration.php' => 'Google Analytics for WooCommerce',
		);
		$active = (array) get_option( 'active_plugins', array() );
		return array_values( array_intersect_key( $known, array_flip( $active ) ) );
	}

	/**
	 * Load the public home page (without our own code) and look for a Google
	 * tag from the theme or another plugin. The result is stored, so the
	 * public site never has to run this check itself.
	 */
	public static function detect( $force = false ) {
		$d = get_option( self::DETECT_OPT );
		if ( ! $force && is_array( $d ) && isset( $d['time'] ) ) {
			return $d;
		}
		$d = array(
			'ok'      => false,
			'ids'     => array(),
			'script'  => false,
			'plugins' => self::tag_plugins(),
			'time'    => time(),
		);
		$probe = add_query_arg(
			array(
				'cas_ga_probe' => '1',
				't'            => time(),
			),
			home_url( '/' )
		);
		// Some hosts refuse unusual requests: fall back to the plain home page.
		$r = null;
		foreach ( array( $probe, home_url( '/' ) ) as $url ) {
			$r = wp_remote_get( $url, array( 'timeout' => 8 ) );
			if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
				break;
			}
		}
		if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
			$html        = preg_replace( '#<!-- cas-ga -->.*?<!-- /cas-ga -->#s', '', (string) wp_remote_retrieve_body( $r ) );
			$d['ok']     = true;
			$d['script'] = (bool) preg_match( '#googletagmanager\.com/(gtag/js|gtm\.js)|google-analytics\.com/(analytics|ga)\.js#i', $html );
			if ( preg_match( '#googletagmanager\.com|google-analytics\.com|gtag\s*\(#i', $html ) && preg_match_all( '/\b(G-[A-Z0-9]{6,12}|GTM-[A-Z0-9]{4,10}|UA-\d{4,10}-\d{1,4})\b/', $html, $m ) ) {
				$d['ids'] = array_values( array_unique( $m[1] ) );
			}
		}
		update_option( self::DETECT_OPT, $d, false );
		return $d;
	}

	/**
	 * Where things stand. $refresh re-checks once a day (admin screens only).
	 *
	 * state: existing | plugin | none | unknown. adding: is our code going out?
	 */
	public static function status( $refresh = false ) {
		$d = get_option( self::DETECT_OPT );
		if ( $refresh && ( ! is_array( $d ) || empty( $d['time'] ) || $d['time'] < time() - DAY_IN_SECONDS ) ) {
			$d = self::detect( true );
		}
		if ( ! is_array( $d ) ) {
			$d = array(
				'ok'      => false,
				'ids'     => array(),
				'script'  => false,
				'plugins' => array(),
			);
		}
		if ( ! empty( $d['ids'] ) || ! empty( $d['script'] ) ) {
			$state = 'existing';
		} elseif ( empty( $d['ok'] ) ) {
			$state = 'unknown';
		} elseif ( ! empty( $d['plugins'] ) ) {
			$state = 'plugin';
		} else {
			$state = 'none';
		}
		$id   = (string) CAS::opt( 'ga_id' );
		$mode = CAS::opt( 'ga_mode' );
		return array(
			'state'   => $state,
			'ids'     => isset( $d['ids'] ) ? (array) $d['ids'] : array(),
			'plugins' => isset( $d['plugins'] ) ? (array) $d['plugins'] : array(),
			'id'      => $id,
			'mode'    => $mode,
			'adding'  => '' !== $id && 'off' !== $mode && ( 'force' === $mode || 'none' === $state ),
		);
	}

	public static function status_text( $s ) {
		$ids = $s['ids'] ? ' (' . implode( ', ', $s['ids'] ) . ')' : '';
		if ( 'existing' === $s['state'] ) {
			if ( $s['adding'] ) {
				/* translators: 1: existing tag IDs, 2: our measurement ID */
				return sprintf( __( 'A Google tag is already on this site%1$s. Yours (%2$s) is being added as well because "Always add it" is selected.', 'clean-admin-studio' ), $ids, $s['id'] );
			}
			/* translators: %s: existing tag IDs */
			return sprintf( __( 'A Google tag is already on this site%s, added by your theme or another plugin. Nothing is added here, so visits are not counted twice.', 'clean-admin-studio' ), $ids );
		}
		if ( $s['adding'] ) {
			/* translators: %s: measurement ID */
			return sprintf( __( 'Your tracking code (%s) is being added to every public page.', 'clean-admin-studio' ), $s['id'] );
		}
		if ( 'plugin' === $s['state'] ) {
			/* translators: %s: plugin names */
			return sprintf( __( 'No Google tag was found in the page, but %s is active and normally adds it (sometimes only after cookie consent). Nothing is added here unless you choose "Always add it".', 'clean-admin-studio' ), implode( ', ', $s['plugins'] ) );
		}
		if ( 'unknown' === $s['state'] ) {
			return __( 'The public site could not be checked just now, so nothing is added.', 'clean-admin-studio' );
		}
		if ( 'off' === $s['mode'] && '' !== $s['id'] ) {
			return __( 'No Google tag was found on this site. Adding is switched off.', 'clean-admin-studio' );
		}
		return __( 'No Google tag was found on this site. Enter your Measurement ID below and it will be added for you.', 'clean-admin-studio' );
	}

	public static function saved() {
		self::forget_report();
		if ( '' !== (string) CAS::opt( 'ga_id' ) || CAS::opt( 'w_analytics' ) || CAS::opt( 'w_health' ) ) {
			self::detect( true );
		}
	}

	public static function forget_report() {
		delete_transient( 'cas_ga_report' );
		delete_transient( 'cas_ga_token' );
	}

	public static function check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'clean-admin-studio' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cas_ga_check' );
		self::detect( true );
		self::forget_report();
		wp_safe_redirect( CAS::page_url( 'analytics' ) );
		exit;
	}

	/** Print the tracking code on public pages, only when it is needed. */
	public static function tag() {
		if ( is_admin() || isset( $_GET['cas_ga_probe'] ) || is_customize_preview() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$s = self::status();
		if ( ! $s['adding'] || ! preg_match( '/^G-[A-Z0-9]{4,20}$/', $s['id'] ) ) {
			return;
		}
		if ( CAS::opt( 'ga_skip_staff' ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return;
		}
		$id = $s['id'];
		echo "<!-- cas-ga -->\n"
			. '<script async src="' . esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . $id ) . '"></script>' . "\n" // phpcs:ignore WordPress.WP.EnqueuedResources
			. '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $id ) . ');</script>' . "\n"
			. "<!-- /cas-ga -->\n";
	}

	/* ---------------------------------------------------------------------
	 * Visitor numbers (Google Analytics Data API, read-only)
	 * ------------------------------------------------------------------ */

	private static function key() {
		$k = json_decode( (string) get_option( self::KEY_OPT, '' ), true );
		return ( is_array( $k ) && ! empty( $k['client_email'] ) && ! empty( $k['private_key'] ) ) ? $k : null;
	}

	public static function key_email() {
		$k = self::key();
		return $k ? $k['client_email'] : '';
	}

	/**
	 * Keep the saved key unless a new, valid one is pasted. The key itself is
	 * never sent back to the browser.
	 */
	public static function sanitize_key( $in ) {
		$current = (string) get_option( self::KEY_OPT, '' );
		if ( ! empty( $_POST['cas_ga_key_remove'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked by options.php.
			return '';
		}
		if ( ! is_string( $in ) || '' === trim( $in ) ) {
			return $current;
		}
		$j = json_decode( trim( $in ), true );
		if ( ! is_array( $j ) || empty( $j['client_email'] ) || empty( $j['private_key'] ) || false === strpos( (string) $j['private_key'], 'PRIVATE KEY' ) ) {
			if ( $in !== $current && function_exists( 'add_settings_error' ) ) {
				add_settings_error( 'cas', 'cas_ga_key', __( 'That does not look like a service account key file, so the saved key was left as it was.', 'clean-admin-studio' ), 'error' );
			}
			return $current;
		}
		return wp_json_encode(
			array(
				'client_email' => sanitize_email( $j['client_email'] ),
				'private_key'  => (string) $j['private_key'],
			)
		);
	}

	private static function b64( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/** Returns an access token, or '' with a readable reason in $error. */
	private static function token( &$error ) {
		$token = get_transient( 'cas_ga_token' );
		if ( $token ) {
			return $token;
		}
		// Signed in with Google: swap the long-term permission for a short-lived token.
		$o = self::oauth();
		if ( '' !== $o['refresh_token'] && 'relay' === $o['via'] ) {
			$relay = self::relay();
			if ( '' === $relay ) {
				$error = __( 'The connect service address is missing. Connect again in the Google Analytics tab.', 'clean-admin-studio' );
				return '';
			}
			$r = wp_remote_post(
				$relay . '/refresh',
				array(
					'timeout' => 15,
					'body'    => array( 'refresh_token' => $o['refresh_token'] ),
				)
			);
			return self::read_token( $r, $error, __( 'The Google connection is no longer valid. Connect again in the Google Analytics tab.', 'clean-admin-studio' ) );
		}
		if ( '' !== $o['refresh_token'] && '' !== $o['client_id'] ) {
			$r = wp_remote_post(
				self::TOKEN_URL,
				array(
					'timeout' => 10,
					'body'    => array(
						'grant_type'    => 'refresh_token',
						'refresh_token' => $o['refresh_token'],
						'client_id'     => $o['client_id'],
						'client_secret' => $o['client_secret'],
					),
				)
			);
			return self::read_token( $r, $error, __( 'The Google connection is no longer valid. Connect again in the Google Analytics tab.', 'clean-admin-studio' ) );
		}

		// Otherwise fall back to a service account key, if one was saved.
		$key = self::key();
		if ( ! $key || ! function_exists( 'openssl_sign' ) ) {
			$error = __( 'Google Analytics is not connected yet.', 'clean-admin-studio' );
			return '';
		}
		$url   = self::TOKEN_URL;
		$now   = time();
		$head  = self::b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claim = self::b64(
			wp_json_encode(
				array(
					'iss'   => $key['client_email'],
					'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
					'aud'   => $url,
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);
		$sig   = '';
		if ( ! @openssl_sign( $head . '.' . $claim, $sig, $key['private_key'], OPENSSL_ALGO_SHA256 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$error = __( 'The saved key could not be used. Paste the key file again.', 'clean-admin-studio' );
			return '';
		}
		$r = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $head . '.' . $claim . '.' . self::b64( $sig ),
				),
			)
		);
		return self::read_token( $r, $error, __( 'Google did not accept the key.', 'clean-admin-studio' ) );
	}

	/** Read Google's token reply, remember the token, or explain the refusal. */
	private static function read_token( $r, &$error, $refused ) {
		if ( is_wp_error( $r ) ) {
			$error = __( 'Google could not be reached.', 'clean-admin-studio' );
			return '';
		}
		$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( empty( $j['access_token'] ) ) {
			$why   = isset( $j['error_description'] ) ? $j['error_description'] : ( isset( $j['error'] ) && is_string( $j['error'] ) ? $j['error'] : '' );
			$error = trim( $refused . ' ' . sanitize_text_field( $why ) );
			return '';
		}
		$ttl = isset( $j['expires_in'] ) ? max( 60, (int) $j['expires_in'] - 120 ) : 1800;
		set_transient( 'cas_ga_token', $j['access_token'], $ttl );
		return $j['access_token'];
	}

	/* ---------------------------------------------------------------------
	 * Sign in with Google
	 * ------------------------------------------------------------------ */

	private static function oauth_blank() {
		return array(
			'client_id'     => '',
			'client_secret' => '',
			'refresh_token' => '',
			'email'         => '',
			'via'           => '',
		);
	}

	/**
	 * Address of a connect service (relay), when one is used. With a relay
	 * the Google app lives in one place and sites need no setup at all.
	 */
	public static function relay() {
		$relay = defined( 'CAS_GOOGLE_RELAY' ) ? (string) CAS_GOOGLE_RELAY : (string) CAS::opt( 'ga_relay' );
		$relay = (string) apply_filters( 'cas_google_relay', $relay );
		return preg_match( '#^https?://#i', $relay ) ? untrailingslashit( esc_url_raw( $relay ) ) : '';
	}

	private static function oauth_saved() {
		$o = get_option( self::OAUTH_OPT, array() );
		return array_merge( self::oauth_blank(), is_array( $o ) ? array_intersect_key( $o, self::oauth_blank() ) : array() );
	}

	/**
	 * The Google sign-in app in use and the saved connection. An agency can
	 * set the app once for every site with two lines in wp-config.php.
	 */
	public static function oauth() {
		$o          = self::oauth_saved();
		$o['fixed'] = false;
		if ( defined( 'CAS_GOOGLE_CLIENT_ID' ) && defined( 'CAS_GOOGLE_CLIENT_SECRET' ) && CAS_GOOGLE_CLIENT_ID && CAS_GOOGLE_CLIENT_SECRET ) {
			$o['client_id']     = (string) CAS_GOOGLE_CLIENT_ID;
			$o['client_secret'] = (string) CAS_GOOGLE_CLIENT_SECRET;
			$o['fixed']         = true;
		}
		return $o;
	}

	public static function has_app() {
		$o = self::oauth();
		return '' !== self::relay() || ( '' !== $o['client_id'] && '' !== $o['client_secret'] );
	}

	public static function connected() {
		$o = self::oauth();
		if ( '' === $o['refresh_token'] ) {
			return false;
		}
		return 'relay' === $o['via'] ? '' !== self::relay() : '' !== $o['client_id'];
	}

	/** The address Google sends people back to after they sign in. */
	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=cas_ga_oauth' );
	}

	/**
	 * Form posts may only change the app details. The secret is kept when the
	 * field is left empty, and tokens can only be written by the plugin.
	 */
	public static function sanitize_oauth( $in ) {
		$cur = self::oauth_saved();
		if ( ! is_array( $in ) ) {
			return $cur;
		}
		if ( self::$internal ) {
			$out = array();
			foreach ( self::oauth_blank() as $k => $blank ) {
				$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( (string) $in[ $k ] ) : $blank;
			}
			return $out;
		}
		$id     = isset( $in['client_id'] ) ? sanitize_text_field( trim( (string) $in['client_id'] ) ) : $cur['client_id'];
		$secret = ( isset( $in['client_secret'] ) && '' !== trim( (string) $in['client_secret'] ) ) ? sanitize_text_field( trim( (string) $in['client_secret'] ) ) : $cur['client_secret'];
		if ( '' === $id ) {
			$secret = '';
		}
		if ( $id !== $cur['client_id'] || $secret !== $cur['client_secret'] ) {
			// A different app cannot use the old permission (one obtained
			// through a connect service does not depend on this app).
			if ( 'relay' !== $cur['via'] ) {
				$cur['refresh_token'] = '';
				$cur['email']         = '';
			}
		}
		$cur['client_id']     = $id;
		$cur['client_secret'] = $secret;
		return $cur;
	}

	private static function save_oauth( $data ) {
		self::$internal = true;
		update_option( self::OAUTH_OPT, array_intersect_key( $data, self::oauth_blank() ), false );
		self::$internal = false;
		delete_transient( 'cas_ga_props' );
		self::forget_report();
	}

	private static function guard( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'clean-admin-studio' ), '', array( 'response' => 403 ) );
		}
		if ( $nonce ) {
			check_admin_referer( $nonce );
		}
	}

	private static function back( $message, $type = 'success' ) {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( 'cas', 'cas_ga', $message, $type );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
		}
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', admin_url( 'options-general.php?page=' . CAS_SLUG ) ) . '#analytics' );
		exit;
	}

	/** Step 1: send the person to Google's own sign-in page. */
	public static function connect() {
		self::guard( 'cas_ga_connect' );
		$o = self::oauth();
		if ( ! self::has_app() ) {
			self::back( __( 'Add the Google sign-in app details first, then save.', 'clean-admin-studio' ), 'error' );
		}
		$state    = wp_generate_password( 40, false );
		$verifier = wp_generate_password( 64, false );
		set_transient(
			'cas_ga_state_' . get_current_user_id(),
			array(
				'state'    => $state,
				'verifier' => $verifier,
			),
			15 * MINUTE_IN_SECONDS
		);
		$relay = self::relay();
		if ( '' !== $relay ) {
			// The connect service talks to Google. It gets a fingerprint of a
			// secret only this site knows, so nobody else can collect the result.
			$url = add_query_arg(
				array(
					'return'    => rawurlencode( self::redirect_uri() ),
					'state'     => $state,
					'challenge' => self::b64( hash( 'sha256', $verifier, true ) ),
				),
				$relay . '/start'
			);
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- the connect service.
			exit;
		}
		$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(
			array(
				'client_id'     => $o['client_id'],
				'redirect_uri'  => self::redirect_uri(),
				'response_type' => 'code',
				'scope'         => 'openid email ' . self::SCOPE,
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- Google's sign-in page.
		exit;
	}

	/** Step 2: Google sends the person back here with a one-time code. */
	public static function callback() {
		self::guard( '' );
		// phpcs:disable WordPress.Security.NonceVerification -- protected by the one-time state value below.
		$uid      = get_current_user_id();
		$expected = get_transient( 'cas_ga_state_' . $uid );
		delete_transient( 'cas_ga_state_' . $uid );
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! is_array( $expected ) || empty( $expected['state'] ) || ! hash_equals( (string) $expected['state'], $state ) ) {
			self::back( __( 'The sign-in could not be verified. Please try connecting again.', 'clean-admin-studio' ), 'error' );
		}
		if ( isset( $_GET['error'] ) || empty( $_GET['code'] ) ) {
			self::back( __( 'Google sign-in was cancelled, so nothing was connected.', 'clean-admin-studio' ), 'error' );
		}
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		// phpcs:enable
		$o     = self::oauth();
		$relay = self::relay();
		if ( '' !== $relay ) {
			$r = wp_remote_post(
				$relay . '/token',
				array(
					'timeout' => 15,
					'body'    => array(
						'code'     => $code,
						'verifier' => (string) $expected['verifier'],
					),
				)
			);
		} else {
			$r = wp_remote_post(
				self::TOKEN_URL,
				array(
					'timeout' => 15,
					'body'    => array(
						'grant_type'    => 'authorization_code',
						'code'          => $code,
						'client_id'     => $o['client_id'],
						'client_secret' => $o['client_secret'],
						'redirect_uri'  => self::redirect_uri(),
					),
				)
			);
		}
		delete_transient( 'cas_ga_token' );
		$error = '';
		if ( ! self::read_token( $r, $error, __( 'Google did not complete the sign-in.', 'clean-admin-studio' ) ) ) {
			self::back( $error, 'error' );
		}
		$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( isset( $j['scope'] ) && false === strpos( (string) $j['scope'], self::SCOPE ) ) {
			delete_transient( 'cas_ga_token' );
			self::back( __( 'Google Analytics access was not ticked on the Google screen. Connect again and allow "See and download your Google Analytics data".', 'clean-admin-studio' ), 'error' );
		}
		if ( empty( $j['refresh_token'] ) ) {
			delete_transient( 'cas_ga_token' );
			self::back( __( 'Google did not grant lasting access. Please connect again.', 'clean-admin-studio' ), 'error' );
		}
		$email = isset( $j['email'] ) ? sanitize_email( (string) $j['email'] ) : '';
		if ( '' === $email && ! empty( $j['id_token'] ) ) {
			$parts = explode( '.', (string) $j['id_token'] );
			if ( isset( $parts[1] ) ) {
				$claims = json_decode( (string) base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				$email  = isset( $claims['email'] ) ? sanitize_email( $claims['email'] ) : '';
			}
		}
		$token                  = get_transient( 'cas_ga_token' );
		$saved                  = self::oauth_saved();
		$saved['refresh_token'] = (string) $j['refresh_token'];
		$saved['email']         = $email;
		$saved['via']           = '' !== $relay ? 'relay' : 'direct';
		self::save_oauth( $saved );
		if ( $token ) {
			set_transient( 'cas_ga_token', $token, 50 * MINUTE_IN_SECONDS );
		}

		// With a single property there is nothing to choose: select it.
		$props = self::properties( true );
		if ( 1 === count( $props ) && '' === (string) CAS::opt( 'ga_property' ) ) {
			update_option( CAS::OPT, array_merge( CAS::all(), array( 'ga_property' => (string) key( $props ) ) ) );
			self::back( __( 'Connected to Google Analytics. Your property was selected for you.', 'clean-admin-studio' ) );
		}
		self::back( __( 'Connected to Google Analytics. Now choose which property to show, then save.', 'clean-admin-studio' ) );
	}

	public static function disconnect() {
		self::guard( 'cas_ga_disconnect' );
		$saved = self::oauth_saved();
		if ( '' !== $saved['refresh_token'] ) {
			// Tell Google to forget the permission too; carry on if that fails.
			wp_remote_post(
				'https://oauth2.googleapis.com/revoke',
				array(
					'timeout' => 8,
					'body'    => array( 'token' => $saved['refresh_token'] ),
				)
			);
		}
		$saved['refresh_token'] = '';
		$saved['email']         = '';
		$saved['via']           = '';
		self::save_oauth( $saved );
		self::back( __( 'Disconnected from Google Analytics.', 'clean-admin-studio' ) );
	}

	/**
	 * The Analytics properties this Google account can see, as id => name.
	 * Any problem is kept in $props_error for the settings screen.
	 */
	public static $props_error = '';

	public static function properties( $force = false ) {
		$cached = get_transient( 'cas_ga_props' );
		if ( ! $force && is_array( $cached ) ) {
			self::$props_error = isset( $cached['error'] ) ? (string) $cached['error'] : '';
			return isset( $cached['list'] ) && is_array( $cached['list'] ) ? $cached['list'] : array();
		}
		$list  = array();
		$error = '';
		$token = self::token( $error );
		$page  = '';
		for ( $i = 0; $token && $i < 5; $i++ ) {
			$r = wp_remote_get(
				'https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=200' . ( $page ? '&pageToken=' . rawurlencode( $page ) : '' ),
				array(
					'timeout' => 12,
					'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				)
			);
			if ( is_wp_error( $r ) ) {
				$error = __( 'Google could not be reached.', 'clean-admin-studio' );
				break;
			}
			$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
			if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || ! is_array( $j ) ) {
				$error = isset( $j['error']['message'] ) ? sanitize_text_field( $j['error']['message'] ) : __( 'Google did not return the list of properties.', 'clean-admin-studio' );
				break;
			}
			foreach ( isset( $j['accountSummaries'] ) ? (array) $j['accountSummaries'] : array() as $account ) {
				foreach ( isset( $account['propertySummaries'] ) ? (array) $account['propertySummaries'] : array() as $prop ) {
					$id = isset( $prop['property'] ) ? preg_replace( '/\D/', '', (string) $prop['property'] ) : '';
					if ( '' !== $id ) {
						$name        = isset( $prop['displayName'] ) ? $prop['displayName'] : $id;
						$list[ $id ] = sanitize_text_field( $name . ( isset( $account['displayName'] ) ? ' (' . $account['displayName'] . ')' : '' ) );
					}
				}
			}
			$page = isset( $j['nextPageToken'] ) ? (string) $j['nextPageToken'] : '';
			if ( '' === $page ) {
				break;
			}
		}
		self::$props_error = $error;
		set_transient(
			'cas_ga_props',
			array(
				'list'  => $list,
				'error' => $error,
			),
			( $list && ! $error ) ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS
		);
		return $list;
	}

	/**
	 * array( series, d7, d28, pages, time ) or array( error ). Cached for an
	 * hour; failures for five minutes.
	 */
	public static function report() {
		$cached = get_transient( 'cas_ga_report' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$fail = function ( $message ) {
			$out = array( 'error' => $message );
			set_transient( 'cas_ga_report', $out, 5 * MINUTE_IN_SECONDS );
			return $out;
		};
		$property = preg_replace( '/\D/', '', (string) CAS::opt( 'ga_property' ) );
		if ( '' === $property ) {
			return $fail( __( 'The Property ID is missing.', 'clean-admin-studio' ) );
		}
		$error = '';
		$token = self::token( $error );
		if ( ! $token ) {
			return $fail( $error );
		}
		$totals = array( array( 'name' => 'activeUsers' ), array( 'name' => 'sessions' ), array( 'name' => 'screenPageViews' ) );
		$month  = array(
			'startDate' => '27daysAgo',
			'endDate'   => 'today',
		);
		$body   = array(
			'requests' => array(
				array(
					'dateRanges'    => array( $month ),
					'dimensions'    => array( array( 'name' => 'date' ) ),
					'metrics'       => array( array( 'name' => 'sessions' ) ),
					'orderBys'      => array( array( 'dimension' => array( 'dimensionName' => 'date' ) ) ),
					'keepEmptyRows' => true,
				),
				array(
					'dateRanges' => array(
						array(
							'startDate' => '6daysAgo',
							'endDate'   => 'today',
						),
						$month,
					),
					'metrics'    => $totals,
				),
				array(
					'dateRanges' => array( $month ),
					'dimensions' => array( array( 'name' => 'pagePath' ) ),
					'metrics'    => array( array( 'name' => 'screenPageViews' ) ),
					'orderBys'   => array(
						array(
							'metric' => array( 'metricName' => 'screenPageViews' ),
							'desc'   => true,
						),
					),
					'limit'      => 5,
				),
			),
		);
		$r      = wp_remote_post(
			'https://analyticsdata.googleapis.com/v1beta/properties/' . $property . ':batchRunReports',
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $fail( __( 'Google could not be reached.', 'clean-admin-studio' ) );
		}
		$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || empty( $j['reports'] ) || ! is_array( $j['reports'] ) ) {
			$why = isset( $j['error']['message'] ) ? sanitize_text_field( $j['error']['message'] ) : '';
			return $fail( trim( __( 'Google Analytics did not return any numbers.', 'clean-admin-studio' ) . ' ' . $why ) );
		}

		$rows = function ( $i ) use ( $j ) {
			return isset( $j['reports'][ $i ]['rows'] ) && is_array( $j['reports'][ $i ]['rows'] ) ? $j['reports'][ $i ]['rows'] : array();
		};
		$out  = array(
			'series' => array(),
			'd7'     => array( 0, 0, 0 ),
			'd28'    => array( 0, 0, 0 ),
			'pages'  => array(),
			'time'   => time(),
		);
		foreach ( $rows( 0 ) as $row ) {
			$out['series'][] = isset( $row['metricValues'][0]['value'] ) ? (int) $row['metricValues'][0]['value'] : 0;
		}
		foreach ( $rows( 1 ) as $row ) {
			$range = isset( $row['dimensionValues'][0]['value'] ) ? $row['dimensionValues'][0]['value'] : 'date_range_0';
			$slot  = 'date_range_1' === $range ? 'd28' : 'd7';
			for ( $i = 0; $i < 3; $i++ ) {
				$out[ $slot ][ $i ] = isset( $row['metricValues'][ $i ]['value'] ) ? (int) $row['metricValues'][ $i ]['value'] : 0;
			}
		}
		foreach ( $rows( 2 ) as $row ) {
			if ( isset( $row['dimensionValues'][0]['value'], $row['metricValues'][0]['value'] ) ) {
				$out['pages'][] = array( sanitize_text_field( $row['dimensionValues'][0]['value'] ), (int) $row['metricValues'][0]['value'] );
			}
		}
		set_transient( 'cas_ga_report', $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Run one Google Analytics report for the chosen property.
	 * Returns the decoded reply, or null with a readable reason in $error.
	 */
	public static function run_report( $body, &$error ) {
		$property = preg_replace( '/\D/', '', (string) CAS::opt( 'ga_property' ) );
		if ( '' === $property ) {
			$error = __( 'Choose a property in the Google Analytics tab.', 'clean-admin-studio' );
			return null;
		}
		$token = self::token( $error );
		if ( ! $token ) {
			return null;
		}
		$r = wp_remote_post(
			'https://analyticsdata.googleapis.com/v1beta/properties/' . $property . ':runReport',
			array(
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $r ) ) {
			$error = __( 'Google could not be reached.', 'clean-admin-studio' );
			return null;
		}
		$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || ! is_array( $j ) ) {
			$error = isset( $j['error']['message'] ) ? sanitize_text_field( $j['error']['message'] ) : __( 'Google Analytics did not return any numbers.', 'clean-admin-studio' );
			return null;
		}
		return $j;
	}

	/** Can visitor numbers be fetched at all (connected and a property chosen)? */
	public static function ready() {
		return ( self::connected() || self::key() ) && '' !== (string) CAS::opt( 'ga_property' );
	}

	private static function spark( $values ) {
		$n = count( $values );
		if ( $n < 2 ) {
			return '';
		}
		$max = max( 1, max( $values ) );
		$pts = array();
		foreach ( array_values( $values ) as $i => $v ) {
			$pts[] = round( $i * ( 280 / ( $n - 1 ) ), 1 ) . ',' . round( 46 - ( $v / $max ) * 42, 1 );
		}
		return '<svg class="cas-spark" viewBox="0 0 280 48" preserveAspectRatio="none" role="img" aria-label="' . esc_attr__( 'Sessions per day, last 28 days', 'clean-admin-studio' ) . '"><polyline fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke" points="' . esc_attr( implode( ' ', $pts ) ) . '" /></svg>';
	}

	/* ---------------------------------------------------------------------
	 * Widgets
	 * ------------------------------------------------------------------ */

	public static function register() {
		if ( CAS::safe_mode() ) {
			return;
		}
		$can_edit = current_user_can( 'edit_posts' );
		if ( CAS::opt( 'w_analytics' ) && $can_edit ) {
			wp_add_dashboard_widget( 'cas_analytics', __( 'Visitors', 'clean-admin-studio' ), array( __CLASS__, 'w_analytics' ) );
		}
		if ( CAS::opt( 'w_recent' ) && $can_edit ) {
			wp_add_dashboard_widget( 'cas_recent', __( 'Recently edited', 'clean-admin-studio' ), array( __CLASS__, 'w_recent' ) );
		}
		if ( CAS::opt( 'w_health' ) && $can_edit ) {
			wp_add_dashboard_widget( 'cas_health', __( 'Site check', 'clean-admin-studio' ), array( __CLASS__, 'w_health' ) );
		}
		if ( CAS::opt( 'w_links' ) && '' !== trim( (string) CAS::opt( 'w_links_items' ) ) ) {
			wp_add_dashboard_widget( 'cas_links', CAS::opt( 'w_links_title' ) ? CAS::opt( 'w_links_title' ) : __( 'Quick links', 'clean-admin-studio' ), array( __CLASS__, 'w_links' ) );
		}
		if ( CAS::opt( 'w_note' ) && '' !== trim( (string) CAS::opt( 'w_note_body' ) ) ) {
			wp_add_dashboard_widget( 'cas_note', CAS::opt( 'w_note_title' ) ? CAS::opt( 'w_note_title' ) : __( 'A note from your web team', 'clean-admin-studio' ), array( __CLASS__, 'w_note' ) );
		}
	}

	public static function w_analytics() {
		$admin     = current_user_can( 'manage_options' );
		$status    = self::status( true );
		$connected = self::ready();
		$report    = $connected ? self::report() : null;

		if ( is_array( $report ) && empty( $report['error'] ) ) {
			$labels = array( __( 'Visitors', 'clean-admin-studio' ), __( 'Sessions', 'clean-admin-studio' ), __( 'Page views', 'clean-admin-studio' ) );
			echo '<div class="cas-w-tiles">';
			foreach ( $labels as $i => $label ) {
				/* translators: %s: a number */
				echo '<div class="cas-w-tile"><strong>' . esc_html( number_format_i18n( $report['d7'][ $i ] ) ) . '</strong><span>' . esc_html( $label ) . '</span><em>' . esc_html( sprintf( __( '%s in 28 days', 'clean-admin-studio' ), number_format_i18n( $report['d28'][ $i ] ) ) ) . '</em></div>';
			}
			echo '</div>';
			echo '<p class="cas-w-cap">' . esc_html__( 'Large numbers: last 7 days.', 'clean-admin-studio' ) . '</p>';
			echo self::spark( $report['series'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from numbers.
			echo '<p class="cas-w-cap">' . esc_html__( 'Sessions per day, last 28 days', 'clean-admin-studio' ) . '</p>';
			if ( $report['pages'] ) {
				echo '<p class="cas-w-h">' . esc_html__( 'Most viewed pages (28 days)', 'clean-admin-studio' ) . '</p><ul class="cas-w-list">';
				foreach ( $report['pages'] as $page ) {
					echo '<li><a class="cas-w-main" href="' . esc_url( home_url( $page[0] ) ) . '" target="_blank" rel="noopener">' . esc_html( $page[0] ) . '</a><span class="cas-w-meta">' . esc_html( number_format_i18n( $page[1] ) ) . '</span></li>';
				}
				echo '</ul>';
			}
		} elseif ( is_array( $report ) ) {
			echo '<p>' . esc_html__( 'Visitor numbers are not available right now.', 'clean-admin-studio' ) . ( $admin ? ' ' . esc_html( $report['error'] ) : '' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Visitor numbers can be shown here once Google Analytics is connected.', 'clean-admin-studio' );
			if ( $admin ) {
				echo ' <a href="' . esc_url( CAS::page_url( 'analytics' ) ) . '">' . esc_html__( 'Connect with Google', 'clean-admin-studio' ) . '</a>';
			}
			echo '</p>';
		}

		echo '<p class="cas-w-foot">';
		if ( $admin ) {
			echo esc_html( self::status_text( $status ) ) . ' <a href="' . esc_url( CAS::page_url( 'analytics' ) ) . '">' . esc_html__( 'Analytics settings', 'clean-admin-studio' ) . '</a> &middot; ';
		}
		echo '<a href="https://analytics.google.com/" target="_blank" rel="noopener">' . esc_html__( 'Open Google Analytics', 'clean-admin-studio' ) . '</a></p>';
	}

	public static function w_recent() {
		$types = array_values( array_diff( get_post_types( array( 'public' => true, 'show_ui' => true ) ), array( 'attachment' ) ) );
		$posts = $types ? get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'perm'           => 'readable',
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'posts_per_page' => 6,
			)
		) : array();
		if ( ! $posts ) {
			echo '<p>' . esc_html__( 'Nothing has been edited yet.', 'clean-admin-studio' ) . '</p>';
			return;
		}
		echo '<ul class="cas-w-list">';
		foreach ( $posts as $p ) {
			$edit   = current_user_can( 'edit_post', $p->ID ) ? get_edit_post_link( $p->ID ) : '';
			$title  = '' !== trim( $p->post_title ) ? $p->post_title : __( '(no title)', 'clean-admin-studio' );
			$type   = get_post_type_object( $p->post_type );
			$status = 'publish' !== $p->post_status ? get_post_status_object( $p->post_status ) : null;
			$meta   = ( $type ? $type->labels->singular_name : '' ) . ( $status ? ', ' . $status->label : '' );
			/* translators: %s: time span such as "2 hours" */
			$when = sprintf( __( '%s ago', 'clean-admin-studio' ), human_time_diff( (int) get_post_modified_time( 'U', true, $p ), time() ) );
			echo '<li><span class="cas-w-main"><a href="' . esc_url( $edit ? $edit : get_permalink( $p ) ) . '">' . esc_html( $title ) . '</a></span><span class="cas-w-meta">' . esc_html( $meta . ' · ' . $when ) . '</span></li>';
		}
		echo '</ul>';
	}

	public static function w_health() {
		$items = array();
		$https = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
		$items[] = array( $https, $https ? __( 'The site uses a secure connection (https).', 'clean-admin-studio' ) : __( 'The site is not using a secure connection (https).', 'clean-admin-studio' ), '' );

		$public  = (bool) get_option( 'blog_public' );
		$items[] = array( $public, $public ? __( 'Search engines can find the site.', 'clean-admin-studio' ) : __( 'Search engines are being asked not to list the site.', 'clean-admin-studio' ), ( ! $public && current_user_can( 'manage_options' ) ) ? admin_url( 'options-reading.php' ) : '' );

		if ( current_user_can( 'update_core' ) || current_user_can( 'update_plugins' ) ) {
			$data    = wp_get_update_data();
			$count   = isset( $data['counts']['total'] ) ? (int) $data['counts']['total'] : 0;
			/* translators: %s: number of updates */
			$items[] = array( 0 === $count, 0 === $count ? __( 'Everything is up to date.', 'clean-admin-studio' ) : sprintf( _n( '%s update is waiting.', '%s updates are waiting.', $count, 'clean-admin-studio' ), number_format_i18n( $count ) ), $count ? admin_url( 'update-core.php' ) : '' );
		}
		if ( current_user_can( 'moderate_comments' ) ) {
			$waiting = (int) wp_count_comments()->moderated;
			/* translators: %s: number of comments */
			$items[] = array( 0 === $waiting, 0 === $waiting ? __( 'No comments are waiting for review.', 'clean-admin-studio' ) : sprintf( _n( '%s comment is waiting for review.', '%s comments are waiting for review.', $waiting, 'clean-admin-studio' ), number_format_i18n( $waiting ) ), $waiting ? admin_url( 'edit-comments.php?comment_status=moderated' ) : '' );
		}
		$s       = self::status( true );
		$tracked = 'existing' === $s['state'] || $s['adding'];
		$items[] = array( $tracked, $tracked ? __( 'Visitor tracking (Google Analytics) is in place.', 'clean-admin-studio' ) : __( 'No visitor tracking (Google Analytics) was found.', 'clean-admin-studio' ), ( ! $tracked && current_user_can( 'manage_options' ) ) ? CAS::page_url( 'analytics' ) : '' );

		echo '<ul class="cas-w-list cas-w-check">';
		foreach ( $items as $item ) {
			echo '<li class="' . ( $item[0] ? 'cas-w-ok' : 'cas-w-warn' ) . '"><span class="dashicons dashicons-' . ( $item[0] ? 'yes-alt' : 'warning' ) . '" aria-hidden="true"></span><span>' . esc_html( $item[1] );
			if ( $item[2] ) {
				echo ' <a href="' . esc_url( $item[2] ) . '">' . esc_html__( 'Look into it', 'clean-admin-studio' ) . '</a>';
			}
			echo '</span></li>';
		}
		echo '</ul>';
	}

	public static function w_links() {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		echo '<ul class="cas-w-links">';
		foreach ( preg_split( '/\r\n|\r|\n/', (string) CAS::opt( 'w_links_items' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$url   = esc_url( 2 === count( $parts ) ? $parts[1] : $parts[0] );
			if ( '' === $url ) {
				continue;
			}
			$label    = ( 2 === count( $parts ) && '' !== $parts[0] ) ? $parts[0] : $url;
			$host     = wp_parse_url( $url, PHP_URL_HOST );
			$external = $host && $host !== $home;
			echo '<li><a href="' . $url . '"' . ( $external ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( $label ) . ( $external ? '<span class="dashicons dashicons-external" aria-hidden="true"></span>' : '' ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $url is escaped above.
		}
		echo '</ul>';
	}

	public static function w_note() {
		echo '<div class="cas-w-note">' . wp_kses_post( wpautop( (string) CAS::opt( 'w_note_body' ) ) ) . '</div>';
	}
}
