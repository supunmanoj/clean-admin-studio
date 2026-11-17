<?php
/**
 * Remove everything the plugin stored.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cas_settings' );
delete_option( 'cas_menu' );
delete_option( 'cas_dash_widgets' );
delete_option( 'cas_ga_key' );
delete_option( 'cas_ga_detect' );
delete_option( 'cas_ga_oauth' );
delete_transient( 'cas_ga_props' );
foreach ( array( '28', '90', '365', 'all' ) as $cas_period ) {
	delete_transient( 'cas_views_' . $cas_period );
}
delete_metadata( 'user', 0, 'cas_views_period', '', true );
delete_post_meta_by_key( '_cas_words' );
delete_transient( 'cas_ga_report' );
delete_transient( 'cas_insights_ga' );
delete_transient( 'cas_insights_content' );
delete_transient( 'cas_insights_posts' );
delete_site_transient( 'cas_update_info' );
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cas_mail_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.SchemaChange
delete_option( 'cas_mail_db' );
delete_transient( 'cas_ga_token' );
delete_metadata( 'user', 0, 'cas_client_view', '', true );
