<?php
/**
 * Plugin Name:       Plugin & Theme ZIP Exporter
 * Plugin URI:        https://example.com/plugin-zip-exporter
 * Description:       خروجی گرفتن ZIP از هر افزونه یا قالب نصب‌شده، مستقیم از پیشخوان وردپرس — بدون نیاز به ورود به هاست.
 * Version:           1.1.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            —
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plugin-zip-exporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PZE_VERSION', '1.1.0' );
define( 'PZE_CAPABILITY', 'install_plugins' );
define( 'PZE_CAPABILITY_THEMES', 'install_themes' );

/**
 * Directory and file names that are never worth shipping inside an export.
 */
function pze_excluded_names() {
	return apply_filters(
		'pze_excluded_names',
		array( '.git', '.svn', '.hg', 'node_modules', '.DS_Store', '.idea', '.vscode', 'Thumbs.db' )
	);
}

/* -------------------------------------------------------------------------
 * Admin pages
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'pze_register_menu' );
function pze_register_menu() {
	add_submenu_page(
		'plugins.php',
		'خروجی ZIP افزونه‌ها',
		'خروجی ZIP',
		PZE_CAPABILITY,
		'plugin-zip-exporter',
		'pze_render_page'
	);

	add_submenu_page(
		'themes.php',
		'خروجی ZIP قالب‌ها',
		'خروجی ZIP قالب',
		PZE_CAPABILITY_THEMES,
		'theme-zip-exporter',
		'pze_render_themes_page'
	);
}

/**
 * Tab bar shared by both screens, so each one links to the other.
 */
function pze_render_tabs( $current ) {
	$tabs = array();
	if ( current_user_can( PZE_CAPABILITY ) ) {
		$tabs['plugins'] = array( 'افزونه‌ها', admin_url( 'plugins.php?page=plugin-zip-exporter' ) );
	}
	if ( current_user_can( PZE_CAPABILITY_THEMES ) ) {
		$tabs['themes'] = array( 'قالب‌ها', admin_url( 'themes.php?page=theme-zip-exporter' ) );
	}
	if ( count( $tabs ) < 2 ) {
		return;
	}
	echo '<h2 class="nav-tab-wrapper" style="margin-bottom:16px;">';
	foreach ( $tabs as $key => $tab ) {
		printf(
			'<a class="nav-tab%s" href="%s">%s</a>',
			$key === $current ? ' nav-tab-active' : '',
			esc_url( $tab[1] ),
			esc_html( $tab[0] )
		);
	}
	echo '</h2>';
}

function pze_render_page() {
	if ( ! current_user_can( PZE_CAPABILITY ) ) {
		wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.' );
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugins = get_plugins();
	uasort(
		$plugins,
		function ( $a, $b ) {
			return strcasecmp( $a['Name'], $b['Name'] );
		}
	);
	?>
	<div class="wrap">
		<h1>خروجی ZIP افزونه‌ها</h1>
		<?php pze_render_tabs( 'plugins' ); ?>
		<?php pze_render_notices(); ?>

		<p class="description" style="font-size:14px;max-width:760px;">
			از هر افزونهٔ نصب‌شده روی این سایت یک فایل ZIP بگیرید. فایل ساخته و مستقیماً دانلود می‌شود؛
			چیزی روی سرور باقی نمی‌ماند. پوشه‌هایی مثل <code>.git</code> و <code>node_modules</code> از خروجی حذف می‌شوند.
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pze_export">
			<?php wp_nonce_field( 'pze_export' ); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column"><input type="checkbox" class="pze-check-all"></td>
						<th scope="col">افزونه</th>
						<th scope="col" style="width:100px;">نسخه</th>
						<th scope="col" style="width:120px;">وضعیت</th>
						<th scope="col" style="width:110px;">حجم</th>
						<th scope="col" style="width:140px;">دانلود</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $plugins as $file => $data ) : ?>
					<?php
					$slug   = pze_slug_from_file( $file );
					$path   = pze_source_path( $file );
					$size   = $path ? pze_dir_size( $path ) : 0;
					$active = is_plugin_active( $file );
					$dl_url = wp_nonce_url(
						admin_url( 'admin-post.php?action=pze_export&plugin=' . rawurlencode( $file ) ),
						'pze_export'
					);
					?>
					<tr>
						<th scope="row" class="check-column">
							<input type="checkbox" name="plugins[]" value="<?php echo esc_attr( $file ); ?>">
						</th>
						<td>
							<strong><?php echo esc_html( $data['Name'] ); ?></strong>
							<div class="row-actions" style="color:#666;">
								<code><?php echo esc_html( $slug ); ?></code>
							</div>
						</td>
						<td><?php echo esc_html( $data['Version'] ? $data['Version'] : '—' ); ?></td>
						<td>
							<?php if ( $active ) : ?>
								<span style="color:#1d7a30;">فعال</span>
							<?php else : ?>
								<span style="color:#777;">غیرفعال</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( size_format( $size, 1 ) ); ?></td>
						<td>
							<a class="button button-primary" href="<?php echo esc_url( $dl_url ); ?>">دانلود ZIP</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top:16px;">
				<button type="submit" class="button">دانلود موارد انتخاب‌شده (یک فایل ZIP)</button>
			</p>
		</form>
	</div>
	<?php
	pze_render_check_all_script();
}

function pze_render_themes_page() {
	if ( ! current_user_can( PZE_CAPABILITY_THEMES ) ) {
		wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.' );
	}

	$themes = wp_get_themes();
	uasort(
		$themes,
		function ( $a, $b ) {
			return strcasecmp( $a->get( 'Name' ), $b->get( 'Name' ) );
		}
	);

	$active_stylesheet = get_stylesheet();
	$active_template   = get_template();
	?>
	<div class="wrap">
		<h1>خروجی ZIP قالب‌ها</h1>
		<?php pze_render_tabs( 'themes' ); ?>
		<?php pze_render_notices(); ?>

		<p class="description" style="font-size:14px;max-width:760px;">
			از هر قالب نصب‌شده یک فایل ZIP بگیرید — همان فایلی که می‌شود در سایت دیگری از راه
			«نمایش ← قالب‌ها ← افزودن ← بارگذاری قالب» نصب کرد. برای چایلد-تم‌ها گزینهٔ
			«همراه والد» یک ZIP شامل هر دو قالب می‌سازد تا در مقصد چیزی جا نماند.
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pze_export_theme">
			<?php wp_nonce_field( 'pze_export_theme' ); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column"><input type="checkbox" class="pze-check-all"></td>
						<th scope="col">قالب</th>
						<th scope="col" style="width:100px;">نسخه</th>
						<th scope="col" style="width:170px;">نوع</th>
						<th scope="col" style="width:120px;">وضعیت</th>
						<th scope="col" style="width:110px;">حجم</th>
						<th scope="col" style="width:230px;">دانلود</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $themes as $stylesheet => $theme ) : ?>
					<?php
					$path      = pze_theme_source_path( $stylesheet );
					$size      = $path ? pze_dir_size( $path ) : 0;
					$parent    = $theme->parent();
					$is_active = ( $stylesheet === $active_stylesheet );
					$in_use    = $is_active || ( $theme->get_stylesheet() === $active_template );
					$dl_url    = wp_nonce_url(
						admin_url( 'admin-post.php?action=pze_export_theme&theme=' . rawurlencode( $stylesheet ) ),
						'pze_export_theme'
					);
					$dl_parent = wp_nonce_url(
						admin_url( 'admin-post.php?action=pze_export_theme&with_parent=1&theme=' . rawurlencode( $stylesheet ) ),
						'pze_export_theme'
					);
					?>
					<tr>
						<th scope="row" class="check-column">
							<input type="checkbox" name="themes[]" value="<?php echo esc_attr( $stylesheet ); ?>">
						</th>
						<td>
							<strong><?php echo esc_html( $theme->get( 'Name' ) ); ?></strong>
							<div class="row-actions" style="color:#666;">
								<code><?php echo esc_html( $stylesheet ); ?></code>
							</div>
						</td>
						<td><?php echo esc_html( $theme->get( 'Version' ) ? $theme->get( 'Version' ) : '—' ); ?></td>
						<td>
							<?php if ( $parent ) : ?>
								چایلد-تم از
								<code><?php echo esc_html( $theme->get_template() ); ?></code>
							<?php else : ?>
								<span style="color:#777;">قالب مستقل</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_active ) : ?>
								<span style="color:#1d7a30;">فعال</span>
							<?php elseif ( $in_use ) : ?>
								<span style="color:#1d7a30;">والدِ فعال</span>
							<?php else : ?>
								<span style="color:#777;">غیرفعال</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( size_format( $size, 1 ) ); ?></td>
						<td>
							<a class="button button-primary" href="<?php echo esc_url( $dl_url ); ?>">دانلود ZIP</a>
							<?php if ( $parent ) : ?>
								<a class="button" href="<?php echo esc_url( $dl_parent ); ?>">همراه والد</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top:16px;">
				<label style="margin-inline-end:16px;">
					<input type="checkbox" name="with_parent" value="1">
					قالب والدِ چایلد-تم‌های انتخاب‌شده هم اضافه شود
				</label>
				<button type="submit" class="button">دانلود موارد انتخاب‌شده (یک فایل ZIP)</button>
			</p>
		</form>
	</div>
	<?php
	pze_render_check_all_script();
}

/**
 * Error notice shared by both screens.
 */
function pze_render_notices() {
	if ( ! class_exists( 'ZipArchive' ) ) {
		echo '<div class="notice notice-error"><p>افزونهٔ PHP به نام <code>ZipArchive</code> روی این سرور فعال نیست. برای ساخت فایل ZIP این افزونه لازم است — از هاستینگ خود فعال‌سازی آن را بخواهید.</p></div>';
	}

	$error = isset( $_GET['pze_error'] ) ? sanitize_text_field( wp_unslash( $_GET['pze_error'] ) ) : '';
	if ( $error ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
	}
}

function pze_render_check_all_script() {
	?>
	<script>
	document.querySelectorAll( '.pze-check-all' ).forEach( function ( master ) {
		master.addEventListener( 'change', function ( e ) {
			var table = e.target.closest( 'table' );
			if ( ! table ) {
				return;
			}
			table.querySelectorAll( 'tbody input[type="checkbox"]' ).forEach( function ( cb ) {
				cb.checked = e.target.checked;
			} );
		} );
	} );
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Export handlers
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_pze_export', 'pze_handle_export' );
function pze_handle_export() {
	if ( ! current_user_can( PZE_CAPABILITY ) ) {
		wp_die( 'شما اجازهٔ خروجی گرفتن از افزونه‌ها را ندارید.', 403 );
	}
	check_admin_referer( 'pze_export' );

	if ( ! class_exists( 'ZipArchive' ) ) {
		pze_bail( 'افزونهٔ PHP به نام ZipArchive روی سرور فعال نیست.' );
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = get_plugins();

	// Requested plugins arrive either as a single ?plugin= or a checkbox array.
	$requested = array();
	if ( isset( $_GET['plugin'] ) ) {
		$requested[] = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
	} elseif ( isset( $_POST['plugins'] ) && is_array( $_POST['plugins'] ) ) {
		$requested = array_map( 'sanitize_text_field', wp_unslash( $_POST['plugins'] ) );
	}

	// Only ever act on keys WordPress itself reports — this is what blocks path traversal.
	$requested = array_values( array_intersect( $requested, array_keys( $installed ) ) );

	if ( empty( $requested ) ) {
		pze_bail( 'هیچ افزونهٔ معتبری انتخاب نشده بود.' );
	}

	$sources = array();
	foreach ( $requested as $file ) {
		$source = pze_source_path( $file );
		if ( $source ) {
			$sources[ pze_slug_from_file( $file ) ] = $source;
		}
	}

	if ( 1 === count( $requested ) ) {
		$data     = $installed[ $requested[0] ];
		$version  = $data['Version'] ? '.' . $data['Version'] : '';
		$filename = pze_slug_from_file( $requested[0] ) . $version . '.zip';
	} else {
		$filename = 'plugins-export-' . gmdate( 'Y-m-d-His' ) . '.zip';
	}

	pze_build_and_stream( $sources, $filename );
}

add_action( 'admin_post_pze_export_theme', 'pze_handle_export_theme' );
function pze_handle_export_theme() {
	if ( ! current_user_can( PZE_CAPABILITY_THEMES ) ) {
		wp_die( 'شما اجازهٔ خروجی گرفتن از قالب‌ها را ندارید.', 403 );
	}
	check_admin_referer( 'pze_export_theme' );

	if ( ! class_exists( 'ZipArchive' ) ) {
		pze_bail( 'افزونهٔ PHP به نام ZipArchive روی سرور فعال نیست.', 'themes' );
	}

	$installed = wp_get_themes();

	// Requested themes arrive either as a single ?theme= or a checkbox array.
	$requested = array();
	if ( isset( $_GET['theme'] ) ) {
		$requested[] = sanitize_text_field( wp_unslash( $_GET['theme'] ) );
	} elseif ( isset( $_POST['themes'] ) && is_array( $_POST['themes'] ) ) {
		$requested = array_map( 'sanitize_text_field', wp_unslash( $_POST['themes'] ) );
	}

	// Same gate as plugins: only stylesheets WordPress itself reports are allowed.
	$requested = array_values( array_intersect( $requested, array_keys( $installed ) ) );

	if ( empty( $requested ) ) {
		pze_bail( 'هیچ قالب معتبری انتخاب نشده بود.', 'themes' );
	}

	$with_parent = ! empty( $_REQUEST['with_parent'] );
	$selected    = $requested;

	if ( $with_parent ) {
		foreach ( $selected as $stylesheet ) {
			$template = $installed[ $stylesheet ]->get_template();
			if ( $template && $template !== $stylesheet && isset( $installed[ $template ] )
				&& ! in_array( $template, $requested, true ) ) {
				$requested[] = $template;
			}
		}
	}

	$sources = array();
	foreach ( $requested as $stylesheet ) {
		$source = pze_theme_source_path( $stylesheet );
		if ( $source ) {
			$sources[ basename( $source ) ] = $source;
		}
	}

	if ( 1 === count( $requested ) ) {
		$theme    = $installed[ $requested[0] ];
		$version  = $theme->get( 'Version' ) ? '.' . $theme->get( 'Version' ) : '';
		$filename = basename( $theme->get_stylesheet_directory() ) . $version . '.zip';
	} else {
		$filename = 'themes-export-' . gmdate( 'Y-m-d-His' ) . '.zip';
	}

	pze_build_and_stream( $sources, $filename, 'themes' );
}

/* -------------------------------------------------------------------------
 * Archive building
 * ---------------------------------------------------------------------- */

/**
 * Zip up a map of "folder name inside the archive" => "absolute source path",
 * then hand the file to the browser.
 */
function pze_build_and_stream( array $sources, $filename, $screen = 'plugins' ) {
	if ( empty( $sources ) ) {
		pze_bail( 'مسیر هیچ‌کدام از موارد انتخاب‌شده پیدا نشد.', $screen );
	}

	$tmp = pze_tmp_file();
	if ( ! $tmp ) {
		pze_bail( 'ساخت فایل موقت ممکن نشد؛ پوشهٔ uploads قابل نوشتن نیست.', $screen );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		@unlink( $tmp );
		pze_bail( 'باز کردن فایل ZIP برای نوشتن ممکن نشد.', $screen );
	}

	foreach ( $sources as $local_root => $source ) {
		if ( is_dir( $source ) ) {
			pze_add_dir( $zip, $source, $local_root );
		} else {
			$zip->addFile( $source, basename( $source ) );
		}
	}

	$zip->close();

	if ( ! file_exists( $tmp ) || ! filesize( $tmp ) ) {
		@unlink( $tmp );
		pze_bail( 'فایل ZIP ساخته شد اما خالی بود.', $screen );
	}

	pze_stream( $tmp, $filename );
}

/**
 * Send the archive to the browser and delete it afterwards.
 */
function pze_stream( $path, $filename ) {
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );

	// Clear any buffered admin output so it can't corrupt the archive.
	while ( ob_get_level() ) {
		ob_end_clean();
	}

	readfile( $path );
	@unlink( $path );
	exit;
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * "akismet/akismet.php" => "akismet"; "hello.php" => "hello".
 */
function pze_slug_from_file( $file ) {
	return false !== strpos( $file, '/' ) ? dirname( $file ) : basename( $file, '.php' );
}

/**
 * Absolute path of a plugin's folder, or of the file itself for single-file plugins.
 * Returns '' when the resolved path escapes the plugins directory.
 */
function pze_source_path( $file ) {
	$root = wp_normalize_path( realpath( WP_PLUGIN_DIR ) );
	$path = realpath( WP_PLUGIN_DIR . '/' . ( false !== strpos( $file, '/' ) ? dirname( $file ) : $file ) );

	if ( ! $path ) {
		return '';
	}
	$path = wp_normalize_path( $path );

	return 0 === strpos( $path, $root . '/' ) ? $path : '';
}

/**
 * Absolute path of a theme's folder.
 * Returns '' when the resolved path sits outside every registered theme root.
 */
function pze_theme_source_path( $stylesheet ) {
	$theme = wp_get_theme( $stylesheet );
	if ( ! $theme->exists() ) {
		return '';
	}

	$path = realpath( $theme->get_stylesheet_directory() );
	if ( ! $path ) {
		return '';
	}
	$path = wp_normalize_path( $path );

	$roots = isset( $GLOBALS['wp_theme_directories'] ) ? (array) $GLOBALS['wp_theme_directories'] : array();
	$roots[] = get_theme_root( $stylesheet );

	foreach ( $roots as $root ) {
		$root = realpath( $root );
		if ( ! $root ) {
			continue;
		}
		$root = untrailingslashit( wp_normalize_path( $root ) );
		if ( 0 === strpos( $path, $root . '/' ) ) {
			return $path;
		}
	}

	return '';
}

/**
 * Recursively add a directory to the archive under $local_root.
 */
function pze_add_dir( ZipArchive $zip, $source, $local_root ) {
	$excluded = pze_excluded_names();
	$source   = rtrim( wp_normalize_path( $source ), '/' );

	$iterator = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
			function ( $current ) use ( $excluded ) {
				return ! in_array( $current->getFilename(), $excluded, true );
			}
		),
		RecursiveIteratorIterator::SELF_FIRST
	);

	$zip->addEmptyDir( $local_root );

	foreach ( $iterator as $item ) {
		$real = wp_normalize_path( $item->getPathname() );
		$rel  = ltrim( substr( $real, strlen( $source ) ), '/' );
		if ( '' === $rel ) {
			continue;
		}

		if ( $item->isDir() ) {
			$zip->addEmptyDir( $local_root . '/' . $rel );
		} elseif ( $item->isLink() ) {
			continue; // Symlinks would either break or escape the archive.
		} elseif ( $item->isFile() && $item->isReadable() ) {
			$zip->addFile( $real, $local_root . '/' . $rel );
		}
	}
}

/**
 * Total size of a directory (or of a single file) in bytes.
 */
function pze_dir_size( $path ) {
	if ( is_file( $path ) ) {
		return (int) filesize( $path );
	}

	$size     = 0;
	$excluded = pze_excluded_names();

	try {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
				function ( $current ) use ( $excluded ) {
					return ! in_array( $current->getFilename(), $excluded, true );
				}
			)
		);
		foreach ( $iterator as $item ) {
			if ( $item->isFile() ) {
				$size += $item->getSize();
			}
		}
	} catch ( Exception $e ) {
		return 0;
	}

	return $size;
}

/**
 * A writable temp path inside uploads, falling back to the system temp dir.
 */
function pze_tmp_file() {
	$uploads = wp_upload_dir();
	$base    = ( ! empty( $uploads['basedir'] ) && wp_is_writable( $uploads['basedir'] ) )
		? $uploads['basedir']
		: get_temp_dir();

	$path = trailingslashit( $base ) . 'pze-' . wp_generate_password( 12, false ) . '.zip';

	return wp_is_writable( dirname( $path ) ) ? $path : '';
}

/**
 * Send the user back to the matching export screen with a message.
 */
function pze_bail( $message, $screen = 'plugins' ) {
	$url = ( 'themes' === $screen )
		? admin_url( 'themes.php?page=theme-zip-exporter' )
		: admin_url( 'plugins.php?page=plugin-zip-exporter' );

	wp_safe_redirect( add_query_arg( 'pze_error', rawurlencode( $message ), $url ) );
	exit;
}
