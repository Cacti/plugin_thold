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
