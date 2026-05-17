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
	// vulnerable pattern strings, expressed as nowdocs for legibility
	$vulnerable_dq = <<<'EOT'
RLIKE '" . get_request_var('rfilter') . "'
EOT;
	$vulnerable_sq = <<<'EOT'
RLIKE '" . get_request_var('rfilter') . "'
EOT;
	expect($src)->not->toContain($vulnerable_dq);
	expect($src)->not->toContain($vulnerable_sq);
});

it('thold_graph.php RLIKE patterns use db_qstr escaping', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_graph.php'));
	expect($src)->toContain("RLIKE ' . db_qstr(get_request_var('rfilter'))");
});

it('thold.php has no raw rfilter concatenated into RLIKE', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold.php'));
	$vulnerable_dq = <<<'EOT'
RLIKE '" . get_request_var('rfilter') . "'
EOT;
	expect($src)->not->toContain($vulnerable_dq);
});

it('thold.php RLIKE pattern uses db_qstr escaping', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold.php'));
	expect($src)->toContain("RLIKE ' . db_qstr(get_request_var('rfilter'))");
});

it('notify_lists.php has no raw rfilter concatenated into RLIKE', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../notify_lists.php'));
	$vulnerable_dq = <<<'EOT'
RLIKE '" . get_request_var('rfilter') . "'
EOT;
	expect($src)->not->toContain($vulnerable_dq);
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

it('rfilter is validated as a PHP regex before reaching any RLIKE clause', function () {
	// thold_graph.php, thold.php, and notify_lists.php all declare rfilter with
	// FILTER_VALIDATE_IS_REGEX in their request validation arrays. This means
	// get_filter_request_var() rejects malformed or catastrophic patterns before
	// any SQL is constructed, mitigating ReDoS at the MySQL RLIKE engine.
	foreach (['thold_graph.php', 'thold.php', 'notify_lists.php'] as $file) {
		$src = file_get_contents(realpath(__DIR__ . '/../../' . $file));
		expect($src)->toContain('FILTER_VALIDATE_IS_REGEX');
	}
});
