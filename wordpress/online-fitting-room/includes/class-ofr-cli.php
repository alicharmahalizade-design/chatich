<?php
/**
 * WP-CLI: wp ofr <command>
 *
 *     wp ofr status            Settings, key, balance and today's usage at a glance.
 *     wp ofr test              Connection test to the try-on service.
 *     wp ofr health            Runs the Site Health checks (incl. web server upload limit).
 *     wp ofr stats [--days=30] Try-ons, add-to-cart, orders and revenue.
 *     wp ofr readiness [--status=blocked]
 *     wp ofr balance [--refresh]
 *     wp ofr logs [--limit=20]
 *     wp ofr cleanup           Deletes expired results, jobs and old rows now.
 *     wp ofr alert-test --channel=email|sms
 */

defined( 'ABSPATH' ) || exit;

class OFR_CLI {
	/**
	 * Settings, key, balance and today's usage at a glance.
	 */
	public function status() {
		$s       = Online_Fitting_Room::settings();
		$balance = OFR_Alerts::balance();
		$rows    = array(
			array(
				'item'  => 'version',
				'value' => Online_Fitting_Room::VERSION,
			),
			array(
				'item'  => 'enabled',
				'value' => $s['enabled'],
			),
			array(
				'item'  => 'api key',
				'value' => OFR_Api::masked_key() ?: '—',
			),
			array(
				'item'  => 'key source',
				'value' => OFR_Api::key_source() ?: '—',
			),
			array(
				'item'  => 'api base',
				'value' => OFR_Api::base(),
			),
			array(
				'item'  => 'try-ons today',
				'value' => Online_Fitting_Room::instance()->usage_today(),
			),
			array(
				'item'  => 'balance',
				'value' => null === $balance['value'] ? '— ' . $balance['error'] : $balance['value'] . ' (' . $balance['source'] . ')',
			),
			array(
				'item'  => 'encryption',
				'value' => OFR_Storage::encryption() ?: 'none',
			),
			array(
				'item'  => 'server formats',
				'value' => implode( ',', OFR_Image::server_formats() ),
			),
			array(
				'item'  => 'pose files',
				'value' => OFR_Pose::is_local() ? 'local' : 'cdn',
			),
		);
		WP_CLI\Utils\format_items( 'table', $rows, array( 'item', 'value' ) );
	}

	/**
	 * Connection test to the try-on service.
	 */
	public function test() {
		OFR_Api::start_budget();
		$result = OFR_Api::test_connection();
		$result['ok'] ? WP_CLI::success( $result['message'] ) : WP_CLI::error( $result['message'] );
	}

	/**
	 * Runs the Site Health checks of the plugin.
	 */
	public function health() {
		$rows = array();
		foreach ( array( 'encryption', 'cleanup', 'proxy', 'counters', 'imagick', 'upload', 'private', 'connection' ) as $test ) {
			$result = call_user_func( array( 'OFR_Health', 'test_' . $test ) );
			$rows[] = array(
				'test'   => $test,
				'status' => $result['status'],
				'label'  => $result['label'],
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'test', 'status', 'label' ) );
	}

	/**
	 * Try-ons, add-to-cart, orders and revenue.
	 *
	 * [--days=<days>]
	 * : Period in days.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--format=<format>]
	 * : table, json or csv.
	 * ---
	 * default: table
	 * ---
	 */
	public function stats( $args, $assoc ) {
		$summary = OFR_Stats::summary( (int) ( $assoc['days'] ?? 30 ) );
		$rows    = array();
		foreach ( $summary as $key => $value ) {
			$rows[] = array(
				'metric' => $key,
				'value'  => $value,
			);
		}
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array( 'metric', 'value' ) );
	}

	/**
	 * Product readiness for try-on.
	 *
	 * [--status=<status>]
	 * : ready, warning, blocked or off.
	 *
	 * [--format=<format>]
	 * ---
	 * default: table
	 * ---
	 */
	public function readiness( $args, $assoc ) {
		OFR_Readiness::flush();
		$scan = OFR_Readiness::scan( 1, $assoc['status'] ?? '' );
		$rows = array();
		do {
			foreach ( $scan['rows'] as $row ) {
				$rows[] = array(
					'id'      => $row['id'],
					'product' => $row['name'],
					'status'  => $row['status'],
					'issues'  => implode( ' | ', wp_list_pluck( $row['issues'], 'text' ) ),
				);
			}
			$page = isset( $page ) ? $page + 1 : 2;
			$scan = $page <= $scan['pages'] ? OFR_Readiness::scan( $page, $assoc['status'] ?? '' ) : null;
		} while ( $scan );
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array( 'id', 'product', 'status', 'issues' ) );
	}

	/**
	 * Remaining service credit.
	 *
	 * [--refresh]
	 * : Ask the service now instead of using the cached value.
	 */
	public function balance( $args, $assoc ) {
		$balance = OFR_Alerts::balance( ! empty( $assoc['refresh'] ) );
		null === $balance['value'] ? WP_CLI::error( $balance['error'] ?: 'Unknown balance.' ) : WP_CLI::line( $balance['value'] . ' (' . $balance['source'] . ')' );
	}

	/**
	 * Latest log entries.
	 *
	 * [--limit=<limit>]
	 * ---
	 * default: 20
	 * ---
	 */
	public function logs( $args, $assoc ) {
		$rows = array_map(
			function ( $row ) {
				return array(
					'time'    => wp_date( 'Y-m-d H:i:s', (int) $row['created'] ),
					'level'   => $row['level'],
					'context' => $row['context'],
					'code'    => $row['code'],
					'detail'  => $row['detail'],
				);
			},
			OFR_Log::recent( (int) ( $assoc['limit'] ?? 20 ) )
		);
		WP_CLI\Utils\format_items( 'table', $rows, array( 'time', 'level', 'context', 'code', 'detail' ) );
	}

	/**
	 * Deletes expired results, jobs and old rows now.
	 */
	public function cleanup() {
		OFR_Storage::cleanup();
		WP_CLI::success( 'Cleanup done.' );
	}

	/**
	 * Sends a test alert.
	 *
	 * --channel=<channel>
	 * : email or sms.
	 *
	 * @subcommand alert-test
	 */
	public function alert_test( $args, $assoc ) {
		$text   = 'Online Fitting Room test alert — ' . home_url();
		$result = 'sms' === $assoc['channel'] ? OFR_Alerts::send_sms( $text ) : OFR_Alerts::send_email( $text );
		$result['ok'] ? WP_CLI::success( 'Sent.' ) : WP_CLI::error( $result['detail'] ?: 'Failed.' );
	}
}

WP_CLI::add_command( 'ofr', 'OFR_CLI' );
