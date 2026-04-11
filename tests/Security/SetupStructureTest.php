<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify setup.php defines required plugin hooks and info function.
 */

describe('thold setup.php structure', function () {
	$readSetupSource = function () {
		$setupPath = realpath(__DIR__ . '/../../setup.php');
		expect($setupPath)->not->toBeFalse('Failed to resolve setup.php');
		expect(is_readable($setupPath))->toBeTrue('setup.php is not readable');

		$source = file_get_contents($setupPath);
		expect($source)->not->toBeFalse('Failed to read setup.php');

		return $source;
	};

	it('defines plugin_thold_install function', function () use ($readSetupSource) {
		$source = $readSetupSource();
		expect($source)->toContain('function plugin_thold_install');
	});

	it('defines plugin_thold_version function', function () use ($readSetupSource) {
		$source = $readSetupSource();
		expect($source)->toContain('function plugin_thold_version');
	});

	it('defines plugin_thold_uninstall function', function () use ($readSetupSource) {
		$source = $readSetupSource();
		expect($source)->toContain('function plugin_thold_uninstall');
	});

	it('returns version array with name key', function () use ($readSetupSource) {
		$source = $readSetupSource();
		expect($source)->toMatch('/[\'\""]name[\'\""]\s*=>/');
	});

	it('returns version array with version key', function () use ($readSetupSource) {
		$source = $readSetupSource();
		expect($source)->toMatch('/[\'\""]version[\'\""]\s*=>/');
	});
});
