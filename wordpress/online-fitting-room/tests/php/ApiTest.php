<?php
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
	protected function tearDown(): void {
		Online_Fitting_Room::$settings['api_base'] = '';
		$GLOBALS['ofr_test_filters']               = array();
	}

	/** @dataProvider keys */
	public function test_clean_key( $pasted, $expected ) {
		$this->assertSame( $expected, OFR_Api::clean_key( $pasted ) );
	}

	public function keys() {
		return array(
			'plain'             => array( 'vton_live_abc123', 'vton_live_abc123' ),
			'spaces and breaks' => array( "  vton_live_abc123 \n", 'vton_live_abc123' ),
			'rtl marks'         => array( "\u{200F}vton_live_abc123\u{200E}", 'vton_live_abc123' ),
			'zero width + nbsp' => array( "vton_live_\u{200C}abc\u{00A0}123", 'vton_live_abc123' ),
			'quotes'            => array( '"vton_live_abc123"', 'vton_live_abc123' ),
			'bearer prefix'     => array( 'Bearer vton_live_abc123', 'vton_live_abc123' ),
		);
	}

	public function test_key_id_detection() {
		$this->assertTrue( OFR_Api::is_key_id( 'key_01ABC' ) );
		$this->assertFalse( OFR_Api::is_key_id( 'vton_live_x' ) );
	}

	/** @dataProvider errors */
	public function test_customer_error( $code, $status, $fragment ) {
		$error = OFR_Api::customer_error( $code );
		$this->assertSame( $status, $error['status'] );
		$this->assertStringContainsString( $fragment, $error['message'] );
	}

	public function errors() {
		return array(
			'transport'      => array( 0, 502, 'ارتباط' ),
			'bad photo'      => array( 422, 422, 'عکس' ),
			'unsupported'    => array( 415, 422, 'عکس' ),
			'too large'      => array( 413, 413, 'حجم' ),
			'no credit'      => array( 402, 503, 'ظرفیت' ),
			'busy'           => array( 429, 503, 'شلوغ' ),
			'not found'      => array( 404, 410, 'پیدا نشد' ),
			'bad key hidden' => array( 401, 503, 'موقتاً' ),
			'server'         => array( 500, 503, 'موقتاً' ),
		);
	}

	public function test_customer_error_never_leaks_vendor_or_key() {
		foreach ( array( 0, 400, 401, 402, 403, 404, 413, 415, 422, 429, 500, 502, 503 ) as $code ) {
			$message = OFR_Api::customer_error( $code )['message'];
			$this->assertStringNotContainsString( 'API', $message );
			$this->assertStringNotContainsString( 'ayaitech', $message );
		}
	}

	public function test_base_accepts_only_https_relays() {
		$this->assertSame( OFR_Api::BASE, OFR_Api::base() );
		Online_Fitting_Room::$settings['api_base'] = 'https://relay.example.ir/vton/';
		$this->assertSame( 'https://relay.example.ir/vton', OFR_Api::base() );
		$this->assertTrue( OFR_Api::uses_relay() );
		Online_Fitting_Room::$settings['api_base'] = 'http://relay.example.ir';
		$this->assertSame( OFR_Api::BASE, OFR_Api::base(), 'Plain http is refused.' );
		$this->assertFalse( OFR_Api::valid_base( 'https://user:pass@relay.example.ir' ) );
		$this->assertFalse( OFR_Api::valid_base( 'https://relay.example.ir/?x=1' ) );
	}

	public function test_key_encryption_round_trip() {
		$stored = OFR_Api::encrypt_key( 'vton_live_secret' );
		$this->assertStringStartsWith( OFR_Api::KEY_PREFIX, $stored );
		$this->assertStringNotContainsString( 'secret', $stored );
		$this->assertSame( 'vton_live_secret', OFR_Api::decrypt_key( $stored ) );
		$this->assertNotSame( $stored, OFR_Api::encrypt_key( 'vton_live_secret' ), 'Random nonce per encryption.' );
		$this->assertSame( 'plain_old_key', OFR_Api::decrypt_key( 'plain_old_key' ), 'Keys saved before 1.8 still work.' );
		$this->assertFalse( OFR_Api::decrypt_key( OFR_Api::KEY_PREFIX . 's.' . base64_encode( str_repeat( 'x', 40 ) ) ) );
	}
}
