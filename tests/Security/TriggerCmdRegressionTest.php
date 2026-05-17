<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for trigger_cmd variable confusion in thold_functions.php.
 *
 * Pre-fix: thold_set_environ() was called with trigger_cmd_high in both the
 * low-breach and norm-restoration branches, causing the wrong command's
 * environment variables to be set.
 *
 * Fix: each branch now passes its own trigger_cmd_* key to thold_set_environ().
 */

$funcs = file_get_contents(realpath(__DIR__ . '/../../thold_functions.php'));

it('thold_functions.php references trigger_cmd_low for low-breach environ setup', function () use ($funcs) {
	expect($funcs)->toContain("thold_data['trigger_cmd_low']");
});

it('thold_functions.php references trigger_cmd_norm for norm-restoration environ setup', function () use ($funcs) {
	expect($funcs)->toContain("thold_data['trigger_cmd_norm']");
});

it('thold_functions.php does not use trigger_cmd_high in low-breach thold_set_environ call', function () use ($funcs) {
	// The pre-fix code passed trigger_cmd_high to thold_set_environ when handling low/norm breaches.
	// Verify that trigger_cmd_low and trigger_cmd_norm each appear near thold_set_environ.
	$pattern = '/thold_set_environ\s*\(\s*\$thold_data\[.trigger_cmd_(?:low|norm).\]/';
	expect((bool) preg_match($pattern, $funcs))->toBeTrue('trigger_cmd_low or trigger_cmd_norm must appear in thold_set_environ calls');
});
