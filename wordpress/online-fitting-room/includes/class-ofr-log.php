<?php
/**
 * Error and event log: the last 500 entries in the ofr_logs table (shown on
 * the settings page), plus WooCommerce → Status → Logs (source
 * "online-fitting-room") and error_log under WP_DEBUG.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Log {
	const SOURCE = 'online-fitting-room';

	public static function add( $level, $context, $code, $detail ) {
		global $wpdb;
		$detail   = is_string( $detail ) ? $detail : wp_json_encode( $detail );
		$detail   = mb_substr( (string) $detail, 0, 2000 );
		$suppress = $wpdb->suppress_errors( true );
		$wpdb->insert(
			OFR_DB::table( 'logs' ),
			array(
				'created' => time(),
				'level'   => $level,
				'context' => mb_substr( (string) $context, 0, 120 ),
				'code'    => (int) $code,
				'detail'  => $detail,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->suppress_errors( $suppress );
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, sprintf( '%s (HTTP %d): %s', $context, $code, $detail ), array( 'source' => self::SOURCE ) );
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && 'error' === $level ) {
			error_log( sprintf( '[Online Fitting Room] %s (HTTP %d): %s', $context, $code, $detail ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	public static function error( $context, $code, $detail ) {
		self::add( 'error', $context, $code, $detail );
	}

	public static function info( $context, $detail ) {
		self::add( 'info', $context, 0, $detail );
	}

	/** @return array[] Newest first. */
	public static function recent( $limit = 50, $level = '' ) {
		global $wpdb;
		$table    = OFR_DB::table( 'logs' );
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $level
			? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE level = %s ORDER BY id DESC LIMIT %d', $table, $level, $limit ), ARRAY_A )
			: $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ), ARRAY_A );
		// phpcs:enable
		$wpdb->suppress_errors( $suppress );
		return $rows ?: array();
	}

	/** Errors of one context family in the last $seconds (used by the outage alert). */
	public static function count_since( $seconds, $context_prefix = '' ) {
		global $wpdb;
		$table = OFR_DB::table( 'logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE level = 'error' AND created >= %d AND context LIKE %s", $table, time() - $seconds, $wpdb->esc_like( $context_prefix ) . '%' ) );
	}

	public static function clear() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . OFR_DB::table( 'logs' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		delete_option( 'ofr_last_error' );
	}
}
