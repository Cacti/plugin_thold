<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for SQL injection via raw string concatenation in bulk
 * form actions.
 *
 * Pre-fix: form_actions() in notify_lists.php used array_to_sql_or() and
 * direct $selected_items[$i] concatenation for DELETE/UPDATE statements.
 * get_allowed_thresholds() and get_allowed_threshold_logs() in
 * thold_functions.php interpolated $graph_id directly into SQL.
 *
 * Fix: all bulk operations use db_execute_prepared() with ? placeholders;
 * get_allowed_thresholds/logs use db_fetch_assoc_prepared/db_fetch_cell_prepared.
 */

$notify_src = file_get_contents(realpath(__DIR__ . '/../../notify_lists.php'));
$funcs_src  = file_get_contents(realpath(__DIR__ . '/../../thold_functions.php'));

it('notify_lists.php does not use array_to_sql_or for bulk operations', function () use ($notify_src) {
	expect($notify_src)->not->toContain('array_to_sql_or($selected_items');
});

it('notify_lists.php delete action uses db_execute_prepared with IN placeholders', function () use ($notify_src) {
	expect($notify_src)->toContain('db_execute_prepared(\'DELETE FROM plugin_notification_lists');
	expect($notify_src)->toContain('$placeholders');
});

it('notify_lists.php associate action uses db_execute_prepared for host updates', function () use ($notify_src) {
	expect($notify_src)->toContain('db_execute_prepared(\'UPDATE host');
	expect($notify_src)->toContain('SET thold_host_email = ?');
});

it('notify_lists.php does not concatenate selected_items[$i] directly into SQL strings', function () use ($notify_src) {
	expect(preg_match("/WHERE id='\s*\\.\\s*\\\$selected_items/", $notify_src))->toBe(0);
	expect(preg_match('/WHERE id=\' \. \$selected_items/', $notify_src))->toBe(0);
	expect(preg_match('/WHERE id=' . "'" . ' \. \$selected_items/', $notify_src))->toBe(0);
});

it('notify_lists.php uses cacti_sizeof instead of count for selected_items loops', function () use ($notify_src) {
	expect($notify_src)->toContain('cacti_sizeof($selected_items)');
	expect(preg_match('/count\(\$selected_items\)/', $notify_src))->toBe(0);
});

it('thold_functions.php get_allowed_thresholds uses db_fetch_assoc_prepared', function () use ($funcs_src) {
	expect($funcs_src)->toContain('db_fetch_assoc_prepared($tholds_sql, $sql_params)');
});

it('thold_functions.php get_allowed_thresholds uses db_fetch_cell_prepared for row count', function () use ($funcs_src) {
	expect($funcs_src)->toContain('db_fetch_cell_prepared($sql, $sql_params)');
});

it('thold_functions.php get_allowed_thresholds does not interpolate graph_id directly', function () use ($funcs_src) {
	expect($funcs_src)->not->toContain('gl.id=$graph_id');
	expect($funcs_src)->not->toContain('gl.id = $graph_id');
});

it('thold_functions.php get_allowed_threshold_logs uses db_fetch_assoc_prepared', function () use ($funcs_src) {
	expect($funcs_src)->toContain('db_fetch_assoc_prepared("SELECT');
});

it('notify_lists.php drp_action guard converts keys to strings before strict comparison', function () use ($notify_src) {
	// array_keys() returns int keys; POST values are strings; strval() cast allows strict in_array()
	expect($notify_src)->toContain("array_map('strval', array_keys(\$actions + \$assoc_actions))");
	expect($notify_src)->toContain("in_array(get_request_var('drp_action'), \$valid_actions, true)");
});
