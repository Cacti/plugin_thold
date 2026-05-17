<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Test bootstrap: stub Cacti framework functions so plugin code
 * can be loaded in isolation without the full Cacti application.
 */

$GLOBALS['__test_db_calls'] = [];
$GLOBALS['config']          = [
	'base_path'     => '/var/www/html/cacti',
	'url_path'      => '/cacti/',
	'cacti_version' => '1.2.999',
];

if (!function_exists('db_execute')) {
	function db_execute($sql) {
		$GLOBALS['__test_db_calls'][] = ['fn' => 'db_execute', 'sql' => $sql, 'params' => []];

		return true;
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = []) {
		$GLOBALS['__test_db_calls'][] = ['fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params];

		return true;
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql) {
		return [];
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = []) {
		return [];
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql) {
		return [];
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = []) {
		return [];
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql) {
		return '';
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = []) {
		return '';
	}
}

if (!function_exists('db_qstr')) {
	function db_qstr($string) {
		return "'" . str_replace("'", "''", $string) . "'";
	}
}

if (!function_exists('html_escape')) {
	function html_escape($string) {
		return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('sanitize_unserialize_selected_items')) {
	function sanitize_unserialize_selected_items($items) {
		if (empty($items)) {
			return false;
		}

		$data = unserialize($items, ['allowed_classes' => false]); // nosemgrep: php.lang.security.unserialize-use.unserialize-use -- test stub mirrors sanitize_unserialize_selected_items; allowed_classes:false blocks object injection

		if (!is_array($data)) {
			return false;
		}

		foreach ($data as $key => $value) {
			if (!is_numeric($value)) {
				return false;
			}
		}

		return $data;
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name, $force = false) {
		return '';
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($name, $value) {
	}
}

if (!function_exists('__')) {
	function __($text, $domain = '') {
		return $text;
	}
}

if (!function_exists('__esc')) {
	function __esc($text, $domain = '') {
		return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $also_print = false, $log_type = '', $level = 0) {
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return is_array($array) ? count($array) : 0;
	}
}

if (!function_exists('get_request_var')) {
	function get_request_var($name) {
		return '';
	}
}

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($name) {
		return '';
	}
}

if (!function_exists('get_filter_request_var')) {
	function get_filter_request_var($name) {
		return '';
	}
}

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', '/var/www/html/cacti');
}
