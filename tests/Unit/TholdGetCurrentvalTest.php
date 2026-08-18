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
	public function testCounterWithNoPreviousReadingYieldsZero(): void {
		$thold = $this->threshold(['lasttime' => 0, 'oldvalue' => '']);

		$this->assertSame(0, $this->currentValue($thold, 600));
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
	 * @return array<string, array{0: int|string, 1: int}>
	 */
	public static function emptyMaximumProvider() {
		return [
			'integer zero'     => [0, 1007500000000],
			'empty string'     => ['', 1008500000100],
			'explicit maximum' => [20000000, 1007500000000],
		];
	}

	/**
	 * @dataProvider emptyMaximumProvider
	 *
	 * @param int|string $maximum
	 * @param int        $reading
	 *
	 * @return void
	 */
	public function testMultiIntervalDeltaScalesTheResetGuard($maximum, $reading): void {
		$thold          = $this->threshold(['lasttime' => 1000, 'oldvalue' => 1000000000000, 'rrd_maximum' => $maximum]);
		$reindexed      = [4 => ['traffic_in' => $reading]];
		$time_reindexed = [4 => 1600];
		$item           = [];
		$currenttime    = 0;

		$expected = $maximum === '' ? $reading / 600 : 12500000;
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
		$thold = $this->threshold(['rrd_step' => $rrd_step]);

		$this->assertEqualsWithDelta(2, $this->currentValue($thold, 700), 1.0e-9);
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
	public function testOutOfOrderCounterAndDeriveSamplesAreUnknown(): void {
		foreach ([self::COUNTER, self::DERIVE] as $type) {
			$thold          = $this->threshold(['data_source_type_id' => $type, 'lasttime' => 1700000400]);
			$reindexed      = [4 => ['traffic_in' => 700]];
			$time_reindexed = [4 => 1700000300];
			$item           = [];
			$currenttime    = 0;

			$this->assertSame('', thold_get_currentval($thold, $reindexed, $time_reindexed, $item, $currenttime));
		}
	}

	/**
	 * @return void
	 */
	public function testDaemonPersistsThePairWithBoundParameters(): void {
		$thold = $this->threshold(['lasttime' => 1000, 'oldvalue' => 100]);

		$this->assertTrue(thold_daemon_persist_sample($thold, [], '', 1300));
		$call = end(CactiStubs::$calls);
		$this->assertStringContainsString('lasttime = FROM_UNIXTIME(?)', $call['sql']);
		$this->assertSame(['', 1000, 100, 9], $call['params']);

		CactiStubs::reset();
		$this->assertTrue(thold_daemon_persist_sample($thold, ['traffic_in' => 700], 2, 1600));
		$call = end(CactiStubs::$calls);
		$this->assertSame([2, 1600, 700, 9], $call['params']);
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
		$this->assertSame(['', 9], $call['params']);

		CactiStubs::reset();
		$this->assertSame(
			['sample_row' => null, 'status_row' => "(9, 1, '')"],
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
	}

	/**
	 * @return void
	 */
	public function testMissingPersistenceKeysFailClosed(): void {
		$this->assertSame(
			['lasttime' => 0, 'oldvalue' => null],
			thold_sample_persistence([], ['traffic_in' => 700], 1600)
		);
	}
}
