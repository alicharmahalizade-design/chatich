<?php
/**
 * Who is this visitor? The real IP behind a CDN or reverse proxy, a signed
 * per-browser id for guests, and the keys usage limits are counted under.
 *
 * Proxy headers (CF-Connecting-IP, X-Forwarded-For, …) can be sent by anyone,
 * so in automatic mode they are only believed when the request really comes
 * from a known CDN edge (Cloudflare or ArvanCloud, by their published IP
 * ranges), from a private/loopback address (a reverse proxy on the same
 * network) or from a proxy the admin listed.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Client {
	const COOKIE       = 'ofr_gid';
	const REFRESH_HOOK = 'ofr_refresh_cdn_ranges';

	/** Cloudflare's published ranges (cloudflare.com/ips), used until the weekly refresh succeeds. */
	const CLOUDFLARE = array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
		'190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	);

	const PRIVATE_RANGES = array( '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8', '100.64.0.0/10', '::1/128', 'fc00::/7', 'fe80::/10' );

	const SOURCES = array(
		'cloudflare'  => 'https://www.cloudflare.com/ips-v4',
		'cloudflare6' => 'https://www.cloudflare.com/ips-v6',
		'arvan'       => 'https://www.arvancloud.ir/en/ips.txt',
	);

	private static $ip = null;

	/* ---------- CIDR ---------- */

	public static function in_cidr( $ip, $cidr ) {
		if ( false === strpos( $cidr, '/' ) ) $cidr .= false === strpos( $cidr, ':' ) ? '/32' : '/128';
		list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		$ip_bin  = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$net_bin = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) return false;
		$bits = (int) $bits;
		if ( $bits < 0 || $bits > strlen( $ip_bin ) * 8 ) return false;
		$bytes = intdiv( $bits, 8 );
		if ( $bytes && 0 !== strncmp( $ip_bin, $net_bin, $bytes ) ) return false;
		$rest = $bits % 8;
		if ( ! $rest ) return true;
		$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
		return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $net_bin[ $bytes ] ) & $mask );
	}

	public static function in_any( $ip, array $cidrs ) {
		foreach ( $cidrs as $cidr ) {
			if ( self::in_cidr( $ip, trim( $cidr ) ) ) return true;
		}
		return false;
	}

	/** Valid CIDRs/IPs from free text (one per line, comma or space separated). */
	public static function parse_cidrs( $text ) {
		$out = array();
		foreach ( preg_split( '/[\s,]+/', (string) $text ) as $item ) {
			$item = trim( $item );
			if ( '' === $item || '#' === $item[0] ) continue;
			$ip = explode( '/', $item )[0];
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) continue;
			if ( false !== strpos( $item, '/' ) ) {
				$bits = explode( '/', $item )[1];
				$max  = false === strpos( $ip, ':' ) ? 32 : 128;
				if ( ! ctype_digit( $bits ) || (int) $bits > $max ) continue;
			}
			$out[] = $item;
		}
		return array_values( array_unique( $out ) );
	}

	/* ---------- CDN ranges ---------- */

	/** @return array { cloudflare: string[], arvan: string[], time: int } */
	public static function ranges() {
		$stored = get_option( 'ofr_cdn_ranges' );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'cloudflare' => ! empty( $stored['cloudflare'] ) ? $stored['cloudflare'] : self::CLOUDFLARE,
			'arvan'      => ! empty( $stored['arvan'] ) ? $stored['arvan'] : array(),
			'time'       => (int) ( $stored['time'] ?? 0 ),
		);
	}

	/** Weekly: download the official lists. A failed download keeps the previous list. */
	public static function refresh_ranges() {
		$current = self::ranges();
		$lists   = array();
		foreach ( self::SOURCES as $name => $url ) {
			$response = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 2 ) );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) continue;
			$lists[ $name ] = self::parse_cidrs( wp_remote_retrieve_body( $response ) );
		}
		$cloudflare = array_merge( $lists['cloudflare'] ?? array(), $lists['cloudflare6'] ?? array() );
		update_option( 'ofr_cdn_ranges', array(
			'cloudflare' => count( $cloudflare ) >= 5 ? $cloudflare : $current['cloudflare'],
			'arvan'      => count( $lists['arvan'] ?? array() ) >= 3 ? $lists['arvan'] : $current['arvan'],
			'time'       => time(),
		), false );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'weekly', self::REFRESH_HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
	}

	/** Trusted proxies the admin listed in the settings. */
	private static function custom_proxies() {
		return self::parse_cidrs( Online_Fitting_Room::settings()['trusted_proxies'] ?? '' );
	}

	/** Which CDN/proxy, if any, this address belongs to: cloudflare, arvan, custom, private or ''. */
	public static function proxy_of( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return '';
		$ranges = self::ranges();
		if ( self::in_any( $ip, $ranges['cloudflare'] ) ) return 'cloudflare';
		if ( $ranges['arvan'] && self::in_any( $ip, $ranges['arvan'] ) ) return 'arvan';
		if ( self::in_any( $ip, self::custom_proxies() ) ) return 'custom';
		if ( self::in_any( $ip, self::PRIVATE_RANGES ) ) return 'private';
		return '';
	}

	/* ---------- Visitor IP ---------- */

	private static function server( $name ) {
		return isset( $_SERVER[ $name ] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER[ $name ] ) ) ) : '';
	}

	/**
	 * X-Forwarded-For lists every hop; the client is the right-most address that
	 * is not one of our own proxies (anything further left can be forged).
	 */
	private static function from_forwarded( $header, $trusted_all ) {
		$list = array_reverse( array_filter( array_map( 'trim', explode( ',', $header ) ) ) );
		foreach ( $list as $candidate ) {
			if ( ! filter_var( $candidate, FILTER_VALIDATE_IP ) ) return '';
			if ( $trusted_all || '' === self::proxy_of( $candidate ) ) return $candidate;
		}
		return '';
	}

	/** @return array { ip, via } — via names the proxy the address came through, or ''. */
	public static function resolve() {
		$remote = self::server( 'REMOTE_ADDR' );
		$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
		$source = Online_Fitting_Room::settings()['ip_source'];
		$cf     = self::server( 'HTTP_CF_CONNECTING_IP' );
		$xff    = self::server( 'HTTP_X_FORWARDED_FOR' );
		$real   = self::server( 'HTTP_X_REAL_IP' );
		$ar     = self::server( 'HTTP_AR_REAL_IP' );
		$valid  = function ( $ip ) {
			return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
		};

		switch ( $source ) {
			case 'remote_addr':
				return array( 'ip' => $remote, 'via' => '' );
			case 'cloudflare': // The admin vouched for the header.
				return $valid( $cf ) ? array( 'ip' => $cf, 'via' => 'cloudflare' ) : array( 'ip' => $remote, 'via' => '' );
			case 'x_real_ip':
				return $valid( $real ) ? array( 'ip' => $real, 'via' => 'x_real_ip' ) : array( 'ip' => $remote, 'via' => '' );
			case 'x_forwarded':
				$ip = $xff ? self::from_forwarded( $xff, true ) : '';
				return $ip ? array( 'ip' => $ip, 'via' => 'x_forwarded' ) : array( 'ip' => $remote, 'via' => '' );
		}

		// Automatic: believe a header only when the request comes from that proxy.
		$proxy = self::proxy_of( $remote );
		if ( 'cloudflare' === $proxy && $valid( $cf ) ) return array( 'ip' => $cf, 'via' => 'cloudflare' );
		if ( '' !== $proxy ) {
			$ip = $xff ? self::from_forwarded( $xff, false ) : '';
			if ( ! $ip && 'arvan' === $proxy && $valid( $ar ) ) $ip = $ar;
			if ( ! $ip && 'private' === $proxy && $valid( $real ) ) $ip = $real;
			if ( $ip ) return array( 'ip' => $ip, 'via' => $proxy );
		}
		return array( 'ip' => $remote, 'via' => '' );
	}

	public static function ip() {
		if ( null === self::$ip ) {
			$resolved = self::resolve();
			self::$ip = $resolved['ip'] ?: 'unknown';
		}
		return self::$ip;
	}

	/**
	 * Remembers what the latest visitor's connection looked like, so the settings
	 * page can warn when every shopper seems to share one CDN address.
	 */
	public static function observe() {
		$remote   = self::server( 'REMOTE_ADDR' );
		$resolved = self::resolve();
		$proxy    = self::proxy_of( $remote );
		$hint     = '';
		if ( in_array( $proxy, array( 'cloudflare', 'arvan' ), true ) && $resolved['ip'] === $remote ) {
			$hint = 'unresolved'; // Behind a CDN, but every visitor would count as the CDN's address.
		} elseif ( '' === $proxy && '' === $resolved['via'] && ( self::server( 'HTTP_AR_REAL_IP' ) || self::server( 'HTTP_CF_CONNECTING_IP' ) ) ) {
			$hint = 'unknown_cdn'; // CDN headers from an edge address we do not know (e.g. Arvan list not downloaded yet).
		} elseif ( 'cloudflare' === Online_Fitting_Room::settings()['ip_source'] && 'cloudflare' !== $proxy && $remote ) {
			$hint = 'spoofable'; // "Cloudflare" chosen, but this request did not come through Cloudflare.
		}
		$last = get_option( 'ofr_client_seen' );
		$data = array( 'proxy' => $proxy, 'via' => $resolved['via'], 'hint' => $hint, 'time' => time() );
		if ( ! is_array( $last ) || $last['hint'] !== $hint || $last['proxy'] !== $proxy || $last['time'] < time() - HOUR_IN_SECONDS ) {
			update_option( 'ofr_client_seen', $data, false );
		}
	}

	/* ---------- Guest id ---------- */

	private static function sign( $id ) {
		return substr( hash_hmac( 'sha256', 'ofr-guest|' . $id, wp_salt( 'nonce' ) ), 0, 24 );
	}

	/** The signed id stored in this browser, or '' when missing or forged. */
	public static function guest_id() {
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( ! preg_match( '/^([a-f0-9]{24})\.([a-f0-9]{24})$/', $cookie, $m ) ) return '';
		return hash_equals( self::sign( $m[1] ), $m[2] ) ? $m[1] : '';
	}

	/** Gives a guest browser its id (called from the uncached session endpoint). */
	public static function ensure_guest_cookie() {
		if ( is_user_logged_in() || '' !== self::guest_id() || headers_sent() ) return;
		$id    = bin2hex( random_bytes( 12 ) );
		$value = $id . '.' . self::sign( $id );
		setcookie( self::COOKIE, $value, array(
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => COOKIEPATH ?: '/',
			'domain'   => COOKIE_DOMAIN ?: '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		) );
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * Limit buckets for this visitor. Members count per account. Guests count per
	 * browser (signed cookie) and, as a net against clearing cookies, per IP with
	 * a larger allowance, because many shoppers share one mobile-carrier IP (CGNAT).
	 *
	 * @return array { primary: string, shared: string[] } — shared keys get the larger allowance.
	 */
	public static function keys() {
		if ( is_user_logged_in() ) {
			$primary = 'user:' . get_current_user_id();
			return array( 'primary' => $primary, 'shared' => array() );
		}
		$guest  = self::guest_id();
		$shared = array( 'ip:' . self::ip() );
		return array( 'primary' => $guest ? 'guest:' . $guest : 'ip:' . self::ip(), 'shared' => $guest ? $shared : array() );
	}

	/** How many times a guest's per-person allowance one IP may use in total. */
	public static function shared_multiplier() {
		return max( 1, (int) apply_filters( 'ofr_shared_ip_multiplier', 4 ) );
	}
}
