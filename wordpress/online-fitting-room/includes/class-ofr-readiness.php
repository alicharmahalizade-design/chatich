<?php
/**
 * Product readiness checklist: which products give good try-ons, which are
 * hidden, and why. The checks only read data WordPress already has (attachment
 * metadata), so a page of 50 products is cheap.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Readiness {
	const PER_PAGE = 50;

	/**
	 * @return array { status: ready|warning|blocked|off, issues: array[] { level, text } }
	 */
	public static function check( $product ) {
		$issues = array();
		$id     = $product->get_id();
		if ( 'no' === get_post_meta( $id, Online_Fitting_Room::META_ENABLED, true ) ) {
			return array(
				'status' => 'off',
				'issues' => array(
					array(
						'level' => 'info',
						'text'  => __( 'پرو مجازی برای این محصول خاموش است.', 'online-fitting-room' ),
					),
				),
			);
		}
		$image_id = (int) $product->get_image_id();
		if ( ! $image_id ) {
			return array(
				'status' => 'blocked',
				'issues' => array(
					array(
						'level' => 'blocked',
						'text'  => __( 'تصویر شاخص ندارد؛ دکمه پرو نمایش داده نمی‌شود.', 'online-fitting-room' ),
					),
				),
			);
		}
		$meta = wp_get_attachment_metadata( $image_id );
		$file = get_attached_file( $image_id );
		if ( ! $file || ! file_exists( $file ) ) {
			$issues[] = array(
				'level' => 'blocked',
				'text'  => __( 'فایل تصویر شاخص روی سرور پیدا نشد.', 'online-fitting-room' ),
			);
		} else {
			$w = (int) ( $meta['width'] ?? 0 );
			$h = (int) ( $meta['height'] ?? 0 );
			if ( $w && $h && min( $w, $h ) < 512 ) {
				$issues[] = array(
					'level' => 'warning',
					/* translators: 1: width, 2: height in pixels. */
					'text'  => sprintf( __( 'تصویر کوچک است (%1$s×%2$s)؛ حداقل ۵۱۲ پیکسل در ضلع کوتاه پیشنهاد می‌شود.', 'online-fitting-room' ), number_format_i18n( $w ), number_format_i18n( $h ) ),
				);
			}
			if ( $w && $h && $w > $h * 1.3 ) {
				$issues[] = array(
					'level' => 'warning',
					'text'  => __( 'تصویر افقی است؛ عکس عمودی یا مربعی از خود لباس نتیجه بهتری می‌دهد.', 'online-fitting-room' ),
				);
			}
			$type = wp_check_filetype( $file )['type'];
			if ( ! in_array( $type, OFR_Api::ALLOWED_MIMES, true ) ) {
				$issues[] = array(
					'level' => 'info',
					'text'  => __( 'فرمت تصویر هنگام پرو خودکار به JPG تبدیل می‌شود.', 'online-fitting-room' ),
				);
			} elseif ( filesize( $file ) > OFR_Api::GARMENT_MAX_BYTES ) {
				$issues[] = array(
					'level' => 'info',
					'text'  => __( 'تصویر بزرگ‌تر از ۸ مگابایت است؛ نسخه کوچک‌تر آن به سرویس ارسال می‌شود.', 'online-fitting-room' ),
				);
			}
		}
		if ( $product->is_type( 'variable' ) ) {
			$children = $product->get_children();
			$with     = 0;
			foreach ( $children as $child_id ) {
				if ( get_post_thumbnail_id( $child_id ) ) {
					++$with;
				}
			}
			if ( $children && 0 === $with ) {
				$issues[] = array(
					'level' => 'warning',
					'text'  => __( 'متغیرها تصویر جداگانه ندارند؛ انتخاب رنگ داخل پنجره پرو نمایش داده نمی‌شود.', 'online-fitting-room' ),
				);
			}
		}
		$category = get_post_meta( $id, Online_Fitting_Room::META_CATEGORY, true ) ?: Online_Fitting_Room::settings()['default_category'];
		if ( 'auto' === $category ) {
			$issues[] = array(
				'level' => 'info',
				'text'  => __( 'نوع لباس «تشخیص خودکار» است؛ تعیین دقیق (بالاتنه، پایین‌تنه، یک‌تکه) نتیجه را بهتر می‌کند.', 'online-fitting-room' ),
			);
		}
		$levels = wp_list_pluck( $issues, 'level' );
		$status = in_array( 'blocked', $levels, true ) ? 'blocked' : ( in_array( 'warning', $levels, true ) ? 'warning' : 'ready' );
		return array(
			'status' => $status,
			'issues' => $issues,
		);
	}

	/**
	 * @return array { rows: array[], total: int, pages: int, counts: array }
	 */
	/** All rows, cached for 10 minutes (invalidated when a product is saved) so large catalogues stay fast. */
	private static function all() {
		$cached = get_transient( 'ofr_readiness' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$ids  = get_posts(
			array(
				'post_type'     => 'product',
				'post_status'   => 'publish',
				'numberposts'   => -1,
				'fields'        => 'ids',
				'orderby'       => 'date',
				'order'         => 'DESC',
				'no_found_rows' => true,
			)
		);
		$rows = array();
		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			update_meta_cache( 'post', $chunk );
			foreach ( $chunk as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}
				$rows[] = array(
					'id'     => $id,
					'name'   => $product->get_name(),
					'thumb'  => get_the_post_thumbnail_url( $id, 'thumbnail' ),
					'tryons' => OFR_Stats::product_count( $id ),
				) + self::check( $product );
			}
		}
		set_transient( 'ofr_readiness', $rows, 10 * MINUTE_IN_SECONDS );
		return $rows;
	}

	public static function flush() {
		delete_transient( 'ofr_readiness' );
	}

	/**
	 * @return array { rows: array[], total: int, pages: int, counts: array }
	 */
	public static function scan( $page = 1, $filter = '' ) {
		$all    = self::all();
		$counts = array_merge(
			array(
				'ready'   => 0,
				'warning' => 0,
				'blocked' => 0,
				'off'     => 0,
			),
			array_count_values( wp_list_pluck( $all, 'status' ) )
		);
		$rows   = $filter ? array_values(
			array_filter(
				$all,
				function ( $row ) use ( $filter ) {
					return $row['status'] === $filter;
				}
			)
		) : $all;
		$total  = count( $rows );
		$page   = max( 1, (int) $page );
		return array(
			'rows'   => array_slice( $rows, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ),
			'total'  => $total,
			'pages'  => max( 1, (int) ceil( $total / self::PER_PAGE ) ),
			'counts' => $counts,
		);
	}
}
