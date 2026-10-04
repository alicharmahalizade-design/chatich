<?php
/**
 * Files for the in-browser photo check (MediaPipe Pose Landmarker, Apache-2.0).
 *
 * They are large (about 17 MB), so they are not shipped in the plugin. The
 * browser loads them only when a shopper picks a photo: from the store's own
 * server once the admin has copied them there (one click in the settings —
 * best for visitors in Iran, where Google's CDN is filtered), otherwise from
 * jsDelivr and Google. If they cannot be loaded the check is simply skipped.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Pose {
	const VERSION = '1.0.1';
	const DIR     = 'ofr-pose';

	/** Local file name => remote source. */
	public static function files() {
		$npm = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@' . self::VERSION;
		return array(
			'vision_bundle.js'          => $npm . '/vision_bundle.mjs', // Saved as .js so every web server sends a JavaScript MIME type.
			'vision_wasm_internal.js'   => $npm . '/wasm/vision_wasm_internal.js',
			'vision_wasm_internal.wasm' => $npm . '/wasm/vision_wasm_internal.wasm',
			'pose_landmarker_lite.task' => 'https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task',
		);
	}

	public static function init() {
		add_action( 'wp_ajax_ofr_pose_download', array( __CLASS__, 'ajax_download' ) );
	}

	private static function dir() {
		$uploads = wp_upload_dir( null, false );
		return empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . self::DIR : '';
	}

	private static function url() {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['baseurl'] ) . self::DIR . '/';
	}

	public static function is_local() {
		$dir = self::dir();
		if ( '' === $dir ) {
			return false;
		}
		foreach ( array_keys( self::files() ) as $file ) {
			if ( ! is_file( $dir . '/' . $file ) || filesize( $dir . '/' . $file ) < 1000 ) {
				return false;
			}
		}
		return true;
	}

	/** @return array|false What the browser needs, or false when the check is off. */
	public static function config() {
		$mode = Online_Fitting_Room::settings()['pose_check'];
		if ( ! in_array( $mode, array( 'warn', 'block' ), true ) ) {
			return false;
		}
		$files = self::files();
		if ( self::is_local() ) {
			$base   = self::url();
			$assets = array(
				'module' => $base . 'vision_bundle.js',
				'loader' => $base . 'vision_wasm_internal.js',
				'binary' => $base . 'vision_wasm_internal.wasm',
				'model'  => $base . 'pose_landmarker_lite.task',
			);
		} else {
			$assets = array(
				'module' => $files['vision_bundle.js'],
				'loader' => $files['vision_wasm_internal.js'],
				'binary' => $files['vision_wasm_internal.wasm'],
				'model'  => $files['pose_landmarker_lite.task'],
			);
		}
		return array( 'mode' => $mode ) + (array) apply_filters( 'ofr_pose_assets', $assets );
	}

	/** Copies the files to uploads/ofr-pose (admin button). */
	public static function download() {
		$dir = self::dir();
		if ( '' === $dir || ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'ofr_pose_dir', __( 'پوشه uploads قابل نوشتن نیست.', 'online-fitting-room' ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}       foreach ( self::files() as $name => $url ) {
			$target   = $dir . '/' . $name;
			$response = wp_remote_get(
				$url,
				array(
					'timeout'  => 120,
					'stream'   => true,
					'filename' => $target . '.part',
				)
			);
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code || ! is_file( $target . '.part' ) || filesize( $target . '.part' ) < 1000 ) {
				if ( is_file( $target . '.part' ) ) {
					wp_delete_file( $target . '.part' );
				}
				/* translators: 1: file name, 2: error. */
				return new WP_Error( 'ofr_pose_fetch', sprintf( __( 'دانلود %1$s انجام نشد (%2$s). اگر هاست به اینترنت خارج دسترسی ندارد، فایل‌ها را دستی در wp-content/uploads/ofr-pose قرار دهید.', 'online-fitting-room' ), $name, is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code ) );
			}
			rename( $target . '.part', $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return true;
	}

	public static function remove() {
		$dir = self::dir();
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		foreach ( array_keys( self::files() ) as $file ) {
			if ( is_file( $dir . '/' . $file ) ) {
				wp_delete_file( $dir . '/' . $file );
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions -- empty plugin folder; failure is harmless.
	}

	public static function ajax_download() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'ofr_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی مجاز نیست.', 'online-fitting-room' ) ), 403 );
		}
		$result = self::download();
		is_wp_error( $result )
			? wp_send_json_error( array( 'message' => $result->get_error_message() ) )
			: wp_send_json_success( array( 'message' => __( 'فایل‌های بررسی عکس روی سرور سایت قرار گرفتند و از این پس از همین‌جا بارگذاری می‌شوند.', 'online-fitting-room' ) ) );
	}
}
