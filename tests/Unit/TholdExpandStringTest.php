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
	 * Outside the per-graph SNMP substitution below (no graph found for this
	 * threshold), a |host_management_ip| token is still resolved against
	 * whatever device the current request is for. Real Cacti's
	 * lib/variables.php sets $device_id as a side effect of the include()
	 * inside thold_expand_string(), so this points library_path at a
	 * one-off fixture that does the same, rather than the shared
	 * tests/fixtures/cacti-lib one every other test here uses - which
	 * every other test in this file has typically already include_once()'d
	 * by the time this runs, so reusing it would silently no-op.
	 *
	 * @return void
	 */
	public function testShellModeEscapesTheTopLevelHostTokenForTheRequestDevice(): void {
		$malicious = "10.0.0.1'; touch /tmp/pwned; echo '";

		$fixtureDir = sys_get_temp_dir() . '/thold-variables-' . uniqid();
		mkdir($fixtureDir, 0777, true);
		file_put_contents($fixtureDir . '/variables.php', "<?php\n\$device_id = 5;\n");

		$originalLibraryPath               = $GLOBALS['config']['library_path'];
		$GLOBALS['config']['library_path'] = $fixtureDir;

		// No graph found for this threshold: db_fetch_row_prepared's first
		// call is the graph_local lookup, its second is thold_substitute_host_data()'s
		// own host lookup.
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		CactiStubs::willReturn('db_fetch_row_prepared', ['hostname' => $malicious]);

		try {
			$result = thold_expand_string($this->thresholdData(), 'alert |host_management_ip|', true);
		} finally {
			$GLOBALS['config']['library_path'] = $originalLibraryPath;
			unlink($fixtureDir . '/variables.php');
			rmdir($fixtureDir);
		}

		$this->assertSame('alert ' . escapeshellarg($malicious), $result);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function thresholdData(array $overrides = []) {
		return $overrides + [
			'local_graph_id'    => 7,
			'local_data_id'     => 4,
			'data_source_name'  => 'traffic_in',
			'thold_template_id' => 0,
		];
	}

	/**
	 * @return void
	 */
	private function graphExists() {
		CactiStubs::willReturn('db_fetch_row_prepared', [
			'id'            => 7,
			'host_id'       => 2,
			'snmp_query_id' => 3,
			'snmp_index'    => '1',
		]);
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
		CactiStubs::willReturn('db_fetch_cell_prepared', 'Router - Traffic');

		$this->assertSame('Router - Traffic', thold_expand_string($this->thresholdData(), '|data_source_description|'));
	}

	/**
	 * @return void
	 */
	public function testTextIsPassedThroughExpandTitleForDataQueryTokens(): void {
		$this->graphExists();
		CactiStubs::willReturn('expand_title', 'alert eth0');

		$this->assertSame('alert eth0', thold_expand_string($this->thresholdData(), 'alert |query_ifName|'));
		$this->assertNotEmpty(CactiStubs::callsTo('expand_title'));
	}

	/**
	 * @return void
	 */
	public function testInterfaceSpeedFallsBackToTheConfiguredDefaultWhenUnknown(): void {
		$this->graphExists();
		CactiStubs::$configOptions['thold_empty_if_speed_default'] = '1000000000';
		CactiStubs::willReturn('db_fetch_cell_prepared', '');

		$result = thold_expand_string($this->thresholdData(), '|query_ifHighSpeed|');

		$this->assertStringNotContainsString('|query_ifHighSpeed|', $result);
	}

	/**
	 * @return void
	 */
	public function testUnthrottledInterfaceSpeedFallsBackToTheConfiguredDefaultWhenUnknown(): void {
		$this->graphExists();
		CactiStubs::$configOptions['thold_empty_if_speed_default'] = '1000000000';
		CactiStubs::willReturn('db_fetch_cell_prepared', '');

		$result = thold_expand_string($this->thresholdData(), '|query_ifSpeed|');

		$this->assertStringNotContainsString('|query_ifSpeed|', $result);
	}

	/**
	 * @return void
	 */
	public function testTextIsReturnedUnchangedWhenTheGraphIsMissing(): void {
		CactiStubs::willReturn('db_fetch_row_prepared', []);

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
		CactiStubs::willReturn('db_fetch_cell_prepared', 'Suggested |data_source_name|');

		$result = thold_expand_string($this->thresholdData(['thold_template_id' => 5]), '');

		$this->assertSame('Suggested traffic_in', $result);
	}

	/**
	 * @return void
	 */
	public function testSurroundingWhitespaceIsTrimmed(): void {
		CactiStubs::willReturn('db_fetch_row_prepared', []);

		$this->assertSame('alert', thold_expand_string($this->thresholdData(), '  alert  '));
	}

	/**
	 * In $shell mode (used when building a trigger command line), host and
	 * data-query token values come from the polled device and must be shell-
	 * escaped rather than substituted raw, or a malicious sysDescr/community/
	 * custom field could inject shell syntax into the command.
	 *
	 * @return void
	 */
	public function testShellModeEscapesHostTokenValues(): void {
		CactiStubs::willReturn('db_fetch_row_prepared', [
			'id'            => 7,
			'host_id'       => 2,
			'snmp_query_id' => '0',
			'snmp_index'    => '',
		]);

		$malicious = "evil'; touch /tmp/pwned; echo '";
		CactiStubs::willReturn('substitute_host_data', $malicious);

		$result = thold_expand_string($this->thresholdData(), 'alert |host_description|', true);

		$this->assertSame('alert ' . escapeshellarg($malicious), $result);
	}

	/**
	 * When the graph has an SNMP data query attached, host/query tokens are
	 * resolved through substitute_snmp_query_data() (so query-indexed fields
	 * work too) rather than through substitute_host_data() alone - and the
	 * result is still escaped before landing in the command.
	 *
	 * @return void
	 */
	public function testShellModeResolvesHostTokensThroughSnmpQueryDataWhenAGraphQueryIsAttached(): void {
		$this->graphExists();

		$malicious = "evil'; touch /tmp/pwned; echo '";
		CactiStubs::willReturn('substitute_snmp_query_data', $malicious);

		$result = thold_expand_string($this->thresholdData(), 'alert |host_description|', true);

		$this->assertSame('alert ' . escapeshellarg($malicious), $result);
		$this->assertNotEmpty(CactiStubs::callsTo('substitute_snmp_query_data'));
	}

	/**
	 * Non-shell callers (email/HTML rendering) must not be affected by the
	 * shell-escaping added for the trigger-command path.
	 *
	 * @return void
	 */
	public function testNonShellModeLeavesHostTokenValuesUnescaped(): void {
		$this->graphExists();
		CactiStubs::willReturn('expand_title', 'alert eth0');

		$this->assertSame('alert eth0', thold_expand_string($this->thresholdData(), 'alert |query_ifName|', false));
	}
}
