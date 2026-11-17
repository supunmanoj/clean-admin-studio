<?php
/**
 * Email: sender name and address, reply-to, return path, a test email,
 * a log of what the site sends, and fewer routine notification emails.
 *
 * Emails are never blocked or altered beyond their sender details, and a
 * problem with the log can never stop an email from going out.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Mail {

	const DB_OPT = 'cas_mail_db';
	const DB_VER = '1';

	/** Marker left where a password link was removed from a logged email. */
	const HIDDEN = '[hidden]';

	/** Log row of the email being sent right now. */
	private static $current = 0;

	/** Filled with the real sender details while a test email goes out. */
	private static $probe = null;

	public static function init() {
		add_filter( 'wp_mail_from', array( __CLASS__, 'from' ), 20 );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ), 20 );
		add_action( 'phpmailer_init', array( __CLASS__, 'mailer' ), 20 );
		add_filter( 'wp_mail', array( __CLASS__, 'record' ), 9999 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'failed' ) );
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'succeeded' ) );

		add_action( 'init', array( __CLASS__, 'quiet' ) );
		add_filter( 'wp_send_new_user_notification_to_admin', array( __CLASS__, 'new_user_mail' ) );
		add_filter( 'auto_plugin_update_send_email', array( __CLASS__, 'update_mail' ), 10, 2 );
		add_filter( 'auto_theme_update_send_email', array( __CLASS__, 'update_mail' ), 10, 2 );
		add_filter( 'auto_core_update_send_email', array( __CLASS__, 'core_mail' ), 10, 2 );
	}

	/** Only the wp-config.php switch pauses email settings; the ?cas_safe_mode link is about the admin screens. */
	private static function paused() {
		return defined( 'CAS_SAFE_MODE' ) && CAS_SAFE_MODE;
	}

	private static function on( $key ) {
		return CAS::opt( $key ) && ! self::paused();
	}

	/* ---------------------------------------------------------------------
	 * Sender
	 * ------------------------------------------------------------------ */

	/** The site's domain the way WordPress works it out for its own emails. */
	public static function domain() {
		$host = strtolower( (string) wp_parse_url( network_home_url(), PHP_URL_HOST ) );
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/** info@ the site's domain, or nothing where that is not a usable address (a local test site). */
	public static function default_email() {
		$email = 'info@' . self::domain();
		return is_email( $email ) ? $email : '';
	}

	public static function email() {
		$email = trim( (string) CAS::opt( 'mail_from_email' ) );
		return ( '' !== $email && is_email( $email ) ) ? $email : self::default_email();
	}

	public static function name() {
		$name = trim( (string) CAS::opt( 'mail_from_name' ) );
		return '' !== $name ? $name : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Replaces WordPress's own "wordpress@domain". An address another plugin
	 * chose on purpose is left alone unless "Use for every email" is on.
	 */
	public static function from( $from ) {
		if ( ! self::on( 'mail_from_on' ) ) {
			return $from;
		}
		$ours = self::email();
		if ( '' === $ours ) {
			return $from;
		}
		return ( CAS::opt( 'mail_force' ) || 0 === strcasecmp( (string) $from, 'wordpress@' . self::domain() ) ) ? $ours : $from;
	}

	public static function from_name( $name ) {
		if ( ! self::on( 'mail_from_on' ) ) {
			return $name;
		}
		$ours = self::name();
		if ( '' === $ours ) {
			return $name;
		}
		return ( CAS::opt( 'mail_force' ) || 'WordPress' === $name ) ? $ours : $name;
	}

	public static function mailer( $phpmailer ) {
		if ( ! is_object( $phpmailer ) ) {
			return;
		}
		if ( ! self::paused() ) {
			$reply = trim( (string) CAS::opt( 'mail_reply_to' ) );
			if ( '' !== $reply && is_email( $reply ) && method_exists( $phpmailer, 'getReplyToAddresses' ) && ! $phpmailer->getReplyToAddresses() ) {
				try {
					$phpmailer->addReplyTo( $reply );
				} catch ( Exception $e ) {
					unset( $e ); // An address the mailer rejects is simply not added.
				}
			}
			if ( CAS::opt( 'mail_return_path' ) && ! empty( $phpmailer->From ) ) {
				$phpmailer->Sender = $phpmailer->From; // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- PHPMailer's own property.
			}
		}
		if ( is_array( self::$probe ) ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName -- PHPMailer's own properties.
			self::$probe = array(
				'from'   => isset( $phpmailer->From ) ? (string) $phpmailer->From : '',
				'name'   => isset( $phpmailer->FromName ) ? (string) $phpmailer->FromName : '',
				'mailer' => isset( $phpmailer->Mailer ) ? (string) $phpmailer->Mailer : '',
			);
			// phpcs:enable
		}
	}

	/** Name of an active email delivery plugin, which may set the sender itself. */
	public static function delivery_plugin() {
		$known = array(
			'wp-mail-smtp'       => 'WP Mail SMTP',
			'wp-mail-smtp-pro'   => 'WP Mail SMTP Pro',
			'post-smtp'          => 'Post SMTP',
			'fluent-smtp'        => 'FluentSMTP',
			'easy-wp-smtp'       => 'Easy WP SMTP',
			'smtp-mailer'        => 'SMTP Mailer',
			'gmail-smtp'         => 'Gmail SMTP',
			'mailgun'            => 'Mailgun',
			'sendgrid-email-delivery-simplified' => 'SendGrid',
			'wp-ses'             => 'WP Offload SES',
			'mailin'             => 'Brevo',
			'sparkpost'          => 'SparkPost',
			'postmark-approved-wordpress-plugin' => 'Postmark',
		);
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
			$dir = dirname( (string) $plugin );
			if ( isset( $known[ $dir ] ) ) {
				return $known[ $dir ];
			}
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Test email
	 * ------------------------------------------------------------------ */

	/** Sends a test email and reports how it left: { ok, error, from, name, mailer }. */
	public static function test( $to ) {
		$error = '';
		$catch = function ( $failure ) use ( &$error ) {
			if ( is_wp_error( $failure ) ) {
				$error = $failure->get_error_message();
			}
		};
		self::$probe = array();
		add_action( 'wp_mail_failed', $catch );
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: %s: site name */
		$ok = wp_mail(
			$to,
			sprintf( __( 'Test email from %s', 'clean-admin-studio' ), $site ),
			/* translators: 1: site name, 2: site address, 3: date and time */
			sprintf( __( "This is a test email from %1\$s (%2\$s).\n\nIf you are reading it, the site can send email. Check who it says it is from: that is what your visitors and customers will see.\n\nSent: %3\$s", 'clean-admin-studio' ), $site, home_url( '/' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
		);
		remove_action( 'wp_mail_failed', $catch );
		$probe       = is_array( self::$probe ) ? self::$probe : array();
		self::$probe = null;
		return array(
			'ok'     => (bool) $ok,
			'error'  => $error,
			'from'   => isset( $probe['from'] ) ? $probe['from'] : '',
			'name'   => isset( $probe['name'] ) ? $probe['name'] : '',
			'mailer' => isset( $probe['mailer'] ) ? $probe['mailer'] : '',
		);
	}

	/* ---------------------------------------------------------------------
	 * Log
	 * ------------------------------------------------------------------ */

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cas_mail_log';
	}

	/** Creates the log table the first time it is needed. */
	private static function ready() {
		if ( self::DB_VER === get_option( self::DB_OPT ) ) {
			return true;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sent_at datetime NOT NULL,
			recipient text NOT NULL,
			subject text NOT NULL,
			body longtext NOT NULL,
			headers text NOT NULL,
			attachments text NOT NULL,
			status varchar(10) NOT NULL DEFAULT 'sent',
			error text NOT NULL,
			PRIMARY KEY  (id),
			KEY sent_at (sent_at)
			) " . $wpdb->get_charset_collate() . ';'
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- table name built from the prefix.
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return false;
		}
		update_option( self::DB_OPT, self::DB_VER );
		return true;
	}

	private static function flat( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Password and sign-in links must not sit readable in the database. */
	private static function redact( $body ) {
		$clean = preg_replace( '/([?&](?:amp;)?(?:key|rp_key|reset_key|login_key|token)=)[^&\s<>"\']+/i', '$1' . self::HIDDEN, (string) $body );
		return is_string( $clean ) ? $clean : (string) $body;
	}

	/** Notes every email as it is handed over for sending. Never changes it. */
	public static function record( $atts ) {
		self::$current = 0;
		if ( ! self::on( 'mail_log' ) || ! is_array( $atts ) ) {
			return $atts;
		}
		global $wpdb;
		$quiet = $wpdb->suppress_errors( true );
		if ( self::ready() ) {
			$body  = self::redact( self::flat( isset( $atts['message'] ) ? $atts['message'] : '' ) );
			$files = array();
			foreach ( (array) ( isset( $atts['attachments'] ) ? $atts['attachments'] : array() ) as $file ) {
				if ( is_string( $file ) && '' !== $file ) {
					$files[] = wp_basename( $file );
				}
			}
			$done = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table(),
				array(
					'sent_at'     => gmdate( 'Y-m-d H:i:s' ),
					'recipient'   => self::flat( isset( $atts['to'] ) ? $atts['to'] : '' ),
					'subject'     => self::flat( isset( $atts['subject'] ) ? $atts['subject'] : '' ),
					'body'        => strlen( $body ) > 200000 ? substr( $body, 0, 200000 ) . "\n[...]" : $body,
					'headers'     => self::redact( is_array( isset( $atts['headers'] ) ? $atts['headers'] : '' ) ? implode( "\n", array_map( 'strval', array_filter( $atts['headers'], 'is_scalar' ) ) ) : self::flat( isset( $atts['headers'] ) ? $atts['headers'] : '' ) ),
					'attachments' => implode( ', ', $files ),
					'status'      => 'sent',
					'error'       => '',
				)
			);
			if ( $done ) {
				self::$current = (int) $wpdb->insert_id;
				if ( 1 === wp_rand( 1, 25 ) ) {
					self::prune();
				}
			}
		}
		$wpdb->suppress_errors( $quiet );
		return $atts;
	}

	public static function failed( $failure ) {
		if ( ! self::$current ) {
			return;
		}
		global $wpdb;
		$quiet = $wpdb->suppress_errors( true );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status' => 'failed',
				'error'  => is_wp_error( $failure ) ? sanitize_text_field( $failure->get_error_message() ) : '',
			),
			array( 'id' => self::$current )
		);
		$wpdb->suppress_errors( $quiet );
		self::$current = 0;
	}

	public static function succeeded() {
		self::$current = 0;
	}

	/** Drops entries older than the chosen number of days, and anything beyond the newest 500. */
	private static function prune() {
		global $wpdb;
		$table = self::table();
		$days  = max( 1, (int) CAS::opt( 'mail_log_days' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- table name built from the prefix.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE sent_at < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
		$newest = (int) $wpdb->get_var( "SELECT MAX(id) FROM $table" );
		if ( $newest > 500 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id <= %d", $newest - 500 ) );
		}
		// phpcs:enable
	}

	/** Has the log ever been started on this site? */
	public static function exists() {
		return self::DB_VER === get_option( self::DB_OPT );
	}

	public static function rows( $limit = 50 ) {
		if ( ! self::exists() ) {
			return array();
		}
		global $wpdb;
		$table = self::table();
		$quiet = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- table name built from the prefix.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", max( 1, (int) $limit ) ) );
		$wpdb->suppress_errors( $quiet );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() {
		if ( ! self::exists() ) {
			return 0;
		}
		global $wpdb;
		$table = self::table();
		$quiet = $wpdb->suppress_errors( true );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$wpdb->suppress_errors( $quiet );
		return $count;
	}

	public static function clear() {
		if ( ! self::exists() ) {
			return;
		}
		global $wpdb;
		$table = self::table();
		$quiet = $wpdb->suppress_errors( true );
		$wpdb->query( "DELETE FROM $table" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$wpdb->suppress_errors( $quiet );
	}

	/**
	 * Can this logged email be sent again as it was? Not when a link was hidden
	 * in it, and not when it had attachments: only their names are kept.
	 */
	public static function can_resend( $row ) {
		return is_object( $row ) && '' !== trim( (string) $row->recipient ) && '' === trim( (string) $row->attachments ) && false === strpos( (string) $row->body . (string) $row->headers, self::HIDDEN );
	}

	/** A logged email as readable text: designed (HTML) emails are shown without their markup. */
	public static function readable( $body, $limit = 6000 ) {
		$body = (string) $body;
		if ( preg_match( '/<(?:html|body|table|div|p|br|a|h[1-6])\b/i', $body ) ) {
			$text = preg_replace( '/<(?:br|\/p|\/div|\/tr|\/h[1-6]|\/li)\b[^>]*>/i', "\n", $body );
			$text = html_entity_decode( wp_strip_all_tags( is_string( $text ) ? $text : $body ), ENT_QUOTES, 'UTF-8' );
			$text = preg_replace( array( '/[ \t]+/', '/\s*\n\s*\n\s*/' ), array( ' ', "\n\n" ), $text );
			$body = trim( is_string( $text ) ? $text : $body );
		}
		return strlen( $body ) > $limit ? substr( $body, 0, $limit ) . "\n[...]" : $body;
	}

	/** Sends a logged email again. Returns true, false, or null when it cannot be resent. */
	public static function resend( $id ) {
		if ( ! self::exists() ) {
			return null;
		}
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- table name built from the prefix.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
		if ( ! self::can_resend( $row ) ) {
			return null;
		}
		$to      = array_filter( array_map( 'trim', explode( ',', (string) $row->recipient ) ) );
		$headers = array_filter( array_map( 'trim', explode( "\n", (string) $row->headers ) ) );
		return (bool) wp_mail( $to, (string) $row->subject, (string) $row->body, $headers );
	}

	/** Removes the log completely (used when the plugin is deleted). */
	public static function drop() {
		global $wpdb;
		$table = $wpdb->prefix . 'cas_mail_log';
		$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.SchemaChange
		delete_option( self::DB_OPT );
	}

	/* ---------------------------------------------------------------------
	 * Fewer notification emails
	 * ------------------------------------------------------------------ */

	public static function quiet() {
		if ( self::on( 'mail_no_pass_admin' ) ) {
			remove_action( 'after_password_reset', 'wp_password_change_notification' );
		}
	}

	public static function new_user_mail( $send ) {
		return self::on( 'mail_no_user_admin' ) ? false : $send;
	}

	/** Automatic plugin and theme updates: stay quiet when every update worked. */
	public static function update_mail( $send, $results = array() ) {
		if ( ! $send || ! self::on( 'mail_no_update_ok' ) ) {
			return $send;
		}
		foreach ( (array) $results as $item ) {
			if ( ! is_object( $item ) || empty( $item->result ) || is_wp_error( $item->result ) ) {
				return $send; // Something failed: that email matters.
			}
		}
		return false;
	}

	public static function core_mail( $send, $type = '' ) {
		return ( 'success' === $type && self::on( 'mail_no_update_ok' ) ) ? false : $send;
	}
}
