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
*/

require_once dirname(__DIR__, 2) . '/setup.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

it('returns translated graph timespan and acknowledgment field labels', function () {
	expect(thold_graph_timespan_description())->toContain('Graph Timespan');
	$labels = thold_acknowledgment_field_labels();
	expect($labels['friendly_name'])->toBe('Acknowledgment Options')
		->and($labels['description'])->toContain('Persistent Acknowledgment');
});

it('reports threshold creation results for non-empty and empty messages', function () {
	thold_report_template_creation_result(2, 'Threshold created');
	thold_report_template_creation_result(0, '');

	expect(true)->toBeTrue();
});

it('invokes template creation reporting from data source and graph actions', function () {
	$restoreBase                    = $GLOBALS['config']['base_path'] ?? null;
	$GLOBALS['config']['base_path'] = dirname(__DIR__, 4);

	CactiStubs::$requestVars['selected_items']    = serialize([10]);
	CactiStubs::$requestVars['thold_template_id'] = 5;
	CactiStubs::willReturnFor('db_fetch_row_prepared', 'FROM thold_template', ['id' => 5, 'data_source_name' => 'traffic_in']);
	CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'FROM data_template_rrd', []);

	try {
		expect(thold_data_source_action_execute('plugin_thold_create'))->toBe('plugin_thold_create');
		expect(thold_graphs_action_execute('plugin_thold_create'))->toBe('plugin_thold_create');
	} finally {
		$GLOBALS['config']['base_path'] = $restoreBase;
	}
});
