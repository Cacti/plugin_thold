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
 * declared as a shim over CactiStub, which records the call and hands back
 * whatever the test programmed.
 *
 * Guarding every declaration with function_exists() keeps this file usable if
 * a future integration suite loads real Cacti first.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

/*
 * base_path has to point at the Cacti root two levels above this plugin:
 * thold_functions.php builds include paths from it at runtime.
 */
$GLOBALS['config'] = [
	'base_path'       => dirname(dirname(dirname(__DIR__))),
	'url_path'        => '/cacti/',
	'cacti_version'   => '1.2.31',
	'cacti_server_os' => 'unix',
];

// thold_expand_string() include_once()s library_path/variables.php at call time.
$GLOBALS['config']['library_path'] = __DIR__ . '/fixtures/cacti-lib';

// thold reads and writes this on every RPN evaluation.
$GLOBALS['rpn_error'] = false;

if (!function_exists('db_execute')) {
	function db_execute($sql, $log = true, $db_conn = false) {
		CactiStub::record('db_execute', $sql);

		return CactiStub::nextReturn('db_execute', true);
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
		CactiStub::record('db_execute_prepared', $sql, $params);

		return CactiStub::nextReturn('db_execute_prepared', true);
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql, $log = true, $db_conn = false) {
		CactiStub::record('db_fetch_assoc', $sql);

		return CactiStub::nextReturn('db_fetch_assoc', []);
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false) {
		CactiStub::record('db_fetch_assoc_prepared', $sql, $params);

		return CactiStub::nextReturn('db_fetch_assoc_prepared', []);
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql, $log = true, $db_conn = false) {
		CactiStub::record('db_fetch_row', $sql);

		return CactiStub::nextReturn('db_fetch_row', []);
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = [], $log = true, $db_conn = false) {
		CactiStub::record('db_fetch_row_prepared', $sql, $params);

		return CactiStub::nextReturn('db_fetch_row_prepared', []);
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql, $col_name = '', $log = true, $db_conn = false) {
		CactiStub::record('db_fetch_cell', $sql);

		return CactiStub::nextReturn('db_fetch_cell', '');
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true, $db_conn = false) {
		CactiStub::record('db_fetch_cell_prepared', $sql, $params);

		return CactiStub::nextReturn('db_fetch_cell_prepared', '');
	}
}

if (!function_exists('db_qstr')) {
	function db_qstr($string) {
		return "'" . str_replace("'", "''", (string) $string) . "'";
	}
}

if (!function_exists('db_begin_transaction')) {
	function db_begin_transaction() {
		CactiStub::record('db_begin_transaction');

		return CactiStub::nextReturn('db_begin_transaction', true);
	}
}

if (!function_exists('db_commit_transaction')) {
	function db_commit_transaction() {
		CactiStub::record('db_commit_transaction');

		return CactiStub::nextReturn('db_commit_transaction', true);
	}
}

if (!function_exists('db_rollback_transaction')) {
	function db_rollback_transaction() {
		CactiStub::record('db_rollback_transaction');

		return CactiStub::nextReturn('db_rollback_transaction', true);
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
		return isset(CactiStub::$configOptions[$name]) ? CactiStub::$configOptions[$name] : '';
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($name, $value) {
		CactiStub::$configOptions[$name] = $value;
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
		CactiStub::$log[] = $message;
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
		return isset(CactiStub::$requestVars[$name]) ? CactiStub::$requestVars[$name] : $default;
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
		return isset(CactiStub::$requestVars[$name]);
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
		return CactiStub::nextReturn('get_simple_graph_perms', true);
	}
}

if (!function_exists('get_policies')) {
	function get_policies($user_id) {
		return CactiStub::nextReturn('get_policies', []);
	}
}

if (!function_exists('get_policy_where')) {
	function get_policy_where($graph_auth_method, $policies, $sql_where) {
		CactiStub::record('get_policy_where', $sql_where);

		return CactiStub::nextReturn('get_policy_where', $sql_where);
	}
}

if (!function_exists('expand_title')) {
	function expand_title($host_id, $snmp_query_id, $snmp_index, $title) {
		CactiStub::record('expand_title', $title);

		return CactiStub::nextReturn('expand_title', $title);
	}
}

if (!function_exists('get_graph_title')) {
	function get_graph_title($local_graph_id) {
		return CactiStub::nextReturn('get_graph_title', 'Traffic - eth0');
	}
}

if (!function_exists('rrdtool_execute')) {
	function rrdtool_execute($command, $log_to_stdout = false, $output_flag = 1, $rrdtool_pipe = false, $logopt = 'WEBLOG') {
		CactiStub::record('rrdtool_execute', $command);

		return CactiStub::nextReturn('rrdtool_execute', '');
	}
}

if (!function_exists('rrdtool_function_interface_speed')) {
	function rrdtool_function_interface_speed($data_local) {
		return CactiStub::nextReturn('rrdtool_function_interface_speed', 0);
	}
}

if (!function_exists('get_timeinstate')) {
	function get_timeinstate($host) {
		return CactiStub::nextReturn('get_timeinstate', '1 day');
	}
}

if (!function_exists('get_daysfromtime')) {
	function get_daysfromtime($timestamp) {
		return CactiStub::nextReturn('get_daysfromtime', '1 day');
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
