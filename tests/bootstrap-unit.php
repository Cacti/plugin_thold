<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Test bootstrap.
 *
 * thold's sources expect to be included by Cacti, which has already defined
 * the db_*, request-variable, and logging helpers as plain global functions.
 * Nothing here talks to a database or a network: each Cacti function is
 * declared as a shim over CactiStubs, which records the call and hands back
 * whatever the test programmed.
 *
 * Guarding every declaration with function_exists() keeps this file usable if
 * a future integration suite loads real Cacti first.
 */

$cacti_root = dirname(__DIR__, 3);
$autoload   = $cacti_root . '/include/vendor/autoload.php';
$version    = $cacti_root . '/include/cacti_version';
$expected   = __DIR__ . '/.cacti-version';

if (!is_readable($autoload)) {
	throw new RuntimeException("Cacti Composer autoloader is not readable: $autoload");
}

if (!is_readable($version)) {
	throw new RuntimeException("Cacti version file is not readable: $version");
}

if (!is_readable($expected)) {
	throw new RuntimeException("Expected Cacti version file is not readable: $expected");
}

$cacti_version = trim((string) file_get_contents($version));
$expected_version = trim((string) file_get_contents($expected));

if ($cacti_version === '') {
	throw new RuntimeException("Cacti version file is empty: $version");
}

if ($expected_version === '') {
	throw new RuntimeException("Expected Cacti version file is empty: $expected");
}

if ($cacti_version !== $expected_version) {
	throw new RuntimeException("Expected Cacti $expected_version, found $cacti_version in $version");
}

require_once $autoload;
require_once __DIR__ . '/Helpers/CactiStubs.php';
require_once __DIR__ . '/TestCase.php';

/*
 * base_path has to point at the Cacti root two levels above this plugin:
 * thold_functions.php builds include paths from it at runtime.
 */
$GLOBALS['config'] = [
	'base_path'       => $cacti_root,
	'url_path'        => '/cacti/',
	'cacti_version'   => $cacti_version,
	'cacti_server_os' => 'unix',
];

// thold_expand_string() include_once()s library_path/variables.php at call time.
$GLOBALS['config']['library_path'] = __DIR__ . '/fixtures/cacti-lib';

// thold reads and writes this on every RPN evaluation.
$GLOBALS['rpn_error'] = false;

// Cacti's list of enabled plugins; thold_check_threshold() declares it global.
$GLOBALS['plugins'] = [];

// Cacti's debug flag, also declared global by thold_check_threshold().
$GLOBALS['debug'] = false;

if (!function_exists('db_execute')) {
	function db_execute($sql, $log = true, $db_conn = false) {
		CactiStubs::record('db_execute', $sql);

		return CactiStubs::nextReturn('db_execute', true, $sql);
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
		CactiStubs::record('db_execute_prepared', $sql, $params);

		return CactiStubs::nextReturn('db_execute_prepared', true, $sql);
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql, $log = true, $db_conn = false) {
		CactiStubs::record('db_fetch_assoc', $sql);

		return CactiStubs::nextReturn('db_fetch_assoc', [], $sql);
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false) {
		CactiStubs::record('db_fetch_assoc_prepared', $sql, $params);

		return CactiStubs::nextReturn('db_fetch_assoc_prepared', [], $sql);
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql, $log = true, $db_conn = false) {
		CactiStubs::record('db_fetch_row', $sql);

		return CactiStubs::nextReturn('db_fetch_row', [], $sql);
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = [], $log = true, $db_conn = false) {
		CactiStubs::record('db_fetch_row_prepared', $sql, $params);

		return CactiStubs::nextReturn('db_fetch_row_prepared', [], $sql);
	}
}

/*
 * Cacti's cell fetchers return false, not '', when the query matches no row.
 * The difference matters on PHP 8: false coerces to 0 in arithmetic while ''
 * raises a TypeError, so a stub returning '' invents failures that production
 * does not have.
 */
if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql, $col_name = '', $log = true, $db_conn = false) {
		CactiStubs::record('db_fetch_cell', $sql);

		return CactiStubs::nextReturn('db_fetch_cell', false, $sql);
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true, $db_conn = false) {
		CactiStubs::record('db_fetch_cell_prepared', $sql, $params);

		return CactiStubs::nextReturn('db_fetch_cell_prepared', false, $sql);
	}
}

if (!function_exists('db_qstr')) {
	function db_qstr($string) {
		return "'" . str_replace("'", "''", (string) $string) . "'";
	}
}

if (!function_exists('db_begin_transaction')) {
	function db_begin_transaction() {
		CactiStubs::record('db_begin_transaction');

		return CactiStubs::nextReturn('db_begin_transaction', true);
	}
}

if (!function_exists('db_commit_transaction')) {
	function db_commit_transaction() {
		CactiStubs::record('db_commit_transaction');

		return CactiStubs::nextReturn('db_commit_transaction', true);
	}
}

if (!function_exists('db_rollback_transaction')) {
	function db_rollback_transaction() {
		CactiStubs::record('db_rollback_transaction');

		return CactiStubs::nextReturn('db_rollback_transaction', true);
	}
}

if (!function_exists('html_escape')) {
	function html_escape($string) {
		return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
	}
}

/*
 * Mirrors Cacti 1.2 lib/functions.php. KEEP IN SYNC: if core tightens its
 * checks, tests here would otherwise keep passing while production diverges.
 */
if (!function_exists('sanitize_unserialize_selected_items')) {
	function sanitize_unserialize_selected_items($items) {
		if (empty($items)) {
			return false;
		}

		$data = unserialize($items, ['allowed_classes' => false]); // nosemgrep: php.lang.security.unserialize-use.unserialize-use -- test stub mirroring Cacti core; allowed_classes:false blocks object injection

		if (!is_array($data)) {
			return false;
		}

		foreach ($data as $value) {
			if (!is_numeric($value)) {
				return false;
			}
		}

		return $data;
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name, $force = false) {
		return isset(CactiStubs::$configOptions[$name]) ? CactiStubs::$configOptions[$name] : '';
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($name, $value) {
		CactiStubs::$configOptions[$name] = $value;
	}
}

if (!function_exists('__')) {
	function __($text) {
		$args = array_slice(func_get_args(), 1);

		// Cacti's __() accepts sprintf arguments after the format string.
		return $args === [] ? $text : vsprintf($text, $args);
	}
}

if (!function_exists('__esc')) {
	function __esc($text) {
		return htmlspecialchars(call_user_func_array('__', func_get_args()), ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = 0) {
		CactiStubs::$log[] = $message;
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return (is_array($array) || $array instanceof Countable) ? count($array) : 0;
	}
}

if (!function_exists('cacti_count')) {
	function cacti_count($array) {
		return cacti_sizeof($array);
	}
}

if (!function_exists('get_request_var')) {
	function get_request_var($name, $default = '') {
		return isset(CactiStubs::$requestVars[$name]) ? CactiStubs::$requestVars[$name] : $default;
	}
}

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($name, $default = '') {
		return get_request_var($name, $default);
	}
}

if (!function_exists('get_filter_request_var')) {
	function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = []) {
		return get_request_var($name);
	}
}

if (!function_exists('isset_request_var')) {
	function isset_request_var($name) {
		return isset(CactiStubs::$requestVars[$name]);
	}
}

if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg($string, $quote = true) {
		return escapeshellarg((string) $string);
	}
}

if (!function_exists('api_plugin_hook_function')) {
	function api_plugin_hook_function($name, $data = '') {
		return $data;
	}
}

if (!function_exists('get_simple_graph_perms')) {
	function get_simple_graph_perms($user_id) {
		return CactiStubs::nextReturn('get_simple_graph_perms', true);
	}
}

if (!function_exists('get_policies')) {
	function get_policies($user_id) {
		return CactiStubs::nextReturn('get_policies', []);
	}
}

if (!function_exists('get_policy_where')) {
	function get_policy_where($graph_auth_method, $policies, $sql_where) {
		CactiStubs::record('get_policy_where', $sql_where);

		return CactiStubs::nextReturn('get_policy_where', $sql_where);
	}
}

if (!function_exists('expand_title')) {
	function expand_title($host_id, $snmp_query_id, $snmp_index, $title) {
		CactiStubs::record('expand_title', $title);

		return CactiStubs::nextReturn('expand_title', $title);
	}
}

if (!function_exists('get_graph_title')) {
	function get_graph_title($local_graph_id) {
		return CactiStubs::nextReturn('get_graph_title', 'Traffic - eth0');
	}
}

if (!function_exists('rrdtool_function_fetch')) {
	function rrdtool_function_fetch($local_data_id, $start_time, $end_time, $resolution = 0, $show_unknown = false, $rrdtool_file = null) {
		CactiStubs::record('rrdtool_function_fetch', (string) $local_data_id);

		return CactiStubs::nextReturn('rrdtool_function_fetch', []);
	}
}

if (!function_exists('get_data_source_path')) {
	function get_data_source_path($local_data_id, $expand_paths = true) {
		return '/var/lib/cacti/rra/test_' . (int) $local_data_id . '.rrd';
	}
}

if (!function_exists('sql_save')) {
	function sql_save($array_items, $table_name, $key_cols = 'id', $autoinc = true, $db_conn = false) {
		CactiStubs::record('sql_save', $table_name, $array_items);

		return CactiStubs::nextReturn('sql_save', 1);
	}
}

if (!function_exists('db_affected_rows')) {
	function db_affected_rows($db_conn = false) {
		return CactiStubs::nextReturn('db_affected_rows', 1);
	}
}

if (!function_exists('rrdtool_function_graph')) {
	function rrdtool_function_graph($local_graph_id, $rra_id, $graph_data_array, $rrdtool_pipe = false, &$xport_meta = [], $user = 0) {
		CactiStubs::record('rrdtool_function_graph', (string) $local_graph_id);

		// A one-pixel PNG stands in for the rendered graph.
		return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAAAAAA6fptVAAAACklEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==', true);
	}
}

if (!function_exists('get_timespan')) {
	function get_timespan(&$timespan, $time, $span, $first_weekdayid) {
		$timespan['begin_now'] = $time - 86400;
		$timespan['end_now']   = $time;
	}
}

if (!function_exists('read_user_setting')) {
	function read_user_setting($config_name, $default = false, $force = false, $user = 0) {
		return CactiStubs::nextReturn('read_user_setting', $default);
	}
}

if (!function_exists('get_selected_theme')) {
	function get_selected_theme() {
		return 'modern';
	}
}

if (!function_exists('mailer')) {
	function mailer($from, $to, $cc = '', $bcc = '', $replyto = '', $subject = '', $body = '', $body_text = '', $attachments = null, $headers = [], $html = true) {
		CactiStubs::$mail[] = [
			'to'      => is_array($to) ? implode(',', $to) : (string) $to,
			'bcc'     => is_array($bcc) ? implode(',', $bcc) : (string) $bcc,
			'subject' => (string) $subject,
		];

		return CactiStubs::nextReturn('mailer', '');
	}
}

if (!function_exists('cacti_snmp_send')) {
	function cacti_snmp_send($hostname, $version, $community, $oid, $value, $type = 's') {
		CactiStubs::record('cacti_snmp_send', (string) $oid);

		return true;
	}
}

if (!function_exists('debounce_run_notification')) {
	function debounce_run_notification($id, $frequency = 60, ...$args) {
		CactiStubs::record('debounce_run_notification', (string) $id);
	}
}

if (!function_exists('array_rekey')) {
	function array_rekey($array, $key, $key_value) {
		$ret_array = [];

		if (is_array($array)) {
			foreach ($array as $item) {
				$item_key = $item[$key];

				if (is_array($key_value)) {
					foreach ($key_value as $value) {
						$ret_array[$item_key][$value] = $item[$value];
					}
				} else {
					$ret_array[$item_key] = $item[$key_value];
				}
			}
		}

		return $ret_array;
	}
}

if (!function_exists('clean_up_name')) {
	function clean_up_name($string) {
		$string = preg_replace('/[\s\.]+/', '_', $string);
		$string = preg_replace('/[^a-zA-Z0-9_]+/', '', $string);

		return preg_replace('/_{2,}/', '_', $string);
	}
}

if (!function_exists('plugin_maint_check_cacti_host')) {
	function plugin_maint_check_cacti_host($host_id) {
		return CactiStubs::nextReturn('plugin_maint_check_cacti_host', false);
	}
}

if (!function_exists('api_plugin_is_enabled')) {
	function api_plugin_is_enabled($plugin) {
		return CactiStubs::nextReturn('api_plugin_is_enabled', false);
	}
}

if (!function_exists('api_plugin_hook')) {
	function api_plugin_hook($name, $data = '') {
		CactiStubs::record('api_plugin_hook', $name);

		return $data;
	}
}

if (!function_exists('api_user_realm_auth')) {
	function api_user_realm_auth($filename = '') {
		return CactiStubs::nextReturn('api_user_realm_auth', true);
	}
}

if (!function_exists('raise_message')) {
	function raise_message($message_id, $message = '', $level = 0) {
		CactiStubs::record('raise_message', (string) $message_id);
	}
}

if (!function_exists('rrdtool_execute')) {
	function rrdtool_execute($command, $log_to_stdout = false, $output_flag = 1, $rrdtool_pipe = false, $logopt = 'WEBLOG') {
		CactiStubs::record('rrdtool_execute', $command);

		return CactiStubs::nextReturn('rrdtool_execute', '', $command);
	}
}

if (!function_exists('rrdtool_function_interface_speed')) {
	function rrdtool_function_interface_speed($data_local) {
		return CactiStubs::nextReturn('rrdtool_function_interface_speed', 0);
	}
}

if (!function_exists('get_timeinstate')) {
	function get_timeinstate($host) {
		return CactiStubs::nextReturn('get_timeinstate', '1 day');
	}
}

if (!function_exists('get_daysfromtime')) {
	function get_daysfromtime($timestamp) {
		return CactiStubs::nextReturn('get_daysfromtime', '1 day');
	}
}

if (!function_exists('number_format_i18n')) {
	function number_format_i18n($number, $decimals = 0, $baseu = 1000) {
		return number_format((float) $number, $decimals < 0 ? 0 : (int) $decimals);
	}
}

if (!defined('FILTER_VALIDATE_IS_REGEX')) {
	define('FILTER_VALIDATE_IS_REGEX', 99999);
}

// Device states, from Cacti include/global_constants.php.
foreach (['HOST_UNKNOWN' => 0, 'HOST_DOWN' => 1, 'HOST_RECOVERING' => 2, 'HOST_UP' => 3, 'HOST_ERROR' => 4] as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

// rrdtool output modes, from Cacti include/global_constants.php.
foreach (['RRDTOOL_OUTPUT_NULL' => 0, 'RRDTOOL_OUTPUT_STDOUT' => 1, 'RRDTOOL_OUTPUT_STDERR' => 2,
	'RRDTOOL_OUTPUT_GRAPH_DATA'    => 3, 'RRDTOOL_OUTPUT_BOOLEAN' => 4,
	'RRDTOOL_OUTPUT_RETURN_STDERR' => 5] as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}

if (!defined('CACTI_DATE_TIME_FORMAT')) {
	define('CACTI_DATE_TIME_FORMAT', 'Y-m-d H:i:s');
}

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', $GLOBALS['config']['base_path']);
}

/**
 * Load a plugin source file at global scope.
 *
 * Several plugin files (includes/arrays.php in particular) define their data
 * as file-scope variables that the rest of the plugin reads as globals, and
 * they read $config while doing so. Requiring them from inside a method would
 * make both halves of that method-local, so the require happens here and any
 * variable the file introduced is published to $GLOBALS.
 *
 * @param string $path Absolute path to the file.
 *
 * @return void
 */
function thold_test_load($path) {
	global $config;

	$__before = get_defined_vars();

	require_once $path;

	foreach (get_defined_vars() as $__name => $__value) {
		if (!array_key_exists($__name, $__before) && strncmp($__name, '__', 2) !== 0) {
			$GLOBALS[$__name] = $__value;
		}
	}
}
