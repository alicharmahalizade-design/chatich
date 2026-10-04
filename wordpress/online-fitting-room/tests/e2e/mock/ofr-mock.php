<?php
/* Test-only: fakes the try-on API and can simulate an Nginx-style 413. */
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( 0 !== strpos( $url, 'https://vton-api.ayaitech.com' ) ) return $pre;
	$path = substr( $url, strlen( 'https://vton-api.ayaitech.com' ) );
	$json = function ( $code, $data ) { return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( $data ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null ); };
	$png  = file_get_contents( WP_CONTENT_DIR . '/mock-result.png' );
	if ( 'POST' === $args['method'] && '/v1/try-ons' === $path && 'nocredit' === get_option( 'mock_mode' ) ) return $json( 402, array( 'detail' => 'insufficient credits' ) );
	if ( 'POST' === $args['method'] && '/v1/try-ons' === $path ) {
		// Keep the person image exactly as the service would receive it.
		if ( preg_match( '/name="person_image"; filename="[^"]*"\r\nContent-Type: [^\r]+\r\n\r\n(.*?)\r\n--/s', $args['body'], $m ) ) file_put_contents( WP_CONTENT_DIR . '/mock-person.bin', $m[1] );
		if ( preg_match( '/name="garment_image"; filename="[^"]*"\r\nContent-Type: [^\r]+\r\n\r\n(.*?)\r\n--/s', $args['body'], $g ) ) update_option( 'mock_garment_md5', md5( $g[1] ) );
		update_option( 'mock_posts', (int) get_option( 'mock_posts', 0 ) + 1 );
		return $json( 201, array( 'id' => 'pred_' . wp_generate_password( 8, false ) ) );
	}
	if ( '/v1/account' === $path ) return $json( 200, array( 'data' => array( 'credits_remaining' => (int) get_option( 'mock_credits', 120 ) ) ) );
	if ( preg_match( '#^/v1/try-ons/([^/]+)/result$#', $path ) ) {
		return array( 'headers' => array( 'content-type' => 'image/png' ), 'body' => $png, 'response' => array( 'code' => 200, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}
	if ( preg_match( '#^/v1/try-ons/([^/]+)$#', $path, $m ) ) {
		$k = 'mock_polls_' . $m[1]; $n = (int) get_option( $k, 0 ) + 1; update_option( $k, $n );
		$mode = get_option( 'mock_mode', 'ok' );
		if ( 'fail' === $mode && $n >= 2 ) return $json( 200, array( 'status' => 'failed', 'error' => 'mock' ) );
		$need = (int) get_option( 'mock_polls_needed', 2 );
		return $n < $need ? $json( 200, array( 'status' => 'processing' ) ) : $json( 200, array( 'status' => 'succeeded', 'result' => array( 'sha256' => hash( 'sha256', $png ) ) ) );
	}
	return $json( 404, array( 'detail' => 'not found' ) );
}, 10, 3 );

// Nginx imitation: an HTML 413 page for big bodies, before WordPress answers.
add_action( 'init', function () {
	$limit = (int) get_option( 'mock_413', 0 );
	$start = ( $_POST['action'] ?? '' ) === 'ofr_start' || false !== strpos( urldecode( $_SERVER['REQUEST_URI'] ?? '' ), 'ofr/v1/start' );
	if ( $limit && $start && isset( $_FILES['avatar'] ) && $_FILES['avatar']['size'] > $limit ) {
		update_option( 'mock_413_hits', (int) get_option( 'mock_413_hits', 0 ) + 1 );
		status_header( 413 ); header( 'Content-Type: text/html' ); echo '<html><body><h1>413 Request Entity Too Large</h1><hr>nginx</body></html>'; exit;
	}
}, 0 );
add_action( 'admin_init', function () {
	if ( get_option( 'mock_413_probe' ) && wp_doing_ajax() && ( $_POST['action'] ?? '' ) === 'ofr_probe' ) { status_header( 413 ); echo '<html>413</html>'; exit; }
}, 0 );

// Security-plugin imitation: REST closed to visitors.
add_filter( 'rest_authentication_errors', function ( $result ) {
	if ( get_option( 'mock_block_rest' ) && false !== strpos( urldecode( $_SERVER['REQUEST_URI'] ?? '' ), 'ofr/v1' ) ) return new WP_Error( 'rest_disabled', 'REST disabled', array( 'status' => 401 ) );
	return $result;
} );
// Mail capture.
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$log = (array) get_option( 'mock_mails', array() ); $log[] = array( 'to' => $atts['to'], 'subject' => $atts['subject'], 'message' => $atts['message'] ); update_option( 'mock_mails', $log, false );
	return true;
}, 10, 2 );
// SMS webhook receiver.
add_action( 'rest_api_init', function () {
	register_rest_route( 'mock/v1', '/sms', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function ( $r ) {
		$log = (array) get_option( 'mock_sms', array() ); $log[] = $r->get_json_params(); update_option( 'mock_sms', $log, false );
		return array( 'ok' => true );
	} ) );
} );
add_filter( 'http_request_host_is_external', '__return_true' );
