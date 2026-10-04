<?php
/**
 * The plugin's own tables. Nothing per try-on, per visitor or per IP goes to
 * wp_options any more (transients there are autoloaded lookups that pile up):
 *
 * - ofr_counters: atomic usage counters, locks, single-use markers (OFR_Quota).
 * - ofr_jobs:     try-ons in progress or finished in the last 24 hours.
 * - ofr_events:   anonymous statistics (try-ons, add-to-cart after a try-on,
 *                 orders); the visitor is a salted hash, never an IP.
 * - ofr_logs:     the last service errors, for the admin.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_DB {
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ofr_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		OFR_Quota::install();
		$charset = $wpdb->get_charset_collate();
		$jobs    = self::table( 'jobs' );
		$events  = self::table( 'events' );
		$logs    = self::table( 'logs' );
		dbDelta(
			"CREATE TABLE {$jobs} (
			token_hash char(64) NOT NULL,
			prediction varchar(191) NOT NULL DEFAULT '',
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			visitor char(32) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'working',
			polls smallint(5) unsigned NOT NULL DEFAULT 0,
			file varchar(64) NOT NULL DEFAULT '',
			mime varchar(20) NOT NULL DEFAULT '',
			refund text NULL,
			refunded tinyint(1) NOT NULL DEFAULT 0,
			checked_at int(10) unsigned NOT NULL DEFAULT 0,
			wake tinyint(1) NOT NULL DEFAULT 0,
			created int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (token_hash),
			KEY created (created),
			KEY prediction (prediction)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			visitor char(32) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			value decimal(19,4) NOT NULL DEFAULT 0,
			created int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY type_created (type,created),
			KEY product_type (product_id,type),
			KEY visitor_product (visitor,product_id)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created int(10) unsigned NOT NULL DEFAULT 0,
			level varchar(10) NOT NULL DEFAULT 'error',
			context varchar(120) NOT NULL DEFAULT '',
			code int(11) NOT NULL DEFAULT 0,
			detail text NULL,
			PRIMARY KEY  (id),
			KEY created (created)
		) {$charset};"
		);
	}

	public static function uninstall() {
		global $wpdb;
		OFR_Quota::uninstall();
		foreach ( array( 'jobs', 'events', 'logs' ) as $name ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/** Old rows: jobs after 24 hours, logs after 30 days (and beyond 500 rows), statistics after 400 days. */
	public static function cleanup() {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'jobs' ) . ' WHERE created < %d', time() - OFR_Storage::ttl() ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'logs' ) . ' WHERE created < %d', time() - 30 * DAY_IN_SECONDS ) );
		$keep = (int) $wpdb->get_var( 'SELECT id FROM ' . self::table( 'logs' ) . ' ORDER BY id DESC LIMIT 1 OFFSET 500' );
		if ( $keep ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'logs' ) . ' WHERE id <= %d', $keep ) );
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'events' ) . ' WHERE created < %d', time() - (int) apply_filters( 'ofr_stats_retention_days', 400 ) * DAY_IN_SECONDS ) );
		// phpcs:enable
		$wpdb->suppress_errors( $suppress );
	}
}
