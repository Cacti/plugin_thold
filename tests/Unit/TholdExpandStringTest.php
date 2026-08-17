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
 * thold_expand_string() resolves the |pipe| tokens, which come from the data
 * query cache rather than from the threshold row.
 *
 * These values are device-supplied, which is why trigger commands expand them
 * before the <TAG> escaping rather than after.
 */
final class TholdExpandStringTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return array<string, mixed>
	 */
	private function thresholdData(array $overrides = array()) {
		return $overrides + array(
			'local_graph_id'    => 7,
			'local_data_id'     => 4,
			'data_source_name'  => 'traffic_in',
			'thold_template_id' => 0,
		);
	}

	/**
	 * @return void
	 */
	private function graphExists() {
		CactiStub::willReturn('db_fetch_row_prepared', array(
			'id'            => 7,
			'host_id'       => 2,
			'snmp_query_id' => 3,
			'snmp_index'    => '1',
		));
	}

	/**
	 * @return void
	 */
	public function testGraphTitleTokenIsResolved(): void {
		$this->graphExists();

		$this->assertSame('Traffic - eth0', thold_expand_string($this->thresholdData(), '|graph_title|'));
	}

	/**
	 * @return void
	 */
	public function testDataSourceNameTokenIsResolved(): void {
		$this->graphExists();

		$this->assertSame('traffic_in', thold_expand_string($this->thresholdData(), '|data_source_name|'));
	}

	/**
	 * @return void
	 */
	public function testDataSourceDescriptionTokenIsResolvedFromTheDatabase(): void {
		$this->graphExists();
		CactiStub::willReturn('db_fetch_cell_prepared', 'Router - Traffic');

		$this->assertSame('Router - Traffic', thold_expand_string($this->thresholdData(), '|data_source_description|'));
	}

	/**
	 * @return void
	 */
	public function testTextIsPassedThroughExpandTitleForDataQueryTokens(): void {
		$this->graphExists();
		CactiStub::willReturn('expand_title', 'alert eth0');

		$this->assertSame('alert eth0', thold_expand_string($this->thresholdData(), 'alert |query_ifName|'));
		$this->assertNotEmpty(CactiStub::callsTo('expand_title'));
	}

	/**
	 * @return void
	 */
	public function testInterfaceSpeedFallsBackToTheConfiguredDefaultWhenUnknown(): void {
		$this->graphExists();
		CactiStub::$configOptions['thold_empty_if_speed_default'] = '1000000000';
		CactiStub::willReturn('db_fetch_cell_prepared', '');

		$result = thold_expand_string($this->thresholdData(), '|query_ifHighSpeed|');

		$this->assertStringNotContainsString('|query_ifHighSpeed|', $result);
	}

	/**
	 * @return void
	 */
	public function testTextIsReturnedUnchangedWhenTheGraphIsMissing(): void {
		CactiStub::willReturn('db_fetch_row_prepared', array());

		$this->assertSame('static text', thold_expand_string($this->thresholdData(), 'static text'));
	}

	/**
	 * An empty template falls back to the threshold template's suggested name,
	 * which is itself a token string and gets expanded in turn.
	 *
	 * @return void
	 */
	public function testEmptyStringFallsBackToTheExpandedTemplateSuggestedName(): void {
		$this->graphExists();
		CactiStub::willReturn('db_fetch_cell_prepared', 'Suggested |data_source_name|');

		$result = thold_expand_string($this->thresholdData(array('thold_template_id' => 5)), '');

		$this->assertSame('Suggested traffic_in', $result);
	}

	/**
	 * @return void
	 */
	public function testSurroundingWhitespaceIsTrimmed(): void {
		CactiStub::willReturn('db_fetch_row_prepared', array());

		$this->assertSame('alert', thold_expand_string($this->thresholdData(), '  alert  '));
	}
}
