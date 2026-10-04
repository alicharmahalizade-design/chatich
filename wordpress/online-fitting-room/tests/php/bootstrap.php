<?php
/**
 * Unit tests run without WordPress: the classes under test are pure PHP apart
 * from a handful of WordPress helpers, stubbed here with their core behaviour.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['ofr_test_filters'] = array();

function __( $text ) { return $text; } // phpcs:ignore
function esc_html__( $text ) { return $text; } // phpcs:ignore
function apply_filters( $hook, $value ) { // phpcs:ignore
	return isset( $GLOBALS['ofr_test_filters'][ $hook ] ) ? call_user_func_array( $GLOBALS['ofr_test_filters'][ $hook ], array_slice( func_get_args(), 1 ) ) : $value;
}
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; } // phpcs:ignore
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); } // phpcs:ignore
function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); } // phpcs:ignore
function trailingslashit( $value ) { return untrailingslashit( $value ) . '/'; } // phpcs:ignore
function wp_json_encode( $data ) { return json_encode( $data ); } // phpcs:ignore
function is_wp_error( $thing ) { return $thing instanceof WP_Error; } // phpcs:ignore
function get_option( $name, $default = false ) { return $GLOBALS['ofr_test_options'][ $name ] ?? $default; } // phpcs:ignore

class WP_Error { // phpcs:ignore
	private $code;
	private $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code = $code;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; } // phpcs:ignore
	public function get_error_data() { return $this->data; } // phpcs:ignore
	public function add_data( $data ) { $this->data = $data; } // phpcs:ignore
}

/** Minimal settings provider for classes that read plugin settings. */
class Online_Fitting_Room { // phpcs:ignore
	public static $settings = array( 'api_key' => '', 'api_base' => '', 'captcha' => 'pow', 'turnstile_site' => '', 'turnstile_secret' => '', 'trusted_proxies' => '', 'ip_source' => 'auto' );
	public static function settings() { return self::$settings; } // phpcs:ignore
}

$root = dirname( __DIR__, 2 );
foreach ( array( 'image', 'api', 'captcha', 'client', 'storage', 'alerts', 'quota' ) as $class ) {
	require_once $root . '/includes/class-ofr-' . $class . '.php';
}
