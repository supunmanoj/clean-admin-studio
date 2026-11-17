<?php
/**
 * Delivers plugin updates from GitHub through the normal WordPress update
 * system: Dashboard > Updates, the Plugins screen and WordPress auto-updates.
 *
 * To ship an update: bump "Version:" and CAS_VERSION in clean-admin-studio.php,
 * commit, then push a tag named after the version (for example v1.6.1) or
 * publish a GitHub release with that tag. A published release wins over a bare
 * tag, and a .zip attached to the release wins over GitHub's source zip.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Updater {

	const REPO  = 'supunmanoj/clean-admin-studio';
	const CACHE = 'cas_update_info';

	/** Update packages are only ever downloaded from these hosts. */
	const HOSTS = array( 'github.com', 'api.github.com', 'codeload.github.com', 'objects.githubusercontent.com' );

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'admin_post_cas_check_update', array( __CLASS__, 'check_now' ) );
	}

	private static function basename() {
		return plugin_basename( CAS_FILE );
	}

	private static function slug() {
		return dirname( self::basename() );
	}

	/**
	 * The version of the files on disk. Right after an update the running code
	 * is still the old version, so the constant cannot be trusted here.
	 */
	private static function installed() {
		$data = get_file_data( CAS_FILE, array( 'version' => 'Version' ) );
		return ( ! empty( $data['version'] ) ) ? (string) $data['version'] : CAS_VERSION;
	}

	private static function api( $path ) {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . $path,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Clean-Admin-Studio/' . CAS_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * A second way to find the newest release, for when GitHub's API says no.
	 * Its allowance of 60 questions an hour is shared by every website on the
	 * same server, so on shared hosting it is often used up by strangers. The
	 * public "latest release" address simply redirects to the newest release.
	 *
	 * @return array|null { version, package, url, changelog, published }
	 */
	private static function web() {
		$args     = array(
			'timeout'     => 10,
			'redirection' => 0,
			'headers'     => array( 'User-Agent' => 'Clean-Admin-Studio/' . CAS_VERSION ),
		);
		$response = wp_remote_head( 'https://github.com/' . self::REPO . '/releases/latest', $args );
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$goes = wp_remote_retrieve_header( $response, 'location' );
		$goes = is_array( $goes ) ? (string) reset( $goes ) : (string) $goes;
		if ( $code < 300 || $code > 399 || ! preg_match( '#^https://github\.com/' . preg_quote( self::REPO, '#' ) . '/releases/tag/([vV]?(\d+(?:\.\d+){1,3}))$#', $goes, $m ) ) {
			return null;
		}
		// The release must carry the ready-made plugin zip.
		$package = 'https://github.com/' . self::REPO . '/releases/download/' . rawurlencode( $m[1] ) . '/clean-admin-studio.zip';
		$check   = wp_remote_head( $package, $args );
		if ( is_wp_error( $check ) || ! in_array( (int) wp_remote_retrieve_response_code( $check ), array( 200, 301, 302 ), true ) ) {
			return null;
		}
		return array(
			'version'   => $m[2],
			'package'   => self::package( $package ),
			'url'       => esc_url_raw( $goes ),
			'changelog' => '',
			'published' => '',
		);
	}

	private static function package( $url ) {
		return in_array( wp_parse_url( (string) $url, PHP_URL_HOST ), self::HOSTS, true ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * The newest version on GitHub, remembered for 6 hours (1 hour after a
	 * failure) to stay well inside GitHub's limits.
	 *
	 * @return array|null { version, package, url, changelog, published, checked }
	 */
	public static function latest( $force = false ) {
		$cached = get_site_transient( self::CACHE );
		if ( ! $force && false !== $cached ) {
			return ( is_array( $cached ) && ! empty( $cached['version'] ) ) ? $cached : null;
		}

		$found = null;

		// A published release, if there is one.
		$release = self::api( '/releases/latest' );
		if ( $release && ! empty( $release['tag_name'] ) && empty( $release['draft'] ) && empty( $release['prerelease'] ) ) {
			$package = isset( $release['zipball_url'] ) ? $release['zipball_url'] : '';
			foreach ( isset( $release['assets'] ) ? (array) $release['assets'] : array() as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && '.zip' === substr( $asset['name'], -4 ) && '' !== self::package( $asset['browser_download_url'] ) ) {
					$package = $asset['browser_download_url'];
					break;
				}
			}
			$found = array(
				'version'   => ltrim( (string) $release['tag_name'], 'vV' ),
				'package'   => self::package( $package ),
				'url'       => esc_url_raw( isset( $release['html_url'] ) ? $release['html_url'] : 'https://github.com/' . self::REPO . '/releases' ),
				'changelog' => isset( $release['body'] ) ? (string) $release['body'] : '',
				'published' => isset( $release['published_at'] ) ? (string) $release['published_at'] : '',
			);
		}

		// Version tags: used when there is no release, or a tag is newer than it.
		$tags = self::api( '/tags?per_page=50' );
		foreach ( (array) $tags as $tag ) {
			$name = isset( $tag['name'] ) ? (string) $tag['name'] : '';
			if ( ! preg_match( '/^[vV]?(\d+(?:\.\d+){1,3})$/', $name, $m ) ) {
				continue;
			}
			if ( $found && version_compare( $m[1], $found['version'], '<=' ) ) {
				continue;
			}
			$found = array(
				'version'   => $m[1],
				'package'   => self::package( 'https://api.github.com/repos/' . self::REPO . '/zipball/refs/tags/' . rawurlencode( $name ) ),
				'url'       => esc_url_raw( 'https://github.com/' . self::REPO . '/commits/' . rawurlencode( $name ) ),
				'changelog' => '',
				'published' => '',
			);
		}

		// The API gave nothing (most often its hourly limit): ask the public page.
		if ( ! $found && null === $release && null === $tags ) {
			$found = self::web();
		}

		if ( ! $found || '' === $found['package'] || ! preg_match( '/^\d+(\.\d+){1,3}$/', $found['version'] ) ) {
			set_site_transient( self::CACHE, array( 'checked' => time() ), HOUR_IN_SECONDS );
			return null;
		}
		$found['checked'] = time();
		set_site_transient( self::CACHE, $found, 6 * HOUR_IN_SECONDS );
		return $found;
	}

	/** What is already known, without asking GitHub: { version?, checked } or null. */
	public static function known() {
		$cached = get_site_transient( self::CACHE );
		return is_array( $cached ) ? $cached : null;
	}

	private static function item( $version, $package = '' ) {
		return (object) array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => self::slug(),
			'plugin'       => self::basename(),
			'new_version'  => $version,
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $package,
			'requires'     => '5.8',
			'requires_php' => '7.4',
			'icons'        => array(),
			'banners'      => array(),
		);
	}

	/** Tells WordPress whether a newer version is available. */
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$latest = self::latest();
		$file   = self::basename();
		$have   = self::installed();
		if ( $latest && version_compare( $latest['version'], $have, '>' ) ) {
			$transient->response[ $file ] = self::item( $latest['version'], $latest['package'] );
			unset( $transient->no_update[ $file ] );
		} else {
			// Listed as up to date so WordPress offers the auto-update switch.
			$transient->no_update[ $file ] = self::item( $have );
			unset( $transient->response[ $file ] );
		}
		return $transient;
	}

	/** Content for the "View details" window. */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== self::slug() ) {
			return $result;
		}
		$latest    = self::latest();
		$changelog = ( $latest && '' !== trim( $latest['changelog'] ) )
			? wpautop( esc_html( $latest['changelog'] ) )
			: '<p>' . esc_html__( 'See the list of changes on GitHub.', 'clean-admin-studio' ) . '</p>';
		$changelog .= '<p><a href="' . esc_url( $latest ? $latest['url'] : 'https://github.com/' . self::REPO ) . '" target="_blank" rel="noopener">' . esc_html__( 'View on GitHub', 'clean-admin-studio' ) . '</a></p>';

		return (object) array(
			'name'          => 'Clean Admin Studio',
			'slug'          => self::slug(),
			'version'       => $latest ? $latest['version'] : CAS_VERSION,
			'author'        => '<a href="https://github.com/supunmanoj">Supun Manoj Kumara</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'last_updated'  => $latest ? $latest['published'] : '',
			'download_link' => $latest ? $latest['package'] : '',
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'White-label and simplify the WordPress admin for clients: menu editor, branding, colors and fonts, dashboard insights, content tools, notification center and login page designer.', 'clean-admin-studio' ) . '</p>',
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * GitHub zips unpack to folders such as "supunmanoj-clean-admin-studio-1a2b3c".
	 * Rename that to the installed folder so WordPress replaces the plugin in
	 * place and keeps it active.
	 */
	public static function folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::basename() ) {
			return $source;
		}
		// Never swap the plugin for something that is not this plugin.
		if ( ! is_file( trailingslashit( $source ) . basename( CAS_FILE ) ) ) {
			return new WP_Error( 'cas_update_package', __( 'Clean Admin Studio: the update package does not contain the plugin.', 'clean-admin-studio' ) );
		}
		$wanted = trailingslashit( $remote_source ) . self::slug() . '/';
		if ( trailingslashit( $source ) === $wanted ) {
			return $source;
		}
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $wanted ), true ) ) {
			return $wanted;
		}
		return new WP_Error( 'cas_update_folder', __( 'Clean Admin Studio: could not prepare the update folder.', 'clean-admin-studio' ) );
	}

	public static function after( $upgrader, $options ) {
		if ( isset( $options['type'], $options['plugins'] ) && 'plugin' === $options['type'] && in_array( self::basename(), (array) $options['plugins'], true ) ) {
			delete_site_transient( self::CACHE );
		}
	}

	public static function check_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cas_check_update' ), 'cas_check_update' );
	}

	public static function row_meta( $links, $file ) {
		if ( $file === self::basename() && current_user_can( 'update_plugins' ) ) {
			$links[] = '<a href="' . esc_url( self::check_url() ) . '">' . esc_html__( 'Check for updates', 'clean-admin-studio' ) . '</a>';
		}
		return $links;
	}

	/** Skips the memory and asks GitHub right now. */
	public static function check_now() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'clean-admin-studio' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cas_check_update' );
		self::latest( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		$back = wp_get_referer();
		wp_safe_redirect( ( $back && false !== strpos( $back, 'page=' . CAS_SLUG ) ) ? CAS::page_url( 'tools' ) : admin_url( 'plugins.php?plugin_status=all' ) );
		exit;
	}
}
