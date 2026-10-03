<?php
/**
 * Optional bot protection for guests before a paid try-on is started.
 *
 * - pow:       invisible built-in challenge. The browser computes a small
 *              proof-of-work (about a second, while the shopper picks a photo)
 *              that the server checks for free. It needs no outside service, so
 *              it also works on hosts inside Iran, and it makes every scripted
 *              request cost real CPU time. Each challenge is signed, expires and
 *              can be used once.
 * - turnstile: Cloudflare Turnstile (site key + secret key from Cloudflare).
 * - off.
 *
 * Other providers (e.g. a CDN's own captcha) can plug in with the
 * ofr_verify_captcha filter.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Captcha {
	const POW_TTL = 900;

	public static function modes() {
		return array(
			'pow'       => __( 'چالش داخلی نامرئی (پیشنهادی، بدون سرویس خارجی)', 'online-fitting-room' ),
			'turnstile' => __( 'Cloudflare Turnstile', 'online-fitting-room' ),
			'off'       => __( 'خاموش', 'online-fitting-room' ),
		);
	}

	public static function mode() {
		$s    = Online_Fitting_Room::settings();
		$mode = $s['captcha'] ?? 'pow';
		if ( 'turnstile' === $mode && ( '' === trim( $s['turnstile_site'] ) || '' === trim( $s['turnstile_secret'] ) ) ) return 'pow'; // Keys missing: keep protection on.
		return array_key_exists( $mode, self::modes() ) ? $mode : 'pow';
	}

	/** Members already count against their own account limit; the check targets anonymous traffic. */
	public static function required() {
		return (bool) apply_filters( 'ofr_captcha_required', 'off' !== self::mode() && ! is_user_logged_in() );
	}

	public static function difficulty() {
		return min( 24, max( 8, (int) apply_filters( 'ofr_pow_difficulty', 16 ) ) );
	}

	private static function sign( $body ) {
		return substr( hash_hmac( 'sha256', 'ofr-pow|' . $body, wp_salt( 'nonce' ) ), 0, 32 );
	}

	public static function challenge() {
		$body = 'v1.' . ( time() + self::POW_TTL ) . '.' . self::difficulty() . '.' . bin2hex( random_bytes( 12 ) );
		return $body . '.' . self::sign( $body );
	}

	public static function leading_zero_bits( $binary ) {
		$bits = 0;
		$len  = strlen( $binary );
		for ( $i = 0; $i < $len; $i++ ) {
			$byte = ord( $binary[ $i ] );
			if ( 0 === $byte ) {
				$bits += 8;
				continue;
			}
			while ( 0 === ( $byte & 0x80 ) ) {
				$bits++;
				$byte <<= 1;
			}
			break;
		}
		return $bits;
	}

	/** Checks a solved challenge without touching storage (signature, expiry, work). */
	public static function check_pow( $challenge, $nonce ) {
		if ( ! is_string( $challenge ) || ! preg_match( '/^(v1\.(\d{10,})\.(\d{1,2})\.([a-f0-9]{24}))\.([a-f0-9]{32})$/', $challenge, $m ) ) return false;
		if ( ! hash_equals( self::sign( $m[1] ), $m[5] ) ) return false;
		if ( (int) $m[2] < time() ) return false;
		if ( ! is_string( $nonce ) || ! preg_match( '/^\d{1,12}$/', $nonce ) ) return false;
		return self::leading_zero_bits( hash( 'sha256', $challenge . ':' . $nonce, true ) ) >= (int) $m[3] ? $m[4] : false;
	}

	public static function verify_pow( $challenge, $nonce ) {
		$id = self::check_pow( $challenge, $nonce );
		return false !== $id && OFR_Quota::once( 'pow|' . $id, self::POW_TTL + MINUTE_IN_SECONDS );
	}

	public static function verify_turnstile( $token ) {
		if ( ! is_string( $token ) || '' === $token || strlen( $token ) > 2048 ) return false;
		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => trim( Online_Fitting_Room::settings()['turnstile_secret'] ),
				'response' => $token,
				'remoteip' => OFR_Client::ip(),
			),
		) );
		if ( is_wp_error( $response ) ) {
			OFR_Api::log_error( 'turnstile', 0, $response->get_error_message() );
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['success'] ) ) OFR_Api::log_error( 'turnstile', (int) wp_remote_retrieve_response_code( $response ), $data['error-codes'] ?? 'rejected' );
		return ! empty( $data['success'] );
	}

	/**
	 * @param array $request The posted fields (already unslashed).
	 * @return true|WP_Error
	 */
	public static function verify( array $request ) {
		if ( ! self::required() ) return true;
		$mode = self::mode();
		if ( 'pow' === $mode ) {
			$ok = self::verify_pow( sanitize_text_field( $request['pow'] ?? '' ), sanitize_text_field( $request['pow_nonce'] ?? '' ) );
		} elseif ( 'turnstile' === $mode ) {
			$ok = self::verify_turnstile( sanitize_text_field( $request['captcha'] ?? '' ) );
		} else {
			$ok = true;
		}
		$ok = (bool) apply_filters( 'ofr_verify_captcha', $ok, $mode, $request );
		return $ok ? true : new WP_Error( 'ofr_captcha', __( 'تأیید امنیتی انجام نشد؛ لطفاً دوباره روی «ساخت تصویر پرو» بزنید.', 'online-fitting-room' ) );
	}

	/** What the browser needs; a fresh challenge with every response. */
	public static function client_config() {
		if ( ! self::required() ) return array( 'mode' => 'off' );
		$mode = self::mode();
		if ( 'pow' === $mode ) return array( 'mode' => 'pow', 'challenge' => self::challenge() );
		if ( 'turnstile' === $mode ) return array( 'mode' => 'turnstile', 'siteKey' => trim( Online_Fitting_Room::settings()['turnstile_site'] ) );
		return array( 'mode' => $mode );
	}
}
