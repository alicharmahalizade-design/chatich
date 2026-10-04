<?php
/**
 * REST API (wp-json/ofr/v1/...). Lighter than admin-ajax.php and not blocked
 * by the security plugins that close admin-ajax to visitors; the browser falls
 * back to admin-ajax by itself when a site disables the REST API.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Rest {
	const NS = 'ofr/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		foreach ( array( 'session', 'start', 'status' ) as $endpoint ) {
			register_rest_route(
				self::NS,
				'/' . $endpoint,
				array(
					'methods'             => 'POST',
					'callback'            => function () use ( $endpoint ) {
						return OFR_Rest::dispatch( $endpoint );
					},
					// Public endpoints: each checks its own nonce, limits and bot protection.
					'permission_callback' => '__return_true',
				)
			);
		}
		register_rest_route(
			self::NS,
			'/result',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					Online_Fitting_Room::instance()->via_rest = true;
					Online_Fitting_Room::instance()->handle_result(); // Streams the image and exits.
				},
				'permission_callback' => '__return_true', // The job token is the credential.
			)
		);
		register_rest_route(
			self::NS,
			'/webhook/(?P<secret>[A-Za-z0-9]{32})',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function dispatch( $endpoint ) {
		$plugin           = Online_Fitting_Room::instance();
		$plugin->via_rest = true;
		$response         = $plugin->run( $endpoint );
		$rest             = new WP_REST_Response(
			array(
				'success' => $response->success,
				'data'    => $response->data,
			),
			$response->status
		);
		$rest->header( 'Cache-Control', 'no-store, private' );
		return $rest;
	}

	/* ---------- Webhook ---------- */

	public static function webhook_secret() {
		$secret = get_option( 'ofr_webhook_secret' );
		if ( ! is_string( $secret ) || ! preg_match( '/^[A-Za-z0-9]{32}$/', $secret ) ) {
			$secret = wp_generate_password( 32, false, false );
			update_option( 'ofr_webhook_secret', $secret, false );
		}
		return $secret;
	}

	public static function webhook_url() {
		return rest_url( self::NS . '/webhook/' . self::webhook_secret() );
	}

	/**
	 * Called by the try-on service when a job changes (if it supports
	 * callbacks). The payload is never trusted: it only marks the job so the
	 * next poll checks the service right away instead of waiting.
	 */
	public static function webhook( WP_REST_Request $request ) {
		if ( ! hash_equals( self::webhook_secret(), (string) $request['secret'] ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 404 );
		}
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : $request->get_body_params();
		$id   = '';
		foreach ( array( array( 'id' ), array( 'prediction_id' ), array( 'data', 'id' ), array( 'try_on', 'id' ) ) as $path ) {
			$value = $body;
			foreach ( $path as $key ) {
				$value = is_array( $value ) && isset( $value[ $key ] ) ? $value[ $key ] : null;
			}
			if ( is_string( $value ) && '' !== $value ) {
				$id = sanitize_text_field( $value );
				break;
			}
		}
		$woken = $id ? OFR_Storage::wake( $id ) : 0;
		update_option(
			'ofr_webhook_last',
			array(
				'time'    => time(),
				'matched' => $woken,
			),
			false
		);
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'matched' => $woken,
			),
			200
		);
	}

	/** Adds the callback URL to new try-ons when the admin enabled it (field name per the service's docs). */
	public static function api_fields( $fields ) {
		if ( 'yes' === Online_Fitting_Room::settings()['webhook'] ) {
			$fields[ (string) apply_filters( 'ofr_webhook_field', 'webhook_url' ) ] = self::webhook_url();
		}
		return $fields;
	}
}
