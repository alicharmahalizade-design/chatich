<?php
/**
 * Statistics and ROI: try-ons, add-to-cart after a try-on, orders that contain
 * tried-on products, and the «۱۲۰ نفر پرو کردند» social proof.
 *
 * Events live in the ofr_events table with an anonymous visitor hash
 * (OFR_Client::visitor()); no IP, name or photo is stored. A cart line added
 * by a visitor who tried that product on in the last 7 days is marked, and the
 * mark travels to the order line (hidden meta _ofr_tryon) and the order
 * (_ofr_tryon = yes) once it is paid.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Stats {
	const ATTRIBUTION_DAYS = 7;

	public static function init() {
		add_action(
			'ofr_tryon_started',
			function ( $product, $variation ) {
				OFR_Stats::record( 'start', $product, $variation );
			},
			10,
			2
		);
		add_action(
			'ofr_tryon_succeeded',
			function ( $product, $variation ) {
				OFR_Stats::record( 'success', $product, $variation );
				OFR_Stats::refresh_count( $product );
			},
			10,
			2
		);
		add_action(
			'ofr_tryon_failed',
			function ( $product, $variation ) {
				OFR_Stats::record( 'fail', $product, $variation );
			},
			10,
			2
		);
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'added_to_cart' ), 10, 6 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'cart_from_session' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item' ), 10, 3 );
		foreach ( array( 'processing', 'completed' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'track_order' ) );
		}
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'admin_item_badge' ), 10, 2 );
		// Orders list column (HPOS and legacy screens).
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'order_column' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'order_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'order_column_value' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'order_column_value' ), 10, 2 );
	}

	private static function table() {
		return OFR_DB::table( 'events' );
	}

	public static function record( $type, $product_id, $variation_id = 0, $visitor = null, $order_id = 0, $value = 0 ) {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		$wpdb->insert(
			self::table(),
			array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'type'         => $type,
			'product_id'   => (int) $product_id,
			'variation_id' => (int) $variation_id,
			'visitor'      => null === $visitor ? OFR_Client::visitor() : (string) $visitor,
			'order_id'     => (int) $order_id,
			'value'        => (float) $value,
			'created'      => time(),
			)
		);
		$wpdb->suppress_errors( $suppress );
	}

	/* ---------- Attribution: cart → order ---------- */

	/** Did this visitor successfully try this product on recently? */
	public static function tried( $product_id, $visitor = null ) {
		global $wpdb;
		$visitor  = null === $visitor ? OFR_Client::visitor() : $visitor;
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . " WHERE visitor = %s AND product_id = %d AND type = 'success' AND created >= %d LIMIT 1", $visitor, (int) $product_id, time() - self::ATTRIBUTION_DAYS * DAY_IN_SECONDS ) );
		$wpdb->suppress_errors( $suppress );
		return (bool) $found;
	}

	public static function cart_item_data( $data, $product_id ) {
		if ( self::tried( $product_id ) ) {
			$data['ofr_tryon'] = 1;
		}
		return $data;
	}

	public static function cart_from_session( $item, $values ) {
		if ( ! empty( $values['ofr_tryon'] ) ) {
			$item['ofr_tryon'] = 1;
		}
		return $item;
	}

	public static function added_to_cart( $key, $product_id, $quantity, $variation_id, $variation, $data ) {
		if ( ! empty( $data['ofr_tryon'] ) ) {
			self::record( 'cart', $product_id, $variation_id );
		}
	}

	public static function order_item( $item, $cart_item_key, $values ) {
		if ( ! empty( $values['ofr_tryon'] ) ) {
			$item->add_meta_data( '_ofr_tryon', 'yes', true );
		}
	}

	/** Once paid: mark the order and record the revenue of its tried-on lines (once per order). */
	public static function track_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' === $order->get_meta( '_ofr_tracked' ) ) {
			return;
		}
		$found = false;
		foreach ( $order->get_items() as $item ) {
			if ( 'yes' !== $item->get_meta( '_ofr_tryon' ) ) {
				continue;
			}
			$found = true;
			self::record( 'order', $item->get_product_id(), $item->get_variation_id(), '', $order->get_id(), (float) $item->get_total() );
		}
		$order->update_meta_data( '_ofr_tracked', 'yes' );
		if ( $found ) {
			$order->update_meta_data( '_ofr_tryon', 'yes' );
		}
		$order->save();
	}

	public static function admin_item_badge( $item_id, $item ) {
		if ( is_object( $item ) && method_exists( $item, 'get_meta' ) && 'yes' === $item->get_meta( '_ofr_tryon' ) ) {
			echo '<p style="margin:4px 0 0;color:#c2410c;font-weight:600">✦ ' . esc_html__( 'مشتری این محصول را پیش از خرید پرو مجازی کرد', 'online-fitting-room' ) . '</p>';
		}
	}

	public static function order_column( $columns ) {
		$columns['ofr_tryon'] = '<span title="' . esc_attr__( 'پرو مجازی', 'online-fitting-room' ) . '">✦</span>';
		return $columns;
	}

	public static function order_column_value( $column, $order ) {
		if ( 'ofr_tryon' !== $column ) {
			return;
		}
		$order = is_object( $order ) ? $order : wc_get_order( $order );
		if ( $order && 'yes' === $order->get_meta( '_ofr_tryon' ) ) {
			echo '<span title="' . esc_attr__( 'شامل محصول پروشده', 'online-fitting-room' ) . '" style="color:#c2410c">✦</span>';
		}
	}

	/* ---------- Social proof ---------- */

	/** Distinct visitors who tried the product on (kept in product meta, so pages read it for free). */
	public static function refresh_count( $product_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT visitor) FROM ' . self::table() . " WHERE product_id = %d AND type = 'success'", (int) $product_id ) );
		update_post_meta( $product_id, '_ofr_tryon_count', $count );
		return $count;
	}

	public static function product_count( $product_id ) {
		return (int) get_post_meta( $product_id, '_ofr_tryon_count', true );
	}

	/** «۱۲۰ نفر این لباس را پرو کردند», or '' below the threshold set in the settings. */
	public static function proof_text( $product_id ) {
		$s = Online_Fitting_Room::settings();
		if ( 'yes' !== $s['social_proof'] ) {
			return '';
		}
		$count = self::product_count( $product_id );
		if ( $count < max( 1, (int) $s['social_min'] ) ) {
			return '';
		}
		/* translators: %s: number of people. */
		return sprintf( __( '%s نفر این لباس را پرو کردند', 'online-fitting-room' ), number_format_i18n( $count ) );
	}

	/* ---------- Reports ---------- */

	private static function since( $days ) {
		return time() - max( 1, (int) $days ) * DAY_IN_SECONDS;
	}

	/** @return array Totals and rates for the period. */
	public static function summary( $days ) {
		global $wpdb;
		$t     = self::table();
		$since = self::since( $days );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
				SUM(type = 'start') AS starts,
				SUM(type = 'success') AS successes,
				SUM(type = 'fail') AS fails,
				COUNT(DISTINCT CASE WHEN type = 'success' THEN visitor END) AS tryers,
				COUNT(DISTINCT CASE WHEN type = 'cart' THEN CONCAT(visitor, '|', product_id) END) AS carts,
				COUNT(DISTINCT CASE WHEN type = 'success' THEN CONCAT(visitor, '|', product_id) END) AS tried_pairs,
				COUNT(DISTINCT CASE WHEN type = 'order' THEN order_id END) AS orders,
				SUM(CASE WHEN type = 'order' THEN value ELSE 0 END) AS revenue
			FROM %i WHERE created >= %d",
				$t,
				$since
			),
			ARRAY_A
		);
		// phpcs:enable
		$row  = array_map( 'floatval', (array) $row );
		$rate = function ( $a, $b ) {
			return $b > 0 ? round( 100 * $a / $b, 1 ) : 0.0;
		};
		return array(
			'starts'       => (int) ( $row['starts'] ?? 0 ),
			'successes'    => (int) ( $row['successes'] ?? 0 ),
			'fails'        => (int) ( $row['fails'] ?? 0 ),
			'tryers'       => (int) ( $row['tryers'] ?? 0 ),
			'carts'        => (int) ( $row['carts'] ?? 0 ),
			'orders'       => (int) ( $row['orders'] ?? 0 ),
			'revenue'      => (float) ( $row['revenue'] ?? 0 ),
			'success_rate' => $rate( $row['successes'] ?? 0, ( $row['successes'] ?? 0 ) + ( $row['fails'] ?? 0 ) ),
			'cart_rate'    => $rate( $row['carts'] ?? 0, $row['tried_pairs'] ?? 0 ),
			'order_rate'   => $rate( $row['orders'] ?? 0, $row['tryers'] ?? 0 ),
		);
	}

	/** Successful try-ons per day (site time), oldest first, with empty days filled. */
	public static function daily( $days ) {
		global $wpdb;
		$t      = self::table();
		$offset = (int) round( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$days   = max( 1, (int) $days );
		$today  = (int) floor( ( time() + $offset ) / DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT FLOOR((created + %d) / 86400) AS d, SUM(type = 'success') AS tryons, COUNT(DISTINCT CASE WHEN type = 'cart' THEN CONCAT(visitor, '|', product_id) END) AS carts FROM %i WHERE created >= %d GROUP BY d", $offset, $t, ( $today - $days + 1 ) * DAY_IN_SECONDS - $offset ), ARRAY_A );
		$by   = array();
		foreach ( (array) $rows as $row ) {
			$by[ (int) $row['d'] ] = $row;
		}
		$out = array();
		for ( $d = $today - $days + 1; $d <= $today; $d++ ) {
			$out[] = array(
				'date'   => wp_date( 'Y-m-d', $d * DAY_IN_SECONDS + 43200 - $offset ),
				'tryons' => (int) ( $by[ $d ]['tryons'] ?? 0 ),
				'carts'  => (int) ( $by[ $d ]['carts'] ?? 0 ),
			);
		}
		return $out;
	}

	/** Best products in the period by successful try-ons. */
	public static function top_products( $days, $limit = 20 ) {
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id,
				SUM(type = 'success') AS tryons,
				COUNT(DISTINCT CASE WHEN type = 'success' THEN visitor END) AS tryers,
				COUNT(DISTINCT CASE WHEN type = 'cart' THEN visitor END) AS carters,
				COUNT(DISTINCT CASE WHEN type = 'order' THEN order_id END) AS orders,
				SUM(CASE WHEN type = 'order' THEN value ELSE 0 END) AS revenue
			FROM %i WHERE created >= %d AND product_id > 0 GROUP BY product_id HAVING tryons > 0 ORDER BY tryons DESC LIMIT %d",
				$t,
				self::since( $days ),
				(int) $limit
			),
			ARRAY_A
		);
		return array_map(
			function ( $row ) {
				$row['cart_rate'] = $row['tryers'] > 0 ? round( 100 * $row['carters'] / $row['tryers'], 1 ) : 0;
				return $row;
			},
			(array) $rows
		);
	}
}
