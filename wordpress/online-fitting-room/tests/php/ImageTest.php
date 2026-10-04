<?php
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase {
	private function file( $bytes, $name = 'x' ) {
		$path = tempnam( sys_get_temp_dir(), 'ofr' );
		file_put_contents( $path, $bytes );
		return $path;
	}

	public function test_sniff_by_signature_not_name() {
		$this->assertSame( 'jpeg', OFR_Image::sniff( $this->file( "\xFF\xD8\xFF\xE0rest" ), 'photo.png' ) );
		$this->assertSame( 'png', OFR_Image::sniff( $this->file( "\x89PNG\r\n\x1a\n" ) ) );
		$this->assertSame( 'webp', OFR_Image::sniff( $this->file( 'RIFF0000WEBPVP8 ' ) ) );
		$this->assertSame( 'gif', OFR_Image::sniff( $this->file( 'GIF89a' ) ) );
		$this->assertSame( 'heic', OFR_Image::sniff( $this->file( "\0\0\0\x18ftypheic\0\0\0\0mif1heic" ) ) );
		$this->assertSame( 'avif', OFR_Image::sniff( $this->file( "\0\0\0\x1cftypavif\0\0\0\0avifmif1" ) ) );
		$this->assertSame( 'raw', OFR_Image::sniff( $this->file( "\0\0\0\x18ftypcrx \0\0\0\0crx " ) ) );
		$this->assertSame( 'tiff', OFR_Image::sniff( $this->file( "II*\0" ), 'scan.tif' ) );
		$this->assertSame( 'raw', OFR_Image::sniff( $this->file( "II*\0" ), 'IMG_1.CR2' ) );
		$this->assertSame( 'svg', OFR_Image::sniff( $this->file( '<svg xmlns="http://www.w3.org/2000/svg">' ) ) );
		$this->assertSame( '', OFR_Image::sniff( $this->file( 'hello world' ) ) );
	}

	private function segment( $marker, $payload ) {
		return "\xFF" . chr( $marker ) . pack( 'n', strlen( $payload ) + 2 ) . $payload;
	}

	private function jpeg() {
		// SOI, APP0 JFIF, DQT, SOF0, DHT, SOS + entropy data (with FF00 stuffing), EOI.
		return "\xFF\xD8" . $this->segment( 0xE0, "JFIF\0\x01\x01\0\0\x01\0\x01\0\0" ) . $this->segment( 0xDB, str_repeat( "\x01", 65 ) )
			. $this->segment( 0xC0, "\x08\0\x10\0\x10\x01\x01\x11\0" ) . $this->segment( 0xC4, str_repeat( "\0", 17 ) )
			. $this->segment( 0xDA, "\x01\x01\0\0\x3F\0" ) . "\x12\xFF\x00\x34\xFF\xD0\x56" . "\xFF\xD9";
	}

	public function test_strip_removes_every_metadata_segment_and_trailing_images() {
		$clean = $this->jpeg();
		$dirty = substr( $clean, 0, 2 ) . $this->segment( 0xE1, "Exif\0\0GPS-35.68,51.38" ) . $this->segment( 0xE1, 'http://ns.adobe.com/xap/1.0/' . "\0<x:xmpmeta/>" )
			. $this->segment( 0xED, "Photoshop 3.0\0IPTC" ) . $this->segment( 0xFE, 'comment' ) . $this->segment( 0xE2, "ICC_PROFILE\0\x01\x01icc" )
			. $this->segment( 0xE2, "MPF\0secondary" ) . substr( $clean, 2 ) . "\xFF\xD8" . $this->segment( 0xE1, "Exif\0\0depth-map" ) . "\xFF\xD9";
		$out = OFR_Image::strip_jpeg_metadata( $dirty );
		$this->assertIsString( $out );
		foreach ( array( 'GPS', 'xmpmeta', 'IPTC', 'comment', 'MPF', 'depth-map' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $out );
		}
		$this->assertStringContainsString( 'ICC_PROFILE', $out, 'The colour profile is kept.' );
		$this->assertStringContainsString( "\x12\xFF\x00\x34\xFF\xD0\x56", $out, 'Entropy data passes through untouched.' );
		$this->assertSame( "\xFF\xD9", substr( $out, -2 ) );
	}

	public function test_strip_rejects_non_jpeg_and_truncated_files() {
		$this->assertFalse( OFR_Image::strip_jpeg_metadata( 'not a jpeg' ) );
		$this->assertFalse( OFR_Image::strip_jpeg_metadata( substr( $this->jpeg(), 0, 30 ) ) );
	}

	public function test_labels() {
		$this->assertSame( 'HEIC', OFR_Image::label( 'heic' ) );
		$this->assertSame( 'JPEG XL', OFR_Image::label( 'jxl' ) );
		$this->assertSame( 'XYZ', OFR_Image::label( 'xyz' ) );
	}
}
