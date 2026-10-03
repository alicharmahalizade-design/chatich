<?php
/**
 * Try-on jobs and private result storage.
 *
 * A job is created when the API accepts a try-on. The browser only ever receives
 * a random job token: the API prediction ID stays on the server, and results
 * are served through a token-checked endpoint.
 *
 * Result files are encrypted at rest with a key derived from the job token,
 * which the server never stores (jobs are looked up by its hash). So even where
 * the web server ignores .htaccess (Nginx) and the folder could be reached
 * directly, a file is unreadable noise to anyone but the shopper who holds the
 * token. Sites can also move the folder outside the web root with the
 * OFR_RESULTS_DIR constant or the ofr_results_dir filter.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Storage {
	const DIR           = 'ofr-results';
	const CLEANUP_HOOK  = 'ofr_cleanup';
	const MAX_POLLS     = 150;
	const TOKEN_PATTERN = '/^[A-Za-z0-9]{32}$/';
	const TTL_HOURS     = 24; // Results and jobs are deleted after this.
	const MAGIC         = 'OFR1';

	public static function ttl() {
		return self::TTL_HOURS * HOUR_IN_SECONDS;
	}

	/* ---------- Jobs ---------- */

	public static function create_job( array $job ) {
		$token = wp_generate_password( 32, false, false );
		$job  += array( 'created' => time(), 'polls' => 0, 'file' => '', 'mime' => '', 'refund' => array(), 'refunded' => false );
		set_transient( self::job_key( $token ), $job, self::ttl() );
		return $token;
	}

	public static function get_job( $token ) {
		if ( ! is_string( $token ) || ! preg_match( self::TOKEN_PATTERN, $token ) ) return null;
		$job = get_transient( self::job_key( $token ) );
		if ( ! is_array( $job ) ) return null;
		// Never trust a cache that outlives its expiry: a job is dead after 24 hours.
		if ( (int) ( $job['created'] ?? 0 ) < time() - self::ttl() ) {
			self::delete_job( $token, $job );
			return null;
		}
		return $job;
	}

	public static function save_job( $token, array $job ) {
		$left = max( 60, (int) $job['created'] + self::ttl() - time() );
		set_transient( self::job_key( $token ), $job, $left );
	}

	public static function delete_job( $token, $job = null ) {
		if ( is_array( $job ) && ! empty( $job['file'] ) ) {
			$path = self::raw_path( $job['file'] );
			if ( $path ) wp_delete_file( $path );
		}
		delete_transient( self::job_key( $token ) );
	}

	/** Prevents two concurrent polls from downloading the same result twice (atomic, see OFR_Quota). */
	public static function lock( $token ) {
		return OFR_Quota::lock( 'job|' . hash( 'sha256', $token ), 60 );
	}

	public static function unlock( $token ) {
		OFR_Quota::unlock( 'job|' . hash( 'sha256', $token ) );
	}

	private static function job_key( $token ) {
		return 'ofr_job_' . hash( 'sha256', $token );
	}

	/* ---------- Encryption ---------- */

	public static function encryption() {
		if ( function_exists( 'sodium_crypto_secretbox' ) ) return 'sodium';
		if ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', array_map( 'strtolower', (array) openssl_get_cipher_methods() ), true ) ) return 'openssl';
		return '';
	}

	private static function file_key( $token ) {
		return hash_hmac( 'sha256', 'ofr-result|' . $token, wp_salt( 'secure_auth' ), true );
	}

	/** @return string|false Encrypted container, or false when no cipher is available. */
	public static function seal( $bytes, $token ) {
		$key = self::file_key( $token );
		switch ( self::encryption() ) {
			case 'sodium':
				$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
				return self::MAGIC . 'S' . $nonce . sodium_crypto_secretbox( $bytes, $nonce, $key );
			case 'openssl':
				$iv     = random_bytes( 12 );
				$tag    = '';
				$cipher = openssl_encrypt( $bytes, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
				return false === $cipher ? false : self::MAGIC . 'G' . $iv . $tag . $cipher;
		}
		return false;
	}

	/** @return string|false Plain bytes; false when the token is wrong or the file was altered. */
	public static function open( $data, $token ) {
		if ( 0 !== strncmp( $data, self::MAGIC, 4 ) ) return false;
		$key = self::file_key( $token );
		$alg = substr( $data, 4, 1 );
		if ( 'S' === $alg && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			return sodium_crypto_secretbox_open( substr( $data, 5 + $n ), substr( $data, 5, $n ), $key );
		}
		if ( 'G' === $alg && function_exists( 'openssl_decrypt' ) ) {
			return openssl_decrypt( substr( $data, 33 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $data, 5, 12 ), substr( $data, 17, 16 ) );
		}
		return false;
	}

	/* ---------- Files ---------- */

	/** True when the folder sits outside the public uploads directory. */
	public static function is_custom_dir() {
		return ( defined( 'OFR_RESULTS_DIR' ) && is_string( OFR_RESULTS_DIR ) && '' !== OFR_RESULTS_DIR ) || has_filter( 'ofr_results_dir' );
	}

	public static function dir() {
		$dir = defined( 'OFR_RESULTS_DIR' ) && is_string( OFR_RESULTS_DIR ) ? OFR_RESULTS_DIR : '';
		if ( '' === $dir ) {
			$uploads = wp_upload_dir( null, false );
			$dir     = empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . self::DIR : '';
		}
		return untrailingslashit( (string) apply_filters( 'ofr_results_dir', $dir ) );
	}

	/** Public URL of the folder when it lives under uploads (used by the Site Health check). */
	public static function url() {
		if ( self::is_custom_dir() ) return '';
		$uploads = wp_upload_dir( null, false );
		return empty( $uploads['error'] ) ? trailingslashit( $uploads['baseurl'] ) . self::DIR : '';
	}

	public static function ensure_dir() {
		$dir = self::dir();
		if ( '' === $dir || ! wp_mkdir_p( $dir ) ) return '';
		// Belt and braces for each web server; the encryption does not depend on them.
		$guards = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'index.html' => '',
			'.htaccess'  => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $guards as $file => $content ) {
			if ( ! file_exists( $dir . '/' . $file ) ) file_put_contents( $dir . '/' . $file, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	/**
	 * Stores a result, encrypted for the holder of $token.
	 *
	 * @return string|false Stored file name.
	 */
	public static function save( $bytes, $mime, $token ) {
		$dir = self::ensure_dir();
		if ( '' === $dir ) return false;
		$sealed = self::seal( $bytes, $token );
		$name   = bin2hex( random_bytes( 16 ) );
		if ( false !== $sealed ) {
			$name .= '.ofr';
			$bytes = $sealed;
		} else {
			$name .= 'image/jpeg' === $mime ? '.jpg' : '.png'; // No cipher on this server: random name only.
		}
		if ( false === file_put_contents( $dir . '/' . $name, $bytes, LOCK_EX ) ) return false; // phpcs:ignore WordPress.WP.AlternativeFunctions
		self::maybe_cleanup();
		return $name;
	}

	private static function raw_path( $name ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[a-f0-9]{32}\.(jpg|png|ofr)$/', $name ) ) return '';
		$path = self::dir() . '/' . $name;
		return is_file( $path ) ? $path : '';
	}

	/** Path of a live result; files past their 24 hours are deleted on sight. */
	public static function path( $name ) {
		$path = self::raw_path( $name );
		if ( $path ) clearstatcache( true, $path );
		if ( $path && filemtime( $path ) < time() - self::ttl() ) {
			wp_delete_file( $path );
			return '';
		}
		return $path;
	}

	/** @return string|false Decrypted result bytes. */
	public static function read( $name, $token ) {
		$path = self::path( $name );
		if ( ! $path ) return false;
		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $data ) return false;
		return '.ofr' === substr( $name, -4 ) ? self::open( $data, $token ) : $data;
	}

	/**
	 * Deletes expired results and counters. Runs hourly via WP-Cron and, so that
	 * it does not depend on cron at all, opportunistically during normal traffic.
	 */
	public static function cleanup() {
		update_option( 'ofr_last_cleanup', time(), false );
		OFR_Quota::cleanup();
		$dir = self::dir();
		if ( '' === $dir || ! is_dir( $dir ) ) return;
		$expired = time() - self::ttl();
		foreach ( self::files( $dir ) as $file ) {
			if ( preg_match( '/\.(jpg|png|ofr)$/', $file ) && filemtime( $file ) < $expired ) wp_delete_file( $file );
		}
	}

	public static function maybe_cleanup() {
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

	/** Every result file and job, at once (deactivation and uninstall: nothing outlives the plugin). */
	public static function remove_all() {
		global $wpdb;
		$dir = self::dir();
		if ( '' !== $dir && is_dir( $dir ) ) {
			foreach ( self::files( $dir ) as $file ) {
				wp_delete_file( $file );
			}
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ofr\\_job\\_%' OR option_name LIKE '\\_transient\\_timeout\\_ofr\\_job\\_%'" );
		// Jobs kept in a persistent object cache point at files that no longer exist, so they can serve nothing.
	}
}
