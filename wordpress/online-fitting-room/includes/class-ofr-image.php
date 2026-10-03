<?php
/**
 * Turns any supported image into something the API accepts (JPG/PNG/WEBP within
 * the size limits): format detection by file signature, then conversion with
 * Imagick or, failing that, GD.
 *
 * Only whitelisted formats are ever decoded, and Imagick is always told the
 * exact coder to use, so a crafted file cannot pick another ImageMagick coder
 * (SVG, MSL, PS, …). SVG is never decoded on the server; browsers rasterise it.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Image {
	const RAW_EXTENSIONS = array( '3fr', 'arw', 'cr2', 'crw', 'dcr', 'dng', 'erf', 'iiq', 'k25', 'kdc', 'mef', 'mrw', 'nef', 'nrw', 'orf', 'pef', 'raf', 'raw', 'rw2', 'sr2', 'srf', 'x3f' );

	/** Format key => [ label, ImageMagick coder, GD loader or '' ]. */
	private static function formats() {
		return array(
			'jpeg' => array( 'JPG', 'jpeg', 'imagecreatefromjpeg' ),
			'png'  => array( 'PNG', 'png', 'imagecreatefrompng' ),
			'webp' => array( 'WEBP', 'webp', 'imagecreatefromwebp' ),
			'gif'  => array( 'GIF', 'gif', 'imagecreatefromgif' ),
			'bmp'  => array( 'BMP', 'bmp', 'imagecreatefrombmp' ),
			'avif' => array( 'AVIF', 'avif', 'imagecreatefromavif' ),
			'heic' => array( 'HEIC', 'heic', '' ),
			'tiff' => array( 'TIFF', 'tiff', '' ),
			'jxl'  => array( 'JPEG XL', 'jxl', '' ),
			'psd'  => array( 'PSD', 'psd', '' ),
			'ico'  => array( 'ICO', 'ico', '' ),
			'raw'  => array( 'RAW', 'dng', '' ),
		);
	}

	public static function label( $format ) {
		return self::formats()[ $format ][0] ?? strtoupper( (string) $format );
	}

	/**
	 * Detects the format from the file signature (never from the name or the
	 * browser-reported type, which are unreliable for HEIC and RAW files).
	 *
	 * @return string Format key, 'svg', or '' when unknown.
	 */
	public static function sniff( $path, $filename = '' ) {
		$h = (string) file_get_contents( $path, false, null, 0, 64 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		if ( 0 === strncmp( $h, "\xFF\xD8\xFF", 3 ) ) return 'jpeg';
		if ( 0 === strncmp( $h, "\x89PNG", 4 ) ) return 'png';
		if ( 0 === strncmp( $h, 'GIF8', 4 ) ) return 'gif';
		if ( 0 === strncmp( $h, 'RIFF', 4 ) && 'WEBP' === substr( $h, 8, 4 ) ) return 'webp';
		if ( 0 === strncmp( $h, 'BM', 2 ) ) return 'bmp';
		if ( 'ftyp' === substr( $h, 4, 4 ) ) {
			$brands = substr( $h, 8, 56 );
			if ( preg_match( '/avi[fs]/', $brands ) ) return 'avif';
			if ( false !== strpos( $brands, 'crx ' ) ) return 'raw'; // Canon CR3.
			if ( preg_match( '/hei[cxms]|hev[cxms]|mif1|msf1/', $brands ) ) return 'heic';
		}
		if ( 0 === strncmp( $h, "\xFF\x0A", 2 ) || 'JXL ' === substr( $h, 4, 4 ) ) return 'jxl';
		if ( 0 === strncmp( $h, '8BPS', 4 ) ) return 'psd';
		if ( 0 === strncmp( $h, 'FUJIFILMCCD-RAW', 15 ) || 0 === strncmp( $h, 'IIRO', 4 ) || 0 === strncmp( $h, "IIU\0", 4 ) ) return 'raw';
		if ( 0 === strncmp( $h, "II*\0", 4 ) || 0 === strncmp( $h, "MM\0*", 4 ) ) return in_array( $ext, self::RAW_EXTENSIONS, true ) ? 'raw' : 'tiff';
		if ( 0 === strncmp( $h, "\0\0\1\0", 4 ) ) return 'ico';
		if ( preg_match( '/^\s*(<\?xml|<svg)/i', $h ) ) return 'svg';
		return '';
	}

	private static function imagick_can( $coder ) {
		static $cache = array();
		if ( ! class_exists( 'Imagick' ) || ! apply_filters( 'ofr_use_imagick', true ) ) return false;
		if ( ! isset( $cache[ $coder ] ) ) {
			try {
				$cache[ $coder ] = (bool) Imagick::queryFormats( strtoupper( $coder ) );
			} catch ( Exception $e ) {
				$cache[ $coder ] = false;
			}
		}
		return $cache[ $coder ];
	}

	private static function gd_can( $format ) {
		$loader = self::formats()[ $format ][2] ?? '';
		if ( ! $loader || ! function_exists( $loader ) ) return false;
		$types = function_exists( 'imagetypes' ) ? imagetypes() : 0;
		$flags = array( 'jpeg' => 'IMG_JPG', 'png' => 'IMG_PNG', 'webp' => 'IMG_WEBP', 'gif' => 'IMG_GIF', 'bmp' => 'IMG_BMP', 'avif' => 'IMG_AVIF' );
		return isset( $flags[ $format ] ) && defined( $flags[ $format ] ) && ( $types & constant( $flags[ $format ] ) );
	}

	public static function server_can( $format ) {
		$def = self::formats()[ $format ] ?? null;
		return $def && ( self::imagick_can( $def[1] ) || self::gd_can( $format ) );
	}

	/** Format keys this server can convert (shown to admins, used by the browser to decide). */
	public static function server_formats() {
		return array_values( array_filter( array_keys( self::formats() ), array( __CLASS__, 'server_can' ) ) );
	}

	/**
	 * @param string $path      Readable image file.
	 * @param string $filename  Original name (only a hint for camera RAW files).
	 * @param int    $max_dim   Longest side of the output.
	 * @param int    $max_bytes Largest file passed through unchanged.
	 * @return array|WP_Error { bytes, mime, format }
	 */
	public static function normalize( $path, $filename, $max_dim, $max_bytes ) {
		$format = self::sniff( $path, $filename );
		if ( '' === $format || 'svg' === $format ) {
			return new WP_Error( 'ofr_unknown_format', 'Unknown or disallowed image format.', array( 'format' => $format ) );
		}
		// A JPG that already fits (the browser normally sends one) passes through untouched. PNG/WEBP
		// are re-encoded so transparent areas become white instead of reaching the API as alpha.
		if ( 'jpeg' === $format ) {
			$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( ! $info ) return new WP_Error( 'ofr_convert_failed', 'Corrupt image.', array( 'format' => $format ) );
			$rotated = self::exif_orientation( $path ) > 1;
			if ( ! $rotated && max( $info[0], $info[1] ) <= $max_dim && filesize( $path ) <= $max_bytes ) {
				// Lossless, but without EXIF/XMP/IPTC: no GPS position, camera serial or name leaves the site.
				$clean = self::strip_jpeg_metadata( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( false !== $clean ) return array( 'bytes' => $clean, 'mime' => 'image/jpeg', 'format' => $format );
			}
		}
		if ( ! self::server_can( $format ) ) {
			return new WP_Error( 'ofr_unsupported_on_server', 'No image library on this server can read ' . self::label( $format ) . '.', array( 'format' => $format ) );
		}
		$coder = self::formats()[ $format ][1];
		$ext   = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		if ( 'raw' === $format && in_array( $ext, self::RAW_EXTENSIONS, true ) && self::imagick_can( $ext ) ) $coder = $ext; // e.g. cr2:, nef:
		$bytes = self::imagick_can( $coder ) ? self::with_imagick( $path, $coder, $max_dim ) : self::with_gd( $path, $format, $max_dim );
		if ( is_wp_error( $bytes ) ) {
			$bytes->add_data( array( 'format' => $format ) );
			return $bytes;
		}
		return array( 'bytes' => $bytes, 'mime' => 'image/jpeg', 'format' => $format );
	}

	private static function with_imagick( $path, $coder, $max_dim ) {
		wp_raise_memory_limit( 'image' );
		try {
			foreach ( array( 'RESOURCETYPE_MEMORY' => 256 * MB_IN_BYTES, 'RESOURCETYPE_MAP' => 512 * MB_IN_BYTES, 'RESOURCETYPE_AREA' => 200000000 ) as $type => $limit ) {
				if ( defined( 'Imagick::' . $type ) ) Imagick::setResourceLimit( constant( 'Imagick::' . $type ), $limit );
			}
			$image = new Imagick();
			if ( 'jpeg' === $coder ) $image->setOption( 'jpeg:size', ( $max_dim * 2 ) . 'x' . ( $max_dim * 2 ) );
			$image->readImage( $coder . ':' . $path ); // Explicit coder: the file cannot choose another one.
			$image->setFirstIterator(); // Animated GIF, multi-page TIFF, PSD: the first frame / composite.
			$frame = $image->getImage();
			$image->clear();
			if ( method_exists( $frame, 'autoOrient' ) ) {
				$frame->autoOrient();
			} else {
				$turns = array( Imagick::ORIENTATION_BOTTOMRIGHT => 180, Imagick::ORIENTATION_RIGHTTOP => 90, Imagick::ORIENTATION_LEFTBOTTOM => -90 );
				$o     = $frame->getImageOrientation();
				if ( isset( $turns[ $o ] ) ) $frame->rotateImage( 'white', $turns[ $o ] );
				$frame->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
			}
			if ( Imagick::COLORSPACE_CMYK === $frame->getImageColorspace() ) $frame->transformImageColorspace( Imagick::COLORSPACE_SRGB );
			$frame->setImageBackgroundColor( 'white' );
			if ( defined( 'Imagick::ALPHACHANNEL_REMOVE' ) ) $frame->setImageAlphaChannel( Imagick::ALPHACHANNEL_REMOVE );
			$frame = $frame->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
			if ( max( $frame->getImageWidth(), $frame->getImageHeight() ) > $max_dim ) {
				$frame->thumbnailImage( $max_dim, $max_dim, true );
			}
			$frame->stripImage();
			$frame->setImageFormat( 'jpeg' );
			$frame->setImageCompressionQuality( 90 );
			$blob = $frame->getImageBlob();
			$frame->clear();
			return $blob;
		} catch ( Exception $e ) {
			return new WP_Error( 'ofr_convert_failed', 'Imagick: ' . $e->getMessage() );
		}
	}

	private static function with_gd( $path, $format, $max_dim ) {
		wp_raise_memory_limit( 'image' );
		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $size && $size[0] * $size[1] > 60000000 ) return new WP_Error( 'ofr_convert_failed', 'GD: image too large to decode safely.' );
		$loader = self::formats()[ $format ][2];
		$source = @$loader( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $source ) return new WP_Error( 'ofr_convert_failed', 'GD could not decode the image.' );
		if ( 'jpeg' === $format ) {
			$turns = array( 3 => 180, 6 => -90, 8 => 90 );
			$o     = self::exif_orientation( $path );
			if ( isset( $turns[ $o ] ) ) $source = imagerotate( $source, $turns[ $o ], 0 );
		}
		$w     = imagesx( $source );
		$h     = imagesy( $source );
		$scale = min( 1, $max_dim / max( $w, $h ) );
		$nw    = max( 1, (int) round( $w * $scale ) );
		$nh    = max( 1, (int) round( $h * $scale ) );
		$out   = imagecreatetruecolor( $nw, $nh );
		imagefill( $out, 0, 0, imagecolorallocate( $out, 255, 255, 255 ) ); // Transparent areas become white.
		imagecopyresampled( $out, $source, 0, 0, 0, 0, $nw, $nh, $w, $h );
		ob_start();
		imagejpeg( $out, null, 90 );
		return ob_get_clean();
	}

	/**
	 * Removes every metadata segment from a JPEG without re-encoding it: EXIF
	 * (GPS, device, date), XMP, IPTC/Photoshop, comments and the extra images
	 * phones append after the main one (depth and gain maps carry their own
	 * EXIF). Kept: JFIF, the ICC colour profile and Adobe's colour transform
	 * flag, which decoders need to show colours correctly.
	 *
	 * @return string|false Clean JPEG, or false when the file is not a well-formed JPEG.
	 */
	public static function strip_jpeg_metadata( $data ) {
		$len = strlen( $data );
		if ( $len < 4 || "\xFF\xD8" !== substr( $data, 0, 2 ) ) return false;
		$out = "\xFF\xD8";
		$pos = 2;
		while ( $pos < $len ) {
			if ( "\xFF" !== $data[ $pos ] ) return false;
			while ( $pos < $len && "\xFF" === $data[ $pos ] ) $pos++; // Fill bytes.
			if ( $pos >= $len ) return false;
			$marker = ord( $data[ $pos ] );
			$pos++;
			if ( 0xD9 === $marker ) return $out . "\xFF\xD9";
			if ( ( $marker >= 0xD0 && $marker <= 0xD7 ) || 0x01 === $marker ) { // No length field.
				$out .= "\xFF" . chr( $marker );
				continue;
			}
			if ( $pos + 2 > $len ) return false;
			$size = ( ord( $data[ $pos ] ) << 8 ) | ord( $data[ $pos + 1 ] );
			if ( $size < 2 || $pos + $size > $len ) return false;
			$segment = substr( $data, $pos, $size );
			$pos    += $size;
			if ( 0xDA === $marker ) {
				// Start of scan: entropy-coded data follows. The first EOI ends the main image; anything after it is dropped.
				$end = self::find_eoi( $data, $pos );
				if ( false === $end ) return false;
				return $out . "\xFF\xDA" . $segment . substr( $data, $pos, $end - $pos ) . "\xFF\xD9";
			}
			$keep = 0xE0 === $marker // APP0 JFIF.
				|| ( 0xE2 === $marker && 0 === strncmp( substr( $segment, 2 ), "ICC_PROFILE\0", 12 ) )
				|| 0xEE === $marker // APP14 Adobe.
				|| ( $marker < 0xE0 || ( $marker > 0xEF && 0xFE !== $marker ) ); // Tables, frame headers… (not APPn, not COM).
			if ( $keep ) $out .= "\xFF" . chr( $marker ) . $segment;
		}
		return false;
	}

	/** Offset of the EOI marker that ends entropy-coded data starting at $pos (FF00 stuffing and RSTn skipped; DHT/SOS of progressive scans pass through). */
	private static function find_eoi( $data, $pos ) {
		$len = strlen( $data );
		while ( false !== ( $pos = strpos( $data, "\xFF", $pos ) ) ) {
			if ( $pos + 1 >= $len ) return false;
			$next = ord( $data[ $pos + 1 ] );
			if ( 0xD9 === $next ) return $pos;
			$pos += 2;
			// Progressive JPEGs: segments between scans (DHT, SOS, DRI…) have lengths; skip their headers so their bytes are not misread.
			if ( 0x00 !== $next && 0xFF !== $next && ! ( $next >= 0xD0 && $next <= 0xD7 ) && $pos + 2 <= $len ) {
				$pos += ( ord( $data[ $pos ] ) << 8 ) | ord( $data[ $pos + 1 ] );
			} elseif ( 0xFF === $next ) {
				$pos--; // Fill byte: look at it again.
			}
		}
		return false;
	}

	private static function exif_orientation( $path ) {
		if ( ! function_exists( 'exif_read_data' ) ) return 1;
		$exif = @exif_read_data( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return (int) ( $exif['Orientation'] ?? 1 );
	}
}
