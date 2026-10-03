<?php
/**
 * Removes everything the plugin stored: settings, product meta, jobs and
 * counters, private result files and the cleanup schedule.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-ofr-storage.php';

global $wpdb;

OFR_Storage::remove_all();
OFR_Storage::unschedule();
wp_clear_scheduled_hook( 'ofr_delete_result' );

foreach ( array( 'ofr_settings', 'ofr_db_version', 'ofr_last_cleanup', 'ofr_last_error', 'ofr_transport', 'ofr_last_ok', 'ofr_last_prediction', 'webich_smart_tryon_settings' ) as $option ) {
	delete_option( $option );
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_ofr_enabled', '_ofr_category', '_webich_tryon_enabled', '_webich_tryon_category')" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ofr\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ofr\\_%'" );
// phpcs:enable
