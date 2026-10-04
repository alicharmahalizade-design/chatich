<?php
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {
	public function test_leading_zero_bits() {
		$this->assertSame( 0, OFR_Captcha::leading_zero_bits( "\x80" ) );
		$this->assertSame( 1, OFR_Captcha::leading_zero_bits( "\x40" ) );
		$this->assertSame( 8, OFR_Captcha::leading_zero_bits( "\x00\xFF" ) );
		$this->assertSame( 15, OFR_Captcha::leading_zero_bits( "\x00\x01" ) );
	}

	public function test_proof_of_work() {
		$challenge = OFR_Captcha::challenge();
		$difficulty = (int) explode( '.', $challenge )[2];
		$nonce = 0;
		while ( OFR_Captcha::leading_zero_bits( hash( 'sha256', $challenge . ':' . $nonce, true ) ) < $difficulty ) {
			++$nonce;
		}
		$this->assertNotFalse( OFR_Captcha::check_pow( $challenge, (string) $nonce ) );
		$this->assertFalse( OFR_Captcha::check_pow( str_replace( ".{$difficulty}.", '.8.', $challenge ), (string) $nonce ), 'Difficulty cannot be lowered.' );
		$this->assertFalse( OFR_Captcha::check_pow( $challenge, 'abc' ) );
		$expired = 'v1.1000000000.16.0123456789abcdef01234567';
		$this->assertFalse( OFR_Captcha::check_pow( $expired . '.' . str_repeat( '0', 32 ), '1' ) );
	}

	public function test_cidr_matching() {
		$this->assertTrue( OFR_Client::in_cidr( '104.16.5.9', '104.16.0.0/13' ) );
		$this->assertFalse( OFR_Client::in_cidr( '104.24.0.1', '104.16.0.0/13' ) );
		$this->assertTrue( OFR_Client::in_cidr( '2606:4700:10::1', '2606:4700::/32' ) );
		$this->assertFalse( OFR_Client::in_cidr( '1.2.3.4', '2606:4700::/32' ) );
		$this->assertTrue( OFR_Client::in_cidr( '5.6.7.8', '5.6.7.8' ) );
		$this->assertSame( array( '1.2.3.0/24', '2001:db8::/32', '9.9.9.9' ), OFR_Client::parse_cidrs( "1.2.3.0/24\nbad\n# c\n2001:db8::/129, 2001:db8::/32 9.9.9.9" ) );
	}

	public function test_result_encryption() {
		$token  = str_repeat( 'a', 32 );
		$image  = random_bytes( 3000 );
		$sealed = OFR_Storage::seal( $image, $token );
		$this->assertSame( $image, OFR_Storage::open( $sealed, $token ) );
		$this->assertFalse( OFR_Storage::open( $sealed, str_repeat( 'b', 32 ) ), 'Another token cannot open it.' );
		$sealed[100] = chr( ord( $sealed[100] ) ^ 1 );
		$this->assertFalse( OFR_Storage::open( $sealed, $token ), 'Tampering is detected.' );
	}

	/** @dataProvider mobiles */
	public function test_mobile_normalization( $input, $expected ) {
		$this->assertSame( $expected, OFR_Alerts::normalize_mobile( $input ) );
	}

	public function mobiles() {
		return array(
			array( '09121234567', '09121234567' ),
			array( '۰۹۱۲۱۲۳۴۵۶۷', '09121234567' ),
			array( '+98 912 123 4567', '09121234567' ),
			array( '00989121234567', '09121234567' ),
			array( '9121234567', '09121234567' ),
		);
	}

	public function test_balance_parsing() {
		$this->assertSame( 120, OFR_Alerts::find_number( array( 'data' => array( 'credits_remaining' => 120 ) ) ) );
		$this->assertSame( 7, OFR_Alerts::find_number( array( 'balance' => '7.9' ) ) );
		$this->assertNull( OFR_Alerts::find_number( array( 'ok' => true ) ) );
	}
}
