<?php
/**
 * Atomic counters, locks and single-use markers in one small table.
 *
 * Usage limits used to be read-then-written transients: fifty requests sent at
 * the same moment all read "0 used" and all passed. Here a unit is taken with a
 * single conditional UPDATE (… SET n = n + 1 WHERE n < limit), which the
 * database applies one request at a time, so a limit can never be exceeded no
 * matter how many requests arrive together. Units are given back when the
 * try-on could not be started.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Quota {
	const TABLE = 'ofr_counters';

	/** Set when the table is unusable; the legacy transient counters are used instead. */
	private static $broken = false;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta( "CREATE TABLE {$table} (
			k char(40) NOT NULL,
			n int(10) unsigned NOT NULL DEFAULT 0,
			expires bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (k),
			KEY expires (expires)
		) {$wpdb->get_charset_collate()};" );
	}

	public static function uninstall() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function key( $name ) {
		return substr( hash( 'sha256', (string) $name ), 0, 40 );
	}

	/**
	 * Runs a write query; false (with the table marked broken) on a database error
	 * such as a missing table, so callers can fall back instead of failing shoppers.
	 */
	private static function write( $sql ) {
		global $wpdb;
		if ( self::$broken ) return false;
		$suppress = $wpdb->suppress_errors( true );
		$result   = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- prepared by the callers.
		$wpdb->suppress_errors( $suppress );
		if ( false === $result ) {
			self::$broken = true;
			if ( class_exists( 'OFR_Api' ) ) OFR_Api::log_error( 'counters table', 0, $wpdb->last_error ?: 'Query failed.' );
		}
		return $result;
	}

	public static function healthy() {
		return ! self::$broken;
	}

	/** Current value of a counter (0 when missing or expired). */
	public static function get( $name ) {
		global $wpdb;
		if ( self::$broken ) return (int) get_transient( 'ofr_c_' . self::key( $name ) );
		$suppress = $wpdb->suppress_errors( true );
		$value    = $wpdb->get_var( $wpdb->prepare( 'SELECT n FROM ' . self::table() . ' WHERE k = %s AND expires > %d', self::key( $name ), time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->suppress_errors( $suppress );
		return (int) $value;
	}

	/**
	 * Takes one unit from a counter that allows $limit units per $ttl seconds.
	 *
	 * @return bool True when the unit was taken.
	 */
	public static function take( $name, $limit, $ttl ) {
		global $wpdb;
		$k   = self::key( $name );
		$now = time();
		// Create the row, or restart it when its window has passed (n is assigned before expires, so it sees the old expiry).
		$ensure = self::write( $wpdb->prepare( 'INSERT INTO ' . self::table() . ' (k, n, expires) VALUES (%s, 0, %d) ON DUPLICATE KEY UPDATE n = IF(expires <= %d, 0, n), expires = IF(expires <= %d, VALUES(expires), expires)', $k, $now + $ttl, $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $ensure ) return self::legacy_take( $k, $limit, $ttl );
		$taken = self::write( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET n = n + 1 WHERE k = %s AND n < %d', $k, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $taken ) return self::legacy_take( $k, $limit, $ttl );
		return 1 === (int) $taken;
	}

	/** Gives back one unit taken earlier (never below zero). */
	public static function give_back( $name ) {
		global $wpdb;
		$k = self::key( $name );
		if ( self::$broken ) {
			$value = (int) get_transient( 'ofr_c_' . $k );
			if ( $value > 0 ) set_transient( 'ofr_c_' . $k, $value - 1, DAY_IN_SECONDS );
			return;
		}
		self::write( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET n = n - 1 WHERE k = %s AND n > 0', $k ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Takes a unit from every bucket, or from none: when one bucket is full the
	 * units already taken are given back.
	 *
	 * @param array $buckets List of { name, limit, ttl, scope }; a limit of 0 means unlimited.
	 * @return array { ok: bool, taken: string[], scope: string } — scope of the full bucket.
	 */
	public static function reserve( array $buckets ) {
		$taken = array();
		foreach ( $buckets as $bucket ) {
			if ( (int) $bucket['limit'] <= 0 ) continue;
			if ( ! self::take( $bucket['name'], (int) $bucket['limit'], (int) $bucket['ttl'] ) ) {
				self::release( $taken );
				return array( 'ok' => false, 'taken' => array(), 'scope' => $bucket['scope'] ?? '' );
			}
			$taken[] = $bucket['name'];
		}
		return array( 'ok' => true, 'taken' => $taken, 'scope' => '' );
	}

	public static function release( array $names ) {
		foreach ( $names as $name ) {
			self::give_back( $name );
		}
	}

	/**
	 * Mutual exclusion that also works across servers: only one INSERT of a
	 * primary key can succeed. A crashed holder's lock expires after $ttl.
	 */
	public static function lock( $name, $ttl = 60 ) {
		global $wpdb;
		$k = self::key( 'lock|' . $name );
		if ( false === self::write( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE k = %s AND expires <= %d', $k, time() ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return self::legacy_lock( $k, $ttl );
		}
		$inserted = self::write( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::table() . ' (k, n, expires) VALUES (%s, 1, %d)', $k, time() + $ttl ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $inserted ) return self::legacy_lock( $k, $ttl );
		return 1 === (int) $inserted;
	}

	public static function unlock( $name ) {
		global $wpdb;
		$k = self::key( 'lock|' . $name );
		delete_transient( 'ofr_l_' . $k );
		self::write( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE k = %s', $k ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** True the first time a value is seen within $ttl (single-use challenges). */
	public static function once( $name, $ttl ) {
		return self::lock( 'once|' . $name, $ttl );
	}

	/** Drops expired rows (hourly cleanup). */
	public static function cleanup() {
		global $wpdb;
		self::write( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE expires < %d', time() - MINUTE_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/* ---------- Fallback when the table cannot be used (not atomic, but keeps the store working) ---------- */

	private static function legacy_take( $k, $limit, $ttl ) {
		$value = (int) get_transient( 'ofr_c_' . $k );
		if ( $value >= $limit ) return false;
		set_transient( 'ofr_c_' . $k, $value + 1, $ttl );
		return true;
	}

	private static function legacy_lock( $k, $ttl ) {
		if ( get_transient( 'ofr_l_' . $k ) ) return false;
		set_transient( 'ofr_l_' . $k, 1, $ttl );
		return true;
	}
}
