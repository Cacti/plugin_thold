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
 * thold_command_execution() runs the operator's trigger command for a breach.
 *
 * The command is assembled from an admin-configured template, so the point of
 * these tests is which template is chosen for which breach direction, and that
 * the values spliced into it cannot extend the command line.
 */
final class TholdCommandExecutionTest extends TestCase {
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

		CactiStub::$configOptions['thold_enable_scripts']      = 'on';
		CactiStub::$configOptions['thold_notification_queue']  = 'on';
		CactiStub::$configOptions['base_url']                  = 'http://cacti.example.org';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function threshold(array $overrides = array()) {
		return $overrides + array(
			'id'                 => 3,
			'local_data_id'      => 4,
			'local_graph_id'     => 7,
			'name'               => 'CPU',
			'name_cache'         => 'CPU',
			'data_source_name'   => 'traffic_in',
			'lastread'           => 95,
			'notes'              => '',
			'dnotes'             => '',
			'external_id'        => '',
			'thold_type'         => 0,
			'thold_hi'           => 90,
			'thold_low'          => 10,
			'thold_fail_trigger' => 3,
			'thold_template_id'  => 0,
			'trigger_cmd_high'   => '',
			'trigger_cmd_low'    => '',
			'trigger_cmd_norm'   => '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function device(array $overrides = array()) {
		return $overrides + array(
			'id'                => 2,
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
	 * The queued command, or null when nothing was queued.
	 *
	 * @return string|null
	 */
	private function queuedCommand() {
		foreach (CactiStub::callsTo('db_execute_prepared') as $call) {
			foreach ($call['params'] as $param) {
				if (is_string($param) && strpos($param, '"command"') !== false) {
					$decoded = json_decode($param, true);

					return isset($decoded['command']) ? $decoded['command'] : null;
				}
			}
		}

		return null;
	}

	/**
	 * @return array<string, array{0: string, 1: array<int, bool>}>
	 */
	public static function breachDirectionProvider() {
		return array(
			'high'    => array('trigger_cmd_high', array(true, false, false)),
			'low'     => array('trigger_cmd_low', array(false, true, false)),
			'restore' => array('trigger_cmd_norm', array(false, false, true)),
		);
	}

	/**
	 * @dataProvider breachDirectionProvider
	 *
	 * @param string            $column
	 * @param array<int, bool>  $breaches
	 *
	 * @return void
	 */
	public function testEachBreachDirectionRunsItsOwnCommand($column, array $breaches): void {
		$thold  = $this->threshold(array($column => '/usr/bin/alert <HOSTNAME>'));
		$device = $this->device();

		thold_command_execution($thold, $device, $breaches[0], $breaches[1], $breaches[2]);

		$this->assertSame("/usr/bin/alert '10.0.0.1'", $this->queuedCommand());
	}

	/**
	 * @dataProvider breachDirectionProvider
	 *
	 * @param string           $column
	 * @param array<int, bool> $breaches
	 *
	 * @return void
	 */
	public function testShellMetacharactersInDeviceDataAreQuoted($column, array $breaches): void {
		$thold  = $this->threshold(array($column => '/usr/bin/alert <DESCRIPTION>'));
		$device = $this->device(array('description' => '; touch /tmp/pwned'));

		thold_command_execution($thold, $device, $breaches[0], $breaches[1], $breaches[2]);

		$this->assertSame("/usr/bin/alert '; touch /tmp/pwned'", $this->queuedCommand());
	}

	/**
	 * @return void
	 */
	public function testNothingRunsWhenScriptsAreDisabled(): void {
		CactiStub::$configOptions['thold_enable_scripts'] = '';

		$thold  = $this->threshold(array('trigger_cmd_high' => '/usr/bin/alert'));
		$device = $this->device();

		thold_command_execution($thold, $device, true, false, false);

		$this->assertNull($this->queuedCommand());
	}

	/**
	 * @return void
	 */
	public function testNothingRunsWhenTheDirectionHasNoCommandConfigured(): void {
		$thold  = $this->threshold();
		$device = $this->device();

		thold_command_execution($thold, $device, true, false, false);

		$this->assertNull($this->queuedCommand());
	}

	/**
	 * A high breach takes precedence, so a threshold configured for both cannot
	 * run two commands in one evaluation.
	 *
	 * @return void
	 */
	public function testHighBreachTakesPrecedenceOverLow(): void {
		$thold = $this->threshold(array(
			'trigger_cmd_high' => '/usr/bin/high',
			'trigger_cmd_low'  => '/usr/bin/low',
		));
		$device = $this->device();

		thold_command_execution($thold, $device, true, true, false);

		$this->assertSame('/usr/bin/high', $this->queuedCommand());
	}

	/**
	 * With the queue off the command runs inline, and its exit status and
	 * output are logged.
	 *
	 * @dataProvider breachDirectionProvider
	 *
	 * @param string           $column
	 * @param array<int, bool> $breaches
	 *
	 * @return void
	 */
	public function testInlineExecutionLogsTheCommandOutput($column, array $breaches): void {
		CactiStub::$configOptions['thold_notification_queue'] = '';

		$thold  = $this->threshold(array($column => '/bin/echo breach'));
		$device = $this->device();

		thold_command_execution($thold, $device, $breaches[0], $breaches[1], $breaches[2]);

		$this->assertNull($this->queuedCommand());
		$this->assertNotEmpty(CactiStub::$log);
	}

	/**
	 * @return void
	 */
	public function testInlineExecutionLogsANonZeroExitStatus(): void {
		CactiStub::$configOptions['thold_notification_queue'] = '';

		$thold  = $this->threshold(array('trigger_cmd_high' => '/bin/false'));
		$device = $this->device();

		thold_command_execution($thold, $device, true, false, false);

		$this->assertNotEmpty(CactiStub::$log);
	}
}
