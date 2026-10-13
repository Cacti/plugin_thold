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

/**
 * thold_graph_button() renders this plugin's graph-page drilldown action
 * icons. This covers the VRULE toggle button's markup, including the
 * 'Toggle Threshold VRULES On'/'Off' title that shows as a label in Cacti's
 * graph action menu.
 */
final class TholdGraphButtonTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('setup.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		CactiStubs::reset();
	}

	/**
	 * @return void
	 */
	public function test_it_prints_the_vrule_button_with_the_shortened_title(): void {
		// A threshold exists for the graph, so the VRULE toggle is shown; the
		// aggregate lookup returns a row so the Create Threshold branch is skipped.
		CactiStubs::willReturnFor('db_fetch_cell_prepared', 'thold_data', 123);
		CactiStubs::willReturnFor('db_fetch_cell_prepared', 'aggregate_graphs', 5);

		$_SERVER['REQUEST_URI'] = '/cacti/graph_view.php?action=preview';
		$_SESSION               = [];

		ob_start();
		thold_graph_button([1 => ['local_graph_id' => 10, 'rra' => 5]]);
		$html = ob_get_clean();

		$this->assertStringContainsString('iconLink tholdVRule', $html);
		$this->assertStringContainsString('tholdVRules far fa-chart-bar', $html);
		// Default state (no session/request var) resolves to the 'On' action and
		// keeps the Thold-specific 'Threshold' reference.
		$this->assertStringContainsString('Toggle Threshold VRULES On', $html);
		$this->assertStringNotContainsString('Toggle VRULES', $html);
	}
}
