<?php
/**
 * Classic Editor: ready-made content boxes (Quick answer, FAQ, Tip...) that
 * take their look from the site's theme, plus a few editor tune-ups.
 * Everything here needs the Classic Editor plugin.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Editor {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_css' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'front_accent' ), 20 );
		add_filter( 'mce_external_plugins', array( __CLASS__, 'mce_plugin' ) );
		add_filter( 'mce_buttons', array( __CLASS__, 'mce_buttons' ), 20 );
		add_filter( 'mce_buttons_2', array( __CLASS__, 'mce_buttons_2' ), 20 );
		add_filter( 'mce_css', array( __CLASS__, 'mce_css' ) );
		add_filter( 'tiny_mce_before_init', array( __CLASS__, 'mce_init' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'labels' ) );
	}

	/** Is the Classic Editor plugin switched on? */
	public static function classic() {
		if ( class_exists( 'Classic_Editor' ) ) {
			return true;
		}
		$file = 'classic-editor/classic-editor.php';
		if ( in_array( $file, (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}
		return is_multisite() && array_key_exists( $file, (array) get_site_option( 'active_sitewide_plugins', array() ) );
	}

	private static function on( $key ) {
		return CAS::opt( $key ) && self::classic() && ! CAS::safe_mode();
	}

	/** The box types, in menu order: key => array( label, html ). */
	public static function boxes() {
		return array(
			'quick'     => array( __( 'Quick answer', 'clean-admin-studio' ), '<div class="cas-box cas-quick"><p>' . esc_html__( 'The short answer, in two or three sentences.', 'clean-admin-studio' ) . '</p></div>' ),
			'keypoints' => array( __( 'Key points', 'clean-admin-studio' ), '<div class="cas-box cas-keypoints"><p><strong>' . esc_html__( 'Key points', 'clean-admin-studio' ) . '</strong></p><ul><li>' . esc_html__( 'First point', 'clean-admin-studio' ) . '</li><li>' . esc_html__( 'Second point', 'clean-admin-studio' ) . '</li></ul></div>' ),
			'tip'       => array( __( 'Tip', 'clean-admin-studio' ), '<div class="cas-box cas-callout cas-tip"><p><strong>' . esc_html__( 'Tip:', 'clean-admin-studio' ) . '</strong> ' . esc_html__( 'your tip here.', 'clean-admin-studio' ) . '</p></div>' ),
			'info'      => array( __( 'Note', 'clean-admin-studio' ), '<div class="cas-box cas-callout cas-info"><p><strong>' . esc_html__( 'Note:', 'clean-admin-studio' ) . '</strong> ' . esc_html__( 'your note here.', 'clean-admin-studio' ) . '</p></div>' ),
			'warning'   => array( __( 'Warning', 'clean-admin-studio' ), '<div class="cas-box cas-callout cas-warning"><p><strong>' . esc_html__( 'Important:', 'clean-admin-studio' ) . '</strong> ' . esc_html__( 'your warning here.', 'clean-admin-studio' ) . '</p></div>' ),
			'faq'       => array( __( 'FAQ section', 'clean-admin-studio' ), '<div class="cas-box cas-faq"><h2>' . esc_html__( 'Frequently asked questions', 'clean-admin-studio' ) . '</h2><h3>' . esc_html__( 'First question?', 'clean-admin-studio' ) . '</h3><p>' . esc_html__( 'The answer.', 'clean-admin-studio' ) . '</p><h3>' . esc_html__( 'Second question?', 'clean-admin-studio' ) . '</h3><p>' . esc_html__( 'The answer.', 'clean-admin-studio' ) . '</p></div>' ),
			'checklist' => array( __( 'Checklist', 'clean-admin-studio' ), '<div class="cas-box cas-checklist"><p><strong>' . esc_html__( 'Checklist', 'clean-admin-studio' ) . '</strong></p><ul><li>' . esc_html__( 'First item', 'clean-admin-studio' ) . '</li><li>' . esc_html__( 'Second item', 'clean-admin-studio' ) . '</li></ul></div>' ),
			'steps'     => array( __( 'Numbered steps', 'clean-admin-studio' ), '<ol class="cas-steps"><li>' . esc_html__( 'First step', 'clean-admin-studio' ) . '</li><li>' . esc_html__( 'Second step', 'clean-admin-studio' ) . '</li></ol>' ),
			'button'    => array( __( 'Button', 'clean-admin-studio' ), '<p><a class="cas-button" href="#">' . esc_html__( 'Button text', 'clean-admin-studio' ) . '</a></p>' ),
			'sources'   => array( __( 'Sources line', 'clean-admin-studio' ), '<p class="cas-sources">' . esc_html__( 'Sources: name them here.', 'clean-admin-studio' ) . '</p>' ),
		);
	}

	/** Has a look of its own been chosen for the boxes (otherwise they follow the theme)? */
	public static function custom() {
		return 'custom' === CAS::opt( 'ed_style' );
	}

	/**
	 * Site-specific touches for the boxes, as CSS variables. While the boxes
	 * follow the theme nothing but the label is set here; a custom look adds
	 * only the properties that were actually chosen.
	 */
	public static function vars_css() {
		$css = '';
		if ( self::custom() ) {
			$css   .= '--cas-box-radius:' . (int) CAS::opt( 'ed_radius' ) . 'px;';
			$accent = (string) CAS::opt( 'ed_accent' );
			if ( '' !== $accent ) {
				$css .= '--cas-box-accent:' . $accent . ';--cas-box-on-accent:' . CAS::on( $accent ) . ';';
			}
			$bg = (string) CAS::opt( 'ed_bg' );
			if ( '' !== $bg ) {
				$css .= '--cas-box-bg:' . $bg . ';--cas-box-tint:0%;';
			}
			$text = (string) CAS::opt( 'ed_text' );
			if ( '' !== $text ) {
				$css .= '--cas-box-text:' . $text . ';';
			}
			$border = (string) CAS::opt( 'ed_border' );
			if ( '' !== $border ) {
				$css .= '--cas-box-border:' . $border . ';';
			}
		}
		// Kept free of double quotes, backslashes and angle brackets: the editor
		// receives this inside a double-quoted script string that WordPress does
		// not escape.
		$label = str_replace( "'", "\u{2019}", (string) CAS::opt( 'ed_quick_label' ) );
		$label = trim( (string) preg_replace( '/["\\\\<>\x00-\x1f]+/', ' ', $label ) );
		if ( '' !== $label ) {
			$css .= "--cas-quick-label:'" . $label . "';";
		}
		return ':root{' . $css . '}';
	}

	public static function css_url() {
		return CAS_URL . 'assets/css/content-blocks.css?ver=' . CAS_VERSION;
	}

	/* ---------------------------------------------------------------------
	 * Public site
	 * ------------------------------------------------------------------ */

	public static function front_css() {
		// Keep published boxes styled even if the editor plugin is later removed.
		if ( ! CAS::opt( 'ed_blocks' ) || ( defined( 'CAS_SAFE_MODE' ) && CAS_SAFE_MODE ) ) {
			return;
		}
		wp_enqueue_style( 'cas-content-blocks', CAS_URL . 'assets/css/content-blocks.css', array(), CAS_VERSION );
		wp_add_inline_style( 'cas-content-blocks', self::vars_css() );
	}

	/**
	 * With no accent colour chosen, the boxes take the theme's own link colour.
	 * Read in the browser, where the theme's styles are known, and only on
	 * pages that actually contain a box.
	 */
	public static function front_accent() {
		if ( ! CAS::opt( 'ed_blocks' ) || ( self::custom() && '' !== (string) CAS::opt( 'ed_accent' ) ) || ( defined( 'CAS_SAFE_MODE' ) && CAS_SAFE_MODE ) ) {
			return;
		}
		$js = "(function(){var b=document.querySelector('.cas-box,.cas-steps,.cas-button,.cas-sources');if(!b||!window.getComputedStyle){return;}var p=b.parentNode,a=document.createElement('a');a.href='#';a.style.cssText='position:absolute;visibility:hidden';p.appendChild(a);var c=getComputedStyle(a).color,t=getComputedStyle(p).color;p.removeChild(a);var m=/^rgba?\\(/.test(c)?c.match(/[\\d.]+/g):null;if(!m||m.length<3||c===t||(m.length>3&&parseFloat(m[3])<0.5)){return;}var l=(0.299*m[0]+0.587*m[1]+0.114*m[2])/255,s=document.documentElement.style;s.setProperty('--cas-box-accent',c);s.setProperty('--cas-box-on-accent',l>0.6?'#111111':'#ffffff');})();";
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $js, array( 'id' => 'cas-box-accent' ) );
		} else {
			echo '<script id="cas-box-accent">' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed script, no user input.
		}
	}

	/* ---------------------------------------------------------------------
	 * Editor
	 * ------------------------------------------------------------------ */

	public static function mce_plugin( $plugins ) {
		if ( self::on( 'ed_blocks' ) ) {
			$plugins['cas_blocks'] = CAS_URL . 'assets/js/editor-blocks.js?ver=' . CAS_VERSION;
		}
		return $plugins;
	}

	public static function mce_buttons( $buttons ) {
		if ( self::on( 'ed_blocks' ) && ! in_array( 'cas_blocks', $buttons, true ) ) {
			$buttons[] = 'cas_blocks';
		}
		return $buttons;
	}

	public static function mce_buttons_2( $buttons ) {
		if ( self::on( 'ed_buttons' ) ) {
			foreach ( array( 'fontsizeselect', 'backcolor', 'superscript', 'subscript' ) as $button ) {
				if ( ! in_array( $button, $buttons, true ) ) {
					$buttons[] = $button;
				}
			}
		}
		return $buttons;
	}

	public static function mce_css( $css ) {
		if ( self::on( 'ed_blocks' ) ) {
			$css = ( $css ? $css . ',' : '' ) . self::css_url();
		}
		return $css;
	}

	public static function mce_init( $init ) {
		if ( ! self::classic() || CAS::safe_mode() ) {
			return $init;
		}
		$style = '';
		if ( CAS::opt( 'ed_blocks' ) ) {
			$style .= self::vars_css();
		}
		if ( CAS::opt( 'ed_comfort' ) ) {
			$style .= 'body#tinymce.wp-editor{max-width:760px;margin:0 auto;padding:14px 18px;font-size:17px;line-height:1.7}';
		}
		if ( '' !== $style ) {
			$init['content_style'] = ( isset( $init['content_style'] ) ? $init['content_style'] . ' ' : '' ) . $style;
		}
		if ( CAS::opt( 'ed_toolbar' ) ) {
			$init['wordpress_adv_hidden'] = false;
		}
		if ( CAS::opt( 'ed_buttons' ) ) {
			$init['fontsize_formats'] = '13px 14px 16px 18px 20px 24px 28px 32px';
		}
		if ( CAS::opt( 'ed_paste_plain' ) ) {
			$init['paste_as_text'] = true;
		}
		return $init;
	}

	/** Hand the box list and labels to the editor button. */
	public static function labels( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! self::on( 'ed_blocks' ) ) {
			return;
		}
		$items = array();
		foreach ( self::boxes() as $key => $box ) {
			$items[] = array(
				'key'  => $key,
				'text' => $box[0],
				'html' => $box[1],
			);
		}
		$data = array(
			'title'  => __( 'Content boxes', 'clean-admin-studio' ),
			'below'  => __( 'New paragraph below this box', 'clean-admin-studio' ),
			'remove' => __( 'Remove this box (keep the text)', 'clean-admin-studio' ),
			'items'  => $items,
		);
		wp_register_script( 'cas-editor-data', false, array(), CAS_VERSION, false );
		wp_enqueue_script( 'cas-editor-data' );
		wp_add_inline_script( 'cas-editor-data', 'window.CAS_BLOCKS = ' . wp_json_encode( $data ) . ';' );
	}
}
