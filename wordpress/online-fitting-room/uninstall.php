<?php
/**
 * Removes everything the plugin stored: settings, product and order meta, its
 * tables (counters, jobs, statistics, logs), private result files, the pose
 * check files and the schedules — on every site of a multisite network.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-ofr-quota.php';
require_once __DIR__ . '/includes/class-ofr-db.php';
require_once __DIR__ . '/includes/class-ofr-storage.php';
require_once __DIR__ . '/includes/class-ofr-pose.php';

function ofr_uninstall_site() {
	global $wpdb;
	OFR_Storage::remove_all();
	OFR_Pose::remove();
	OFR_DB::uninstall();
	foreach ( array( 'ofr_cleanup', 'ofr_refresh_cdn_ranges', 'ofr_delete_result', 'ofr_daily_report' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	foreach ( array( 'ofr_settings', 'ofr_db_version', 'ofr_last_cleanup', 'ofr_last_error', 'ofr_transport', 'ofr_last_ok', 'ofr_last_prediction', 'ofr_client_seen', 'ofr_cdn_ranges', 'ofr_balance', 'ofr_credit_epoch', 'ofr_alert_low_sent', 'ofr_alert_times', 'ofr_alert_log', 'ofr_webhook_secret', 'ofr_webhook_last', 'webich_smart_tryon_settings' ) as $option ) {
		delete_option( $option );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_ofr_enabled', '_ofr_category', '_ofr_tryon_count', '_webich_tryon_enabled', '_webich_tryon_category')" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ofr\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ofr\\_%'" );
	// Order marks: kept on orders would be harmless, but nothing of the plugin should remain.
	$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_ofr_tryon'" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_ofr_tryon', '_ofr_tracked')" );
	$hpos = $wpdb->prefix . 'wc_orders_meta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos ) ) === $hpos ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key IN (%s, %s)', $hpos, '_ofr_tryon', '_ofr_tracked' ) );
	}
	// phpcs:enable
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $ofr_site_id ) {
		switch_to_blog( $ofr_site_id );
		ofr_uninstall_site();
		restore_current_blog();
	}
} else {
	ofr_uninstall_site();
}
