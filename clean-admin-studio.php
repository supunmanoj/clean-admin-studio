<?php
/**
 * Plugin Name:       Clean Admin Studio
 * Plugin URI:        https://github.com/supunmanoj/clean-admin-studio
 * Description:       White-label and simplify the WordPress admin for clients: menu editor, branding, colors and fonts, modern dashboard, notification center and login page designer.
 * Version:           1.7.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Supun Manoj Kumara
 * Author URI:        https://github.com/supunmanoj
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       clean-admin-studio
 * Update URI:        https://github.com/supunmanoj/clean-admin-studio
 *
 * Safety net: add  define( 'CAS_SAFE_MODE', true );  to wp-config.php to switch
 * every customization off, or open any admin page with ?cas_safe_mode=1.
 */

defined( 'ABSPATH' ) || exit;

define( 'CAS_VERSION', '1.7.1' ); // Keep in step with "Version:" above.
define( 'CAS_FILE', __FILE__ );
define( 'CAS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAS_URL', plugin_dir_url( __FILE__ ) );
define( 'CAS_SLUG', 'clean-admin-studio' );

require_once CAS_DIR . 'includes/class-cas.php';
require_once CAS_DIR . 'includes/class-cas-login.php';
require_once CAS_DIR . 'includes/class-cas-branding.php';
require_once CAS_DIR . 'includes/class-cas-cleanup.php';
require_once CAS_DIR . 'includes/class-cas-menu.php';
require_once CAS_DIR . 'includes/class-cas-widgets.php';
require_once CAS_DIR . 'includes/class-cas-content.php';
require_once CAS_DIR . 'includes/class-cas-editor.php';
require_once CAS_DIR . 'includes/class-cas-insights.php';
require_once CAS_DIR . 'includes/class-cas-updater.php';
require_once CAS_DIR . 'includes/class-cas-mail.php';
require_once CAS_DIR . 'includes/class-cas-admin-page.php';

CAS::init();
CAS_Login::init();
CAS_Branding::init();
CAS_Cleanup::init();
CAS_Menu::init();
CAS_Widgets::init();
CAS_Content::init();
CAS_Editor::init();
CAS_Insights::init();
CAS_Updater::init();
CAS_Mail::init();
CAS_Admin_Page::init();
