<?php
/**
 * Removes everything the plugin stored: settings, product meta, jobs and
 * counters (table and transients), private result files and the schedules —
 * on every site of a multisite network.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-ofr-quota.php';
require_once __DIR__ . '/includes/class-ofr-storage.php';

function ofr_uninstall_site() {
	global $wpdb;
	OFR_Storage::remove_all();
	OFR_Quota::uninstall();
	foreach ( array( 'ofr_cleanup', 'ofr_refresh_cdn_ranges', 'ofr_delete_result' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	foreach ( array( 'ofr_settings', 'ofr_db_version', 'ofr_last_cleanup', 'ofr_last_error', 'ofr_transport', 'ofr_last_ok', 'ofr_last_prediction', 'ofr_client_seen', 'ofr_cdn_ranges', 'webich_smart_tryon_settings' ) as $option ) {
		delete_option( $option );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_ofr_enabled', '_ofr_category', '_webich_tryon_enabled', '_webich_tryon_category')" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ofr\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ofr\\_%'" );
	// phpcs:enable
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		ofr_uninstall_site();
		restore_current_blog();
	}
} else {
	ofr_uninstall_site();
}
