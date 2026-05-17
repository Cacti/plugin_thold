<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

it('setup.php defines plugin_thold_install', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
	expect($src)->toContain('function plugin_thold_install');
});

it('setup.php defines plugin_thold_uninstall', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
	expect($src)->toContain('function plugin_thold_uninstall');
});

it('setup.php defines plugin_thold_version', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
	expect($src)->toContain('function plugin_thold_version');
});

it('setup.php defines plugin_thold_check_config', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
	expect($src)->toContain('function plugin_thold_check_config');
});

it('setup.php reads plugin metadata from INFO file', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
	expect($src)->toContain('parse_ini_file');
});

it('setup.php registers poller_output hook', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
	expect($src)->toContain("'poller_output'");
});
