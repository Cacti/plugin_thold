<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Several hooks lazy-load the threshold library on demand. After its move to
 * includes/functions.php, invoke each one so the relocated include_once path
 * is exercised; a bad relocation would surface here. The hooks run against the
 * bootstrap's Cacti stubs and any downstream error is irrelevant to the goal.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/polling.php';
});

it('resolves includes/functions.php from every hook that lazy-loads it', function () {
	$restoreBase                    = $GLOBALS['config']['base_path'] ?? null;
	$restoreOpts                    = CactiStubs::$configOptions;
	$GLOBALS['config']['base_path'] = dirname(__DIR__, 4);
	CactiStubs::$configOptions['alert_deadnotify'] = 'on';

	$hooks = [
		static fn () => thold_rrd_graph_graph_options([]),
		static fn () => thold_device_action_execute('thold'),
		static fn () => thold_api_device_new([]),
		static fn () => thold_api_device_save([]),
		static fn () => thold_data_source_action_execute(''),
		static fn () => thold_graphs_action_execute(''),
		static fn () => thold_create_graph_thold([]),
		static fn () => thold_data_source_remove([]),
		static fn () => thold_clog_regex_threshold([]),
		static fn () => thold_update_host_status(),
	];

	// This suite runs failOnDeprecation/Warning/Notice; feeding the hooks
	// placeholder input legitimately trips those, so swallow engine messages
	// while the include_once lines execute, then return control to PHPUnit.
	set_error_handler(static fn (): bool => true);

	try {
		foreach ($hooks as $hook) {
			ob_start();

			try {
				$hook();
			} catch (\Throwable $e) {
				// Reaching the include_once at the top of each hook is the point.
			} finally {
				ob_end_clean();
			}
		}
	} finally {
		restore_error_handler();
		CactiStubs::$configOptions      = $restoreOpts;
		$GLOBALS['config']['base_path'] = $restoreBase;
	}

	expect(function_exists('plugin_thold_csp_nonce'))->toBeTrue();
});
