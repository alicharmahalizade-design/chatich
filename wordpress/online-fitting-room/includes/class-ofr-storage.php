<?php
/**
 * Try-on jobs and private result storage.
 *
 * A job is created when the API accepts a try-on. The browser only ever receives
 * a random job token: the API prediction ID stays on the server, and results
 * are served through a token-checked endpoint from a directory that is not
 * listable and denies direct web access, under random file names.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Storage {
	const DIR           = 'ofr-results';
	const CLEANUP_HOOK  = 'ofr_cleanup';
	const MAX_POLLS     = 150;
	const TOKEN_PATTERN = '/^[A-Za-z0-9]{32}$/';
	const TTL_HOURS     = 24; // Results and jobs are deleted after this.

	public static function ttl() {
		return self::TTL_HOURS * HOUR_IN_SECONDS;
	}

	/* ---------- Jobs ---------- */

	public static function create_job( array $job ) {
		$token = wp_generate_password( 32, false, false );
		$job  += array( 'created' => time(), 'polls' => 0, 'file' => '', 'mime' => '' );
		set_transient( self::job_key( $token ), $job, self::ttl() );
		return $token;
	}

	public static function get_job( $token ) {
		if ( ! is_string( $token ) || ! preg_match( self::TOKEN_PATTERN, $token ) ) return null;
		$job = get_transient( self::job_key( $token ) );
		return is_array( $job ) ? $job : null;
	}

	public static function save_job( $token, array $job ) {
		$left = max( 60, (int) $job['created'] + self::ttl() - time() );
		set_transient( self::job_key( $token ), $job, $left );
	}

	/** Prevents two concurrent polls from downloading the same result twice. */
	public static function lock( $token ) {
		$key = 'ofr_lock_' . md5( $token );
		if ( get_transient( $key ) ) return false;
		set_transient( $key, 1, 60 );
		return true;
	}

	public static function unlock( $token ) {
		delete_transient( 'ofr_lock_' . md5( $token ) );
	}

	private static function job_key( $token ) {
		return 'ofr_job_' . hash( 'sha256', $token );
	}

	/* ---------- Files ---------- */

	public static function dir() {
		$uploads = wp_upload_dir( null, false );
		return empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . self::DIR : '';
	}

	private static function ensure_dir() {
		$dir = self::dir();
		if ( '' === $dir || ! wp_mkdir_p( $dir ) ) return '';
		if ( ! file_exists( $dir . '/index.php' ) ) file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	/** @return string|false Stored file name. */
	public static function save( $bytes, $mime ) {
		$dir = self::ensure_dir();
		if ( '' === $dir ) return false;
		$name = bin2hex( random_bytes( 16 ) ) . ( 'image/jpeg' === $mime ? '.jpg' : '.png' );
		if ( false === file_put_contents( $dir . '/' . $name, $bytes, LOCK_EX ) ) return false; // phpcs:ignore WordPress.WP.AlternativeFunctions
		self::maybe_cleanup();
		return $name;
	}

	public static function path( $name ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[a-f0-9]{32}\.(jpg|png)$/', $name ) ) return '';
		$path = self::dir() . '/' . $name;
		return is_file( $path ) ? $path : '';
	}

	/**
	 * Deletes expired results. Runs hourly via WP-Cron and, so that it does not
	 * depend on cron at all, opportunistically after saving a new result.
	 */
	public static function cleanup() {
		update_option( 'ofr_last_cleanup', time(), false );
		$dir = self::dir();
		if ( '' === $dir || ! is_dir( $dir ) ) return;
		$expired = time() - self::ttl();
		foreach ( self::files( $dir ) as $file ) {
			if ( preg_match( '/\.(jpg|png)$/', $file ) && filemtime( $file ) < $expired ) wp_delete_file( $file );
		}
	}

	private static function maybe_cleanup() {
		if ( (int) get_option( 'ofr_last_cleanup', 0 ) < time() - HOUR_IN_SECONDS ) self::cleanup();
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CLEANUP_HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/** Single-file events queued by version 1.2.0 and the legacy Webich plugin. */
	public static function delete_legacy_result( $path ) {
		$uploads = wp_upload_dir( null, false );
		$base    = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$target  = wp_normalize_path( (string) $path );
		if ( 0 === strpos( $target, $base ) && is_file( $target ) && preg_match( '/^(ofr-result-|webich-tryon-)/', basename( $target ) ) ) {
			wp_delete_file( $target );
		}
	}

	/** Regular files in $dir, including dotfiles (no GLOB_BRACE: unavailable on some systems). */
	private static function files( $dir ) {
		$files = array();
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( is_string( $entry ) && is_file( $dir . '/' . $entry ) ) $files[] = $dir . '/' . $entry;
		}
		return $files;
	}

	public static function remove_all() {
		$dir = self::dir();
		if ( '' === $dir || ! is_dir( $dir ) ) return;
		foreach ( self::files( $dir ) as $file ) {
			wp_delete_file( $file );
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
