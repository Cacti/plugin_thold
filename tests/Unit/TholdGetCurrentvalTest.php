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
 * thold_get_currentval() turns a raw sample into the rate a threshold is
 * compared against, per the data source type.
 */
final class TholdGetCurrentvalTest extends TestCase {
	const GAUGE    = 1;
	const COUNTER  = 2;
	const DERIVE   = 3;
	const ABSOLUTE = 4;

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
	private function threshold(array $overrides = []) {
		return $overrides + [
			'id'                   => 9,
			'thold_id'             => 9,
			'local_data_id'        => 4,
			'name'                 => 'traffic_in',
			'data_source_type_id'  => self::COUNTER,
			'rrd_step'             => 300,
			'rrd_maximum'          => 0,
			'lasttime'             => 1700000000,
			'oldvalue'             => 100,
		];
	}

	/**
	 * @param array<string, mixed> $thold
	 * @param float|int|string     $reading
	 *
	 * @return mixed
	 */
	private function currentValue(array $thold, $reading) {
		$reindexed      = [4 => ['traffic_in' => $reading]];
		$time_reindexed = [4 => 1700000300];
		$item           = [];
		$currenttime    = 0;

		return thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime);
	}

	/**
	 * @return void
	 */
	public function testGaugeReturnsTheReadingUnchanged(): void {
		$thold = $this->threshold(['data_source_type_id' => self::GAUGE]);

		$this->assertSame(42, $this->currentValue($thold, 42));
	}

	/**
	 * @return void
	 */
	public function testAbsoluteDividesTheReadingByTheStep(): void {
		$thold = $this->threshold(['data_source_type_id' => self::ABSOLUTE]);

		$this->assertEqualsWithDelta(2, $this->currentValue($thold, 600), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testAbsoluteRejectsStaleAndOutOfOrderIntervals(): void {
		foreach ([1000 + 86400, 900] as $sample_time) {
			$thold          = $this->threshold(['data_source_type_id' => self::ABSOLUTE, 'lasttime' => 1000]);
			$reindexed      = [4 => ['traffic_in' => 600]];
			$time_reindexed = [4 => $sample_time];
			$item           = [];
			$currenttime    = 0;

			$this->assertSame('', thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime));
		}
	}

	/**
	 * @return void
	 */
	public function testGaugeRemainsValidAcrossAGap(): void {
		$thold          = $this->threshold(['data_source_type_id' => self::GAUGE, 'lasttime' => 1000]);
		$reindexed      = [4 => ['traffic_in' => 42]];
		$time_reindexed = [4 => 1000 + 7 * 86400];
		$item           = [];
		$currenttime    = 0;

		$this->assertSame(42, thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime));
	}

	/**
	 * @return void
	 */
	public function testCounterReturnsTheDeltaOverTheStep(): void {
		$thold = $this->threshold(['oldvalue' => 100]);

		$this->assertEqualsWithDelta(2, $this->currentValue($thold, 700), 1.0e-9);
	}

	/**
	 * A counter that legitimately read zero last cycle is not the same as
	 * having no previous reading. Treating it as absent reports a rate of zero
	 * for the first interval after a device reboot.
	 *
	 * @return void
	 */
	public function testCounterTreatsAPreviousReadingOfZeroAsReal(): void {
		$thold = $this->threshold(['oldvalue' => 0]);

		$this->assertEqualsWithDelta(2, $this->currentValue($thold, 600), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testCounterWithNoPreviousReadingYieldsUnknown(): void {
		$thold = $this->threshold(['lasttime' => 0, 'oldvalue' => '']);

		$this->assertSame('', $this->currentValue($thold, 600));
	}

	/**
	 * A 32-bit counter that wraps has advanced by (2^32 - old) + new. Using
	 * 2^32-1 as the modulus loses exactly one count per wrap.
	 *
	 * @return void
	 */
	public function testThirtyTwoBitWrapUsesTheCorrectModulus(): void {
		$thold = $this->threshold(['oldvalue' => 4294967290, 'rrd_step' => 1, 'lasttime' => 1700000299]);

		$this->assertEqualsWithDelta(11, $this->currentValue($thold, 5), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testSixtyFourBitWrapUsesTheCorrectModulus(): void {
		$thold = $this->threshold(['oldvalue' => '18446744073709551610', 'rrd_step' => 1, 'lasttime' => 1700000299]);

		$this->assertEqualsWithDelta(11, $this->currentValue($thold, 5), 1.0e-9);
	}

	/**
	 * RRD values can arrive in scientific notation. GMP accepts only integer
	 * strings, so these values must use the non-fatal floating-point fallback.
	 *
	 * @return void
	 */
	public function testSixtyFourBitWrapAcceptsScientificNotation(): void {
		$thold = $this->threshold(['oldvalue' => '1.8446744073709552E+19', 'rrd_step' => 1, 'lasttime' => 1700000299]);

		$this->assertEqualsWithDelta(5, $this->currentValue($thold, 5), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testDeriveDividesTheDeltaByTheStep(): void {
		$thold = $this->threshold(['data_source_type_id' => self::DERIVE, 'oldvalue' => 100]);

		$this->assertEqualsWithDelta(2, $this->currentValue($thold, 700), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testNonNumericReadingYieldsTheNoValueSentinel(): void {
		$this->assertSame('', $this->currentValue($this->threshold(), 'U'));
	}

	/**
	 * @return void
	 */
	public function testMissingDataSourceYieldsTheNoValueSentinel(): void {
		$thold          = $this->threshold();
		$reindexed      = [];
		$time_reindexed = [4 => 1700000300];
		$item           = [];
		$currenttime    = 0;

		$this->assertSame('', thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime));
	}

	/**
	 * @return void
	 */
	public function testLowerUpperCombinationRejectsUnknownInputs(): void {
		$thold = ['local_data_id' => 4, 'upper_ds' => 'upper'];

		$this->assertSame('', thold_calculate_lower_upper($thold, '', [4 => ['upper' => 5]]));
		$this->assertSame('', thold_calculate_lower_upper($thold, 7, [4 => ['upper' => 'U']]));
		$this->assertSame('', thold_calculate_lower_upper($thold, 7, [4 => []]));
		$this->assertEqualsWithDelta((5 * 4294967296) + 7, thold_calculate_lower_upper($thold, 7, [4 => ['upper' => 5]]), 1.0e-9);
		$this->assertEqualsWithDelta((2147483648.0 * 4294967296) + 7, thold_calculate_lower_upper($thold, 7, [4 => ['upper' => 2147483648]]), 1);
		$this->assertEqualsWithDelta((4294967295.0 * 4294967296) + 7, thold_calculate_lower_upper($thold, 7, [4 => ['upper' => 4294967295]]), 1);
		$this->assertSame('', thold_calculate_lower_upper($thold, 7, [4 => ['upper' => -1]]));
		$this->assertSame('', thold_calculate_lower_upper($thold, 7, [4 => ['upper' => 4294967296]]));
	}

	/**
	 * @return void
	 */
	public function testCdefAndNestedExpressionPreserveUnknownValues(): void {
		$this->assertSame('', thold_build_cdef(1, '', 4, 5));
		$this->assertSame(
			['sample_row' => "(9, 1, '', FROM_UNIXTIME(1700000300), '700')", 'status_row' => null],
			thold_polling_sample_row($this->threshold(), ['traffic_in' => 700], '', 1700000300)
		);

		$nested = $this->threshold([
			'lasttime'      => 1000,
			'rrd_heartbeat' => 600,
		]);
		CactiStubs::willReturn('db_fetch_row_prepared', $nested);
		$outer          = $this->threshold(['expression' => '|ds:traffic_in|', 'lastread' => 2]);
		$reindexed      = [4 => ['traffic_in' => 700]];
		$time_reindexed = [4 => 1900];

		$this->assertSame('', thold_calculate_expression($outer, '', $reindexed, $time_reindexed));
		$this->assertSame([], CactiStubs::$log);

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', $nested);
		$time_reindexed[4] = 1300;
		$this->assertEqualsWithDelta(2, thold_calculate_expression($outer, '', $reindexed, $time_reindexed), 1.0e-9);

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		CactiStubs::willReturn('db_fetch_row_prepared', ['data_source_type_id' => self::COUNTER]);
		CactiStubs::willReturn('db_fetch_row_prepared', ['rrd_step' => 300]);
		CactiStubs::willReturn('rrdtool_execute', '1700000000');
		CactiStubs::willReturn('rrdtool_function_fetch', [
			'data_source_names' => ['traffic_in'],
			'values'            => [['1700000000' => 2.0]],
		]);
		$this->assertEqualsWithDelta(2.0, thold_calculate_expression($outer, '', $reindexed, $time_reindexed), 1.0e-9);
		$this->assertSame([], CactiStubs::$log);

		CactiStubs::reset();
		CactiStubs::$configOptions['dsstats_enable'] = 'on';
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		CactiStubs::willReturn('db_fetch_row_prepared', ['data_source_type_id' => self::COUNTER]);
		CactiStubs::willReturn('db_fetch_cell_prepared', 3.5);
		$this->assertEqualsWithDelta(3.5, thold_calculate_expression($outer, '', $reindexed, $time_reindexed), 1.0e-9);
		$this->assertSame([], CactiStubs::callsTo('rrdtool_function_fetch'));

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		CactiStubs::willReturn('db_fetch_row_prepared', ['data_source_type_id' => self::GAUGE]);
		$this->assertSame('700', thold_calculate_expression($outer, '', $reindexed, $time_reindexed));
		$this->assertSame([], CactiStubs::callsTo('rrdtool_function_fetch'));

		foreach ([
			[],
			['data_source_names' => ['traffic_out'], 'values' => [['1700000000' => 2.0]]],
		] as $missing_fetch) {
			CactiStubs::reset();
			CactiStubs::willReturn('db_fetch_row_prepared', []);
			CactiStubs::willReturn('db_fetch_row_prepared', ['data_source_type_id' => self::COUNTER]);
			CactiStubs::willReturn('db_fetch_row_prepared', ['rrd_step' => 300]);
			CactiStubs::willReturn('rrdtool_execute', '1700000000');
			CactiStubs::willReturn('rrdtool_function_fetch', $missing_fetch);
			$this->assertSame('', thold_calculate_expression($outer, '', $reindexed, $time_reindexed));
		}

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		$this->assertSame('', thold_calculate_expression($outer, '', $reindexed, $time_reindexed));

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		$reindexed = [];
		$this->assertSame('', thold_calculate_expression($outer, '', $reindexed, $time_reindexed));
		$this->assertStringContainsString('expression source traffic_in is unavailable', CactiStubs::$log[0]);
		$log_call = CactiStubs::callsTo('cacti_log')[0];
		$this->assertSame('THOLD', $log_call['params'][2]);
		$this->assertSame(POLLER_VERBOSITY_MEDIUM, $log_call['params'][3]);
	}

	/**
	 * @return void
	 */
	public function testMissedSampleCarriesTheValueAndTimestampTogether(): void {
		$thold = $this->threshold(['lasttime' => 1000, 'oldvalue' => 100]);

		$missed = thold_sample_persistence($thold, [], 1300);
		$this->assertSame(['lasttime' => 1000, 'oldvalue' => 100], $missed);
		$this->assertSame($missed, thold_sample_persistence($thold, ['traffic_in' => 'U'], 1300));
		$this->assertSame($missed, thold_sample_persistence($thold, ['traffic_in' => 'nan'], 1300));
		$this->assertSame($missed, thold_sample_persistence($thold, ['traffic_in' => ''], 1300));
		$this->assertSame(
			['lasttime' => 1600, 'oldvalue' => 700],
			thold_sample_persistence($thold, ['traffic_in' => 700], 1600)
		);
		$this->assertSame(
			['lasttime' => 1600, 'oldvalue' => '700'],
			thold_sample_persistence($thold, ['traffic_in' => '700'], 1600)
		);

		$thold['lasttime'] = $missed['lasttime'];
		$thold['oldvalue'] = $missed['oldvalue'];
		$reindexed         = [4 => ['traffic_in' => 700]];
		$time_reindexed    = [4 => 1600];
		$item              = [];
		$currenttime       = 0;

		$this->assertEqualsWithDelta(
			1,
			thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime),
			1.0e-9
		);
	}

	/**
	 * @return array<string, array{0: int|string|null, 1: int, 2: float}>
	 */
	public static function emptyMaximumProvider() {
		return [
			'integer zero'        => [0, 1009000000100, 1009000000100 / 600],
			'empty string'        => ['', 1009000000100, 1009000000100 / 600],
			'null maximum'        => [null, 1009000000100, 1009000000100 / 600],
			'unknown maximum'     => ['U', 1009000000100, 1009000000100 / 600],
			'unresolved if speed' => ['|query_ifSpeed|', 1009000000100, 1009000000100 / 600],
			'explicit maximum'    => [20000000, 1015000000000, 1015000000000 / 600],
		];
	}

	/**
	 * @dataProvider emptyMaximumProvider
	 *
	 * @param int|string|null $maximum
	 * @param int        $reading
	 * @param float      $expected
	 *
	 * @return void
	 */
	public function testMultiIntervalDeltaScalesTheResetGuard($maximum, $reading, $expected): void {
		if ($maximum === '|query_ifSpeed|') {
			CactiStubs::willReturn('db_fetch_row_prepared', ['host_id' => 1, 'snmp_query_id' => 2, 'snmp_index' => 'eth0']);
			CactiStubs::willReturn('db_fetch_cell_prepared', '');
		}

		$thold          = $this->threshold(['lasttime' => 1000, 'oldvalue' => 1000000000000, 'rrd_maximum' => $maximum]);
		$reindexed      = [4 => ['traffic_in' => $reading]];
		$time_reindexed = [4 => 1600];
		$item           = [];
		$currenttime    = 0;

		$this->assertEqualsWithDelta($expected, thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testMultiIntervalWrapUsesTheWholeElapsedTime(): void {
		$thold          = $this->threshold(['lasttime' => 1000, 'oldvalue' => 4294967290, 'rrd_maximum' => 0]);
		$reindexed      = [4 => ['traffic_in' => 5]];
		$time_reindexed = [4 => 1600];
		$item           = [];
		$currenttime    = 0;

		$this->assertEqualsWithDelta(
			11 / 600,
			thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime),
			1.0e-9
		);
	}

	/**
	 * @return void
	 */
	public function testWrapResetGuardUsesTheEffectiveSampleInterval(): void {
		CactiStubs::$configOptions['poller_interval'] = 300;
		$thold = $this->threshold([
			'lasttime'   => 1700000000,
			'oldvalue'   => 1000,
			'rrd_step'   => 60,
			'rrd_maximum' => 0,
		]);

		$this->assertEqualsWithDelta(999 / 300, $this->currentValue($thold, 999), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testStaleCounterAndDeriveSamplesAreDiscarded(): void {
		foreach ([self::COUNTER, self::DERIVE] as $type) {
			$thold          = $this->threshold(['data_source_type_id' => $type, 'lasttime' => 1000, 'oldvalue' => 100]);
			$reindexed      = [4 => ['traffic_in' => 700]];
			$time_reindexed = [4 => 1000 + 7 * 86400];
			$item           = [];
			$currenttime    = 0;

			$this->assertSame('', thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime));
		}
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function invalidRrdStepProvider() {
		return [
			'zero'        => [0],
			'null'        => [null],
			'non-numeric' => ['invalid'],
		];
	}

	/**
	 * @dataProvider invalidRrdStepProvider
	 *
	 * @param mixed $rrd_step
	 *
	 * @return void
	 */
	public function testInvalidRrdStepFallsBackToThePollerInterval($rrd_step): void {
		CactiStubs::$configOptions['poller_interval'] = 300;
		$thold = $this->threshold([
			'data_source_type_id' => self::ABSOLUTE,
			'lasttime'            => 0,
			'rrd_step'            => $rrd_step,
		]);

		$this->assertEqualsWithDelta(2, $this->currentValue($thold, 600), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testEvaluationCadenceMayExceedTheRrdStep(): void {
		CactiStubs::$configOptions['poller_interval'] = 300;
		$thold = $this->threshold(['rrd_step' => 60]);

		$this->assertEqualsWithDelta(2, $this->currentValue($thold, 700), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testRrdHeartbeatControlsGapAcceptanceWithASafeFloor(): void {
		$accepted       = $this->threshold(['lasttime' => 1000, 'rrd_heartbeat' => 1800]);
		$reindexed      = [4 => ['traffic_in' => 700]];
		$time_reindexed = [4 => 1900];
		$item           = [];
		$currenttime    = 0;

		$this->assertEqualsWithDelta(
			600 / 900,
			thold_get_currentval($accepted, $reindexed, $time_reindexed, $item, $currenttime),
			1.0e-9
		);

		$floored                  = $accepted;
		$floored['rrd_heartbeat'] = 120;
		$time_reindexed[4]        = 1300;
		$this->assertEqualsWithDelta(
			2,
			thold_get_currentval($floored, $reindexed, $time_reindexed, $item, $currenttime),
			1.0e-9
		);

		$time_reindexed[4] = 1700;
		$this->assertSame('', thold_get_currentval($floored, $reindexed, $time_reindexed, $item, $currenttime));
	}

	/**
	 * @return void
	 */
	public function testNonNumericSampleTimeUsesTheRrdStep(): void {
		$thold          = $this->threshold();
		$reindexed      = [4 => ['traffic_in' => 700]];
		$time_reindexed = [4 => 'U'];
		$item           = [];
		$currenttime    = 0;

		$this->assertEqualsWithDelta(2, thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testFirstAbsoluteSampleUsesTheValidatedPollerInterval(): void {
		CactiStubs::$configOptions['poller_interval'] = 300;
		$thold = $this->threshold([
			'data_source_type_id' => self::ABSOLUTE,
			'lasttime'            => 0,
			'rrd_step'            => 0,
		]);

		$this->assertEqualsWithDelta(2, $this->currentValue($thold, 600), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testOutOfOrderCounterAndDeriveSamplesAreUnknown(): void {
		foreach ([self::COUNTER, self::DERIVE] as $type) {
			$thold          = $this->threshold(['data_source_type_id' => $type, 'lasttime' => 1700000400]);
			$reindexed      = [4 => ['traffic_in' => 700]];
			$time_reindexed = [4 => 1700000300];
			$item           = [];
			$currenttime    = 0;

			$this->assertSame('', thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime));
		}

		$this->assertSame(
			['lasttime' => 1700000300, 'oldvalue' => 700],
			thold_sample_persistence($thold, ['traffic_in' => 700], 1700000300)
		);
		$this->assertSame(
			['lasttime' => 1700000400, 'oldvalue' => 100],
			thold_sample_persistence($thold, ['traffic_in' => 700], 1700000400)
		);
		$this->assertSame([
			'sample_row' => "(9, 1, '', FROM_UNIXTIME(1700000300), '700')",
			'status_row' => null,
		], thold_polling_sample_row($thold, ['traffic_in' => 700], '', 1700000300));

		CactiStubs::reset();
		$this->assertTrue(thold_daemon_persist_sample($thold, ['traffic_in' => 700], '', 1700000300));
		$call = end(CactiStubs::$calls);
		$this->assertSame([1, '', 1700000300, 700, 9], $call['params']);

		$reanchored     = $this->threshold(['lasttime' => 1700000300, 'oldvalue' => 700]);
		$reindexed      = [4 => ['traffic_in' => 1000]];
		$time_reindexed = [4 => 1700000600];
		$item           = [];
		$currenttime    = 0;
		$this->assertEqualsWithDelta(
			1,
			thold_get_currentval($reanchored, $reindexed, $time_reindexed, $item, $currenttime),
			1.0e-9
		);
	}

	/**
	 * @return void
	 */
	public function testDeriveWithAnInvalidPriorValueIsUnknown(): void {
		$thold = $this->threshold([
			'data_source_type_id' => self::DERIVE,
			'oldvalue'            => 'U',
		]);

		$this->assertSame('', $this->currentValue($thold, 700));
	}

	/**
	 * @return void
	 */
	public function testDaemonPersistsThePairWithBoundParameters(): void {
		$thold = $this->threshold(['lasttime' => 1000, 'oldvalue' => 100]);

		$this->assertTrue(thold_daemon_persist_sample($thold, [], '', 1300));
		$call = end(CactiStubs::$calls);
		$this->assertStringContainsString('lasttime = FROM_UNIXTIME(?)', $call['sql']);
		$this->assertSame([1, '', 1000, 100, 9], $call['params']);

		CactiStubs::reset();
		$this->assertTrue(thold_daemon_persist_sample($thold, ['traffic_in' => 700], 2, 1600));
		$call = end(CactiStubs::$calls);
		$this->assertSame([1, 2, 1600, 700, 9], $call['params']);
	}

	/**
	 * @return void
	 */
	public function testDaemonPropagatesPersistenceFailure(): void {
		CactiStubs::willReturn('db_execute_prepared', false);
		$this->assertFalse(thold_daemon_persist_sample(
			$this->threshold(['lasttime' => 1000]),
			[],
			'',
			1300
		));

		CactiStubs::reset();
		CactiStubs::willReturn('db_execute_prepared', false);
		$this->assertFalse(thold_daemon_persist_sample(
			$this->threshold(['lasttime' => 0]),
			[],
			'',
			1300
		));
	}

	/**
	 * @return void
	 */
	public function testNeverSampledThresholdLeavesTheTimestampPairUntouched(): void {
		$thold = $this->threshold(['lasttime' => 0, 'oldvalue' => null]);

		$this->assertSame(
			['lasttime' => 0, 'oldvalue' => null],
			thold_sample_persistence($thold, ['traffic_in' => 'U'], 1300)
		);
		$this->assertTrue(thold_daemon_persist_sample($thold, ['traffic_in' => 'U'], '', 1300));
		$call = end(CactiStubs::$calls);
		$this->assertStringNotContainsString('FROM_UNIXTIME', $call['sql']);
		$this->assertStringNotContainsString('oldvalue', $call['sql']);
		$this->assertSame([1, '', 9], $call['params']);

		CactiStubs::reset();
		$this->assertSame(
			['sample_row' => null, 'status_row' => ['id' => 9, 'tcheck' => 1, 'lastread' => '']],
			thold_polling_sample_row($thold, ['traffic_in' => 'U'], '', 1300)
		);
		$this->assertSame([], CactiStubs::$calls);
	}

	/**
	 * @return void
	 */
	public function testPollerBuildsTheSamePersistedPair(): void {
		$thold = $this->threshold(['lasttime' => 1000, 'oldvalue' => 100]);

		$this->assertSame([
			'sample_row' => "(9, 1, '2', FROM_UNIXTIME(1000), '100')",
			'status_row' => null,
		], thold_polling_sample_row($thold, [], 2, 1300));
		$this->assertSame([
			'sample_row' => "(9, 1, '2', FROM_UNIXTIME(1600), '700')",
			'status_row' => null,
		], thold_polling_sample_row($thold, ['traffic_in' => 700], 2, 1600));
		$this->assertSame([
			'sample_row' => "(9, 1, '2', FROM_UNIXTIME(1000), '')",
			'status_row' => null,
		], thold_polling_sample_row($this->threshold(['lasttime' => 1000, 'oldvalue' => null]), [], 2, 1300));
	}

	/**
	 * @return void
	 */
	public function testMissingPersistenceKeysFailClosed(): void {
		$this->assertSame(
			['lasttime' => 0, 'oldvalue' => null],
			thold_sample_persistence([], ['traffic_in' => 700], 1600)
		);
		$this->assertFalse(thold_daemon_persist_sample([], ['traffic_in' => 700], 2, 1600));
		$this->assertSame(
			['sample_row' => null, 'status_row' => null],
			thold_polling_sample_row([], ['traffic_in' => 700], 2, 1600)
		);
	}

	/**
	 * @return void
	 */
	public function testUnavailableSampleLogsOnlyOnTheStateTransition(): void {
		$thold = $this->threshold([
			'lastread'  => 12,
			'name_cache' => 'Traffic in',
		]);

		thold_polling_sample_row($thold, [], '', 1700000300);
		$this->assertCount(1, CactiStubs::$log);
		$log_call = CactiStubs::callsTo('cacti_log')[0];
		$this->assertSame('THOLD', $log_call['params'][2]);
		$this->assertSame(POLLER_VERBOSITY_MEDIUM, $log_call['params'][3]);

		$thold['lastread'] = '';
		thold_polling_sample_row($thold, [], '', 1700000600);
		$this->assertCount(1, CactiStubs::$log);
	}

	/**
	 * @return void
	 */
	public function testPollerCleanupRunsForEitherBatchType(): void {
		thold_polling_cleanup(false);
		$this->assertSame([], CactiStubs::$calls);

		CactiStubs::willReturn('db_affected_rows', 1);
		thold_polling_cleanup(true);
		$this->assertSame('db_execute_prepared', CactiStubs::$calls[0]['fn']);
		$this->assertStringContainsString('local_data_id = 0', CactiStubs::$calls[0]['sql']);
		$this->assertArrayHasKey('time_last_change_thold', CactiStubs::$configOptions);
	}
}
