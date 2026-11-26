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

require_once dirname(__DIR__, 2) . '/setup.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

final class TholdI18nHelpersTest extends TestCase {
	public function test_form_field_helpers_return_translated_definitions(): void {
		$timespan = thold_graph_timespan_form_field([7 => 'Last Day'], '|arg1:graph_timespan|');
		$this->assertSame('Graph Timespan', $timespan['friendly_name']);
		$this->assertStringContainsString('Graph Timespan', $timespan['description']);
		$this->assertSame('|arg1:graph_timespan|', $timespan['value']);

		$ack = thold_acknowledgment_form_field('persist_ack');
		$this->assertSame('Acknowledgment Options', $ack['friendly_name']);
		$this->assertStringContainsString('Persistent Acknowledgment', $ack['description']);
		$this->assertSame('persist_ack', $ack['value']);
		$this->assertCount(3, $ack['items']);

		$cdef = thold_cdef_form_field(['cdef' => 4]);
		$this->assertSame('Threshold CDEF', $cdef['friendly_name']);
		$this->assertSame(4, $cdef['value']);
		$this->assertArrayHasKey(0, $cdef['array']);
	}

	public function test_reports_threshold_creation_results_for_non_empty_and_empty_messages(): void {
		thold_report_template_creation_result(2, 'Threshold created');
		thold_report_template_creation_result(0, '');

		$this->assertCount(2, CactiStubs::callsTo('raise_message'));
	}

	public function test_invokes_template_creation_reporting_from_data_source_and_graph_actions(): void {
		CactiStubs::$requestVars['selected_items']    = serialize([10]);
		CactiStubs::$requestVars['thold_template_id'] = 5;
		CactiStubs::willReturnFor('db_fetch_row_prepared', 'FROM thold_template', ['id' => 5, 'data_source_name' => 'traffic_in']);
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'FROM data_template_rrd', []);

		$this->assertSame('plugin_thold_create', thold_data_source_action_execute('plugin_thold_create'));
		$this->assertSame('plugin_thold_create', thold_graphs_action_execute('plugin_thold_create'));
		$this->assertCount(2, CactiStubs::callsTo('raise_message'));
	}
}
