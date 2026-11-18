<?php
/**
 * Content lists: word count, views (from Google Analytics), last updated and
 * thumbnail columns for every post type, a Duplicate link, and switching
 * comments off on the public site.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Content {

	const WORDS_META = '_cas_words';

	/** Views for the posts on the current list screen: post ID => number|null. */
	private static $views = null;

	/** Why views could not be fetched, for the column tooltip. */
	private static $views_error = '';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'columns' ), 99 );
		add_action( 'load-edit.php', array( __CLASS__, 'remember_period' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'period_picker' ), 99 );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort' ) );
		add_action( 'save_post', array( __CLASS__, 'store_words' ), 20, 2 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_filter( 'post_row_actions', array( __CLASS__, 'row_action' ), 20, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_action' ), 20, 2 );
		add_action( 'admin_post_cas_duplicate', array( __CLASS__, 'duplicate' ) );

		add_filter( 'comments_open', array( __CLASS__, 'closed' ), 20, 2 );
		add_filter( 'pings_open', array( __CLASS__, 'closed' ), 20, 2 );
		add_filter( 'comments_array', array( __CLASS__, 'no_comments' ), 20, 2 );
		add_filter( 'get_comments_number', array( __CLASS__, 'zero' ), 20, 2 );
		add_filter( 'comments_template', array( __CLASS__, 'blank_template' ), 20 );
		add_filter( 'feed_links_show_comments_feed', array( __CLASS__, 'comments_feed' ), 20 );
		add_filter( 'pre_render_block', array( __CLASS__, 'no_comment_blocks' ), 20, 2 );
	}

	/** Every post type people edit in the admin (posts, pages, products...). */
	public static function types() {
		$types = get_post_types( array( 'show_ui' => true ), 'names' );
		unset( $types['attachment'], $types['wp_block'], $types['wp_template'], $types['wp_template_part'], $types['wp_navigation'], $types['wp_global_styles'], $types['wp_font_family'], $types['wp_font_face'] );
		return array_values( array_filter( $types, 'is_post_type_viewable' ) );
	}

	private static function any_column() {
		return ! CAS::safe_mode() && ( CAS::opt( 'col_words' ) || CAS::opt( 'col_views' ) || CAS::opt( 'col_modified' ) || CAS::opt( 'col_thumb' ) );
	}

	private static function views_on() {
		return CAS::opt( 'col_views' ) && CAS_Widgets::ready();
	}

	/* ---------------------------------------------------------------------
	 * Columns
	 * ------------------------------------------------------------------ */

	public static function columns() {
		if ( ! self::any_column() && ! self::hide_comments_column() ) {
			return;
		}
		foreach ( self::types() as $type ) {
			add_filter( 'manage_' . $type . '_posts_columns', array( __CLASS__, 'add_columns' ), 99 );
			add_action( 'manage_' . $type . '_posts_custom_column', array( __CLASS__, 'cell' ), 10, 2 );
			add_filter( 'manage_edit-' . $type . '_sortable_columns', array( __CLASS__, 'sortable' ) );
			// Runs after everything else has added its columns to this list.
			add_filter( 'manage_edit-' . $type . '_columns', array( __CLASS__, 'dedupe' ), 9999 );
		}
	}

	public static function body_class( $classes ) {
		return self::any_column() ? $classes . ' cas-cols' : $classes;
	}

	/** With comments switched off for visitors, the Comments column is only noise. */
	private static function hide_comments_column() {
		return CAS::opt( 'comments_off' ) && ! CAS::safe_mode();
	}

	/** Keeps the Title column readable when a list has many columns. */
	public static function assets( $hook ) {
		if ( 'edit.php' === $hook && self::any_column() ) {
			wp_enqueue_script( 'cas-lists', CAS_URL . 'assets/js/lists.js', array(), CAS_VERSION, true );
			wp_localize_script(
				'cas-lists',
				'casLists',
				array(
					'aside' => __( 'no room on this screen', 'clean-admin-studio' ),
					'force' => __( 'There is not enough room for this column on this screen. Untick it and tick it again to show it anyway.', 'clean-admin-studio' ),
				)
			);
		}
	}

	public static function periods() {
		return array(
			'28'  => __( 'Last 28 days', 'clean-admin-studio' ),
			'90'  => __( 'Last 3 months', 'clean-admin-studio' ),
			'365' => __( 'Last 12 months', 'clean-admin-studio' ),
			'all' => __( 'All time', 'clean-admin-studio' ),
		);
	}

	/** The period this person last picked, or the site default. */
	public static function period() {
		$periods = self::periods();
		$mine    = (string) get_user_meta( get_current_user_id(), 'cas_views_period', true );
		return isset( $periods[ $mine ] ) ? $mine : (string) CAS::opt( 'col_views_period' );
	}

	/**
	 * The content type of the list being built. Read from the hook name, which
	 * is also right while a row is redrawn after Quick Edit.
	 */
	private static function list_type() {
		if ( preg_match( '/^manage_(?:edit-(.+)_columns|(.+)_posts_columns)$/', (string) current_filter(), $m ) ) {
			return '' !== $m[1] ? $m[1] : $m[2];
		}
		return isset( $GLOBALS['typenow'] ) ? (string) $GLOBALS['typenow'] : '';
	}

	public static function add_columns( $cols ) {
		$type = self::list_type();
		if ( self::hide_comments_column() && ! ( 'product' === $type && CAS::opt( 'comments_keep_reviews' ) ) ) {
			unset( $cols['comments'] );
		}
		if ( ! self::any_column() ) {
			return $cols;
		}
		$new = array();
		if ( CAS::opt( 'col_thumb' ) && ( '' === $type || post_type_supports( $type, 'thumbnail' ) ) ) {
			$new['cas_thumb'] = '<span class="dashicons dashicons-format-image" title="' . esc_attr__( 'Featured image', 'clean-admin-studio' ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Featured image', 'clean-admin-studio' ) . '</span>';
		}
		if ( CAS::opt( 'col_words' ) ) {
			$new['cas_words'] = __( 'Words', 'clean-admin-studio' );
		}
		if ( self::views_on() ) {
			$periods          = self::periods();
			$new['cas_views'] = '<span title="' . esc_attr( $periods[ self::period() ] ) . '">' . esc_html__( 'Views', 'clean-admin-studio' ) . '</span>';
		}
		if ( CAS::opt( 'col_modified' ) ) {
			$new['cas_modified'] = __( 'Updated', 'clean-admin-studio' );
		}

		// The thumbnail goes straight after the tick box, the rest before Date.
		$out = array();
		foreach ( $cols as $key => $label ) {
			if ( 'date' === $key ) {
				foreach ( $new as $k => $l ) {
					if ( 'cas_thumb' !== $k ) {
						$out[ $k ] = $l;
					}
				}
			}
			$out[ $key ] = $label;
			if ( 'cb' === $key && isset( $new['cas_thumb'] ) ) {
				$out['cas_thumb'] = $new['cas_thumb'];
			}
		}
		return $out + $new;
	}

	/**
	 * With one of our columns showing, a second column with the same information
	 * from the theme or another plugin (its own word count, a views counter...)
	 * only crowds the Title. It is taken off the list. Nothing is deleted, and a
	 * list that does not show our column keeps the other one.
	 */
	public static function dedupe( $cols ) {
		if ( ! is_array( $cols ) || ! CAS::opt( 'col_dedupe' ) || CAS::safe_mode() ) {
			return $cols;
		}
		$type  = self::list_type();
		$rules = array(
			'cas_words'    => array( '/word[_-]?counts?|(^|[_-])words$/i', array( 'word count', 'word counts', 'wordcount', 'words' ) ),
			'cas_views'    => array( '/(^|[_-])(post|page)?[_-]?views?([_-]count)?$|pageviews/i', array( 'views', 'post views', 'page views', 'pageviews', 'view count' ) ),
			'cas_modified' => array( '/(^|[_-])(last[_-]?)?(modified|updated)([_-]date)?$/i', array( 'modified', 'last modified', 'updated', 'last updated', 'modified date', 'date modified' ) ),
			'cas_thumb'    => array( '/(^|[_-])(thumb(nail)?s?|featured[_-]?image)$/i', array( 'thumbnail', 'thumb', 'featured image' ) ),
		);
		$core  = array( 'cb', 'title', 'author', 'date', 'categories', 'tags', 'comments', 'name', 'parent' );
		foreach ( $rules as $ours => $rule ) {
			if ( ! isset( $cols[ $ours ] ) ) {
				continue;
			}
			if ( 'cas_thumb' === $ours && ! in_array( $type, array( 'post', 'page' ), true ) ) {
				// A picture column that comes with a content type (a shop's product
				// picture, a directory's photo) belongs to its list and may not be
				// the featured image: where it is there, ours steps aside instead.
				$own = '/(^|[_-])(thumb(nail)?s?|featured[_-]?image|img|images?|photos?|pictures?)$/i';
				$say = array_merge( $rule[1], array( 'image', 'photo', 'picture' ) );
				foreach ( $cols as $key => $label ) {
					$text = strtolower( trim( wp_strip_all_tags( is_string( $label ) ? $label : '' ) ) );
					if ( 0 !== strpos( (string) $key, 'cas_' ) && ( preg_match( $own, (string) $key ) || in_array( $text, $say, true ) ) ) {
						unset( $cols['cas_thumb'] );
						break;
					}
				}
				continue;
			}
			foreach ( $cols as $key => $label ) {
				$key = (string) $key;
				if ( 0 === strpos( $key, 'cas_' ) || 0 === strpos( $key, 'taxonomy-' ) || in_array( $key, $core, true ) ) {
					continue;
				}
				$text = strtolower( trim( wp_strip_all_tags( is_string( $label ) ? $label : '' ) ) );
				if ( preg_match( $rule[0], $key ) || in_array( $text, $rule[1], true ) ) {
					unset( $cols[ $key ] );
				}
			}
		}
		return $cols;
	}

	public static function sortable( $cols ) {
		if ( CAS::opt( 'col_words' ) ) {
			$cols['cas_words'] = 'cas_words';
		}
		if ( CAS::opt( 'col_modified' ) ) {
			$cols['cas_modified'] = 'modified';
		}
		return $cols;
	}

	public static function cell( $column, $post_id ) {
		switch ( $column ) {
			case 'cas_words':
				echo esc_html( number_format_i18n( self::words( $post_id ) ) );
				break;
			case 'cas_views':
				$views = self::views( $post_id );
				if ( null === $views ) {
					echo '<span class="cas-dash"' . ( self::$views_error && current_user_can( 'manage_options' ) ? ' title="' . esc_attr( self::$views_error ) . '"' : '' ) . ' aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__( 'No data', 'clean-admin-studio' ) . '</span>';
				} else {
					echo esc_html( number_format_i18n( $views ) );
				}
				break;
			case 'cas_modified':
				$time = (int) get_post_modified_time( 'U', true, $post_id );
				if ( $time ) {
					/* translators: %s: time span such as "2 hours" */
					$text = ( time() - $time < WEEK_IN_SECONDS ) ? sprintf( __( '%s ago', 'clean-admin-studio' ), human_time_diff( $time, time() ) ) : get_the_modified_date( _x( 'j M Y', 'short date in the Updated column', 'clean-admin-studio' ), $post_id );
					echo '<span title="' . esc_attr( get_the_modified_date( '', $post_id ) . ' ' . get_the_modified_time( '', $post_id ) ) . '">' . esc_html( $text ) . '</span>';
				}
				break;
			case 'cas_thumb':
				$img = get_the_post_thumbnail( $post_id, array( 40, 40 ), array( 'loading' => 'lazy' ) );
				echo $img ? $img : '<span class="cas-nothumb" aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- core image tag.
				break;
		}
	}

	/* ---------------------------------------------------------------------
	 * Word count
	 * ------------------------------------------------------------------ */

	public static function count_words( $content ) {
		$text = wp_strip_all_tags( strip_shortcodes( (string) $content ), true );
		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'\x{2019}\-]*/u', $text );
	}

	private static function words( $post_id ) {
		$saved = get_post_meta( $post_id, self::WORDS_META, true );
		if ( '' !== $saved ) {
			return (int) $saved;
		}
		$post  = get_post( $post_id );
		$count = $post ? self::count_words( $post->post_content ) : 0;
		update_post_meta( $post_id, self::WORDS_META, $count );
		return $count;
	}

	public static function store_words( $post_id, $post ) {
		if ( ! CAS::opt( 'col_words' ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post ) {
			return;
		}
		update_post_meta( $post_id, self::WORDS_META, self::count_words( $post->post_content ) );
	}

	/** Sort the list by word count, counting any posts not yet counted first. */
	public static function sort( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'cas_words' !== $query->get( 'orderby' ) || ! CAS::opt( 'col_words' ) ) {
			return;
		}
		$type = $query->get( 'post_type' );
		$type = ( is_string( $type ) && '' !== $type ) ? $type : 'post';
		$todo = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'posts_per_page' => 300,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => self::WORDS_META,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $todo as $id ) {
			self::words( $id );
		}
		$query->set( 'meta_key', self::WORDS_META ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$query->set( 'orderby', 'meta_value_num' );
	}

	/* ---------------------------------------------------------------------
	 * Views from Google Analytics
	 * ------------------------------------------------------------------ */

	public static function remember_period() {
		// phpcs:disable WordPress.Security.NonceVerification -- a display preference, validated against a fixed list.
		if ( isset( $_GET['cas_views_period'] ) ) {
			$period  = sanitize_key( wp_unslash( $_GET['cas_views_period'] ) );
			$periods = self::periods();
			if ( isset( $periods[ $period ] ) ) {
				update_user_meta( get_current_user_id(), 'cas_views_period', $period );
			}
		}
		// phpcs:enable
	}

	public static function period_picker( $post_type ) {
		if ( CAS::safe_mode() || ! self::views_on() || ! in_array( $post_type, self::types(), true ) ) {
			return;
		}
		$current = self::period();
		echo '<label class="screen-reader-text" for="cas-views-period">' . esc_html__( 'Period for the Views column', 'clean-admin-studio' ) . '</label><select name="cas_views_period" id="cas-views-period">';
		foreach ( self::periods() as $value => $label ) {
			/* translators: %s: period such as "Last 28 days" */
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, (string) $value, false ) . '>' . esc_html( sprintf( __( 'Views: %s', 'clean-admin-studio' ), $label ) ) . '</option>';
		}
		echo '</select>';
	}

	private static function path( $post ) {
		if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return '';
		}
		$path = (string) wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH );
		return '' === $path ? '/' : $path;
	}

	private static function views( $post_id ) {
		if ( null === self::$views ) {
			self::$views = self::load_views();
		}
		return isset( self::$views[ $post_id ] ) ? self::$views[ $post_id ] : null;
	}

	/**
	 * One request to Google for all rows on screen that are not already
	 * remembered. Numbers are kept for six hours.
	 */
	private static function load_views() {
		global $wp_query;
		$out   = array();
		$paths = array();
		foreach ( ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) ? $wp_query->posts : array() as $post ) {
			// Page lists hand over ID-only rows, so always load the full post.
			$post = get_post( is_object( $post ) ? (int) $post->ID : (int) $post );
			$path = self::path( $post );
			if ( $post && '' !== $path ) {
				$paths[ $post->ID ] = $path;
			}
		}
		if ( ! $paths ) {
			return $out;
		}
		$period = self::period();
		$key    = 'cas_views_' . $period;
		$cache  = get_transient( $key );
		$cache  = is_array( $cache ) ? $cache : array();
		$now    = time();
		$need   = array();
		foreach ( $paths as $id => $path ) {
			if ( isset( $cache[ $path ] ) && $cache[ $path ][1] > $now - 6 * HOUR_IN_SECONDS ) {
				$out[ $id ] = (int) $cache[ $path ][0];
			} else {
				$need[ $path ] = true;
			}
		}
		if ( ! $need ) {
			return $out;
		}

		// Analytics may record a page with or without its closing slash.
		$ask = array();
		foreach ( array_keys( $need ) as $path ) {
			$ask[] = $path;
			$alt   = '/' === substr( $path, -1 ) ? untrailingslashit( $path ) : trailingslashit( $path );
			if ( '' !== $alt && $alt !== $path ) {
				$ask[] = $alt;
			}
		}
		$starts = array(
			'28'  => '27daysAgo',
			'90'  => '89daysAgo',
			'365' => '364daysAgo',
			'all' => '2015-08-14',
		);
		$error  = '';
		$reply  = CAS_Widgets::run_report(
			array(
				'dateRanges'      => array(
					array(
						'startDate' => $starts[ $period ],
						'endDate'   => 'today',
					),
				),
				'dimensions'      => array( array( 'name' => 'pagePath' ) ),
				'metrics'         => array( array( 'name' => 'screenPageViews' ) ),
				'dimensionFilter' => array(
					'filter' => array(
						'fieldName'    => 'pagePath',
						'inListFilter' => array( 'values' => array_values( array_unique( $ask ) ) ),
					),
				),
				'limit'           => 1000,
			),
			$error
		);
		if ( null === $reply ) {
			self::$views_error = $error;
			return $out;
		}
		$found = array();
		foreach ( isset( $reply['rows'] ) && is_array( $reply['rows'] ) ? $reply['rows'] : array() as $row ) {
			if ( isset( $row['dimensionValues'][0]['value'], $row['metricValues'][0]['value'] ) ) {
				$p           = (string) $row['dimensionValues'][0]['value'];
				$p           = '/' === $p ? '/' : untrailingslashit( $p );
				$found[ $p ] = ( isset( $found[ $p ] ) ? $found[ $p ] : 0 ) + (int) $row['metricValues'][0]['value'];
			}
		}
		foreach ( $paths as $id => $path ) {
			if ( isset( $need[ $path ] ) ) {
				$bare           = '/' === $path ? '/' : untrailingslashit( $path );
				$count          = isset( $found[ $bare ] ) ? $found[ $bare ] : 0;
				$out[ $id ]     = $count;
				$cache[ $path ] = array( $count, $now );
			}
		}
		if ( count( $cache ) > 1500 ) {
			$cache = array_slice( $cache, -1500, null, true );
		}
		set_transient( $key, $cache, DAY_IN_SECONDS );
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Duplicate
	 * ------------------------------------------------------------------ */

	public static function row_action( $actions, $post ) {
		if ( CAS::safe_mode() || ! CAS::opt( 'dup_link' ) || ! $post || 'trash' === $post->post_status ) {
			return $actions;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( $type->cap->create_posts ) ) {
			return $actions;
		}
		$url                      = wp_nonce_url( admin_url( 'admin-post.php?action=cas_duplicate&post=' . $post->ID ), 'cas_duplicate_' . $post->ID );
		/* translators: %s: post title */
		$actions['cas_duplicate'] = '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( __( 'Duplicate "%s" as a draft', 'clean-admin-studio' ), $post->post_title ) ) . '">' . esc_html__( 'Duplicate', 'clean-admin-studio' ) . '</a>';
		return $actions;
	}

	public static function duplicate() {
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$post = $id ? get_post( $id ) : null;
		check_admin_referer( 'cas_duplicate_' . $id );
		$type = $post ? get_post_type_object( $post->post_type ) : null;
		if ( ! $post || ! $type || ! CAS::opt( 'dup_link' ) || ! current_user_can( 'edit_post', $id ) || ! current_user_can( $type->cap->create_posts ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this item.', 'clean-admin-studio' ), '', array( 'response' => 403 ) );
		}
		$new_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => $post->post_type,
					/* translators: %s: original title */
					'post_title'     => sprintf( __( '%s (copy)', 'clean-admin-studio' ), $post->post_title ),
					'post_content'   => $post->post_content,
					'post_excerpt'   => $post->post_excerpt,
					'post_status'    => 'draft',
					'post_author'    => get_current_user_id(),
					'post_parent'    => $post->post_parent,
					'menu_order'     => $post->menu_order,
					'comment_status' => $post->comment_status,
					'ping_status'    => $post->ping_status,
					'post_password'  => $post->post_password,
				)
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $taxonomy );
			}
		}
		$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time', self::WORDS_META );
		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Comments off on the public site
	 * ------------------------------------------------------------------ */

	private static function comments_off( $post_id = 0 ) {
		if ( is_admin() || ! CAS::opt( 'comments_off' ) || ( defined( 'CAS_SAFE_MODE' ) && CAS_SAFE_MODE ) ) {
			return false;
		}
		// Shop reviews are comments too: keep them unless asked otherwise.
		if ( CAS::opt( 'comments_keep_reviews' ) && 'product' === get_post_type( $post_id ? $post_id : null ) ) {
			return false;
		}
		return true;
	}

	public static function closed( $open, $post_id = 0 ) {
		return self::comments_off( $post_id ) ? false : $open;
	}

	public static function no_comments( $comments, $post_id = 0 ) {
		return self::comments_off( $post_id ) ? array() : $comments;
	}

	public static function zero( $count, $post_id = 0 ) {
		return self::comments_off( $post_id ) ? 0 : $count;
	}

	public static function blank_template( $template ) {
		return self::comments_off() ? CAS_DIR . 'includes/blank.php' : $template;
	}

	/** Block themes print comments with blocks instead of a template. */
	public static function no_comment_blocks( $pre, $block ) {
		$names = array( 'core/comments', 'core/post-comments', 'core/post-comments-form', 'core/comments-title', 'core/post-comments-count', 'core/post-comments-link', 'core/latest-comments' );
		if ( isset( $block['blockName'] ) && in_array( $block['blockName'], $names, true ) && self::comments_off() ) {
			return '';
		}
		return $pre;
	}

	public static function comments_feed( $show ) {
		return ( ! is_admin() && CAS::opt( 'comments_off' ) ) ? false : $show;
	}
}
