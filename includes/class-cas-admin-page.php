<?php
/**
 * The settings screen (Settings > plugin name) plus import, export and reset.
 */

defined( 'ABSPATH' ) || exit;

final class CAS_Admin_Page {

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CAS_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_post_cas_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_cas_import', array( __CLASS__, 'import' ) );
		add_action( 'admin_post_cas_reset', array( __CLASS__, 'reset' ) );
		add_action( 'admin_post_cas_mail_test', array( __CLASS__, 'mail_test' ) );
		add_action( 'admin_post_cas_mail_resend', array( __CLASS__, 'mail_resend' ) );
		add_action( 'admin_post_cas_mail_clear', array( __CLASS__, 'mail_clear' ) );
	}

	public static function menu() {
		$name       = CAS::opt( 'plugin_name' );
		self::$hook = add_options_page( $name, $name, 'manage_options', CAS_SLUG, array( __CLASS__, 'render' ) );
	}

	public static function register() {
		register_setting( 'cas_group', CAS::OPT, array( 'sanitize_callback' => array( 'CAS', 'sanitize' ) ) );
		register_setting( 'cas_group', CAS::MENU_OPT, array( 'sanitize_callback' => array( 'CAS_Menu', 'sanitize' ) ) );
		register_setting( 'cas_group', CAS_Widgets::KEY_OPT, array( 'sanitize_callback' => array( 'CAS_Widgets', 'sanitize_key' ) ) );
		register_setting( 'cas_group', CAS_Widgets::OAUTH_OPT, array( 'sanitize_callback' => array( 'CAS_Widgets', 'sanitize_oauth' ) ) );
		if ( false === get_option( CAS_Widgets::KEY_OPT ) ) {
			add_option( CAS_Widgets::KEY_OPT, '', '', 'no' );
		}
		if ( false === get_option( CAS_Widgets::OAUTH_OPT ) ) {
			add_option( CAS_Widgets::OAUTH_OPT, array(), '', 'no' );
		}
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( CAS::page_url() ) . '">' . esc_html__( 'Settings', 'clean-admin-studio' ) . '</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'cas-settings', CAS_URL . 'assets/css/settings.css', array(), CAS_VERSION );
		wp_enqueue_style( 'cas-content-blocks', CAS_URL . 'assets/css/content-blocks.css', array(), CAS_VERSION );
		wp_add_inline_style( 'cas-content-blocks', CAS_Editor::vars_css() );
		wp_enqueue_script( 'cas-settings', CAS_URL . 'assets/js/settings.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), CAS_VERSION, true );
		wp_localize_script(
			'cas-settings',
			'CAS_DATA',
			array(
				'chooseImage' => __( 'Choose image', 'clean-admin-studio' ),
				'useImage'    => __( 'Use this image', 'clean-admin-studio' ),
				'confirmReset' => __( 'Reset every setting to its default? This cannot be undone.', 'clean-admin-studio' ),
				'themeFound'  => __( 'Picked up from your theme:', 'clean-admin-studio' ),
				'themeFailed' => __( 'The theme colour could not be read. Choose one by hand.', 'clean-admin-studio' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Field helpers
	 * ------------------------------------------------------------------ */

	private static function name( $key ) {
		return CAS::OPT . '[' . $key . ']';
	}

	private static function row( $label, $control, $desc = '', $for = '' ) {
		echo '<div class="cas-row"><div class="cas-row-label">';
		echo $for ? '<label for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label>' : '<span>' . esc_html( $label ) . '</span>';
		if ( $desc ) {
			echo '<p class="cas-desc">' . wp_kses_post( $desc ) . '</p>';
		}
		echo '</div><div class="cas-row-control">' . $control . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
	}

	private static function toggle( $key, $label, $desc = '' ) {
		$id      = 'cas-' . $key;
		$control = '<label class="cas-switch" for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="1" ' . checked( CAS::opt( $key ), 1, false ) . ' /><span class="cas-slider" aria-hidden="true"></span></label>';
		self::row( $label, $control, $desc, $id );
	}

	private static function text( $key, $label, $desc = '', $placeholder = '', $type = 'text' ) {
		$id      = 'cas-' . $key;
		$control = '<input type="' . esc_attr( $type ) . '" class="cas-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( CAS::opt( $key ) ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		self::row( $label, $control, $desc, $id );
	}

	private static function number( $key, $label, $desc = '', $unit = 'px' ) {
		$schema  = CAS::schema();
		$id      = 'cas-' . $key;
		$control = '<span class="cas-number"><input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( CAS::opt( $key ) ) . '" min="' . esc_attr( $schema[ $key ][2] ) . '" max="' . esc_attr( $schema[ $key ][3] ) . '" step="1" /><em>' . esc_html( $unit ) . '</em></span>';
		self::row( $label, $control, $desc, $id );
	}

	private static function textarea( $key, $label, $desc = '', $code = false, $rows = 4 ) {
		$id      = 'cas-' . $key;
		$control = '<textarea class="cas-input' . ( $code ? ' cas-code' : '' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" rows="' . (int) $rows . '"' . ( $code ? ' spellcheck="false"' : '' ) . '>' . esc_textarea( CAS::opt( $key ) ) . '</textarea>';
		self::row( $label, $control, $desc, $id );
	}

	private static function color( $key, $label, $desc = '' ) {
		$schema  = CAS::schema();
		$id      = 'cas-' . $key;
		$control = '<input type="text" class="cas-color" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( CAS::opt( $key ) ) . '" data-default-color="' . esc_attr( $schema[ $key ][1] ) . '" />';
		self::row( $label, $control, $desc, $id );
	}

	private static function image( $key, $label, $desc = '' ) {
		$id      = 'cas-' . $key;
		$url     = CAS::opt( $key );
		$control = '<div class="cas-image"><span class="cas-image-preview">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="" />' : '' ) . '</span>'
			. '<input type="url" class="cas-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $url ) . '" placeholder="https://" />'
			. '<button type="button" class="button cas-image-pick">' . esc_html__( 'Choose', 'clean-admin-studio' ) . '</button>'
			. '<button type="button" class="button-link cas-image-clear">' . esc_html__( 'Remove', 'clean-admin-studio' ) . '</button></div>';
		self::row( $label, $control, $desc, $id );
	}

	private static function select( $key, $label, $choices, $desc = '' ) {
		$id      = 'cas-' . $key;
		$control = '<select class="cas-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '">';
		foreach ( $choices as $value => $text ) {
			$control .= '<option value="' . esc_attr( $value ) . '" ' . selected( CAS::opt( $key ), $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		$control .= '</select>';
		self::row( $label, $control, $desc, $id );
	}

	private static function card_open( $title, $intro = '' ) {
		echo '<section class="cas-card"><h2>' . esc_html( $title ) . '</h2>';
		if ( $intro ) {
			echo '<p class="cas-card-intro">' . wp_kses_post( $intro ) . '</p>';
		}
	}

	private static function card_close() {
		echo '</section>';
	}

	/* ---------------------------------------------------------------------
	 * Screen
	 * ------------------------------------------------------------------ */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = array(
			'start'     => array( 'admin-home', __( 'Start here', 'clean-admin-studio' ) ),
			'branding'  => array( 'awards', __( 'Branding', 'clean-admin-studio' ) ),
			'style'     => array( 'art', __( 'Colors & fonts', 'clean-admin-studio' ) ),
			'menu'      => array( 'menu-alt', __( 'Menu editor', 'clean-admin-studio' ) ),
			'toolbar'   => array( 'admin-generic', __( 'Toolbar & footer', 'clean-admin-studio' ) ),
			'dashboard' => array( 'dashboard', __( 'Dashboard', 'clean-admin-studio' ) ),
			'analytics' => array( 'chart-bar', __( 'Google Analytics', 'clean-admin-studio' ) ),
			'content'   => array( 'editor-table', __( 'Content & comments', 'clean-admin-studio' ) ),
			'editor'    => array( 'edit', __( 'Classic Editor', 'clean-admin-studio' ) ),
			'cleanup'   => array( 'hidden', __( 'Notices & clean-up', 'clean-admin-studio' ) ),
			'login'     => array( 'lock', __( 'Login page', 'clean-admin-studio' ) ),
			'email'     => array( 'email', __( 'Email', 'clean-admin-studio' ) ),
			'privacy'   => array( 'shield', __( 'Version & URLs', 'clean-admin-studio' ) ),
			'tools'     => array( 'admin-tools', __( 'Tools', 'clean-admin-studio' ) ),
		);
		?>
		<div class="wrap cas-wrap">
			<h1 class="screen-reader-text"><?php echo esc_html( CAS::opt( 'plugin_name' ) ); ?></h1>
			<hr class="wp-header-end" />
			<header class="cas-header">
				<div>
					<div class="cas-title"><?php echo esc_html( CAS::opt( 'plugin_name' ) ); ?></div>
					<div class="cas-sub"><?php esc_html_e( 'A cleaner, branded admin for your clients.', 'clean-admin-studio' ); ?></div>
				</div>
				<div class="cas-header-actions">
					<?php if ( CAS::safe_mode() ) : ?>
						<span class="cas-pill cas-pill-warn"><?php esc_html_e( 'Safe mode: customizations are paused', 'clean-admin-studio' ); ?></span>
					<?php endif; ?>
					<button type="submit" form="cas-form" class="button button-primary cas-save"><?php esc_html_e( 'Save changes', 'clean-admin-studio' ); ?></button>
				</div>
			</header>

			<div class="cas-layout">
				<nav class="cas-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'clean-admin-studio' ); ?>">
					<?php foreach ( $tabs as $id => $tab ) : ?>
						<a href="#<?php echo esc_attr( $id ); ?>" class="cas-tab" data-tab="<?php echo esc_attr( $id ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $tab[0] ); ?>"></span><?php echo esc_html( $tab[1] ); ?></a>
					<?php endforeach; ?>
				</nav>

				<div class="cas-content">
					<form method="post" action="options.php" id="cas-form">
						<?php
						settings_fields( 'cas_group' );
						foreach ( array( 'start', 'branding', 'style', 'menu', 'toolbar', 'dashboard', 'analytics', 'content', 'editor', 'cleanup', 'login', 'email', 'privacy' ) as $id ) {
							echo '<div class="cas-panel" id="cas-panel-' . esc_attr( $id ) . '" data-panel="' . esc_attr( $id ) . '">';
							call_user_func( array( __CLASS__, 'panel_' . $id ) );
							echo '</div>';
						}
						?>
					</form>
					<?php // The test email has its own small form; its field and button sit in the Email tab. ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="cas-mail-test"><input type="hidden" name="action" value="cas_mail_test" /><?php wp_nonce_field( 'cas_mail_test' ); ?></form>
					<div class="cas-panel" id="cas-panel-tools" data-panel="tools"><?php self::panel_tools(); ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private static function panel_start() {
		self::card_open(
			__( 'How it works', 'clean-admin-studio' ),
			__( 'Nothing in the admin changes until you switch it on. The <strong>Email</strong> tab is the exception: the site\'s own sender details and the email log are on from the start, and you can switch either off there. <strong>Branding and styling</strong> apply to everyone. <strong>Hiding</strong> (menu items, notices, help, screen options) applies to clients only, so you keep the full admin. To preview exactly what clients see, open the menu under your name (top right) and choose <strong>Client view</strong>.', 'clean-admin-studio' )
		);
		echo '<ul class="cas-steps">';
		$steps = array(
			'branding'  => __( 'Add your logo and brand name', 'clean-admin-studio' ),
			'style'     => __( 'Pick colors and a font', 'clean-admin-studio' ),
			'menu'      => __( 'Rename, reorder and hide menu items', 'clean-admin-studio' ),
			'dashboard' => __( 'Tidy the dashboard', 'clean-admin-studio' ),
			'analytics' => __( 'Connect Google Analytics', 'clean-admin-studio' ),
			'content'   => __( 'Add list columns, switch comments off', 'clean-admin-studio' ),
			'editor'    => __( 'Add content boxes to the editor', 'clean-admin-studio' ),
			'cleanup'   => __( 'Tuck notices away', 'clean-admin-studio' ),
			'login'     => __( 'Design the login page', 'clean-admin-studio' ),
			'email'     => __( 'Set who emails come from, test and log them', 'clean-admin-studio' ),
		);
		foreach ( $steps as $tab => $label ) {
			echo '<li><a href="#' . esc_attr( $tab ) . '" class="cas-goto" data-tab="' . esc_attr( $tab ) . '">' . esc_html( $label ) . '<span class="dashicons dashicons-arrow-right-alt2"></span></a></li>';
		}
		echo '</ul>';
		self::card_close();

		self::card_open( __( 'Who keeps the full admin', 'clean-admin-studio' ), __( 'Everyone else is treated as a client. If clients are administrators too, untick Administrator and tick your own account instead.', 'clean-admin-studio' ) );
		$control = '<div class="cas-checks">';
		foreach ( wp_roles()->roles as $slug => $role ) {
			$control .= '<label><input type="checkbox" name="' . esc_attr( self::name( 'exempt_roles' ) ) . '[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, (array) CAS::opt( 'exempt_roles' ), true ), true, false ) . ' /> ' . esc_html( translate_user_role( $role['name'] ) ) . '</label>';
		}
		$control .= '</div>';
		self::row( __( 'Roles', 'clean-admin-studio' ), $control );

		$control = '<div class="cas-checks">';
		$admins  = get_users(
			array(
				'role__in' => array( 'administrator' ),
				'number'   => 50,
				'fields'   => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		foreach ( $admins as $u ) {
			$control .= '<label><input type="checkbox" name="' . esc_attr( self::name( 'exempt_users' ) ) . '[]" value="' . (int) $u->ID . '" ' . checked( in_array( (int) $u->ID, array_map( 'intval', (array) CAS::opt( 'exempt_users' ) ), true ), true, false ) . ' /> ' . esc_html( $u->display_name ) . ' <span class="cas-muted">(' . esc_html( $u->user_login ) . ')</span>' . ( get_current_user_id() === (int) $u->ID ? ' <span class="cas-pill">' . esc_html__( 'you', 'clean-admin-studio' ) . '</span>' : '' ) . '</label>';
		}
		$control .= '</div>';
		self::row( __( 'Specific people', 'clean-admin-studio' ), $control, __( 'Always keep the full admin, whatever their role.', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'This plugin', 'clean-admin-studio' ) );
		self::text( 'plugin_name', __( 'Name shown in the admin', 'clean-admin-studio' ), __( 'Used in the Settings menu and the Plugins list.', 'clean-admin-studio' ) );
		self::toggle( 'hide_plugin', __( 'Hide from the Plugins list', 'clean-admin-studio' ), __( 'Clients will not see this plugin listed.', 'clean-admin-studio' ) );
		self::card_close();
	}

	private static function panel_branding() {
		self::card_open( __( 'Your brand', 'clean-admin-studio' ) );
		self::text( 'brand_name', __( 'Brand name', 'clean-admin-studio' ), __( 'Leave empty to use the site title.', 'clean-admin-studio' ), get_bloginfo( 'name' ) );
		self::text( 'brand_url', __( 'Brand link', 'clean-admin-studio' ), __( 'Where the logo links to. Defaults to the dashboard (admin) or the home page (login).', 'clean-admin-studio' ), 'https://', 'url' );
		self::image( 'logo_url', __( 'Logo', 'clean-admin-studio' ), __( 'Used on the login page and above the menu.', 'clean-admin-studio' ) );
		self::image( 'icon_url', __( 'Small icon', 'clean-admin-studio' ), __( 'Square image for the toolbar and the browser tab.', 'clean-admin-studio' ) );
		self::toggle( 'menu_logo', __( 'Show the logo above the menu', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'Wording', 'clean-admin-studio' ) );
		self::text( 'howdy', __( 'Replace "Howdy,"', 'clean-admin-studio' ), __( 'The greeting next to the user name in the toolbar. Leave empty to keep it.', 'clean-admin-studio' ), __( 'Welcome,', 'clean-admin-studio' ) );
		self::toggle( 'strip_wp_title', __( 'Remove "WordPress" from browser tab titles', 'clean-admin-studio' ) );
		self::card_close();
	}

	private static function panel_style() {
		self::card_open( __( 'Admin theme', 'clean-admin-studio' ) );
		self::toggle( 'style_enabled', __( 'Use custom colors and fonts', 'clean-admin-studio' ), __( 'Replaces the per-user color schemes.', 'clean-admin-studio' ) );
		self::toggle( 'modern_skin', __( 'Modern look', 'clean-admin-studio' ), __( 'Rounded cards, softer borders and roomier menus.', 'clean-admin-studio' ) );
		echo '<div class="cas-row"><div class="cas-row-label"><span>' . esc_html__( 'Quick presets', 'clean-admin-studio' ) . '</span><p class="cas-desc">' . esc_html__( 'Fills in the colors below. Save to apply.', 'clean-admin-studio' ) . '</p></div><div class="cas-row-control"><div class="cas-presets" id="cas-presets"></div></div></div>';
		self::card_close();

		self::card_open( __( 'Colors', 'clean-admin-studio' ), __( 'Hover, active and text-on-color shades are worked out for you so everything stays readable.', 'clean-admin-studio' ) );
		echo '<div class="cas-grid">';
		self::color( 'c_accent', __( 'Accent', 'clean-admin-studio' ), __( 'Buttons and the active menu item.', 'clean-admin-studio' ) );
		self::color( 'c_link', __( 'Links', 'clean-admin-studio' ) );
		self::color( 'c_menu_bg', __( 'Menu background', 'clean-admin-studio' ) );
		self::color( 'c_menu_text', __( 'Menu text', 'clean-admin-studio' ) );
		self::color( 'c_bar_bg', __( 'Toolbar background', 'clean-admin-studio' ) );
		self::color( 'c_bar_text', __( 'Toolbar text', 'clean-admin-studio' ) );
		self::color( 'c_body_bg', __( 'Page background', 'clean-admin-studio' ) );
		self::color( 'c_text', __( 'Text', 'clean-admin-studio' ) );
		self::color( 'c_heading', __( 'Headings', 'clean-admin-studio' ) );
		echo '</div>';
		self::card_close();

		self::card_open( __( 'Typography and icons', 'clean-admin-studio' ) );
		$fonts = array();
		foreach ( CAS::fonts() as $k => $f ) {
			$fonts[ $k ] = $f[0];
		}
		self::select( 'font_family', __( 'Font', 'clean-admin-studio' ), $fonts, __( 'Named fonts load from Google Fonts. Choose System default to avoid any outside request.', 'clean-admin-studio' ) );
		self::text( 'font_custom', __( 'Custom font stack', 'clean-admin-studio' ), __( 'Only used when Font is set to Custom, for fonts already on the site.', 'clean-admin-studio' ), '"Helvetica Neue", Arial' );
		self::number( 'font_size', __( 'Text size', 'clean-admin-studio' ) );
		self::number( 'menu_font_size', __( 'Menu text size', 'clean-admin-studio' ) );
		self::number( 'icon_size', __( 'Menu icon size', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'Custom CSS', 'clean-admin-studio' ) );
		self::textarea( 'custom_css', __( 'Admin CSS', 'clean-admin-studio' ), __( 'Added to every admin page.', 'clean-admin-studio' ), true, 7 );
		self::card_close();
	}

	private static function panel_menu() {
		$rows = CAS_Menu::editor_items();
		self::card_open(
			__( 'Menu editor', 'clean-admin-studio' ),
			__( 'Drag to reorder. Type a new name, click an icon to change it, or tick Hide. Hidden items disappear for clients only and their pages keep working, so nothing breaks. Leave a name empty to keep the original.', 'clean-admin-studio' )
		);
		echo '<input type="hidden" name="' . esc_attr( CAS::MENU_OPT ) . '" id="cas-menu-json" value="' . esc_attr( wp_json_encode( CAS_Menu::data() ) ) . '" />';
		echo '<p class="cas-menu-tools"><button type="button" class="button" id="cas-menu-expand">' . esc_html__( 'Expand all', 'clean-admin-studio' ) . '</button> <button type="button" class="button" id="cas-menu-clear">' . esc_html__( 'Clear all menu changes', 'clean-admin-studio' ) . '</button></p>';
		echo '<ul class="cas-menu-list" id="cas-menu-list">';
		foreach ( $rows as $row ) {
			if ( $row['sep'] ) {
				echo '<li class="cas-mi cas-mi-sep" data-key="' . esc_attr( $row['key'] ) . '"><div class="cas-mi-row"><span class="cas-handle dashicons dashicons-move" title="' . esc_attr__( 'Drag to reorder', 'clean-admin-studio' ) . '"></span><span class="cas-sep-line"></span><span class="cas-muted">' . esc_html__( 'Separator', 'clean-admin-studio' ) . '</span><span class="cas-sep-line"></span><label class="cas-hide"><input type="checkbox" class="cas-mi-hide" ' . checked( $row['hidden'], true, false ) . ' /> ' . esc_html__( 'Hide', 'clean-admin-studio' ) . '</label></div></li>';
				continue;
			}
			$icon    = $row['new_icon'] ? $row['new_icon'] : $row['icon'];
			$is_dash = 0 === strpos( $icon, 'dashicons-' );
			$is_url  = (bool) preg_match( '#^(https?:)?//|^data:image#i', $icon );
			echo '<li class="cas-mi' . ( $row['hidden'] ? ' is-hidden' : '' ) . '" data-key="' . esc_attr( $row['key'] ) . '"' . ( isset( $row['pkey'] ) ? ' data-pkey="' . esc_attr( $row['pkey'] ) . '"' : '' ) . '><div class="cas-mi-row">';
			echo '<span class="cas-handle dashicons dashicons-move" title="' . esc_attr__( 'Drag to reorder', 'clean-admin-studio' ) . '"></span>';
			echo '<button type="button" class="cas-icon-btn" data-icon="' . esc_attr( $row['new_icon'] ) . '" data-orig="' . esc_attr( $row['icon'] ) . '" title="' . esc_attr__( 'Change icon', 'clean-admin-studio' ) . '">';
			if ( $is_dash ) {
				echo '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
			} elseif ( $is_url ) {
				echo '<img src="' . esc_attr( $icon ) . '" alt="" />';
			} else {
				echo '<span class="dashicons dashicons-admin-generic cas-icon-fallback"></span>';
			}
			echo '</button>';
			echo '<input type="text" class="cas-mi-title" value="' . esc_attr( $row['custom'] ) . '" placeholder="' . esc_attr( $row['title'] ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: menu item */ __( 'New name for %s', 'clean-admin-studio' ), $row['title'] ) ) . '" />';
			if ( $row['subs'] ) {
				echo '<button type="button" class="button-link cas-mi-toggle" aria-expanded="false">' . esc_html( sprintf( /* translators: %d: number of submenu items */ _n( '%d sub-item', '%d sub-items', count( $row['subs'] ), 'clean-admin-studio' ), count( $row['subs'] ) ) ) . '<span class="dashicons dashicons-arrow-down-alt2"></span></button>';
			}
			echo '<label class="cas-hide"><input type="checkbox" class="cas-mi-hide" ' . checked( $row['hidden'], true, false ) . ' /> ' . esc_html__( 'Hide', 'clean-admin-studio' ) . '</label>';
			echo '</div>';
			if ( $row['subs'] ) {
				echo '<ul class="cas-sub-list">';
				foreach ( $row['subs'] as $sub ) {
					echo '<li class="cas-si' . ( $sub['hidden'] ? ' is-hidden' : '' ) . '" data-key="' . esc_attr( $sub['key'] ) . '"><span class="cas-handle dashicons dashicons-move"></span><input type="text" class="cas-si-title" value="' . esc_attr( $sub['custom'] ) . '" placeholder="' . esc_attr( $sub['title'] ) . '" aria-label="' . esc_attr( $sub['title'] ) . '" /><label class="cas-hide"><input type="checkbox" class="cas-si-hide" ' . checked( $sub['hidden'], true, false ) . ' /> ' . esc_html__( 'Hide', 'clean-admin-studio' ) . '</label></li>';
				}
				echo '</ul>';
			}
			echo '</li>';
		}
		echo '</ul>';
		if ( ! $rows ) {
			echo '<p class="cas-muted">' . esc_html__( 'The menu could not be read on this screen.', 'clean-admin-studio' ) . '</p>';
		}
		self::card_close();

		echo '<div class="cas-icon-picker" id="cas-icon-picker" hidden><div class="cas-ip-box" role="dialog" aria-label="' . esc_attr__( 'Choose an icon', 'clean-admin-studio' ) . '"><div class="cas-ip-head"><input type="search" id="cas-ip-search" placeholder="' . esc_attr__( 'Search icons', 'clean-admin-studio' ) . '" /><button type="button" class="button" id="cas-ip-reset">' . esc_html__( 'Use original', 'clean-admin-studio' ) . '</button><button type="button" class="button-link" id="cas-ip-close" aria-label="' . esc_attr__( 'Close', 'clean-admin-studio' ) . '"><span class="dashicons dashicons-no-alt"></span></button></div><div class="cas-ip-grid" id="cas-ip-grid"></div><div class="cas-ip-foot"><input type="url" id="cas-ip-url" placeholder="' . esc_attr__( 'Or paste an image URL (20x20 works best)', 'clean-admin-studio' ) . '" /><button type="button" class="button" id="cas-ip-use-url">' . esc_html__( 'Use image', 'clean-admin-studio' ) . '</button></div></div></div>';
	}

	private static function panel_toolbar() {
		self::card_open( __( 'Top toolbar', 'clean-admin-studio' ) );
		self::toggle( 'bar_brand', __( 'Show your brand in the toolbar', 'clean-admin-studio' ), __( 'Small icon and brand name at the left.', 'clean-admin-studio' ) );
		self::toggle( 'bar_hide_wp_logo', __( 'Remove the WordPress logo menu', 'clean-admin-studio' ) );
		self::toggle( 'bar_hide_updates', __( 'Remove the updates counter', 'clean-admin-studio' ) );
		self::toggle( 'bar_hide_comments', __( 'Remove the comments shortcut', 'clean-admin-studio' ) );
		self::toggle( 'bar_hide_new', __( 'Remove the "+ New" menu', 'clean-admin-studio' ) );
		self::toggle( 'bar_hide_customize', __( 'Remove "Customize" (front end)', 'clean-admin-studio' ) );
		self::toggle( 'bar_hide_search', __( 'Remove the search box (front end)', 'clean-admin-studio' ) );
		self::toggle( 'hide_front_bar', __( 'Hide the toolbar on the public site for clients', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'Footer', 'clean-admin-studio' ) );
		self::textarea( 'footer_text', __( 'Footer text', 'clean-admin-studio' ), __( 'Replaces "Thank you for creating with WordPress". Basic HTML allowed. Use {site} and {year}.', 'clean-admin-studio' ), false, 2 );
		self::toggle( 'hide_footer_version', __( 'Hide the version number in the footer', 'clean-admin-studio' ) );
		self::card_close();
	}

	private static function panel_dashboard() {
		self::card_open( __( 'Welcome area', 'clean-admin-studio' ), __( 'A clean header for the dashboard with a greeting, shortcuts and key numbers. Each person only sees what they are allowed to use.', 'clean-admin-studio' ) );
		self::toggle( 'dash_hero', __( 'Show the welcome area', 'clean-admin-studio' ) );
		self::text( 'dash_hero_title', __( 'Heading', 'clean-admin-studio' ), __( 'Use {name} for the person\'s first name and {site} for the site title.', 'clean-admin-studio' ) );
		self::textarea( 'dash_hero_text', __( 'Message', 'clean-admin-studio' ), __( 'Optional. Basic HTML allowed.', 'clean-admin-studio' ), false, 3 );
		self::toggle( 'dash_quick', __( 'Shortcut buttons', 'clean-admin-studio' ) );
		self::toggle( 'dash_stats', __( 'Content totals', 'clean-admin-studio' ), __( 'Posts, pages and comments, plus whatever your other plugins list in "At a Glance", such as forums and topics.', 'clean-admin-studio' ) );
		self::toggle( 'dash_insights', __( 'Insights row', 'clean-admin-studio' ), __( 'Under the welcome area: views, visitors, time per visitor and the most viewed page from Google Analytics, plus posts updated and posts published in the last 30 days.', 'clean-admin-studio' ) );
		self::text( 'support_label', __( 'Support button label', 'clean-admin-studio' ), '', __( 'Get support', 'clean-admin-studio' ) );
		self::text( 'support_url', __( 'Support link', 'clean-admin-studio' ), __( 'A web address or mailto: link. Leave empty for no button.', 'clean-admin-studio' ), 'https://' );
		self::card_close();

		self::card_open( __( 'Extra widgets', 'clean-admin-studio' ), __( 'Each person only sees the widgets they are allowed to use.', 'clean-admin-studio' ) );
		self::toggle( 'w_analytics', __( 'Visitors (Google Analytics)', 'clean-admin-studio' ), __( 'Visitor totals, a 28-day trend and the most viewed pages. Connect it in the Google Analytics tab.', 'clean-admin-studio' ) );
		self::toggle( 'w_recent', __( 'Recently edited', 'clean-admin-studio' ), __( 'The last six posts and pages that were changed, with links to carry on editing.', 'clean-admin-studio' ) );
		self::toggle( 'w_health', __( 'Site check', 'clean-admin-studio' ), __( 'Plain-language status: secure connection, search visibility, updates, comments and visitor tracking.', 'clean-admin-studio' ) );
		self::toggle( 'w_links', __( 'Quick links', 'clean-admin-studio' ) );
		self::text( 'w_links_title', __( 'Quick links title', 'clean-admin-studio' ) );
		self::textarea( 'w_links_items', __( 'Links', 'clean-admin-studio' ), __( 'One per line, as: Label | https://address', 'clean-admin-studio' ), false, 4 );
		self::toggle( 'w_note', __( 'Note to clients', 'clean-admin-studio' ) );
		self::text( 'w_note_title', __( 'Note title', 'clean-admin-studio' ) );
		self::textarea( 'w_note_body', __( 'Note', 'clean-admin-studio' ), __( 'For example how to reach you and what to do when something breaks. Basic HTML allowed.', 'clean-admin-studio' ), false, 4 );
		self::card_close();

		self::card_open( __( 'Dashboard widgets', 'clean-admin-studio' ), __( 'Tick the boxes you want to remove from the dashboard for everyone.', 'clean-admin-studio' ) );
		self::toggle( 'dash_hide_welcome', __( 'Remove the WordPress welcome panel', 'clean-admin-studio' ) );
		$widgets = get_option( 'cas_dash_widgets', array() );
		$hidden  = (array) CAS::opt( 'dash_hidden' );
		$control = '';
		if ( $widgets && is_array( $widgets ) ) {
			$control .= '<div class="cas-checks cas-checks-wide" id="cas-widgets">';
			foreach ( $widgets as $id => $title ) {
				$control .= '<label><input type="checkbox" name="' . esc_attr( self::name( 'dash_hidden' ) ) . '[]" value="' . esc_attr( $id ) . '" ' . checked( in_array( (string) $id, $hidden, true ), true, false ) . ' /> ' . esc_html( $title ? $title : $id ) . '</label>';
			}
			$control .= '</div><p><button type="button" class="button-link" id="cas-widgets-all">' . esc_html__( 'Tick all', 'clean-admin-studio' ) . '</button> &middot; <button type="button" class="button-link" id="cas-widgets-none">' . esc_html__( 'Untick all', 'clean-admin-studio' ) . '</button></p>';
		} else {
			$control .= '<p class="cas-muted">' . sprintf( /* translators: %s: dashboard link */ esc_html__( 'Open the %s once so the widgets on this site can be listed here.', 'clean-admin-studio' ), '<a href="' . esc_url( admin_url( 'index.php' ) ) . '">' . esc_html__( 'Dashboard', 'clean-admin-studio' ) . '</a>' ) . '</p>';
		}
		self::row( __( 'Remove these widgets', 'clean-admin-studio' ), $control, __( 'Widgets added by new plugins appear here after the next dashboard visit.', 'clean-admin-studio' ) );
		self::card_close();
	}

	private static function panel_analytics() {
		$ga = CAS_Widgets::status( true );
		self::card_open( __( 'Tracking code', 'clean-admin-studio' ), __( 'The plugin first looks at your public pages. If your theme or another plugin already adds a Google tag, nothing is added here. Only when none is found is your code added.', 'clean-admin-studio' ) );
		echo '<p class="cas-callout' . ( ( 'existing' === $ga['state'] || $ga['adding'] ) ? '' : ' cas-callout-warn' ) . '">' . esc_html( CAS_Widgets::status_text( $ga ) ) . ' <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cas_ga_check' ), 'cas_ga_check' ) ) . '">' . esc_html__( 'Check again', 'clean-admin-studio' ) . '</a></p>';
		self::text( 'ga_id', __( 'Measurement ID', 'clean-admin-studio' ), __( 'Looks like G-XXXXXXXXXX. In Google Analytics: Admin > Data streams.', 'clean-admin-studio' ), 'G-XXXXXXXXXX' );
		self::select(
			'ga_mode',
			__( 'When to add the code', 'clean-admin-studio' ),
			array(
				'auto'  => __( 'Only if the site has no Google tag (recommended)', 'clean-admin-studio' ),
				'force' => __( 'Always add it', 'clean-admin-studio' ),
				'off'   => __( 'Never add it', 'clean-admin-studio' ),
			)
		);
		self::toggle( 'ga_skip_staff', __( 'Do not count signed-in staff', 'clean-admin-studio' ), __( 'Visits by people who can edit the site are left out.', 'clean-admin-studio' ) );
		self::card_close();

		$oauth     = CAS_Widgets::oauth();
		$has_app   = CAS_Widgets::has_app();
		$connected = CAS_Widgets::connected();
		$property  = (string) CAS::opt( 'ga_property' );

		self::card_open( __( 'Visitor numbers', 'clean-admin-studio' ), __( 'Sign in with Google to show visitor numbers on the dashboard and the Views column on your content lists. The plugin can only read numbers; it cannot change anything in your Google account.', 'clean-admin-studio' ) );

		if ( $connected ) {
			echo '<p class="cas-callout cas-connected"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__( 'Connected', 'clean-admin-studio' ) . ( $oauth['email'] ? ' ' . esc_html__( 'as', 'clean-admin-studio' ) . ' <strong>' . esc_html( $oauth['email'] ) . '</strong>' : '' ) . ' &middot; <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cas_ga_disconnect' ), 'cas_ga_disconnect' ) ) . '">' . esc_html__( 'Disconnect', 'clean-admin-studio' ) . '</a></p>';
			$props = CAS_Widgets::properties();
			if ( $props ) {
				$control = '<select class="cas-input" id="cas-ga_property" name="' . esc_attr( self::name( 'ga_property' ) ) . '"><option value="">' . esc_html__( 'Choose a property', 'clean-admin-studio' ) . '</option>';
				if ( '' !== $property && ! isset( $props[ $property ] ) ) {
					/* translators: %s: property number */
					$props[ $property ] = sprintf( __( 'Property %s', 'clean-admin-studio' ), $property );
				}
				foreach ( $props as $id => $label ) {
					$control .= '<option value="' . esc_attr( $id ) . '" ' . selected( $property, (string) $id, false ) . '>' . esc_html( $label ) . '</option>';
				}
				$control .= '</select>';
				self::row( __( 'Show numbers for', 'clean-admin-studio' ), $control, __( 'The website (property) in your Google Analytics account.', 'clean-admin-studio' ), 'cas-ga_property' );
			} else {
				echo '<p class="cas-callout cas-callout-warn">' . esc_html__( 'Your properties could not be listed, so enter the Property ID by hand.', 'clean-admin-studio' ) . ( CAS_Widgets::$props_error ? ' ' . esc_html( CAS_Widgets::$props_error ) : '' ) . '</p>';
				self::text( 'ga_property', __( 'Property ID', 'clean-admin-studio' ), __( 'Numbers only. In Google Analytics: Admin > Property details.', 'clean-admin-studio' ), '123456789' );
			}
			if ( ! CAS::opt( 'w_analytics' ) && ! CAS::opt( 'col_views' ) && ! ( CAS::opt( 'dash_hero' ) && CAS::opt( 'dash_insights' ) ) ) {
				echo '<p class="cas-callout cas-callout-warn">' . esc_html__( 'Nothing is showing these numbers yet. Turn on the welcome area with its Insights row or the Visitors widget in the Dashboard tab, or the Views column in the Content tab.', 'clean-admin-studio' ) . '</p>';
			}
		} elseif ( $has_app ) {
			echo '<p class="cas-connect"><a class="button button-primary cas-connect-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cas_ga_connect' ), 'cas_ga_connect' ) ) . '"><span class="dashicons dashicons-google" aria-hidden="true"></span>' . esc_html__( 'Connect with Google', 'clean-admin-studio' ) . '</a><span class="cas-muted">' . esc_html__( 'You will choose your Google account, allow read-only access, and come straight back here.', 'clean-admin-studio' ) . '</span></p>';
		} else {
			echo '<p class="cas-callout cas-callout-warn">' . esc_html__( 'One-time setup: create a Google sign-in app (about five minutes) and paste its Client ID and Client secret below. After that, connecting is a single button.', 'clean-admin-studio' ) . '</p>';
		}

		if ( '' !== (string) CAS::opt( 'ga_relay' ) ) {
			// A connect service set up with an earlier version keeps working.
			echo '<input type="hidden" name="' . esc_attr( self::name( 'ga_relay' ) ) . '" value="' . esc_attr( CAS::opt( 'ga_relay' ) ) . '" />';
		}
		if ( ! $connected ) {
			// Keep the chosen property while the connection is being set up again.
			echo '<input type="hidden" name="' . esc_attr( self::name( 'ga_property' ) ) . '" value="' . esc_attr( $property ) . '" />';
		}

		// The sign-in app: open when it still has to be set up.
		echo '<details class="cas-details"' . ( $has_app ? '' : ' open' ) . '><summary>' . esc_html__( 'Connection setup (one time)', 'clean-admin-studio' ) . '</summary>';
		if ( ! empty( $oauth['fixed'] ) ) {
			echo '<p class="cas-muted">' . esc_html__( 'The sign-in app is set in wp-config.php for this site.', 'clean-admin-studio' ) . '</p>';
		} else {
			echo '<ol class="cas-howto">'
				. '<li>' . wp_kses_post( __( 'Open <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a> and create a project.', 'clean-admin-studio' ) ) . '</li>'
				. '<li>' . esc_html__( 'Under APIs & Services > Library, enable "Google Analytics Data API" and "Google Analytics Admin API".', 'clean-admin-studio' ) . '</li>'
				. '<li>' . esc_html__( 'Under OAuth consent screen, choose External, fill in the app name and your email, then publish the app ("In production").', 'clean-admin-studio' ) . '</li>'
				. '<li>' . esc_html__( 'Under Credentials, create an OAuth client ID of type "Web application" and add this as an Authorized redirect URI:', 'clean-admin-studio' ) . '<br /><code class="cas-copy">' . esc_html( CAS_Widgets::redirect_uri() ) . '</code></li>'
				. '<li>' . esc_html__( 'Paste the Client ID and Client secret below and save. The Connect with Google button then appears above.', 'clean-admin-studio' ) . '</li>'
				. '</ol>';
			$control = '<input type="text" class="cas-input cas-code" id="cas-ga-client-id" name="' . esc_attr( CAS_Widgets::OAUTH_OPT ) . '[client_id]" value="' . esc_attr( $oauth['client_id'] ) . '" placeholder="1234567890-abc.apps.googleusercontent.com" autocomplete="off" spellcheck="false" />';
			self::row( __( 'Client ID', 'clean-admin-studio' ), $control, '', 'cas-ga-client-id' );
			$control = '<input type="password" class="cas-input cas-code" id="cas-ga-client-secret" name="' . esc_attr( CAS_Widgets::OAUTH_OPT ) . '[client_secret]" value="" placeholder="' . ( '' !== $oauth['client_secret'] ? esc_attr__( 'Saved. Leave empty to keep it.', 'clean-admin-studio' ) : 'GOCSPX-...' ) . '" autocomplete="new-password" spellcheck="false" />';
			self::row( __( 'Client secret', 'clean-admin-studio' ), $control, __( 'Kept on this site only. It is never shown again and is left out of exports.', 'clean-admin-studio' ), 'cas-ga-client-secret' );
			echo '<p class="cas-muted">' . wp_kses_post( __( 'Looking after several sites? One Google app can serve all of them: add each site\'s redirect address to it, or put <code>CAS_GOOGLE_CLIENT_ID</code> and <code>CAS_GOOGLE_CLIENT_SECRET</code> in wp-config.php so nobody has to type them.', 'clean-admin-studio' ) ) . '</p>';
		}
		echo '</details>';

		// A service account key saved by an earlier version can still be removed here.
		$email = CAS_Widgets::key_email();
		if ( $email ) {
			echo '<details class="cas-details"><summary>' . esc_html__( 'Saved service account key', 'clean-admin-studio' ) . '</summary>';
			echo '<p class="cas-callout">' . esc_html__( 'A key is saved for', 'clean-admin-studio' ) . ' <code>' . esc_html( $email ) . '</code></p><p><label><input type="checkbox" name="cas_ga_key_remove" value="1" /> ' . esc_html__( 'Remove the saved key', 'clean-admin-studio' ) . '</label></p>';
			echo '</details>';
		}
		self::card_close();
	}

	private static function panel_content() {
		$ready = CAS_Widgets::ready();
		self::card_open( __( 'List columns', 'clean-admin-studio' ), __( 'Extra columns on the Posts, Pages, Products and every other content list. Each person can still hide any column with Screen Options.', 'clean-admin-studio' ) );
		self::toggle( 'col_words', __( 'Word count', 'clean-admin-studio' ), __( 'Click the column heading to sort by length.', 'clean-admin-studio' ) );
		self::toggle( 'col_views', __( 'Views (Google Analytics)', 'clean-admin-studio' ), __( 'How many times each page was viewed. A period picker appears above each list.', 'clean-admin-studio' ) );
		if ( ! $ready ) {
			echo '<p class="cas-callout cas-callout-warn">' . esc_html__( 'The Views column appears once Google Analytics is connected and a property is chosen.', 'clean-admin-studio' ) . ' <a href="#analytics">' . esc_html__( 'Open the Google Analytics tab', 'clean-admin-studio' ) . '</a></p>';
		}
		self::select( 'col_views_period', __( 'Usual period for views', 'clean-admin-studio' ), CAS_Content::periods(), __( 'Each person can switch period on the list itself.', 'clean-admin-studio' ) );
		self::toggle( 'col_modified', __( 'Last updated', 'clean-admin-studio' ), __( 'When each item was last changed. Sortable.', 'clean-admin-studio' ) );
		self::toggle( 'col_thumb', __( 'Featured image', 'clean-admin-studio' ), __( 'A small thumbnail at the start of each row.', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'Shortcuts', 'clean-admin-studio' ) );
		self::toggle( 'dup_link', __( 'Duplicate link', 'clean-admin-studio' ), __( 'Adds "Duplicate" under each title. The copy is saved as a draft with the same categories, tags and settings.', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'Comments', 'clean-admin-studio' ) );
		self::toggle( 'comments_off', __( 'Switch comments off on the public site', 'clean-admin-studio' ), __( 'Hides comment lists and comment forms on every post type and stops new comments. The Comments column also leaves the content lists. Nothing is deleted, and bbPress forums are not affected.', 'clean-admin-studio' ) );
		self::toggle( 'comments_keep_reviews', __( 'Keep product reviews', 'clean-admin-studio' ), __( 'Shop reviews are comments too. Leave this on to keep them.', 'clean-admin-studio' ) );
		self::card_close();
	}

	/** A typical article to read the theme's colours from: the newest post, else the home page. */
	private static function sample_url() {
		$posts = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'has_password'     => false,
				'suppress_filters' => false,
			)
		);
		$url   = $posts ? get_permalink( $posts[0] ) : '';
		return $url ? $url : home_url( '/' );
	}

	private static function panel_editor() {
		if ( ! CAS_Editor::classic() ) {
			echo '<p class="cas-callout cas-callout-warn"><strong>' . esc_html__( 'These options need the Classic Editor plugin.', 'clean-admin-studio' ) . '</strong> ' . esc_html__( 'It is not active on this site, so nothing on this tab does anything yet.', 'clean-admin-studio' );
			if ( current_user_can( 'install_plugins' ) ) {
				echo ' <a href="' . esc_url( admin_url( 'plugin-install.php?s=classic-editor&tab=search&type=term' ) ) . '">' . esc_html__( 'Install or activate Classic Editor', 'clean-admin-studio' ) . '</a>';
			}
			echo '</p>';
		}
		self::card_open( __( 'Content boxes', 'clean-admin-studio' ), __( 'Adds a "Content boxes" button to the editor toolbar. The boxes take their font and colours from your theme, so they look at home on any site.', 'clean-admin-studio' ) );
		self::toggle( 'ed_blocks', __( 'Content boxes', 'clean-admin-studio' ), __( 'Quick answer, Key points, Tip, Note, Warning, FAQ, Checklist, Numbered steps, Button and Sources.', 'clean-admin-studio' ) );
		self::select(
			'ed_style',
			__( 'Box style', 'clean-admin-studio' ),
			array(
				'theme'  => __( 'Match my theme (recommended)', 'clean-admin-studio' ),
				'custom' => __( 'Custom', 'clean-admin-studio' ),
			),
			__( 'Matching uses your theme\'s font, text colour and link colour, and keeps following the theme if it changes. Choose Custom only if the boxes should look different.', 'clean-admin-studio' )
		);
		echo '<div id="cas-box-custom"' . ( CAS_Editor::custom() ? '' : ' hidden' ) . '>';
		echo '<p class="cas-howto-title">' . esc_html__( 'Custom look. Anything left empty keeps following the theme.', 'clean-admin-studio' ) . '</p>';
		self::color( 'ed_accent', __( 'Accent colour', 'clean-admin-studio' ), __( 'Labels, markers, the Tip strip and the button.', 'clean-admin-studio' ) );
		echo '<div class="cas-row"><div class="cas-row-label"><span>' . esc_html__( 'Start from the theme colour', 'clean-admin-studio' ) . '</span><p class="cas-desc">' . esc_html__( 'Reads the link colour from your newest post and fills it in as the accent.', 'clean-admin-studio' ) . '</p></div><div class="cas-row-control"><button type="button" class="button" id="cas-match-theme" data-url="' . esc_url( self::sample_url() ) . '">' . esc_html__( 'Match my theme', 'clean-admin-studio' ) . '</button> <span class="cas-muted" id="cas-match-result" role="status"></span></div></div>';
		self::color( 'ed_bg', __( 'Box background', 'clean-admin-studio' ) );
		self::color( 'ed_text', __( 'Box text', 'clean-admin-studio' ) );
		self::color( 'ed_border', __( 'Box border', 'clean-admin-studio' ) );
		self::number( 'ed_radius', __( 'Corner roundness', 'clean-admin-studio' ) );
		echo '</div>';
		self::text( 'ed_quick_label', __( 'Quick answer label', 'clean-admin-studio' ), __( 'The small heading shown at the top of a Quick answer box.', 'clean-admin-studio' ) );
		echo '<div class="cas-preview"><p class="cas-preview-note">' . esc_html__( 'Preview. While matching the theme, the boxes use your theme\'s own font and colours on the site, so only the shapes are shown here.', 'clean-admin-studio' ) . '</p>';
		$boxes = CAS_Editor::boxes();
		foreach ( array( 'quick', 'tip', 'warning', 'faq', 'checklist', 'steps', 'button' ) as $key ) {
			echo wp_kses_post( $boxes[ $key ][1] );
		}
		echo '</div>';
		self::card_close();

		self::card_open( __( 'Editor tune-ups', 'clean-admin-studio' ) );
		self::toggle( 'ed_toolbar', __( 'Always show the full toolbar', 'clean-admin-studio' ), __( 'Opens the second row of buttons without clicking "Toolbar Toggle".', 'clean-admin-studio' ) );
		self::toggle( 'ed_buttons', __( 'Extra buttons', 'clean-admin-studio' ), __( 'Font size, highlight colour, superscript and subscript on the second row.', 'clean-admin-studio' ) );
		self::toggle( 'ed_paste_plain', __( 'Paste as plain text', 'clean-admin-studio' ), __( 'Removes formatting carried over from Word, Google Docs or other websites.', 'clean-admin-studio' ) );
		self::toggle( 'ed_comfort', __( 'Comfortable writing width', 'clean-admin-studio' ), __( 'Larger text in a centred column, closer to how the page reads.', 'clean-admin-studio' ) );
		self::card_close();
	}

	private static function panel_cleanup() {
		self::card_open( __( 'Notices', 'clean-admin-studio' ), __( '"Settings saved" style confirmations always stay where they are.', 'clean-admin-studio' ) );
		self::select(
			'notices',
			__( 'Plugin and system notices', 'clean-admin-studio' ),
			array(
				'show'    => __( 'Show as usual', 'clean-admin-studio' ),
				'collect' => __( 'Collect behind a bell in the toolbar (recommended)', 'clean-admin-studio' ),
				'hide'    => __( 'Hide from clients, collect for me', 'clean-admin-studio' ),
			),
			__( 'Collected notices stay one click away, so nothing important is lost.', 'clean-admin-studio' )
		);
		self::toggle( 'hide_update_nags', __( 'Hide "update available" nags from clients', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'Screen clutter', 'clean-admin-studio' ), __( 'These apply to clients only.', 'clean-admin-studio' ) );
		self::toggle( 'hide_help', __( 'Hide the Help tab', 'clean-admin-studio' ) );
		self::toggle( 'hide_screen_options', __( 'Hide Screen Options', 'clean-admin-studio' ) );
		self::toggle( 'disable_email_check', __( 'Skip the "confirm your admin email" screen', 'clean-admin-studio' ), __( 'WordPress shows it at login every few months.', 'clean-admin-studio' ) );
		self::card_close();
	}

	private static function panel_login() {
		self::card_open( __( 'Login page design', 'clean-admin-studio' ) );
		self::toggle( 'login_enabled', __( 'Use the custom login design', 'clean-admin-studio' ), '<a href="' . esc_url( wp_login_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the login page', 'clean-admin-studio' ) . '</a> ' . esc_html__( '(use a private window to see it while signed in). If your theme or another plugin already styles the login page, switch that off first so the two designs do not mix.', 'clean-admin-studio' ) );
		self::image( 'login_logo_url', __( 'Login logo', 'clean-admin-studio' ), __( 'Leave empty to use the brand logo, or the brand name as text.', 'clean-admin-studio' ) );
		self::number( 'login_logo_width', __( 'Logo width', 'clean-admin-studio' ) );
		self::image( 'login_bg_image', __( 'Background image', 'clean-admin-studio' ) );
		echo '<div class="cas-grid">';
		self::color( 'login_bg_color', __( 'Background', 'clean-admin-studio' ) );
		self::color( 'login_card_bg', __( 'Form background', 'clean-admin-studio' ) );
		self::color( 'login_text', __( 'Form text', 'clean-admin-studio' ) );
		self::color( 'login_btn', __( 'Button', 'clean-admin-studio' ) );
		echo '</div>';
		self::number( 'login_radius', __( 'Corner roundness', 'clean-admin-studio' ) );
		self::textarea( 'login_message', __( 'Message above the form', 'clean-admin-studio' ), __( 'Optional. Basic HTML allowed.', 'clean-admin-studio' ), false, 2 );
		self::card_close();

		self::card_open( __( 'Login page options', 'clean-admin-studio' ) );
		self::toggle( 'login_hide_back', __( 'Hide "Go to site" link', 'clean-admin-studio' ) );
		self::toggle( 'login_hide_lost', __( 'Hide "Lost your password?" and register links', 'clean-admin-studio' ) );
		self::toggle( 'login_hide_lang', __( 'Hide the language switcher', 'clean-admin-studio' ) );
		self::toggle( 'login_generic_error', __( 'Use one generic error message', 'clean-admin-studio' ), __( 'Does not reveal whether the username or the password was wrong.', 'clean-admin-studio' ) );
		self::textarea( 'login_custom_css', __( 'Login CSS', 'clean-admin-studio' ), '', true, 5 );
		self::card_close();
	}

	private static function panel_privacy() {
		self::card_open( __( 'WordPress version', 'clean-admin-studio' ) );
		self::toggle( 'remove_generator', __( 'Remove the version tag from pages and feeds', 'clean-admin-studio' ) );
		self::toggle( 'mask_asset_version', __( 'Mask the version in script and style addresses', 'clean-admin-studio' ), __( 'Replaces ?ver=x.y.z with a code, so caching keeps working.', 'clean-admin-studio' ) );
		self::toggle( 'clean_head', __( 'Remove legacy discovery links', 'clean-admin-studio' ), __( 'RSD, Windows Live Writer and shortlink tags in the page head.', 'clean-admin-studio' ) );
		self::card_close();

		$conflict = CAS_Login::slug_conflict();
		$slug     = CAS::opt( 'login_slug' );
		$intro    = __( 'Move the login page away from wp-login.php. The old address and /wp-admin (when signed out) then show "not found".', 'clean-admin-studio' );
		self::card_open( __( 'Login address', 'clean-admin-studio' ), $intro );
		if ( $conflict ) {
			echo '<p class="cas-callout cas-callout-warn">' . esc_html( $conflict ) . '</p>';
		}
		$control = '<span class="cas-slug"><code>' . esc_html( trailingslashit( home_url() ) ) . '</code><input type="text" class="cas-input" id="cas-login_slug" name="' . esc_attr( self::name( 'login_slug' ) ) . '" value="' . esc_attr( $slug ) . '" placeholder="' . esc_attr__( 'leave empty to keep wp-login.php', 'clean-admin-studio' ) . '" /></span>';
		self::row( __( 'Custom login address', 'clean-admin-studio' ), $control, '', 'cas-login_slug' );
		if ( $slug && ! $conflict ) {
			echo '<p class="cas-callout">' . esc_html__( 'Your login page is now:', 'clean-admin-studio' ) . ' <a href="' . esc_url( CAS_Login::login_url() ) . '"><strong>' . esc_html( CAS_Login::login_url() ) . '</strong></a></p>';
		}
		echo '<p class="cas-callout cas-callout-warn">' . wp_kses_post( __( '<strong>Write the new address down before saving.</strong> If you ever get locked out, add <code>define( \'CAS_SAFE_MODE\', true );</code> to wp-config.php and wp-login.php works again. Social-login callbacks and password-protected posts keep working.', 'clean-admin-studio' ) ) . '</p>';
		self::card_close();
	}

	private static function panel_email() {
		$domain  = CAS_Mail::domain();
		$default = CAS_Mail::default_email();
		$site    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		/* translators: %s: address such as wordpress@example.com */
		self::card_open( __( 'Sender', 'clean-admin-studio' ), sprintf( __( 'WordPress sends its emails as "WordPress" from %s. These settings replace that with your own name and address.', 'clean-admin-studio' ), '<code>wordpress@' . esc_html( $domain ) . '</code>' ) );
		$plugin = CAS_Mail::delivery_plugin();
		if ( $plugin ) {
			/* translators: %s: plugin name */
			echo '<p class="cas-callout cas-callout-warn">' . esc_html( sprintf( __( '%s is active on this site. If it has its own From name or From email set, those are used instead of the ones here.', 'clean-admin-studio' ), $plugin ) ) . '</p>';
		}
		if ( CAS_Mail::overridden() ) {
			$real = CAS_Mail::effective();
			/* translators: %s: sender such as "Site Name <info@example.com>" */
			echo '<p class="cas-callout cas-callout-warn">' . esc_html( sprintf( __( 'Other code on this site sets the sender after this plugin, so emails go out as %s. The name and address below are not used while it does.', 'clean-admin-studio' ), trim( $real['name'] . ' <' . $real['email'] . '>' ) ) ) . '</p>';
		}
		self::toggle( 'mail_from_on', __( 'Use my own sender details', 'clean-admin-studio' ) );
		/* translators: %s: site name */
		self::text( 'mail_from_name', __( 'From name', 'clean-admin-studio' ), sprintf( __( 'Leave empty to use the site name: %s', 'clean-admin-studio' ), '<strong>' . esc_html( $site ) . '</strong>' ), $site );
		self::text(
			'mail_from_email',
			__( 'From email', 'clean-admin-studio' ),
			/* translators: %s: email address */
			( $default ? sprintf( __( 'Leave empty to use %s.', 'clean-admin-studio' ), '<strong>' . esc_html( $default ) . '</strong>' ) . ' ' : '' ) . __( 'Use an address on this site\'s own domain, or emails are more likely to land in spam.', 'clean-admin-studio' ),
			$default,
			'email'
		);
		self::toggle( 'mail_force', __( 'Use for every email', 'clean-admin-studio' ), __( 'Also replaces sender details that other plugins set on purpose, such as form notifications. When off, only the WordPress default is replaced.', 'clean-admin-studio' ) );
		self::text( 'mail_reply_to', __( 'Reply-to address', 'clean-admin-studio' ), __( 'Where replies go when an email does not name its own. Leave empty for none.', 'clean-admin-studio' ), '', 'email' );
		self::toggle( 'mail_return_path', __( 'Send bounces to the From email', 'clean-admin-studio' ), __( 'Sets the return path to match the From email. This helps emails pass spam checks on many hosts.', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'Send a test email', 'clean-admin-studio' ), __( 'Sends a short message using the saved settings, so save any changes first.', 'clean-admin-studio' ) );
		$user = wp_get_current_user();
		echo '<div class="cas-row"><div class="cas-row-label"><label for="cas-mail-to">' . esc_html__( 'Send to', 'clean-admin-studio' ) . '</label></div><div class="cas-row-control cas-inline"><input type="email" class="cas-input" id="cas-mail-to" name="to" form="cas-mail-test" value="' . esc_attr( $user->user_email ) . '" required /> <button type="submit" class="button" form="cas-mail-test">' . esc_html__( 'Send test email', 'clean-admin-studio' ) . '</button></div></div>';
		self::card_close();

		self::card_open( __( 'Fewer notification emails', 'clean-admin-studio' ), __( 'Routine emails WordPress sends to the site administrator. Emails to your visitors and customers are not affected.', 'clean-admin-studio' ) );
		self::toggle( 'mail_no_user_admin', __( 'No email when someone registers', 'clean-admin-studio' ), __( 'The new person still gets their own welcome email.', 'clean-admin-studio' ) );
		self::toggle( 'mail_no_pass_admin', __( 'No email when someone resets their password', 'clean-admin-studio' ) );
		self::toggle( 'mail_no_update_ok', __( 'No email after a successful automatic update', 'clean-admin-studio' ), __( 'You are still told when an automatic update fails.', 'clean-admin-studio' ) );
		self::card_close();

		self::card_open( __( 'Email log', 'clean-admin-studio' ), __( 'A record of every email the site sends, so you can check that something went out and send it again. "Sent" means it was handed to the server\'s mail system; it cannot confirm it reached the inbox. Password links are hidden in the log.', 'clean-admin-studio' ) );
		self::toggle( 'mail_log', __( 'Keep a log of emails', 'clean-admin-studio' ) );
		self::number( 'mail_log_days', __( 'Keep entries for', 'clean-admin-studio' ), __( 'Older entries are removed on their own. At most the newest 500 are kept.', 'clean-admin-studio' ), __( 'days', 'clean-admin-studio' ) );

		$rows  = CAS_Mail::rows( 50 );
		$total = CAS_Mail::count();
		if ( ! $rows ) {
			echo '<p class="cas-muted">' . esc_html__( 'No emails have been logged yet.', 'clean-admin-studio' ) . '</p>';
		} else {
			echo '<p class="cas-log-head"><span class="cas-muted">';
			/* translators: 1: number shown, 2: total number */
			echo esc_html( $total > count( $rows ) ? sprintf( __( 'Showing the newest %1$s of %2$s emails.', 'clean-admin-studio' ), number_format_i18n( count( $rows ) ), number_format_i18n( $total ) ) : sprintf( _n( '%s email', '%s emails', $total, 'clean-admin-studio' ), number_format_i18n( $total ) ) );
			echo '</span> <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cas_mail_clear' ), 'cas_mail_clear' ) ) . '">' . esc_html__( 'Clear the log', 'clean-admin-studio' ) . '</a></p>';
			echo '<div class="cas-log-wrap"><table class="cas-log"><thead><tr><th scope="col">' . esc_html__( 'When', 'clean-admin-studio' ) . '</th><th scope="col">' . esc_html__( 'To', 'clean-admin-studio' ) . '</th><th scope="col">' . esc_html__( 'Subject', 'clean-admin-studio' ) . '</th><th scope="col">' . esc_html__( 'Status', 'clean-admin-studio' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'clean-admin-studio' ) . '</span></th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$time   = strtotime( $row->sent_at . ' UTC' );
				$failed = 'failed' === $row->status;
				$body   = CAS_Mail::readable( $row->body );
				echo '<tr><td class="cas-log-when" title="' . esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) ) . '">';
				/* translators: %s: time span such as "2 hours" */
				echo esc_html( ( time() - $time < DAY_IN_SECONDS ) ? sprintf( __( '%s ago', 'clean-admin-studio' ), human_time_diff( $time, time() ) ) : wp_date( _x( 'j M, H:i', 'short date and time in the email log', 'clean-admin-studio' ), $time ) );
				echo '</td><td class="cas-log-to">' . esc_html( $row->recipient ) . '</td><td class="cas-log-subject"><details><summary>' . esc_html( '' !== trim( (string) $row->subject ) ? $row->subject : __( '(no subject)', 'clean-admin-studio' ) ) . '</summary>';
				if ( $failed && '' !== (string) $row->error ) {
					echo '<p class="cas-log-error">' . esc_html( $row->error ) . '</p>';
				}
				if ( '' !== (string) $row->attachments ) {
					/* translators: %s: file names */
					echo '<p class="cas-muted">' . esc_html( sprintf( __( 'Attachments: %s', 'clean-admin-studio' ), $row->attachments ) ) . '</p>';
				}
				echo '<pre class="cas-log-body">' . esc_html( $body ) . '</pre></details></td>';
				echo '<td><span class="cas-pill ' . ( $failed ? 'cas-pill-warn' : 'cas-pill-ok' ) . '">' . esc_html( $failed ? __( 'Failed', 'clean-admin-studio' ) : __( 'Sent', 'clean-admin-studio' ) ) . '</span></td><td class="cas-log-act">';
				if ( CAS_Mail::can_resend( $row ) ) {
					echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cas_mail_resend&id=' . (int) $row->id ), 'cas_mail_resend' ) ) . '">' . esc_html__( 'Send again', 'clean-admin-studio' ) . '</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		self::card_close();
	}

	private static function panel_tools() {
		if ( current_user_can( 'update_plugins' ) ) {
			$known  = CAS_Updater::known();
			$latest = ( $known && ! empty( $known['version'] ) ) ? (string) $known['version'] : '';
			self::card_open( __( 'Updates', 'clean-admin-studio' ), __( 'New versions arrive through the normal WordPress Updates screen. To have them installed for you, switch on "Enable auto-updates" for this plugin on the Plugins screen.', 'clean-admin-studio' ) );
			/* translators: %s: version number */
			echo '<p>' . esc_html( sprintf( __( 'Installed version: %s', 'clean-admin-studio' ), CAS_VERSION ) ) . '</p>';
			if ( '' !== $latest && version_compare( $latest, CAS_VERSION, '>' ) ) {
				/* translators: %s: version number */
				echo '<p class="cas-callout cas-callout-warn">' . esc_html( sprintf( __( 'Version %s is available.', 'clean-admin-studio' ), $latest ) ) . ' <a href="' . esc_url( self_admin_url( 'update-core.php' ) ) . '">' . esc_html__( 'Go to Updates', 'clean-admin-studio' ) . '</a></p>';
			} elseif ( '' !== $latest ) {
				echo '<p class="cas-callout">' . esc_html__( 'You have the latest version.', 'clean-admin-studio' ) . '</p>';
			} elseif ( $known ) {
				echo '<p class="cas-muted">' . esc_html__( 'No published version was found on the last check.', 'clean-admin-studio' ) . '</p>';
			}
			echo '<p><a class="button" href="' . esc_url( CAS_Updater::check_url() ) . '">' . esc_html__( 'Check for updates', 'clean-admin-studio' ) . '</a>';
			if ( $known && ! empty( $known['checked'] ) ) {
				/* translators: %s: time span such as "2 hours" */
				echo ' <span class="cas-muted">' . esc_html( sprintf( __( 'Last checked %s ago.', 'clean-admin-studio' ), human_time_diff( (int) $known['checked'], time() ) ) ) . '</span>';
			}
			echo '</p>';
			self::card_close();
		}

		self::card_open( __( 'Safe mode', 'clean-admin-studio' ), __( 'If something ever looks wrong, safe mode shows the admin exactly as WordPress ships it, without changing your settings.', 'clean-admin-studio' ) );
		echo '<p><a class="button" href="' . esc_url( add_query_arg( 'cas_safe_mode', '1', admin_url( 'options-general.php?page=' . CAS_SLUG ) ) ) . '">' . esc_html__( 'Open this screen in safe mode', 'clean-admin-studio' ) . '</a></p>';
		echo '<p class="cas-muted">' . wp_kses_post( __( 'Add <code>?cas_safe_mode=1</code> to any admin address for a one-off, or <code>define( \'CAS_SAFE_MODE\', true );</code> in wp-config.php to pause everything.', 'clean-admin-studio' ) ) . '</p>';
		self::card_close();

		self::card_open( __( 'Export', 'clean-admin-studio' ), __( 'Download every setting, including the menu layout, to reuse on another site.', 'clean-admin-studio' ) );
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cas_export' ), 'cas_export' ) ) . '">' . esc_html__( 'Download settings file', 'clean-admin-studio' ) . '</a></p>';
		self::card_close();

		self::card_open( __( 'Import', 'clean-admin-studio' ), __( 'Paste the contents of a settings file. This replaces the current settings.', 'clean-admin-studio' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cas_import" />';
		wp_nonce_field( 'cas_import' );
		echo '<textarea name="cas_import" class="cas-input cas-code" rows="5" spellcheck="false" required></textarea><p><button type="submit" class="button">' . esc_html__( 'Import settings', 'clean-admin-studio' ) . '</button></p></form>';
		self::card_close();

		self::card_open( __( 'Reset', 'clean-admin-studio' ), __( 'Remove every customization and return to the WordPress defaults.', 'clean-admin-studio' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="cas-reset-form"><input type="hidden" name="action" value="cas_reset" />';
		wp_nonce_field( 'cas_reset' );
		echo '<label class="cas-confirm"><input type="checkbox" name="confirm" value="1" required /> ' . esc_html__( 'Yes, reset everything', 'clean-admin-studio' ) . '</label> <button type="submit" class="button cas-danger">' . esc_html__( 'Reset all settings', 'clean-admin-studio' ) . '</button></form>';
		self::card_close();
	}

	/* ---------------------------------------------------------------------
	 * Tools
	 * ------------------------------------------------------------------ */

	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'clean-admin-studio' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	private static function back( $code, $message, $type = 'success', $tab = 'tools' ) {
		// WordPress prints these messages as they are, so anything that came from a
		// mail server or a setting is made safe here.
		add_settings_error( 'cas', $code, esc_html( $message ), $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', admin_url( 'options-general.php?page=' . CAS_SLUG ) ) . '#' . $tab );
		exit;
	}

	public static function mail_test() {
		self::guard( 'cas_mail_test' );
		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! $to || ! is_email( $to ) ) {
			self::back( 'cas_mail_bad', __( 'Enter a valid email address to send the test to.', 'clean-admin-studio' ), 'error', 'email' );
		}
		$result = CAS_Mail::test( $to );
		if ( ! $result['ok'] ) {
			/* translators: %s: reason given by the mail system */
			self::back( 'cas_mail_failed', sprintf( __( 'The test email could not be sent. %s', 'clean-admin-studio' ), $result['error'] ? $result['error'] : __( 'The server gave no reason. Its mail function may be switched off: ask your host, or use an email delivery (SMTP) plugin.', 'clean-admin-studio' ) ), 'error', 'email' );
		}
		$sender = $result['from'] ? trim( $result['name'] . ' <' . $result['from'] . '>' ) : '';
		/* translators: 1: recipient, 2: sender such as "Site Name <info@example.com>" */
		$text = $sender ? sprintf( __( 'Test email sent to %1$s as %2$s. Check the inbox, and the spam folder if it is not there.', 'clean-admin-studio' ), $to, $sender )
			/* translators: %s: recipient */
			: sprintf( __( 'Test email sent to %s. Check the inbox, and the spam folder if it is not there.', 'clean-admin-studio' ), $to );
		self::back( 'cas_mail_sent', $text, 'success', 'email' );
	}

	public static function mail_resend() {
		self::guard( 'cas_mail_resend' );
		$result = CAS_Mail::resend( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		if ( null === $result ) {
			self::back( 'cas_mail_noresend', __( 'That email cannot be sent again.', 'clean-admin-studio' ), 'error', 'email' );
		}
		if ( ! $result ) {
			self::back( 'cas_mail_refailed', __( 'The email could not be sent again. See the newest entry in the log for the reason.', 'clean-admin-studio' ), 'error', 'email' );
		}
		self::back( 'cas_mail_resent', __( 'Email sent again.', 'clean-admin-studio' ), 'success', 'email' );
	}

	public static function mail_clear() {
		self::guard( 'cas_mail_clear' );
		CAS_Mail::clear();
		self::back( 'cas_mail_cleared', __( 'The email log was cleared.', 'clean-admin-studio' ), 'success', 'email' );
	}

	public static function export() {
		self::guard( 'cas_export' );
		$data = array(
			'plugin'   => 'clean-admin-studio',
			'version'  => CAS_VERSION,
			'settings' => CAS::all(),
			'menu'     => CAS_Menu::data(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="clean-admin-studio-settings.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	public static function import() {
		self::guard( 'cas_import' );
		$raw  = isset( $_POST['cas_import'] ) ? wp_unslash( $_POST['cas_import'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON, sanitised below.
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			self::back( 'cas_import_bad', __( 'That does not look like a settings file. Nothing was changed.', 'clean-admin-studio' ), 'error' );
		}
		// Fill gaps with defaults so older files do not switch things off by accident.
		$settings = CAS::sanitize( array_merge( CAS::defaults(), $data['settings'] ) );
		// Never import a login address silently: it could lock the importer out.
		$settings['login_slug'] = CAS::opt( 'login_slug' );
		update_option( CAS::OPT, $settings );
		if ( isset( $data['menu'] ) ) {
			update_option( CAS::MENU_OPT, CAS_Menu::sanitize( $data['menu'] ) );
		}
		self::back( 'cas_imported', __( 'Settings imported. The login address was left as it was.', 'clean-admin-studio' ) );
	}

	public static function reset() {
		self::guard( 'cas_reset' );
		delete_option( CAS::OPT );
		delete_option( CAS::MENU_OPT );
		self::back( 'cas_reset', __( 'All settings were reset.', 'clean-admin-studio' ) );
	}
}
