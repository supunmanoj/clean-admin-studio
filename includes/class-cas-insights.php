<?php
/**
 * Dashboard insights: a row of tiles under the welcome area with visitor
 * numbers from Google Analytics and how many posts were updated and
 * published in the last 30 days.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Insights {

	const GA_CACHE      = 'cas_insights_ga';
	const CONTENT_CACHE = 'cas_insights_posts';

	public static function init() {
		add_action( 'wp_ajax_cas_insights', array( __CLASS__, 'ajax' ) );
		add_action( 'save_post', array( __CLASS__, 'forget' ) );
		add_action( 'deleted_post', array( __CLASS__, 'forget' ) );
	}

	public static function forget() {
		delete_transient( self::CONTENT_CACHE );
	}

	private static function on() {
		return CAS::opt( 'dash_insights' ) && ! CAS::safe_mode() && current_user_can( 'edit_posts' );
	}

	/**
	 * Printed straight under the welcome area. Numbers already on hand are
	 * shown at once; anything that needs a trip to Google is fetched after the
	 * page has loaded, so the dashboard never waits for it.
	 */
	public static function render() {
		if ( ! self::on() ) {
			return;
		}
		$ready = CAS_Widgets::ready();
		$cache = $ready ? get_transient( self::GA_CACHE ) : null;
		$have  = ! $ready || is_array( $cache );
		$stale = $ready && is_array( $cache ) && ( time() - (int) $cache['time'] > ( empty( $cache['error'] ) ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS ) );

		echo '<div class="cas-insights' . ( $have ? '' : ' is-loading' ) . '" id="cas-insights"' . ( $have ? '' : ' aria-busy="true"' ) . '>';
		echo $have ? self::html( is_array( $cache ) ? $cache : null ) : self::skeleton(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped where built.
		echo '</div>';

		if ( ! $have || $stale ) {
			$url = admin_url( 'admin-ajax.php?action=cas_insights&_wpnonce=' . wp_create_nonce( 'cas_insights' ) );
			$js  = '(function(){var b=document.getElementById("cas-insights");if(!b){return;}var done=function(h){if(h&&h.indexOf("cas-in-grid")>-1){b.innerHTML=h;}else if(b.className.indexOf("is-loading")>-1){b.hidden=true;}b.className="cas-insights";b.removeAttribute("aria-busy");};if(!window.fetch){done("");return;}fetch(' . wp_json_encode( $url ) . ',{credentials:"same-origin"}).then(function(r){return r.ok?r.text():"";}).then(done).catch(function(){done("");});})();';
			if ( function_exists( 'wp_print_inline_script_tag' ) ) {
				wp_print_inline_script_tag( $js );
			} else {
				echo '<script>' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed script; the address is JSON-encoded.
			}
		}
	}

	public static function ajax() {
		check_ajax_referer( 'cas_insights' );
		if ( ! self::on() ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		nocache_headers();
		echo self::html( CAS_Widgets::ready() ? self::ga( true ) : null ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped where built.
		wp_die();
	}

	/* ---------------------------------------------------------------------
	 * Numbers
	 * ------------------------------------------------------------------ */

	/** Visitor numbers for the last 28 days and the 28 before, kept for an hour. */
	private static function ga( $fresh = false ) {
		$cache = get_transient( self::GA_CACHE );
		if ( is_array( $cache ) && ! $fresh ) {
			return $cache;
		}
		$now   = array(
			'startDate' => '27daysAgo',
			'endDate'   => 'today',
		);
		$out   = array(
			'time'   => time(),
			'error'  => '',
			'series' => array(),
			'cur'    => array( 0, 0, 0, 0 ),
			'prev'   => array( 0, 0, 0, 0 ),
			'top'    => array(),
		);
		$error = '';

		$totals = CAS_Widgets::run_report(
			array(
				'dateRanges' => array(
					$now,
					array(
						'startDate' => '55daysAgo',
						'endDate'   => '28daysAgo',
					),
				),
				'metrics'    => array(
					array( 'name' => 'screenPageViews' ),
					array( 'name' => 'activeUsers' ),
					array( 'name' => 'sessions' ),
					array( 'name' => 'userEngagementDuration' ),
				),
			),
			$error
		);
		if ( null === $totals ) {
			$out['error'] = $error;
			// Keep the last good numbers on screen if there are any.
			if ( is_array( $cache ) && empty( $cache['error'] ) ) {
				$cache['time'] = time() - HOUR_IN_SECONDS + 5 * MINUTE_IN_SECONDS;
				set_transient( self::GA_CACHE, $cache, 12 * HOUR_IN_SECONDS );
				return $cache;
			}
			set_transient( self::GA_CACHE, $out, 12 * HOUR_IN_SECONDS );
			return $out;
		}
		foreach ( self::rows( $totals ) as $row ) {
			$slot = ( isset( $row['dimensionValues'][0]['value'] ) && 'date_range_1' === $row['dimensionValues'][0]['value'] ) ? 'prev' : 'cur';
			for ( $i = 0; $i < 4; $i++ ) {
				$out[ $slot ][ $i ] = isset( $row['metricValues'][ $i ]['value'] ) ? (int) round( (float) $row['metricValues'][ $i ]['value'] ) : 0;
			}
		}

		$daily = CAS_Widgets::run_report(
			array(
				'dateRanges' => array( $now ),
				'dimensions' => array( array( 'name' => 'date' ) ),
				'metrics'    => array( array( 'name' => 'screenPageViews' ) ),
				'orderBys'   => array( array( 'dimension' => array( 'dimensionName' => 'date' ) ) ),
				'limit'      => 60,
			),
			$error
		);
		$by    = array();
		foreach ( self::rows( $daily ) as $row ) {
			if ( isset( $row['dimensionValues'][0]['value'], $row['metricValues'][0]['value'] ) ) {
				$by[ (string) $row['dimensionValues'][0]['value'] ] = (int) $row['metricValues'][0]['value'];
			}
		}
		if ( $by ) {
			// Days without a visit are missing from the reply: fill them in.
			$last = strtotime( (string) max( array_keys( $by ) ) );
			for ( $i = 27; $i >= 0; $i-- ) {
				$day             = gmdate( 'Ymd', $last - $i * DAY_IN_SECONDS );
				$out['series'][] = isset( $by[ $day ] ) ? $by[ $day ] : 0;
			}
		}

		$pages = CAS_Widgets::run_report(
			array(
				'dateRanges' => array( $now ),
				'dimensions' => array( array( 'name' => 'pagePath' ) ),
				'metrics'    => array( array( 'name' => 'screenPageViews' ) ),
				'orderBys'   => array(
					array(
						'metric' => array( 'metricName' => 'screenPageViews' ),
						'desc'   => true,
					),
				),
				'limit'      => 3,
			),
			$error
		);
		foreach ( self::rows( $pages ) as $row ) {
			if ( isset( $row['dimensionValues'][0]['value'], $row['metricValues'][0]['value'] ) ) {
				$out['top'][] = array( sanitize_text_field( $row['dimensionValues'][0]['value'] ), (int) $row['metricValues'][0]['value'] );
			}
		}

		set_transient( self::GA_CACHE, $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}

	private static function rows( $reply ) {
		return ( is_array( $reply ) && isset( $reply['rows'] ) && is_array( $reply['rows'] ) ) ? $reply['rows'] : array();
	}

	/**
	 * Posts published and posts updated in the last 30 days, kept for an hour.
	 * A post that was first published in that time counts as published only.
	 */
	private static function content() {
		$cache = get_transient( self::CONTENT_CACHE );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- two counts, cached below.
		$out = array(
			'published' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_date_gmt >= %s", $since ) ),
			'updated'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_modified_gmt >= %s AND post_date_gmt < %s", $since, $since ) ),
		);
		// phpcs:enable
		set_transient( self::CONTENT_CACHE, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Output
	 * ------------------------------------------------------------------ */

	private static function html( $ga ) {
		$ready = CAS_Widgets::ready();
		if ( $ready && null === $ga ) {
			$ga = self::ga();
		}
		$good  = $ready && is_array( $ga ) && empty( $ga['error'] );
		$tiles = array();

		if ( $good ) {
			$tiles[] = self::tile(
				array(
					'class' => 'cas-in-views',
					'icon'  => 'visibility',
					'label' => __( 'Views', 'clean-admin-studio' ),
					'value' => number_format_i18n( $ga['cur'][0] ),
					'sub'   => self::delta( $ga['cur'][0], $ga['prev'][0] ) . esc_html__( 'last 28 days', 'clean-admin-studio' ),
					'extra' => self::spark( $ga['series'] ),
				)
			);
			$tiles[] = self::tile(
				array(
					'icon'  => 'groups',
					'label' => __( 'Visitors', 'clean-admin-studio' ),
					'value' => number_format_i18n( $ga['cur'][1] ),
					'sub'   => self::delta( $ga['cur'][1], $ga['prev'][1] ) . esc_html__( 'last 28 days', 'clean-admin-studio' ),
				)
			);
			$time    = $ga['cur'][1] ? $ga['cur'][3] / $ga['cur'][1] : 0;
			$before  = $ga['prev'][1] ? $ga['prev'][3] / $ga['prev'][1] : 0;
			$tiles[] = self::tile(
				array(
					'icon'  => 'clock',
					'label' => __( 'Time per visitor', 'clean-admin-studio' ),
					'value' => self::duration( $time ),
					'sub'   => self::delta( $time, $before ) . esc_html__( 'on average', 'clean-admin-studio' ),
				)
			);
			if ( $ga['top'] ) {
				$path    = (string) $ga['top'][0][0];
				$post_id = '/' === $path ? 0 : url_to_postid( home_url( $path ) );
				$title   = $post_id ? get_the_title( $post_id ) : ( '/' === $path ? __( 'Home page', 'clean-admin-studio' ) : $path );
				$tiles[] = self::tile(
					array(
						'class'  => 'cas-in-top-page',
						'icon'   => 'star-filled',
						'label'  => __( 'Most viewed', 'clean-admin-studio' ),
						'title'  => $title,
						/* translators: %s: number of views */
						'sub'    => esc_html( sprintf( _n( '%s view', '%s views', $ga['top'][0][1], 'clean-admin-studio' ), number_format_i18n( $ga['top'][0][1] ) ) ),
						'href'   => home_url( $path ),
						'target' => true,
					)
				);
			}
		} elseif ( ! $ready && current_user_can( 'manage_options' ) ) {
			$tiles[] = self::tile(
				array(
					'class' => 'cas-in-hint',
					'icon'  => 'chart-area',
					'label' => __( 'Views and visitors', 'clean-admin-studio' ),
					'title' => __( 'Connect Google Analytics to see them here', 'clean-admin-studio' ),
					'sub'   => esc_html__( 'Takes a minute', 'clean-admin-studio' ),
					'href'  => CAS::page_url( 'analytics' ),
				)
			);
		}

		$content = self::content();
		$tiles[] = self::tile(
			array(
				'icon'  => 'update',
				'label' => __( 'Posts updated', 'clean-admin-studio' ),
				'value' => number_format_i18n( $content['updated'] ),
				'sub'   => esc_html__( 'in the last 30 days', 'clean-admin-studio' ),
				'href'  => admin_url( 'edit.php?orderby=modified&order=desc' ),
			)
		);
		// On a phone the tiles sit two abreast under the full-width Views tile:
		// a last tile left on its own takes the whole row.
		$alone   = $good ? 0 === ( count( $tiles ) + 1 ) % 2 : 1 === ( count( $tiles ) + 1 ) % 2;
		$tiles[] = self::tile(
			array(
				'class' => $alone ? 'cas-in-wide-sm' : '',
				'icon'  => 'admin-post',
				'label' => __( 'Posts published', 'clean-admin-studio' ),
				'value' => number_format_i18n( $content['published'] ),
				'sub'   => esc_html__( 'in the last 30 days', 'clean-admin-studio' ),
				'href'  => admin_url( 'edit.php?post_status=publish&orderby=date&order=desc' ),
			)
		);

		$note = '';
		if ( $good ) {
			/* translators: %s: time span such as "12 mins" */
			$note = sprintf( __( 'Compared with the 28 days before. Updated %s ago.', 'clean-admin-studio' ), human_time_diff( (int) $ga['time'], time() ) );
		} elseif ( $ready && current_user_can( 'manage_options' ) && is_array( $ga ) && ! empty( $ga['error'] ) ) {
			$note = __( 'Visitor numbers could not be loaded:', 'clean-admin-studio' ) . ' ' . $ga['error'];
		}

		return '<div class="cas-in-head"><h3>' . esc_html__( 'Insights', 'clean-admin-studio' ) . '</h3>' . ( $note ? '<span>' . esc_html( $note ) . '</span>' : '' ) . '</div>'
			. '<div class="cas-in-grid cas-in-n' . count( $tiles ) . '">' . implode( '', $tiles ) . '</div>';
	}

	private static function tile( $t ) {
		$t    = wp_parse_args(
			$t,
			array(
				'class'  => '',
				'icon'   => 'chart-bar',
				'label'  => '',
				'value'  => '',
				'title'  => '',
				'sub'    => '',
				'extra'  => '',
				'href'   => '',
				'target' => false,
				'tip'    => '',
			)
		);
		$tag  = $t['href'] ? 'a' : 'div';
		$attr = $t['href'] ? ' href="' . esc_url( $t['href'] ) . '"' . ( $t['target'] ? ' target="_blank" rel="noopener"' : '' ) : '';
		$body = '<span class="cas-in-top"><span class="cas-in-ico dashicons dashicons-' . esc_attr( $t['icon'] ) . '" aria-hidden="true"></span><span class="cas-in-label">' . esc_html( $t['label'] ) . '</span></span>';
		if ( '' !== $t['value'] ) {
			$body .= '<strong class="cas-in-value"' . ( $t['tip'] ? ' title="' . esc_attr( $t['tip'] ) . '"' : '' ) . '>' . esc_html( $t['value'] ) . '</strong>';
		}
		if ( '' !== $t['title'] ) {
			$body .= '<strong class="cas-in-title">' . esc_html( $t['title'] ) . '</strong>';
		}
		if ( '' !== $t['sub'] ) {
			$body .= '<span class="cas-in-sub">' . $t['sub'] . '</span>';
		}
		return '<' . $tag . ' class="cas-in-tile ' . esc_attr( $t['class'] ) . '"' . $attr . '>' . $body . $t['extra'] . '</' . $tag . '>';
	}

	/** A small "+12%" or "-8%" badge against the period before. */
	private static function delta( $now, $before ) {
		if ( $before <= 0 ) {
			return '';
		}
		$pct   = (int) round( ( $now - $before ) / $before * 100 );
		$class = $pct > 0 ? 'up' : ( $pct < 0 ? 'down' : 'flat' );
		return '<span class="cas-in-delta cas-in-' . $class . '" title="' . esc_attr__( 'Compared with the 28 days before', 'clean-admin-studio' ) . '">' . ( $pct > 0 ? '+' : ( $pct < 0 ? '&minus;' : '' ) ) . esc_html( number_format_i18n( abs( $pct ) ) ) . '%</span> ';
	}

	private static function duration( $seconds ) {
		$seconds = (int) round( $seconds );
		if ( $seconds < 60 ) {
			/* translators: %d: seconds */
			return sprintf( __( '%ds', 'clean-admin-studio' ), $seconds );
		}
		/* translators: 1: minutes, 2: seconds */
		return sprintf( __( '%1$dm %2$02ds', 'clean-admin-studio' ), floor( $seconds / 60 ), $seconds % 60 );
	}

	private static function spark( $values ) {
		$n = count( $values );
		if ( $n < 2 ) {
			return '';
		}
		$max = max( 1, max( $values ) );
		$pts = array();
		foreach ( array_values( $values ) as $i => $v ) {
			$pts[] = round( $i / ( $n - 1 ) * 100, 2 ) . ',' . round( 26 - $v / $max * 22, 2 );
		}
		$line = implode( ' ', $pts );
		return '<svg class="cas-in-spark" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true" focusable="false"><polygon points="0,28 ' . $line . ' 100,28" fill="currentColor" opacity="0.12"></polygon><polyline points="' . $line . '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline></svg>';
	}

	private static function skeleton() {
		return '<div class="cas-in-head"><h3>' . esc_html__( 'Insights', 'clean-admin-studio' ) . '</h3><span>' . esc_html__( 'Loading the latest numbers', 'clean-admin-studio' ) . '</span></div>'
			. '<div class="cas-in-grid cas-in-n6">' . str_repeat( '<div class="cas-in-tile cas-in-skel"><span class="cas-in-bar"></span><span class="cas-in-bar"></span><span class="cas-in-bar"></span></div>', 6 ) . '</div>';
	}
}
