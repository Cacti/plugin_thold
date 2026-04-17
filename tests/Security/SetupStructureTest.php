<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Verify setup.php defines required plugin hooks and info function.

function thold_read_setup_source() {
	$setupPath = realpath(__DIR__ . '/../../setup.php');
	expect($setupPath)->not->toBeFalse('Failed to resolve setup.php');
	expect(is_readable($setupPath))->toBeTrue('setup.php is not readable');

	$source = file_get_contents($setupPath);
	expect($source)->not->toBeFalse('Failed to read setup.php');

	return $source;
}

it('defines plugin_thold_install function', function () {
	$source = thold_read_setup_source();
	expect($source)->toContain('function plugin_thold_install');
});

it('defines plugin_thold_version function', function () {
	$source = thold_read_setup_source();
	expect($source)->toContain('function plugin_thold_version');
});

it('defines plugin_thold_uninstall function', function () {
	$source = thold_read_setup_source();
	expect($source)->toContain('function plugin_thold_uninstall');
});

it('returns version array with name key', function () {
	$source = thold_read_setup_source();
	expect($source)->toMatch('/[\'\""]name[\'\""]\s*=>/');
});

it('reads plugin metadata from INFO and returns the info section', function () {
	$source = thold_read_setup_source();
	expect($source)->toContain('parse_ini_file');
	expect($source)->toContain("return \$info['info'];");
});

it('setup.php file exists and is readable at expected path', function () {
	$setupPath = __DIR__ . '/../../setup.php';
	expect(file_exists($setupPath))->toBeTrue('setup.php does not exist at expected location');
	expect(is_readable($setupPath))->toBeTrue('setup.php exists but is not readable');
	expect(realpath($setupPath))->not->toBeFalse('realpath() failed to resolve setup.php');
});

it('registers required poller and device hooks via api_plugin_register_hook', function () {
	$source = thold_read_setup_source();
	expect($source)->toContain("'poller_output'");
	expect($source)->toContain("'poller_bottom'");
	expect($source)->toContain("'device_action_array'");
	expect($source)->toContain("'api_device_save'");
});

it('api_plugin_register_hook calls use at least four arguments', function () {
	$source  = thold_read_setup_source();
	$matches = [];
	preg_match_all('/api_plugin_register_hook\s*\([^;)]+\)/', $source, $matches);

	expect(count($matches[0]))->toBeGreaterThan(0, 'No api_plugin_register_hook calls found in setup.php');

	foreach ($matches[0] as $call) {
		$argCount = substr_count($call, ',') + 1;
		expect($argCount)->toBeGreaterThanOrEqual(
			4,
			"Hook call has fewer than 4 arguments: {$call}"
		);
	}
});

it('api_plugin_register_hook hook names are non-empty string literals', function () {
	$source = thold_read_setup_source();
	expect($source)->not->toMatch("/api_plugin_register_hook\s*\(\s*[^,]+,\s*''\s*,/");
	expect($source)->not->toMatch('/api_plugin_register_hook\s*\(\s*[^,]+,\s*""\s*,/');
});
