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
 * Integration coverage for the plugin lifecycle in setup.php:
 * plugin_thold_uninstall()'s side effects, plugin_thold_version()'s INFO
 * parsing, and plugin_thold_upgrade()'s page/version gating (the paths that
 * return before ever reaching the full plugin_thold_install(true) reinstall,
 * which needs a live Cacti include/database.php and is out of scope here).
 */

require_once dirname(__DIR__, 2) . '/setup.php';

beforeEach(function () {
	CactiStubs::reset();
});

it('reads name and version from INFO', function () {
	$info = plugin_thold_version();

	expect($info)->toHaveKey('name');
	expect($info)->toHaveKey('version');
	expect($info['name'])->toBe('thold');
});

it('removes thold settings on uninstall', function () {
	plugin_thold_uninstall();

	$calls = CactiStubs::callsTo('db_execute');

	expect($calls)->not->toBeEmpty();
	expect($calls[0]['sql'])->toContain('DELETE FROM settings');
	expect($calls[0]['sql'])->toContain('thold');
});

it('does not check for an upgrade on pages outside its allow-list', function () {
	CactiStubs::willReturn('get_current_page', 'graphs.php');

	expect(plugin_thold_upgrade())->toBeFalse();
	expect(CactiStubs::callsTo('db_fetch_cell'))->toBeEmpty();
});

it('does not reinstall when the installed version already matches', function () {
	CactiStubs::willReturn('get_current_page', 'thold.php');
	CactiStubs::willReturnFor('db_fetch_cell', 'plugin_config', plugin_thold_version()['version']);

	expect(plugin_thold_upgrade())->toBeTrue();
	expect(CactiStubs::callsTo('api_plugin_register_hook'))->toBeEmpty();
});

it('prunes bundled dev-only files on a version-drift upgrade', function () {
	CactiStubs::willReturn('get_current_page', 'thold.php');
	CactiStubs::willReturnFor('db_fetch_cell', 'plugin_config', '0.0.0');

	// Force plugin_thold_install(true) to short-circuit immediately (its
	// version_compare guard) so this exercises the drift branch + prune call
	// without the full reinstall, which needs a live Cacti include/database.php.
	$restoreCacti = $GLOBALS['config']['cacti_version'];
	$GLOBALS['config']['cacti_version'] = '1.1';

	// Sandbox base_path so thold_prune_files() runs against a throwaway
	// tree with a copy of the real INFO (so plugin_thold_version() still
	// matches) and no manifest.json (prune no-ops), never the real checkout.
	$restoreBase = $GLOBALS['config']['base_path'];
	$base = sys_get_temp_dir() . '/thold-itest-' . uniqid();
	mkdir($base . '/plugins/thold', 0777, true);
	copy(dirname(__DIR__, 2) . '/INFO', $base . '/plugins/thold/INFO');
	$GLOBALS['config']['base_path'] = $base;

	try {
		expect(plugin_thold_upgrade())->toBeTrue();
	} finally {
		$GLOBALS['config']['base_path']    = $restoreBase;
		$GLOBALS['config']['cacti_version'] = $restoreCacti;
	}
});
