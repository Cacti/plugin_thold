<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify setup.php defines the required plugin lifecycle functions and INFO
 * declares a name/version, mirroring the shared Cacti plugin test framework
 * convention (see plugin_evidence's tests/Security/SetupStructureTest.php).
 */

$source = plugin_test_read_source('setup.php');

$infoFile = parse_ini_file(__DIR__ . '/../../INFO', true);
if (!is_array($infoFile) || !isset($infoFile['info']) || !is_array($infoFile['info'])) {
	throw new RuntimeException('Unable to parse the INFO section');
}
$info = $infoFile['info'];

it('defines plugin_thold_install function', function () use ($source) {
	expect($source)->toContain('function plugin_thold_install');
});

it('defines plugin_thold_uninstall function', function () use ($source) {
	expect($source)->toContain('function plugin_thold_uninstall');
});

it('defines plugin_thold_version function', function () use ($source) {
	expect($source)->toContain('function plugin_thold_version');
});

it('declares a plugin name in INFO', function () use ($info) {
	expect($info)->toHaveKey('name');
});

it('declares a plugin version in INFO', function () use ($info) {
	expect($info)->toHaveKey('version');
});
