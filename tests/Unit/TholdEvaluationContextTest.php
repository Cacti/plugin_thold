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
 * The settings a threshold evaluation reads.
 *
 * These were computed inline in thold_check_threshold() and so could only be
 * reached by driving a whole poll. Now that they are their own function the
 * rules they encode -- trigger fallback, recipient resolution, whether a
 * graph is attached -- can be checked directly.
 */
final class TholdEvaluationContextTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @param array<string, mixed> $overrides
	 *
	 * @return array<string, mixed>
	 */
	private function context(array $overrides = []) {
		return thold_evaluation_context($overrides + [
			'id'                         => 1,
			'name'                       => 'CPU',
			'name_cache'                 => 'CPU',
			'local_graph_id'             => 7,
			'local_data_id'              => 4,
			'data_source_name'           => 'traffic_in',
			'lastread'                   => 50,
			'thold_alert'                => 0,
			'thold_fail_trigger'         => 3,
			'thold_warning_fail_trigger' => 2,
			'notify_alert'               => 0,
			'notify_warning'             => 0,
			'notify_extra'               => '',
			'notify_warning_extra'       => '',
			'syslog_enabled'             => '',
			'syslog_priority'            => 5,
			'syslog_facility'            => 1,
		]);
	}

	/**
	 * @return void
	 */
	public function testThresholdTriggersAreUsedWhenSet(): void {
		$context = $this->context();

		$this->assertSame(3, $context['trigger']);
		$this->assertSame(2, $context['warning_trigger']);
	}

	/**
	 * @return void
	 */
	public function testUnsetTriggersFallBackToTheGlobalDefault(): void {
		CactiStubs::$configOptions['alert_trigger'] = 5;

		$context = $this->context([
			'thold_fail_trigger'         => '',
			'thold_warning_fail_trigger' => '',
		]);

		$this->assertSame(5, $context['trigger']);
		$this->assertSame(5, $context['warning_trigger']);
	}

	/**
	 * @return void
	 */
	public function testSyslogIsOffUnlessTheThresholdEnablesIt(): void {
		$this->assertFalse($this->context()['syslog']);
		$this->assertTrue($this->context(['syslog_enabled' => 'on'])['syslog']);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function trapSettingProvider() {
		return [
			'alert'   => ['thold_alert_snmp', 'thold_snmp_traps'],
			'warning' => ['thold_alert_snmp_warning', 'thold_snmp_warning_traps'],
			'normal'  => ['thold_alert_snmp_normal', 'thold_snmp_normal_traps'],
		];
	}

	/**
	 * @dataProvider trapSettingProvider
	 *
	 * @param string $option
	 * @param string $key
	 *
	 * @return void
	 */
	public function testEachTrapClassFollowsItsOwnSetting($option, $key): void {
		$this->assertFalse($this->context()[$key]);

		CactiStubs::$configOptions[$option] = 'on';

		$this->assertTrue($this->context()[$key]);
	}

	/**
	 * Alerts go to the warning recipients as well only when the two lists
	 * differ and the operator has asked for it.
	 *
	 * @return void
	 */
	public function testWarningRecipientsAreAddedOnlyWhenConfiguredAndDistinct(): void {
		CactiStubs::$configOptions['thold_notify_alerts_to_warning_recipients'] = 'on';

		$this->assertTrue($this->context(['notify_alert' => 1, 'notify_warning' => 2])['notify_different']);
		$this->assertFalse($this->context(['notify_alert' => 2, 'notify_warning' => 2])['notify_different']);
		$this->assertFalse($this->context(['notify_alert' => 1, 'notify_warning' => 0])['notify_different']);
	}

	/**
	 * @return void
	 */
	public function testWarningRecipientsAreNotAddedWhenTheOptionIsOff(): void {
		$this->assertFalse($this->context(['notify_alert' => 1, 'notify_warning' => 2])['notify_different']);
	}

	/**
	 * @return void
	 */
	public function testAGraphIsAttachedByDefault(): void {
		$file_array = $this->context()['file_array'];

		$this->assertSame(7, $file_array['local_graph_id']);
		$this->assertSame('image/png', $file_array['mimetype']);
	}

	/**
	 * @return void
	 */
	public function testTextOnlyNotificationsAttachNoGraph(): void {
		CactiStubs::$configOptions['thold_send_text_only'] = 'on';

		$this->assertSame([], $this->context()['file_array']);
	}

	/**
	 * @return void
	 */
	public function testNoGraphIsAttachedWhenTheThresholdHasNone(): void {
		$this->assertSame([], $this->context(['local_graph_id' => 0])['file_array']);
	}

	/**
	 * @return void
	 */
	public function testTheGraphUrlPointsAtTheThresholdsGraph(): void {
		CactiStubs::$configOptions['base_url'] = 'http://cacti.example.org';

		$this->assertSame(
			'http://cacti.example.org/graph.php?local_graph_id=7&rra_id=all',
			$this->context()['url']
		);
	}

	/**
	 * @return void
	 */
	public function testRecipientsAreResolvedForBothClasses(): void {
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'FROM plugin_thold_contacts', [['data' => 'ops@example.org']]);

		$context = $this->context();

		$this->assertStringContainsString('ops@example.org', $context['alert_emails']);
		$this->assertArrayHasKey('warning_emails', $context);
		$this->assertArrayHasKey('alert_bcc_emails', $context);
		$this->assertArrayHasKey('warning_bcc_emails', $context);
	}

	/**
	 * @return void
	 */
	public function testTheCurrentReadingIsCarriedThrough(): void {
		$this->assertSame(95, $this->context(['lastread' => 95])['lastread']);
	}

	/**
	 * @return void
	 */
	public function testThePreviousAlertStateIsCarriedThrough(): void {
		$this->assertSame(2, $this->context(['thold_alert' => 2])['alertstat']);
	}
}
