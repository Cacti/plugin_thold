<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for RLIKE SQL injection via unescaped rfilter.
 *
 * Pre-fix: get_request_var('rfilter') was concatenated directly into RLIKE patterns
 * without any escaping, allowing regex injection and SQL manipulation.
 *
 * Fix: all RLIKE patterns now use db_qstr(get_request_var('rfilter')) which
 * returns a properly SQL-escaped quoted string.
 */

it('thold_graph.php has no raw rfilter concatenated into RLIKE', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_graph.php'));
	expect($src)->not->toContain("RLIKE '" . '" . get_request_var(\'rfilter\') . "\'');
	expect($src)->not->toContain("RLIKE '" . "\" . get_request_var('rfilter') . \"'");
});

it('thold_graph.php RLIKE patterns use db_qstr escaping', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_graph.php'));
	expect($src)->toContain("RLIKE ' . db_qstr(get_request_var('rfilter'))");
});

it('thold.php has no raw rfilter concatenated into RLIKE', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold.php'));
	expect($src)->not->toContain("RLIKE '" . '" . get_request_var(\'rfilter\') . "\'');
});

it('thold.php RLIKE pattern uses db_qstr escaping', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold.php'));
	expect($src)->toContain("RLIKE ' . db_qstr(get_request_var('rfilter'))");
});

it('notify_lists.php has no raw rfilter concatenated into RLIKE', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../notify_lists.php'));
	expect($src)->not->toContain("RLIKE '" . '" . get_request_var(\'rfilter\') . "\'');
});

it('notify_lists.php RLIKE patterns use db_qstr escaping', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../notify_lists.php'));
	expect($src)->toContain("RLIKE ' . db_qstr(get_request_var('rfilter'))");
});

it('db_qstr wraps value in single-quoted escaped string', function () {
	expect(db_qstr("O'Brien"))->toBe("'O''Brien'");
	expect(db_qstr('normal'))->toBe("'normal'");
	expect(db_qstr("1' OR '1'='1"))->toBe("'1'' OR ''1''=''1'");
});
