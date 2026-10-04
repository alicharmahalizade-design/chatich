<?php
/**
 * Try-on modal.
 *
 * Override it by copying this file to yourtheme/online-fitting-room/modal.php.
 * Keep the data-* attributes: the script finds every element by them.
 *
 * Available variables:
 *
 * @var array  $s     Plugin settings (Online_Fitting_Room::settings()).
 * @var string $theme Colour mode: light, dark or auto (escaped).
 * @var bool   $guide Whether the photo guide is shown.
 *
 * @package online-fitting-room
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ofr" hidden dir="rtl" data-theme="<?php echo $theme; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>">
	<div class="ofr__backdrop" data-ofr-close></div>
	<section class="ofr__dialog" role="dialog" aria-modal="true" aria-labelledby="ofr-title" tabindex="-1">
		<button type="button" class="ofr__close" data-ofr-close aria-label="<?php esc_attr_e( 'بستن', 'online-fitting-room' ); ?>">×</button>
		<header class="ofr__header"><span class="ofr__eyebrow"><?php esc_html_e( 'اتاق پُرُو آنلاین', 'online-fitting-room' ); ?></span><h2 id="ofr-title"><?php echo esc_html( $s['modal_title'] ); ?></h2>
		<?php
		if ( '' !== trim( $s['modal_subtitle'] ) ) :
			?>
			<p><?php echo esc_html( $s['modal_subtitle'] ); ?></p><?php endif; ?></header>
		<ol class="ofr__progress" aria-label="<?php esc_attr_e( 'مراحل پرو', 'online-fitting-room' ); ?>"><li class="is-active" aria-current="step"><i>۱</i><span class="ofr__sr"><?php esc_html_e( 'انتخاب عکس', 'online-fitting-room' ); ?></span></li><li><i>۲</i><span class="ofr__sr"><?php esc_html_e( 'پردازش', 'online-fitting-room' ); ?></span></li><li><i>۳</i><span class="ofr__sr"><?php esc_html_e( 'نتیجه', 'online-fitting-room' ); ?></span></li></ol>
		<div class="ofr__body">
			<div class="ofr__stage ofr__login" data-stage="login">
				<h3 tabindex="-1"><?php esc_html_e( 'برای استفاده از اتاق پُرُو وارد حساب خود شوید', 'online-fitting-room' ); ?></h3>
				<p><?php esc_html_e( 'پرو مجازی فقط برای کاربران عضو فعال است.', 'online-fitting-room' ); ?></p>
				<a class="ofr__primary" data-login href="#"><?php esc_html_e( 'ورود / ثبت‌نام', 'online-fitting-room' ); ?></a>
			</div>

			<div class="ofr__stage is-active" data-stage="upload">
				<div class="ofr__product"><img data-product-image alt=""><div><small><?php esc_html_e( 'لباس انتخابی', 'online-fitting-room' ); ?></small><strong data-product-title></strong><span class="ofr__price"><del data-product-regular hidden></del> <span data-product-price></span></span><small class="ofr__proof" data-product-proof hidden></small></div></div>
				<div class="ofr__swatches" data-swatches hidden><span class="ofr__swatches-label"><?php esc_html_e( 'رنگ برای پرو:', 'online-fitting-room' ); ?></span><div class="ofr__swatch-list" role="radiogroup" aria-label="<?php esc_attr_e( 'رنگ برای پرو', 'online-fitting-room' ); ?>"></div></div>
				<div class="ofr__pick<?php echo $guide ? ' has-guide' : ''; ?>">
					<?php if ( $guide ) : ?>
						<figure class="ofr__guide" data-guide>
							<button type="button" class="ofr__guide-zoom" data-zoom-guide aria-label="<?php esc_attr_e( 'نمایش بزرگ راهنمای عکس', 'online-fitting-room' ); ?>">
								<img src="<?php echo esc_url( plugins_url( 'assets/img/photo-guide-small.webp', OFR_FILE ) ); ?>" data-full="<?php echo esc_url( plugins_url( 'assets/img/photo-guide.webp', OFR_FILE ) ); ?>" width="420" height="560" loading="lazy" decoding="async" alt="<?php esc_attr_e( 'راهنمای عکس مناسب: روبه‌دوربین بایستید، نور کافی و یکنواخت، دست‌ها کمی از بدن فاصله داشته باشد، پس‌زمینه ساده، تمام بدن داخل کادر.', 'online-fitting-room' ); ?>">
								<span class="ofr__guide-zoom-icon" aria-hidden="true">⤢</span>
							</button>
							<figcaption><?php esc_html_e( 'نمونه عکس مناسب', 'online-fitting-room' ); ?></figcaption>
						</figure>
					<?php endif; ?>
					<div class="ofr__pick-main">
						<label class="ofr__drop" data-drop><input type="file" accept="image/*" data-avatar><span class="ofr__drop-icon" aria-hidden="true">＋</span><strong><?php esc_html_e( 'عکس تمام‌قد خودت را انتخاب کن', 'online-fitting-room' ); ?></strong><em><?php esc_html_e( 'هر فرمت عکسی (JPG، HEIC آیفون، PNG، WEBP، AVIF و…)', 'online-fitting-room' ); ?></em><em class="ofr__drop-hint"><?php esc_html_e( 'یا عکس را اینجا بکشید و رها کنید (Ctrl+V هم کار می‌کند)', 'online-fitting-room' ); ?></em></label>
						<div class="ofr__preview" hidden><img data-preview alt="<?php esc_attr_e( 'پیش‌نمایش تصویر', 'online-fitting-room' ); ?>"><span class="ofr__saved-badge" data-saved-badge hidden><?php esc_html_e( 'عکس ذخیره‌شده روی این دستگاه', 'online-fitting-room' ); ?></span><p class="ofr__preview-note" data-preview-note hidden></p><div class="ofr__preview-actions"><button type="button" data-change><?php esc_html_e( 'تغییر عکس', 'online-fitting-room' ); ?></button><button type="button" data-forget hidden><?php esc_html_e( 'حذف از این دستگاه', 'online-fitting-room' ); ?></button></div></div>
					</div>
				</div>
				<p class="ofr__photo-check" data-photo-check role="status" hidden></p>
				<?php if ( 'yes' === $s['remember_photo'] ) : ?>
					<label class="ofr__check"><input type="checkbox" data-remember><span><?php esc_html_e( 'عکسم را روی همین دستگاه نگه دار تا برای لباس‌های دیگر دوباره آپلود نکنم (فقط در مرورگر خودم ذخیره می‌شود).', 'online-fitting-room' ); ?></span></label>
				<?php endif; ?>
				<label class="ofr__check ofr__consent"><input type="checkbox" data-consent><span><?php echo Online_Fitting_Room::consent_html( $s['privacy_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses() in consent_html(). ?></span></label>
				<div class="ofr__captcha" data-captcha hidden></div>
				<div class="ofr__error" role="alert" hidden><span data-error-text></span><a class="ofr__upsell" data-upsell href="#" hidden></a></div>
				<p class="ofr__remaining" data-remaining hidden></p>
				<button type="button" class="ofr__primary" data-start disabled><?php esc_html_e( 'ساخت تصویر پرو', 'online-fitting-room' ); ?> <b aria-hidden="true">←</b></button>
			</div>

			<div class="ofr__stage ofr__working" data-stage="working">
				<div class="ofr__scan" data-scan>
					<div class="ofr__scan-photo"><img data-scan-photo alt=""><span class="ofr__scan-line" aria-hidden="true"></span><span class="ofr__scan-grid" aria-hidden="true"></span></div>
					<span class="ofr__scan-plus" aria-hidden="true">+</span>
					<div class="ofr__scan-garment"><img data-scan-garment alt=""></div>
				</div>
				<div class="ofr__orb" data-orb aria-hidden="true" hidden><span></span></div>
				<h3 tabindex="-1"><?php esc_html_e( 'داریم استایل جدیدت را می‌سازیم', 'online-fitting-room' ); ?></h3>
				<p data-status-text role="status" aria-live="polite"></p>
				<div class="ofr__meter" data-meter role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e( 'پیشرفت تخمینی', 'online-fitting-room' ); ?>"><span></span></div>
				<small data-eta><?php esc_html_e( 'این مرحله معمولاً چند ثانیه زمان می‌برد.', 'online-fitting-room' ); ?></small>
				<button type="button" class="ofr__background" data-background><?php esc_html_e( 'بستن و ادامه خرید — وقتی آماده شد خبرتان می‌کنیم', 'online-fitting-room' ); ?></button>
			</div>

			<div class="ofr__stage ofr__result" data-stage="result">
				<div class="ofr__compare" data-compare-view>
					<img class="ofr__compare-after" data-result alt="<?php esc_attr_e( 'نتیجه پرو مجازی', 'online-fitting-room' ); ?>">
					<div class="ofr__compare-before" data-before-wrap hidden><img data-before alt="<?php esc_attr_e( 'عکس شما پیش از پرو', 'online-fitting-room' ); ?>"></div>
					<span class="ofr__compare-handle" data-handle hidden aria-hidden="true"><i>‹ ›</i></span>
					<input type="range" class="ofr__compare-range" data-compare min="0" max="100" value="50" dir="ltr" hidden aria-label="<?php esc_attr_e( 'مقایسه قبل و بعد', 'online-fitting-room' ); ?>">
					<span class="ofr__compare-tag is-before" data-tag-before hidden><?php esc_html_e( 'قبل', 'online-fitting-room' ); ?></span><span class="ofr__compare-tag is-after" data-tag-after hidden><?php esc_html_e( 'بعد', 'online-fitting-room' ); ?></span>
					<button type="button" class="ofr__fullscreen" data-zoom-result aria-label="<?php esc_attr_e( 'نمایش تمام‌صفحه و بزرگ‌نمایی', 'online-fitting-room' ); ?>">⤢</button>
				</div>
				<div class="ofr__result-side">
					<span class="ofr__success"><?php esc_html_e( '✓ آماده شد', 'online-fitting-room' ); ?></span>
					<h3 tabindex="-1"><?php esc_html_e( 'این استایل چطور شد؟', 'online-fitting-room' ); ?></h3>
					<p data-result-product></p>
					<div class="ofr__swatches is-compact" data-result-swatches hidden><span class="ofr__swatches-label"><?php esc_html_e( 'امتحان با رنگ دیگر:', 'online-fitting-room' ); ?></span><div class="ofr__swatch-list"></div></div>
					<a class="ofr__primary" data-cart href="#"></a>
					<p class="ofr__cart-note" data-cart-note role="status" hidden></p>
					<div class="ofr__row">
						<a class="ofr__secondary" data-download href="#"><?php esc_html_e( 'دانلود', 'online-fitting-room' ); ?></a>
						<button type="button" class="ofr__secondary" data-share hidden><?php esc_html_e( 'اشتراک‌گذاری', 'online-fitting-room' ); ?></button>
					</div>
					<div class="ofr__links">
						<button type="button" data-retry><?php esc_html_e( 'امتحان دوباره با همین عکس', 'online-fitting-room' ); ?></button>
						<button type="button" data-again><?php esc_html_e( 'پرو با عکس دیگر', 'online-fitting-room' ); ?></button>
						<button type="button" data-open-compare hidden></button>
					</div>
				</div>
			</div>

			<div class="ofr__stage ofr__gallery" data-stage="compare">
				<h3 tabindex="-1"><?php esc_html_e( 'مقایسه پروهای شما', 'online-fitting-room' ); ?></h3>
				<div class="ofr__gallery-grid" data-gallery></div>
				<button type="button" class="ofr__secondary" data-back-result><?php esc_html_e( 'بازگشت به نتیجه', 'online-fitting-room' ); ?></button>
			</div>
		</div>
		<div class="ofr__dropzone" data-dropzone hidden aria-hidden="true"><span></span></div>
	</section>
	<div class="ofr__viewer" data-viewer hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'نمایش بزرگ', 'online-fitting-room' ); ?>" tabindex="-1">
		<img data-viewer-img alt="">
		<button type="button" class="ofr__viewer-close" data-viewer-close aria-label="<?php esc_attr_e( 'بستن', 'online-fitting-room' ); ?>">×</button>
		<p class="ofr__viewer-hint"><?php esc_html_e( 'برای بزرگ‌نمایی دو انگشت را باز کنید یا دوبار بزنید', 'online-fitting-room' ); ?></p>
	</div>
</div>
<div class="ofr-toast" data-ofr-toast hidden dir="rtl" role="status" aria-live="polite" data-theme="<?php echo $theme; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>"><span class="ofr-toast__icon" aria-hidden="true"></span><span class="ofr-toast__text" data-toast-text></span><button type="button" class="ofr-toast__action" data-toast-action hidden></button><button type="button" class="ofr-toast__close" data-toast-close aria-label="<?php esc_attr_e( 'بستن', 'online-fitting-room' ); ?>">×</button></div>
