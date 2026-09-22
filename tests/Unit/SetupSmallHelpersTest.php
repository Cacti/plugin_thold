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
 * Unit coverage for a handful of small, pure/near-pure setup.php functions
 * that had no test coverage at all: plugin_thold_check_config(),
 * thold_check_dependencies(), plugin_thold_check_strict(),
 * thold_multiexplode(), and the *_action_array() menu-entry helpers
 * (thold_device_action_array()/thold_data_source_action_array()/
 * thold_graphs_action_array()).
 *
 * This plugin's setup.php also contains much larger, deeply
 * domain-specific functions (device/graph/data-source action
 * execute/prepare, SNMP agent cache install/uninstall, device
 * template editors, etc.) that are intentionally left untouched here -
 * those need dedicated, carefully-scoped fixtures of their own rather
 * than a speculative pass.
 */

require_once dirname(__DIR__, 2) . '/setup.php';

beforeEach(function () {
	CactiStubs::reset();
});

it('always reports success and delegates to plugin_thold_upgrade()', function () {
	CactiStubs::willReturn('get_current_page', 'graphs.php');

	expect(plugin_thold_check_config())->toBeTrue();

	// graphs.php is outside plugin_thold_upgrade()'s own page allow-list, so
	// it should return early without touching the database.
	expect(CactiStubs::callsTo('db_fetch_cell'))->toBeEmpty();
});

it('reports its dependencies as always satisfied', function () {
	expect(thold_check_dependencies())->toBeTrue();
});

it('reports strict mode as a problem when the server sql_mode contains STRICT', function () {
	CactiStubs::willReturn('db_fetch_cell', 'STRICT_TRANS_TABLES,NO_ZERO_DATE');

	expect(plugin_thold_check_strict())->toBeFalse();
});

it('reports no problem when the server sql_mode is not strict', function () {
	CactiStubs::willReturn('db_fetch_cell', 'NO_ZERO_DATE');

	expect(plugin_thold_check_strict())->toBeTrue();
});

it('splits a string on any of several delimiters', function () {
	expect(thold_multiexplode([',', ';'], 'a,b;c,d'))->toBe(['a', 'b', 'c', 'd']);
});

it('treats a single-delimiter array like a plain explode()', function () {
	expect(thold_multiexplode([','], 'a,b,c'))->toBe(['a', 'b', 'c']);
});

it('adds the Apply Thresholds device action without disturbing existing ones', function () {
	$actions = thold_device_action_array(['1' => 'Delete']);

	expect($actions)->toHaveKey('1');
	expect($actions)->toHaveKey('thold');
	expect($actions['thold'])->toBe('Apply Thresholds');
});

it('adds the Create Threshold from Template data source action', function () {
	$actions = thold_data_source_action_array(['1' => 'Delete']);

	expect($actions)->toHaveKey('1');
	expect($actions)->toHaveKey('plugin_thold_create');
});

it('adds the Create Threshold from Template graph action', function () {
	$actions = thold_graphs_action_array(['1' => 'Delete']);

	expect($actions)->toHaveKey('1');
	expect($actions)->toHaveKey('plugin_thold_create');
});

it('registers the TH clog regex handler without disturbing existing entries', function () {
	$regexArray = thold_clog_regex_array([['name' => 'Other', 'regex' => '/x/', 'func' => 'other_fn']]);

	expect($regexArray)->toHaveCount(2);
	expect($regexArray[1]['name'])->toBe('TH');
	expect($regexArray[1]['func'])->toBe('thold_clog_regex_threshold');
});
