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

it('notify_lists.php bulk write actions are wrapped in transactions', function () use ($notify_src) {
	// All four bulk action blocks must begin a transaction.
	$beginCount = substr_count($notify_src, 'db_begin_transaction()');
	expect($beginCount)->toBe(4);
});

it('notify_lists.php bulk write actions commit on success', function () use ($notify_src) {
	$commitCount = substr_count($notify_src, 'db_commit_transaction()');
	expect($commitCount)->toBe(4);
});

it('notify_lists.php bulk write actions rollback on failure', function () use ($notify_src) {
	// Each transaction block must have a matching rollback path for when db_execute_prepared returns false.
	$rollbackCount = substr_count($notify_src, 'db_rollback_transaction()');
	expect($rollbackCount)->toBe(4);
});

it('notify_lists.php bulk write actions track $ok flag for all db_execute_prepared calls', function () use ($notify_src) {
	// Every db_execute_prepared result must be ANDed into $ok so partial failure triggers rollback.
	expect($notify_src)->toContain('$ok = true');
	expect(preg_match('/\$ok\s*=\s*db_execute_prepared/', $notify_src))->toBe(1);
	// [^)]+ stops at the first ) inside multi-argument calls; use substr_count instead
	expect(substr_count($notify_src, ') && $ok'))->toBeGreaterThanOrEqual(6);
});

it('notify_lists.php per-item loops break immediately on first failure', function () use ($notify_src) {
	// Each loop must break as soon as $ok is false rather than continuing to issue DB calls.
	// associate (2 loops) + save_templates (2 loops) + save_tholds (2 loops) = 6 break guards.
	// The flat delete sequence has no loop and therefore no break guard.
	$breakCount = substr_count($notify_src, 'if (!$ok) {');
	expect($breakCount)->toBeGreaterThanOrEqual(6);
	expect($notify_src)->toContain('if (!$ok) {');
	expect($notify_src)->toContain('break;');
});

it('notify_lists.php save_templates calls thold_template_update_thresholds after commit only', function () use ($notify_src) {
	// thold_template_update_thresholds must not run inside the transaction boundary
	// (it should be called after db_commit_transaction() in the on-success path).
	$commitPos  = strpos($notify_src, 'db_commit_transaction();' . "\n\n\t\t\t\t\t// Propagate");
	expect($commitPos)->not->toBeFalse();
	// The function must not appear between db_begin_transaction and db_commit_transaction in this block.
	$beginPos = strrpos(substr($notify_src, 0, $commitPos), 'db_begin_transaction()');
	$between  = substr($notify_src, $beginPos, $commitPos - $beginPos);
	expect($between)->not->toContain('thold_template_update_thresholds');
});
