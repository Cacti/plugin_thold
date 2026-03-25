<?php
declare(strict_types=1);

/*
 * Stubs for Cacti global functions. Each guard prevents double-declaration
 * when multiple test files load this helper.
 *
 * These stubs replicate only the signatures and return types that the
 * plugin code depends on. They do NOT connect to a database.
 */

if (!function_exists('db_execute')) {
	function db_execute(string $sql, bool $log = true): bool
	{
		return true;
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared(string $sql, array $params = [], bool $log = true): bool
	{
		return true;
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc(string $sql, bool $log = true): array
	{
		return [];
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared(string $sql, array $params = [], bool $log = true): array
	{
		return [];
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row(string $sql, bool $log = true): array
	{
		return [];
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared(string $sql, array $params = [], bool $log = true): array
	{
		return [];
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell(string $sql, string $column = '', bool $log = true): mixed
	{
		return null;
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared(string $sql, array $params = [], string $column = '', bool $log = true): mixed
	{
		return null;
	}
}

if (!function_exists('html_escape')) {
	/* Must match Cacti's own implementation: ENT_QUOTES|ENT_HTML5, UTF-8. */
	function html_escape(mixed $value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('get_filter_request_var')) {
	function get_filter_request_var(string $name, int $filter = FILTER_VALIDATE_INT, array $options = []): mixed
	{
		return null;
	}
}

if (!function_exists('get_request_var')) {
	function get_request_var(string $name, mixed $default = ''): mixed
	{
		return $default;
	}
}

if (!function_exists('api_plugin_user_realm_auth')) {
	function api_plugin_user_realm_auth(string $filename = ''): bool
	{
		return false;
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option(string $option, bool $force = false): string
	{
		return '';
	}
}

if (!function_exists('input_validate_input_number')) {
	function input_validate_input_number(mixed $value, string $variable = ''): void
	{
		if (!is_numeric($value)) {
			throw new \InvalidArgumentException(
				sprintf('Input validation failure: %s is not numeric', $variable ?: (string) $value)
			);
		}
	}
}
