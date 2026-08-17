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
 * thold_set_environ() exports the threshold context that a trigger command
 * reads from its environment.
 *
 * With the notification queue on, thold_putenv() accumulates the pairs and
 * returns them instead of calling putenv(), which is what makes the function
 * observable here.
 */
final class TholdSetEnvironTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		CactiStub::$configOptions['thold_notification_queue'] = 'on';
		CactiStub::$configOptions['base_url']                 = 'http://cacti.example.org';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function threshold(array $overrides = array()) {
		return $overrides + array(
			'id'                 => 3,
			'local_data_id'      => 4,
			'local_graph_id'     => 7,
			'name_cache'         => 'CPU',
			'notes'              => '',
			'dnotes'             => 'device note',
			'external_id'        => '',
			'thold_type'         => 0,
			'thold_hi'           => 90,
			'thold_low'          => 10,
			'thold_fail_trigger' => 3,
			'time_hi'            => 80,
			'time_low'           => 20,
			'time_fail_trigger'  => 2,
			'time_fail_length'   => 300,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function device(array $overrides = array()) {
		return $overrides + array(
			'description'       => 'router1',
			'hostname'          => '10.0.0.1',
			'location'          => 'rack 4',
			'site_id'           => 1,
			'status'            => 3,
			'status_fail_date'  => '2026-01-01 00:00:00',
			'status_rec_date'   => '2026-01-02 00:00:00',
			'status_last_error' => '',
		);
	}

	/**
	 * Collect the exported environment as a name => value map.
	 *
	 * @param array<string, mixed> $thold
	 * @param array<string, mixed> $device
	 *
	 * @return array<string, string>
	 */
	private function environment(array $thold, array $device) {
		$pairs = thold_set_environ('', $thold, $device, 42, 7, 'traffic_in');
		$map   = array();

		foreach ($pairs as $pair) {
			list($name, $value) = explode('=', $pair, 2);

			$map[$name] = $value;
		}

		return $map;
	}

	/**
	 * @return void
	 */
	public function testThresholdAndDeviceContextIsExported(): void {
		$env = $this->environment($this->threshold(), $this->device());

		$this->assertSame('3', $env['THOLD_ID']);
		$this->assertSame('router1', $env['THOLD_DESCRIPTION']);
		$this->assertSame('10.0.0.1', $env['THOLD_HOSTNAME']);
		$this->assertSame('42', $env['THOLD_CURRENTVALUE']);
		$this->assertSame('traffic_in', $env['THOLD_DSNAME']);
		$this->assertSame('device note', $env['THOLD_DEVICENOTE']);
	}

	/**
	 * The queue accumulates across calls, so the first pair has to reset it or
	 * a later command inherits the previous threshold's context.
	 *
	 * @return void
	 */
	public function testEachCallStartsFromAnEmptyEnvironment(): void {
		$this->environment($this->threshold(), $this->device());
		$env = $this->environment($this->threshold(array('id' => 9)), $this->device());

		$this->assertSame('9', $env['THOLD_ID']);
		$this->assertCount(1, array_keys(array_filter(array_keys($env), function ($name) {
			return $name === 'THOLD_ID';
		})));
	}

	/**
	 * @return void
	 */
	public function testStaticThresholdExportsItsBoundsAndNoDuration(): void {
		$env = $this->environment($this->threshold(), $this->device());

		$this->assertSame('90', $env['THOLD_HI']);
		$this->assertSame('10', $env['THOLD_LOW']);
		$this->assertSame('3', $env['THOLD_TRIGGER']);
		$this->assertSame('', $env['THOLD_DURATION']);
	}

	/**
	 * @return void
	 */
	public function testTimeBasedThresholdExportsTheTimeBoundsAndADuration(): void {
		$env = $this->environment($this->threshold(array('thold_type' => 2)), $this->device());

		$this->assertSame('80', $env['THOLD_HI']);
		$this->assertSame('20', $env['THOLD_LOW']);
		$this->assertSame('2', $env['THOLD_TRIGGER']);
		$this->assertNotSame('', $env['THOLD_DURATION']);
	}

	/**
	 * @return void
	 */
	public function testBaselineThresholdExportsEmptyBounds(): void {
		$env = $this->environment($this->threshold(array('thold_type' => 1)), $this->device());

		$this->assertSame('', $env['THOLD_HI']);
		$this->assertSame('', $env['THOLD_LOW']);
		$this->assertSame('', $env['THOLD_TRIGGER']);
		$this->assertSame('', $env['THOLD_DURATION']);
	}

	/**
	 * @return void
	 */
	public function testNotesAreTagExpandedWhenPresent(): void {
		$env = $this->environment($this->threshold(array('notes' => 'see <HOSTNAME>')), $this->device());

		$this->assertSame('see 10.0.0.1', $env['THOLD_NOTES']);
	}

	/**
	 * @return void
	 */
	public function testNotesAreEmptyWhenTheThresholdHasNone(): void {
		$env = $this->environment($this->threshold(), $this->device());

		$this->assertSame('', $env['THOLD_NOTES']);
	}

	/**
	 * @return void
	 */
	public function testExternalIdIsExportedOnlyWhenSet(): void {
		$env = $this->environment($this->threshold(), $this->device());
		$this->assertArrayNotHasKey('THOLD_EXTERNAL_ID', $env);

		$env = $this->environment($this->threshold(array('external_id' => 'INC-42')), $this->device());
		$this->assertSame('INC-42', $env['THOLD_EXTERNAL_ID']);
	}

	/**
	 * @return void
	 */
	public function testUnknownThresholdTypeExportsAnEmptyTypeName(): void {
		$env = $this->environment($this->threshold(array('thold_type' => 99)), $this->device());

		$this->assertSame('', $env['THOLD_THOLDTYPE']);
	}

	/**
	 * @return void
	 */
	public function testGraphUrlIsExported(): void {
		$env = $this->environment($this->threshold(), $this->device());

		$this->assertSame('http://cacti.example.org/graph.php?local_graph_id=7', $env['THOLD_URL']);
	}
}
