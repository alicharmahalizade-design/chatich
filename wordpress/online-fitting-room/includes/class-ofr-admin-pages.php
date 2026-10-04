<?php
/**
 * Settings page tabs beyond the settings themselves: statistics and ROI,
 * product readiness, alerts & SMS, and the error log.
 */

defined( 'ABSPATH' ) || exit;

final class OFR_Admin_Pages {
	public static function init() {
		add_action( 'wp_ajax_ofr_clear_logs', array( __CLASS__, 'ajax_clear_logs' ) );
	}

	public static function tabs() {
		return array(
			'settings'  => array( __( 'تنظیمات', 'online-fitting-room' ), 'plug' ),
			'stats'     => array( __( 'آمار و بازده', 'online-fitting-room' ), 'chart' ),
			'readiness' => array( __( 'آمادگی محصولات', 'online-fitting-room' ), 'check' ),
			'alerts'    => array( __( 'اعتبار، اعلان و پیامک', 'online-fitting-room' ), 'bell' ),
			'logs'      => array( __( 'گزارش خطا', 'online-fitting-room' ), 'list' ),
		);
	}

	private static function url( $tab, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'online-fitting-room',
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	public static function nav( $current ) {
		echo '<nav class="ofr-tabs" aria-label="' . esc_attr__( 'بخش‌ها', 'online-fitting-room' ) . '">';
		foreach ( self::tabs() as $tab => $def ) {
			printf(
				'<a href="%s" class="ofr-tab%s"%s>%s%s</a>',
				esc_url( self::url( $tab ) ),
				$tab === $current ? ' is-active' : '',
				$tab === $current ? ' aria-current="page"' : '',
				OFR_Admin::icon( $def[1] ), // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
				esc_html( $def[0] )
			);
		}
		echo '</nav>';
	}

	public static function render( $tab ) {
		call_user_func( array( __CLASS__, 'render_' . $tab ) );
	}

	private static function heading( $title, $subtitle ) {
		echo '<span class="ofr-eyebrow">' . esc_html__( 'مرکز مدیریت', 'online-fitting-room' ) . '</span><h1 class="ofr-title">' . esc_html( $title ) . '</h1><p class="ofr-subtitle">' . esc_html( $subtitle ) . '</p><hr class="wp-header-end">';
	}

	private static function money( $amount ) {
		return function_exists( 'wc_price' ) ? wp_strip_all_tags( html_entity_decode( wc_price( $amount ), ENT_QUOTES, 'UTF-8' ) ) : number_format_i18n( $amount );
	}

	/* ---------- Statistics & ROI ---------- */

	private static function render_stats() {
		$days = absint( $_GET['days'] ?? 30 ); // phpcs:ignore WordPress.Security.NonceVerification -- view filter.
		$days = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
		$sum  = OFR_Stats::summary( $days );
		$rows = OFR_Stats::daily( $days );
		$top  = OFR_Stats::top_products( $days, 20 );
		self::heading( __( 'آمار و بازده اتاق پُرُو', 'online-fitting-room' ), __( 'چقدر پرو می‌شود، چند نفر بعد از پرو خرید می‌کنند و چه درآمدی از محصولات پروشده آمده است.', 'online-fitting-room' ) );
		echo '<div class="ofr-filters" role="group" aria-label="' . esc_attr__( 'بازه زمانی', 'online-fitting-room' ) . '">';
		foreach ( array( 7, 30, 90 ) as $d ) {
			/* translators: %s: number of days. */
			printf( '<a class="ofr-chip%s" href="%s"%s>%s</a>', $d === $days ? ' is-active' : '', esc_url( self::url( 'stats', array( 'days' => $d ) ) ), $d === $days ? ' aria-current="true"' : '', esc_html( sprintf( __( '%s روز اخیر', 'online-fitting-room' ), number_format_i18n( $d ) ) ) );
		}
		echo '</div>';

		$tiles = array(
			array( __( 'پروهای موفق', 'online-fitting-room' ), number_format_i18n( $sum['successes'] ), sprintf( /* translators: %s: percent. */ __( 'نرخ موفقیت %s٪', 'online-fitting-room' ), number_format_i18n( $sum['success_rate'], 1 ) ) ),
			array( __( 'مشتریان پروکننده', 'online-fitting-room' ), number_format_i18n( $sum['tryers'] ), __( 'بازدیدکننده یکتا', 'online-fitting-room' ) ),
			array( __( 'افزودن به سبد پس از پرو', 'online-fitting-room' ), number_format_i18n( $sum['carts'] ), sprintf( /* translators: %s: percent. */ __( '%s٪ از پروها', 'online-fitting-room' ), number_format_i18n( $sum['cart_rate'], 1 ) ) ),
			array( __( 'سفارش‌های دارای پرو', 'online-fitting-room' ), number_format_i18n( $sum['orders'] ), sprintf( /* translators: %s: percent. */ __( '%s٪ از پروکنندگان', 'online-fitting-room' ), number_format_i18n( $sum['order_rate'], 1 ) ) ),
			array( __( 'فروش محصولات پروشده', 'online-fitting-room' ), self::money( $sum['revenue'] ), __( 'سفارش‌های پرداخت‌شده', 'online-fitting-room' ) ),
		);
		echo '<section class="ofr-stats ofr-stats--5">';
		foreach ( $tiles as $tile ) {
			echo '<div class="ofr-stat"><span class="ofr-stat__label">' . esc_html( $tile[0] ) . '</span><strong class="ofr-stat__value">' . esc_html( $tile[1] ) . '</strong><span class="ofr-stat__note">' . esc_html( $tile[2] ) . '</span></div>';
		}
		echo '</section>';

		echo '<section class="ofr-card"><header class="ofr-card__head"><span class="ofr-card__icon">' . OFR_Admin::icon( 'chart' ) . '</span><div><h2>' . esc_html__( 'پروهای موفق روزانه', 'online-fitting-room' ) . '</h2><p>' . esc_html__( 'برای دیدن عدد هر روز، نشانگر را روی ستون ببرید.', 'online-fitting-room' ) . '</p></div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
		self::bar_chart( $rows );
		echo '<details class="ofr-details"><summary>' . esc_html__( 'نمایش به‌صورت جدول', 'online-fitting-room' ) . '</summary><table class="ofr-table"><thead><tr><th>' . esc_html__( 'تاریخ', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'پرو موفق', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'افزودن به سبد پس از پرو', 'online-fitting-room' ) . '</th></tr></thead><tbody>';
		foreach ( array_reverse( $rows ) as $row ) {
			echo '<tr><td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row['date'] . ' 12:00' ) ) ) . '</td><td>' . esc_html( number_format_i18n( $row['tryons'] ) ) . '</td><td>' . esc_html( number_format_i18n( $row['carts'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></details></section>';

		echo '<section class="ofr-card"><header class="ofr-card__head"><span class="ofr-card__icon">' . OFR_Admin::icon( 'spark' ) . '</span><div><h2>' . esc_html__( 'محصولات پرطرفدار در پرو', 'online-fitting-room' ) . '</h2><p>' . esc_html__( 'نرخ افزودن به سبد یعنی چند درصد از کسانی که محصول را پرو کردند آن را به سبد اضافه کردند.', 'online-fitting-room' ) . '</p></div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
		if ( ! $top ) {
			echo '<p class="ofr-empty">' . esc_html__( 'هنوز در این بازه پرویی ثبت نشده است.', 'online-fitting-room' ) . '</p>';
		} else {
			echo '<div class="ofr-table-wrap"><table class="ofr-table"><thead><tr><th>' . esc_html__( 'محصول', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'پرو موفق', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'پروکننده', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'نرخ افزودن به سبد', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'سفارش', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'فروش', 'online-fitting-room' ) . '</th></tr></thead><tbody>';
			foreach ( $top as $row ) {
				$name = get_the_title( $row['product_id'] ) ?: '#' . $row['product_id'];
				echo '<tr><td><a href="' . esc_url( get_edit_post_link( $row['product_id'] ) ?: '#' ) . '">' . esc_html( $name ) . '</a></td><td>' . esc_html( number_format_i18n( $row['tryons'] ) ) . '</td><td>' . esc_html( number_format_i18n( $row['tryers'] ) ) . '</td><td><span class="ofr-meter" style="--v:' . esc_attr( min( 100, (float) $row['cart_rate'] ) ) . '%"></span> ' . esc_html( number_format_i18n( $row['cart_rate'], 1 ) ) . '٪</td><td>' . esc_html( number_format_i18n( $row['orders'] ) ) . '</td><td>' . esc_html( self::money( $row['revenue'] ) ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '<p class="ofr-help">' . esc_html__( 'سفارشی «دارای پرو» است که مشتری محصولی از آن را در ۷ روز پیش از افزودن به سبد پرو کرده باشد؛ این سفارش‌ها در فهرست سفارش‌ها با ✦ مشخص‌اند. آمار بدون IP و اطلاعات شخصی و با شناسه ناشناس ذخیره می‌شود.', 'online-fitting-room' ) . '</p></section>';
	}

	/** Single-series column chart: 4px rounded tops, thin bars, hover tooltip (admin.js), table view below. */
	private static function bar_chart( $rows ) {
		$n     = count( $rows );
		$max   = max( 1, max( wp_list_pluck( $rows, 'tryons' ) ) );
		$nice  = (int) ( pow( 10, floor( log10( $max ) ) ) * ceil( $max / pow( 10, floor( log10( $max ) ) ) ) );
		$nice  = max( $nice, 1 );
		$w     = 1000;
		$h     = 260;
		$left  = 36;
		$bot   = 26;
		$plotw = $w - $left - 8;
		$ploth = $h - $bot - 10;
		$slot  = $plotw / max( 1, $n );
		$bar   = min( 24, max( 3, $slot - 4 ) );
		echo '<div class="ofr-chart" data-ofr-chart><svg viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" role="img" aria-label="' . esc_attr__( 'نمودار پروهای موفق روزانه', 'online-fitting-room' ) . '" preserveAspectRatio="xMidYMid meet">';
		foreach ( array( 0, 0.5, 1 ) as $f ) {
			$y = 10 + $ploth * ( 1 - $f );
			echo '<line class="ofr-chart__grid" x1="' . (int) $left . '" x2="' . (int) ( $w - 8 ) . '" y1="' . esc_attr( $y ) . '" y2="' . esc_attr( $y ) . '"/>';
			echo '<text class="ofr-chart__tick" x="' . (int) ( $left - 6 ) . '" y="' . esc_attr( $y + 4 ) . '" text-anchor="end">' . esc_html( number_format_i18n( round( $nice * $f ) ) ) . '</text>';
		}
		foreach ( $rows as $i => $row ) {
			$x     = $left + $i * $slot + ( $slot - $bar ) / 2;
			$bh    = $ploth * $row['tryons'] / $nice;
			$y     = 10 + $ploth - $bh;
			$label = date_i18n( 'j M', strtotime( $row['date'] . ' 12:00' ) );
			/* translators: 1: date, 2: try-ons. */
			$tip = sprintf( __( '%2$s پرو — %1$s', 'online-fitting-room' ), "\u{2068}" . $label . "\u{2069}", number_format_i18n( $row['tryons'] ) );
			echo '<g class="ofr-chart__col" data-tip="' . esc_attr( $tip ) . '"><rect class="ofr-chart__hit" x="' . esc_attr( $left + $i * $slot ) . '" y="10" width="' . esc_attr( $slot ) . '" height="' . (int) $ploth . '"/>';
			if ( $row['tryons'] > 0 ) {
				$r = min( 4, $bh, $bar / 2 );
				echo '<path class="ofr-chart__bar" d="M' . esc_attr( $x ) . ',' . esc_attr( 10 + $ploth ) . 'V' . esc_attr( $y + $r ) . 'Q' . esc_attr( $x ) . ',' . esc_attr( $y ) . ' ' . esc_attr( $x + $r ) . ',' . esc_attr( $y ) . 'H' . esc_attr( $x + $bar - $r ) . 'Q' . esc_attr( $x + $bar ) . ',' . esc_attr( $y ) . ' ' . esc_attr( $x + $bar ) . ',' . esc_attr( $y + $r ) . 'V' . esc_attr( 10 + $ploth ) . 'Z"/>';
			}
			echo '<title>' . esc_html( $tip ) . '</title></g>';
			// Every few days a date under the axis, so labels never collide.
			$step = max( 1, (int) ceil( $n / 8 ) );
			if ( ( 0 === $i % $step && $n - 1 - $i >= $step / 2 ) || $i === $n - 1 ) {
				echo '<text class="ofr-chart__tick" x="' . esc_attr( $left + $i * $slot + $slot / 2 ) . '" y="' . (int) ( $h - 6 ) . '" text-anchor="middle">' . esc_html( $label ) . '</text>';
			}
		}
		echo '<line class="ofr-chart__axis" x1="' . (int) $left . '" x2="' . (int) ( $w - 8 ) . '" y1="' . (int) ( 10 + $ploth ) . '" y2="' . (int) ( 10 + $ploth ) . '"/>';
		echo '</svg><div class="ofr-chart__tip" hidden></div></div>';
	}

	/* ---------- Readiness ---------- */

	private static function render_readiness() {
		$filter = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- view filter.
		$filter = in_array( $filter, array( 'ready', 'warning', 'blocked', 'off' ), true ) ? $filter : '';
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification -- pagination.
		$scan   = OFR_Readiness::scan( $page, $filter );
		$labels = array(
			'ready'   => array( __( 'آماده', 'online-fitting-room' ), '✓' ),
			'warning' => array( __( 'قابل بهبود', 'online-fitting-room' ), '!' ),
			'blocked' => array( __( 'نمایش داده نمی‌شود', 'online-fitting-room' ), '✕' ),
			'off'     => array( __( 'خاموش', 'online-fitting-room' ), '–' ),
		);
		self::heading( __( 'آمادگی محصولات برای پرو', 'online-fitting-room' ), __( 'کدام محصولات نتیجه خوب می‌دهند، کدام دکمه پرو ندارند و چرا.', 'online-fitting-room' ) );
		echo '<div class="ofr-filters" role="group" aria-label="' . esc_attr__( 'وضعیت', 'online-fitting-room' ) . '"><a class="ofr-chip' . ( '' === $filter ? ' is-active' : '' ) . '" href="' . esc_url( self::url( 'readiness' ) ) . '">' . esc_html__( 'همه', 'online-fitting-room' ) . ' <b>' . esc_html( number_format_i18n( array_sum( $scan['counts'] ) ) ) . '</b></a>';
		foreach ( $labels as $status => $label ) {
			echo '<a class="ofr-chip ofr-chip--' . esc_attr( $status ) . ( $status === $filter ? ' is-active' : '' ) . '" href="' . esc_url( self::url( 'readiness', array( 'status' => $status ) ) ) . '"><i aria-hidden="true">' . esc_html( $label[1] ) . '</i> ' . esc_html( $label[0] ) . ' <b>' . esc_html( number_format_i18n( $scan['counts'][ $status ] ?? 0 ) ) . '</b></a>';
		}
		echo '</div><section class="ofr-card">';
		if ( ! $scan['rows'] ) {
			echo '<p class="ofr-empty">' . esc_html__( 'محصولی با این وضعیت نیست.', 'online-fitting-room' ) . '</p>';
		} else {
			echo '<div class="ofr-table-wrap"><table class="ofr-table ofr-ready"><thead><tr><th>' . esc_html__( 'محصول', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'وضعیت', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'نکته‌ها', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'پروکننده', 'online-fitting-room' ) . '</th></tr></thead><tbody>';
			foreach ( $scan['rows'] as $row ) {
				echo '<tr><td><a class="ofr-product-cell" href="' . esc_url( get_edit_post_link( $row['id'] ) ?: '#' ) . '">' . ( $row['thumb'] ? '<img src="' . esc_url( $row['thumb'] ) . '" alt="" width="40" height="40" loading="lazy">' : '<span class="ofr-noimg"></span>' ) . esc_html( $row['name'] ) . '</a></td>';
				echo '<td><span class="ofr-badge ofr-badge--' . esc_attr( $row['status'] ) . '"><i aria-hidden="true">' . esc_html( $labels[ $row['status'] ][1] ) . '</i> ' . esc_html( $labels[ $row['status'] ][0] ) . '</span></td><td><ul class="ofr-issues">';
				foreach ( $row['issues'] as $issue ) {
					echo '<li class="is-' . esc_attr( $issue['level'] ) . '">' . esc_html( $issue['text'] ) . '</li>';
				}
				echo ( $row['issues'] ? '' : '<li class="is-ok">' . esc_html__( 'همه‌چیز آماده است.', 'online-fitting-room' ) . '</li>' ) . '</ul></td><td>' . esc_html( number_format_i18n( $row['tryons'] ) ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
			if ( $scan['pages'] > 1 ) {
				echo '<div class="ofr-pager">' . wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%', self::url( 'readiness', $filter ? array( 'status' => $filter ) : array() ) ),
							'format'  => '',
							'current' => $page,
							'total'   => $scan['pages'],
						)
					)
				) . '</div>';
			}
		}
		echo '<p class="ofr-help">' . esc_html__( 'بهترین تصویر برای پرو: خود لباس به‌تنهایی یا روی مانکن، روبه‌رو، عمودی، با پس‌زمینه ساده و حداقل ۵۱۲ پیکسل. فهرست هر ۱۰ دقیقه یا با ذخیره هر محصول به‌روز می‌شود.', 'online-fitting-room' ) . '</p></section>';
	}

	/* ---------- Alerts & SMS ---------- */

	public static function sanitize_alerts( $input, $old ) {
		$on     = function ( $key ) use ( $input ) {
			return ! empty( $input[ $key ] ) ? 'yes' : 'no';
		};
		$bought = absint( $input['credits_bought'] ?? $old['credits_bought'] );
		$mode   = in_array( $input['credit_mode'] ?? '', array( 'api', 'manual' ), true ) ? $input['credit_mode'] : $old['credit_mode'];
		if ( 'manual' === $mode && ( $bought !== (int) $old['credits_bought'] || 'manual' !== $old['credit_mode'] ) ) {
			OFR_Alerts::reset_manual();
		}
		$events  = array_intersect( array_map( 'sanitize_key', (array) ( $input['alert_events'] ?? array() ) ), array_keys( OFR_Alerts::events() ) );
		$emails  = array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', (string) ( $input['alert_emails'] ?? '' ) ) ), 'is_email' );
		$mobiles = array_filter( array_map( array( 'OFR_Alerts', 'normalize_mobile' ), preg_split( '/[\s,;]+/', (string) ( $input['sms_recipients'] ?? '' ) ) ) );
		$key     = $old['sms_key'];
		if ( ! empty( $input['sms_key_clear'] ) ) {
			$key = '';
		} elseif ( isset( $input['sms_key'] ) && 0 === strpos( (string) $input['sms_key'], OFR_Api::KEY_PREFIX ) ) {
			$key = $input['sms_key'];
		} elseif ( isset( $input['sms_key'] ) && '' !== trim( $input['sms_key'] ) ) {
			$key = OFR_Api::encrypt_key( trim( $input['sms_key'] ) );
		}
		return array(
			'credit_mode'       => $mode,
			'credits_bought'    => $bought,
			'credits_threshold' => absint( $input['credits_threshold'] ?? $old['credits_threshold'] ),
			'alert_email'       => $on( 'alert_email' ),
			'alert_emails'      => implode( ', ', $emails ),
			'alert_events'      => implode( ',', $events ),
			'sms_provider'      => array_key_exists( $input['sms_provider'] ?? '', OFR_Alerts::providers() ) ? $input['sms_provider'] : $old['sms_provider'],
			'sms_key'           => $key,
			'sms_sender'        => sanitize_text_field( $input['sms_sender'] ?? '' ),
			'sms_recipients'    => implode( ', ', $mobiles ),
			'sms_webhook'       => esc_url_raw( trim( (string) ( $input['sms_webhook'] ?? '' ) ) ),
		);
	}

	private static function render_alerts() {
		$s       = Online_Fitting_Room::settings();
		$balance = OFR_Alerts::balance();
		$events  = array_map( 'trim', explode( ',', (string) $s['alert_events'] ) );
		$log     = (array) get_option( 'ofr_alert_log', array() );
		self::heading( __( 'اعتبار، اعلان و پیامک', 'online-fitting-room' ), __( 'پیش از تمام شدن اعتبار یا قطع سرویس خبردار شوید؛ با ایمیل یا پیامک.', 'online-fitting-room' ) );
		settings_errors();
		// phpcs:disable WordPress.Security.EscapeOutput -- OFR_Admin helpers escape their output.
		?>
		<section class="ofr-stats">
			<div class="ofr-stat"><span class="ofr-stat__label"><?php esc_html_e( 'اعتبار باقی‌مانده', 'online-fitting-room' ); ?></span><strong class="ofr-stat__value" data-ofr-balance><?php echo null === $balance['value'] ? '—' : esc_html( number_format_i18n( $balance['value'] ) ); ?></strong><span class="ofr-stat__note"><?php echo $balance['error'] ? esc_html( $balance['error'] ) : ( 'manual' === $balance['source'] ? esc_html__( 'تخمین: خریداری‌شده منهای پروهای انجام‌شده', 'online-fitting-room' ) : esc_html( sprintf( /* translators: %s: time span. */ __( 'از سرویس، %s پیش', 'online-fitting-room' ), human_time_diff( $balance['time'] ) ) ) ); ?></span></div>
			<div class="ofr-stat"><span class="ofr-stat__label"><?php esc_html_e( 'آستانه هشدار', 'online-fitting-room' ); ?></span><strong class="ofr-stat__value"><?php echo esc_html( number_format_i18n( $s['credits_threshold'] ) ); ?></strong><span class="ofr-stat__note"><?php esc_html_e( 'پرو', 'online-fitting-room' ); ?></span></div>
			<div class="ofr-stat"><span class="ofr-stat__label"><?php esc_html_e( 'پروهای امروز', 'online-fitting-room' ); ?></span><strong class="ofr-stat__value"><?php echo esc_html( number_format_i18n( Online_Fitting_Room::instance()->usage_today() ) ); ?></strong><span class="ofr-stat__note"><?php esc_html_e( 'شروع‌شده', 'online-fitting-room' ); ?></span></div>
		</section>
		<form method="post" action="options.php" class="ofr-form">
			<?php settings_fields( OFR_Admin::GROUP ); ?>
			<input type="hidden" name="<?php echo OFR_Admin::name( '_tab' ); ?>" value="alerts">
			<div class="ofr-grid">
				<section class="ofr-card">
					<header class="ofr-card__head"><span class="ofr-card__icon"><?php echo OFR_Admin::icon( 'chart' ); ?></span><div><h2><?php esc_html_e( 'اعتبار سرویس', 'online-fitting-room' ); ?></h2><p><?php esc_html_e( 'اگر سرویس مقدار اعتبار را اعلام نکند، تعداد خریداری‌شده را وارد کنید تا افزونه کم شدن آن را بشمارد.', 'online-fitting-room' ); ?></p></div></header>
					<div class="ofr-field"><label class="ofr-label" for="ofr-credit_mode"><?php esc_html_e( 'منبع اعتبار', 'online-fitting-room' ); ?></label>
					<?php
					OFR_Admin::select(
						'credit_mode',
						array(
							'api'    => __( 'خواندن از سرویس مربع API', 'online-fitting-room' ),
							'manual' => __( 'شمارش در افزونه (اعتبار خریداری‌شده)', 'online-fitting-room' ),
						),
						$s['credit_mode']
					);
					?>
																							</div>
					<div class="ofr-row">
						<div class="ofr-field"><label class="ofr-label" for="ofr-bought"><?php esc_html_e( 'اعتبار خریداری‌شده (پرو)', 'online-fitting-room' ); ?></label><input id="ofr-bought" class="ofr-input" type="number" min="0" name="<?php echo OFR_Admin::name( 'credits_bought' ); ?>" value="<?php echo esc_attr( $s['credits_bought'] ); ?>"><p class="ofr-help"><?php esc_html_e( 'با هر شارژ، عدد جدید را وارد کنید؛ شمارش از همان لحظه دوباره شروع می‌شود.', 'online-fitting-room' ); ?></p></div>
						<div class="ofr-field"><label class="ofr-label" for="ofr-threshold"><?php esc_html_e( 'هشدار وقتی اعتبار کمتر شد از', 'online-fitting-room' ); ?></label><input id="ofr-threshold" class="ofr-input" type="number" min="0" name="<?php echo OFR_Admin::name( 'credits_threshold' ); ?>" value="<?php echo esc_attr( $s['credits_threshold'] ); ?>"></div>
					</div>
					<button type="button" class="ofr-btn ofr-btn--soft" data-ofr-action="ofr_refresh_balance"><?php esc_html_e( 'به‌روزرسانی اعتبار', 'online-fitting-room' ); ?></button>
				</section>
				<section class="ofr-card">
					<header class="ofr-card__head"><span class="ofr-card__icon"><?php echo OFR_Admin::icon( 'bell' ); ?></span><div><h2><?php esc_html_e( 'چه وقت خبر بدهیم؟', 'online-fitting-room' ); ?></h2><p><?php esc_html_e( 'هشدار کم شدن اعتبار یک بار در هر عبور از آستانه ارسال می‌شود.', 'online-fitting-room' ); ?></p></div></header>
					<div class="ofr-field ofr-checks">
						<?php foreach ( OFR_Alerts::events() as $key => $label ) : ?>
							<label class="ofr-check"><input type="checkbox" name="<?php echo OFR_Admin::name( 'alert_events' ); ?>[]" value="<?php echo esc_attr( $key ); ?>"<?php checked( in_array( $key, $events, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</div>
					<div class="ofr-field"><?php OFR_Admin::toggle( 'alert_email', $s['alert_email'], __( 'ارسال با ایمیل', 'online-fitting-room' ) ); ?></div>
					<div class="ofr-field"><label class="ofr-label" for="ofr-emails"><?php esc_html_e( 'ایمیل گیرندگان', 'online-fitting-room' ); ?></label><input id="ofr-emails" class="ofr-input" dir="ltr" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" name="<?php echo OFR_Admin::name( 'alert_emails' ); ?>" value="<?php echo esc_attr( $s['alert_emails'] ); ?>"><p class="ofr-help"><?php esc_html_e( 'خالی یعنی ایمیل مدیر سایت. چند ایمیل را با کاما جدا کنید.', 'online-fitting-room' ); ?></p></div>
					<button type="button" class="ofr-btn ofr-btn--soft" data-ofr-action="ofr_test_alert" data-channel="email"><?php esc_html_e( 'ایمیل آزمایشی', 'online-fitting-room' ); ?></button>
				</section>
				<section class="ofr-card ofr-card--wide">
					<header class="ofr-card__head"><span class="ofr-card__icon"><?php echo OFR_Admin::icon( 'support' ); ?></span><div><h2><?php esc_html_e( 'پنل پیامک', 'online-fitting-room' ); ?></h2><p><?php esc_html_e( 'کلید وب‌سرویس را از پنل پیامک خود بگیرید؛ کلید رمزنگاری‌شده ذخیره می‌شود. پس از ذخیره، «پیامک آزمایشی» را بزنید.', 'online-fitting-room' ); ?></p></div></header>
					<div class="ofr-row">
						<div class="ofr-field"><label class="ofr-label" for="ofr-sms_provider"><?php esc_html_e( 'سرویس پیامک', 'online-fitting-room' ); ?></label><?php OFR_Admin::select( 'sms_provider', OFR_Alerts::providers(), $s['sms_provider'] ); ?></div>
						<div class="ofr-field"><label class="ofr-label" for="ofr-sms-key"><?php esc_html_e( 'کلید API / توکن', 'online-fitting-room' ); ?></label><input id="ofr-sms-key" class="ofr-input" type="password" dir="ltr" autocomplete="new-password" name="<?php echo OFR_Admin::name( 'sms_key' ); ?>" placeholder="<?php echo esc_attr( $s['sms_key'] ? __( 'ذخیره شده؛ برای حفظ خالی بگذارید', 'online-fitting-room' ) : '' ); ?>">
						<?php
						if ( $s['sms_key'] ) :
							?>
							<label class="ofr-check"><input type="checkbox" name="<?php echo OFR_Admin::name( 'sms_key_clear' ); ?>" value="1"> <?php esc_html_e( 'حذف کلید', 'online-fitting-room' ); ?></label><?php endif; ?></div>
						<div class="ofr-field"><label class="ofr-label" for="ofr-sms-sender"><?php esc_html_e( 'شماره خط ارسال', 'online-fitting-room' ); ?></label><input id="ofr-sms-sender" class="ofr-input" dir="ltr" name="<?php echo OFR_Admin::name( 'sms_sender' ); ?>" value="<?php echo esc_attr( $s['sms_sender'] ); ?>" placeholder="3000…"></div>
						<div class="ofr-field"><label class="ofr-label" for="ofr-sms-to"><?php esc_html_e( 'موبایل گیرندگان', 'online-fitting-room' ); ?></label><input id="ofr-sms-to" class="ofr-input" dir="ltr" name="<?php echo OFR_Admin::name( 'sms_recipients' ); ?>" value="<?php echo esc_attr( $s['sms_recipients'] ); ?>" placeholder="09121234567, 09351234567"></div>
					</div>
					<div class="ofr-field" data-ofr-sms-webhook><label class="ofr-label" for="ofr-sms-webhook"><?php esc_html_e( 'آدرس وب‌هوک', 'online-fitting-room' ); ?></label><input id="ofr-sms-webhook" class="ofr-input" dir="ltr" name="<?php echo OFR_Admin::name( 'sms_webhook' ); ?>" value="<?php echo esc_attr( $s['sms_webhook'] ); ?>" placeholder="https://"><p class="ofr-help"><?php esc_html_e( 'برای سرویس‌های دیگر (تلگرام، بله، اتوماسیون): بدنه JSON شامل message، recipients و site با POST ارسال می‌شود؛ کلید (اگر وارد شود) در هدر Authorization: Bearer.', 'online-fitting-room' ); ?></p></div>
					<button type="button" class="ofr-btn ofr-btn--soft" data-ofr-action="ofr_test_alert" data-channel="sms"><?php esc_html_e( 'پیامک آزمایشی', 'online-fitting-room' ); ?></button>
				</section>
			</div>
			<div class="ofr-savebar"><span><?php esc_html_e( 'پیش از آزمایش، تغییرات را ذخیره کنید.', 'online-fitting-room' ); ?></span><button type="submit" class="ofr-btn ofr-btn--primary"><?php esc_html_e( 'ذخیره', 'online-fitting-room' ); ?></button></div>
		</form>
		<section class="ofr-card" style="margin-top:18px">
			<header class="ofr-card__head"><span class="ofr-card__icon"><?php echo OFR_Admin::icon( 'list' ); ?></span><div><h2><?php esc_html_e( 'آخرین اعلان‌های ارسال‌شده', 'online-fitting-room' ); ?></h2></div></header>
			<?php if ( ! $log ) : ?>
				<p class="ofr-empty"><?php esc_html_e( 'هنوز اعلانی ارسال نشده است.', 'online-fitting-room' ); ?></p>
			<?php else : ?>
				<div class="ofr-table-wrap"><table class="ofr-table"><thead><tr><th><?php esc_html_e( 'زمان', 'online-fitting-room' ); ?></th><th><?php esc_html_e( 'متن', 'online-fitting-room' ); ?></th><th><?php esc_html_e( 'نتیجه', 'online-fitting-room' ); ?></th></tr></thead><tbody>
				<?php foreach ( $log as $item ) : ?>
					<tr><td><?php echo esc_html( wp_date( 'Y-m-d H:i', $item['time'] ) ); ?></td><td><?php echo esc_html( $item['text'] ); ?></td><td>
					<?php foreach ( (array) $item['results'] as $channel => $result ) : ?>
						<span class="ofr-badge ofr-badge--<?php echo ! empty( $result['ok'] ) ? 'ready' : 'blocked'; ?>"><?php echo esc_html( 'sms' === $channel ? __( 'پیامک', 'online-fitting-room' ) : __( 'ایمیل', 'online-fitting-room' ) ); ?> <?php echo ! empty( $result['ok'] ) ? '✓' : '✕'; ?></span> <?php echo empty( $result['ok'] ) && ! empty( $result['detail'] ) ? '<small>' . esc_html( $result['detail'] ) . '</small>' : ''; ?>
					<?php endforeach; ?>
					</td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
		</section>
		<?php
		// phpcs:enable
	}

	/* ---------- Error log ---------- */

	private static function render_logs() {
		$rows = OFR_Log::recent( 100 );
		self::heading( __( 'گزارش خطا', 'online-fitting-room' ), __( 'آخرین خطاهای سرویس، تبدیل عکس و پیامک؛ برای پشتیبانی همین جدول را بفرستید.', 'online-fitting-room' ) );
		echo '<p><a class="ofr-btn ofr-btn--soft" href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=' . OFR_Log::SOURCE ) ) . '">' . esc_html__( 'گزارش‌های ووکامرس', 'online-fitting-room' ) . '</a> ';
		if ( $rows ) {
			echo '<button type="button" class="ofr-btn ofr-btn--soft" data-ofr-action="ofr_clear_logs" data-confirm="1">' . esc_html__( 'پاک کردن گزارش‌ها', 'online-fitting-room' ) . '</button>';
		}
		echo '</p><section class="ofr-card">';
		if ( ! $rows ) {
			echo '<p class="ofr-empty">' . esc_html__( 'خطایی ثبت نشده است. 🎉', 'online-fitting-room' ) . '</p>';
		} else {
			echo '<div class="ofr-table-wrap"><table class="ofr-table ofr-logs"><thead><tr><th>' . esc_html__( 'زمان', 'online-fitting-room' ) . '</th><th>' . esc_html__( 'بخش', 'online-fitting-room' ) . '</th><th>HTTP</th><th>' . esc_html__( 'جزئیات', 'online-fitting-room' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				echo '<tr class="is-' . esc_attr( $row['level'] ) . '"><td>' . esc_html( wp_date( 'Y-m-d H:i:s', (int) $row['created'] ) ) . '</td><td>' . esc_html( $row['context'] ) . '</td><td>' . esc_html( $row['code'] ?: '—' ) . '</td><td dir="ltr"><code>' . esc_html( $row['detail'] ) . '</code></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</section>';
	}

	public static function ajax_clear_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'ofr_admin', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی مجاز نیست.', 'online-fitting-room' ) ), 403 );
		}
		OFR_Log::clear();
		wp_send_json_success(
			array(
				'message' => __( 'گزارش‌ها پاک شدند.', 'online-fitting-room' ),
				'reload'  => true,
			)
		);
	}
}
