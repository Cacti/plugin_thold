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
 * Tag substitution in thold_replace_threshold_tags().
 *
 * The threshold trigger commands are admin-configured templates, but the
 * values substituted into them come from the device table and from user-edited
 * notes. In $shell mode every such value must be quoted so it cannot terminate
 * the command and start another. Email and HTML callers must be unaffected.
 */
final class TholdReplaceThresholdTagsTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');

		// Defines $thold_types, which the <THOLDTYPE> substitution reads. A prior
		// test class may have already required this file once (require_once is
		// keyed by real path across the whole process, so a second require_once
		// here is a no-op) - force a fresh include when the global didn't survive.
		self::loadPluginSource('includes/arrays.php');

		if (empty($GLOBALS['thold_types'])) {
			include dirname(__DIR__, 2) . '/includes/arrays.php';

			$GLOBALS['thold_types'] = $thold_types;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function threshold(array $overrides = []) {
		return $overrides + [
			'id'                 => 1,
			'name_cache'         => 'CPU',
			'notes'              => '',
			'dnotes'             => '',
			'external_id'        => '',
			'thold_type'         => 0,
			'thold_hi'           => 90,
			'thold_low'          => 10,
			'thold_fail_trigger' => 3,
			'time_hi'            => 80,
			'time_low'           => 20,
			'time_fail_trigger'  => 2,
			'time_fail_length'   => 300,
			'local_data_id'      => 4,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function device(array $overrides = []) {
		return $overrides + [
			'description' => 'router1',
			'hostname'    => '10.0.0.1',
			'location'    => 'rack 4',
			'site_id'     => 1,
		];
	}

	/**
	 * Substitute tags into $text.
	 *
	 * @param string               $text
	 * @param array<string, mixed> $thold
	 * @param array<string, mixed> $device
	 * @param bool                 $shell
	 * @param mixed                $currentval
	 * @param string               $dataSourceName
	 *
	 * @return string
	 */
	private function substitute($text, array $thold, array $device, $shell, $currentval = 42, $dataSourceName = 'traffic_in') {
		return thold_replace_threshold_tags($text, $thold, $device, $currentval, 7, $dataSourceName, $shell);
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function deviceDerivedTagProvider() {
		return [
			'description' => ['<DESCRIPTION>', 'description', 'device'],
			'hostname'    => ['<HOSTNAME>', 'hostname', 'device'],
			'location'    => ['<LOCATION>', 'location', 'device'],
			'notes'       => ['<NOTES>', 'notes', 'threshold'],
			'device note' => ['<DEVICENOTE>', 'dnotes', 'threshold'],
			'external id' => ['<EXTERNALID>', 'external_id', 'threshold'],
			'name'        => ['<THRESHOLDNAME>', 'name_cache', 'threshold'],
		];
	}

	/**
	 * @dataProvider deviceDerivedTagProvider
	 *
	 * @param string $tag
	 * @param string $column
	 * @param string $source
	 *
	 * @return void
	 */
	public function testShellModeQuotesEveryDeviceDerivedTag($tag, $column, $source): void {
		$payload = '; touch /tmp/pwned';
		$thold   = $this->threshold($source === 'threshold' ? [$column => $payload] : []);
		$device  = $this->device($source === 'device' ? [$column => $payload] : []);

		$result = $this->substitute("/usr/bin/alert $tag", $thold, $device, true);

		$this->assertStringContainsString(escapeshellarg($payload), $result);
		$this->assertStringNotContainsString('alert ; touch', $result);
	}

	/**
	 * A device-controlled value (e.g. description) can contain another tag's
	 * literal placeholder text (e.g. "<HOSTNAME>"). Every value is
	 * substituted in a single strtr() pass over the original text, so that
	 * literal text is never re-scanned and substituted a second time - it
	 * can't land a later value's shell metacharacters outside of its own
	 * quoting.
	 *
	 * @return void
	 */
	public function testShellModeDoesNotReSubstituteATagLiteralInsideAnotherValue(): void {
		$thold  = $this->threshold();
		$device = $this->device([
			'description' => '<HOSTNAME>',
			'hostname'    => '; touch /tmp/pwned',
		]);

		$result = $this->substitute('/usr/bin/alert <DESCRIPTION> <HOSTNAME>', $thold, $device, true);

		$this->assertStringContainsString(escapeshellarg('<HOSTNAME>'), $result);
		$this->assertStringContainsString(escapeshellarg('; touch /tmp/pwned'), $result);
		$this->assertStringNotContainsString("''; touch", $result);
	}

	/**
	 * @dataProvider deviceDerivedTagProvider
	 *
	 * @param string $tag
	 * @param string $column
	 * @param string $source
	 *
	 * @return void
	 */
	public function testEmailModeLeavesDeviceDerivedTagsUnquoted($tag, $column, $source): void {
		$thold  = $this->threshold($source === 'threshold' ? [$column => "O'Brien"] : []);
		$device = $this->device($source === 'device' ? [$column => "O'Brien"] : []);

		$result = $this->substitute("Alert on $tag", $thold, $device, false);

		$this->assertStringContainsString("O'Brien", $result);
		$this->assertStringNotContainsString("'O'\\''Brien'", $result);
	}

	/**
	 * The site name comes from the sites table, which an operator edits, so it
	 * needs the same treatment as the device columns.
	 *
	 * @return void
	 */
	public function testShellModeQuotesTheSiteName(): void {
		CactiStubs::willReturn('db_fetch_cell_prepared', '$(id)');

		$result = $this->substitute('/usr/bin/alert <SITE>', $this->threshold(), $this->device(), true);

		$this->assertStringContainsString(escapeshellarg('$(id)'), $result);
	}

	/**
	 * @return void
	 */
	public function testSiteFallsBackToDefaultWhenTheDeviceHasNoSite(): void {
		CactiStubs::willReturn('db_fetch_cell_prepared', '');

		$result = $this->substitute('site=<SITE>', $this->threshold(), $this->device(), false);

		$this->assertSame('site=Default', $result);
	}

	/**
	 * The current reading is an RRD value rather than a number in every case;
	 * it must not be able to extend the command line either.
	 *
	 * @return void
	 */
	public function testShellModeQuotesTheCurrentValue(): void {
		$result = $this->substitute('/usr/bin/alert <CURRENTVALUE>', $this->threshold(), $this->device(), true, '; id');

		$this->assertStringContainsString(escapeshellarg('; id'), $result);
		$this->assertStringNotContainsString('alert ; id', $result);
	}

	/**
	 * @return void
	 */
	public function testEmailModeLeavesTheCurrentValueUnquoted(): void {
		$result = $this->substitute('value=<CURRENTVALUE>', $this->threshold(), $this->device(), false, 42);

		$this->assertSame('value=42', $result);
	}

	/**
	 * The data source name is read straight from the data source table; a
	 * name containing shell metacharacters must not be able to break out of
	 * the configured trigger command.
	 *
	 * @return void
	 */
	public function testShellModeQuotesTheDataSourceName(): void {
		$result = $this->substitute('/usr/bin/alert <DSNAME>', $this->threshold(), $this->device(), true, 42, '; touch /tmp/pwned');

		$this->assertStringContainsString(escapeshellarg('; touch /tmp/pwned'), $result);
		$this->assertStringNotContainsString('alert ; touch', $result);
	}

	/**
	 * @return void
	 */
	public function testEmailModeLeavesTheDataSourceNameUnquoted(): void {
		$result = $this->substitute('ds=<DSNAME>', $this->threshold(), $this->device(), false, 42, "O'Brien");

		$this->assertSame("ds=O'Brien", $result);
	}

	/**
	 * @return void
	 */
	public function testGraphAndThresholdIdentifiersAreSubstituted(): void {
		$result = $this->substitute('<GRAPHID>/<THOLD_ID>', $this->threshold(['id' => 5]), $this->device(), false);

		$this->assertSame('7/5', $result);
	}

	/**
	 * @return void
	 */
	public function testStaticThresholdBoundsAreSubstituted(): void {
		$result = $this->substitute('<HI>/<LOW>/<TRIGGER>', $this->threshold(), $this->device(), false);

		$this->assertSame('90/10/3', $result);
	}

	/**
	 * A time-based threshold reports its own bounds and a duration rather than
	 * the static ones.
	 *
	 * @return void
	 */
	public function testTimeBasedThresholdSubstitutesTheTimeBounds(): void {
		$result = $this->substitute('[<HI>][<LOW>][<TRIGGER>]', $this->threshold(['thold_type' => 2]), $this->device(), false);

		$this->assertSame('[80][20][2]', $result);
	}

	/**
	 * A baseline threshold has neither static nor time bounds, so the tags
	 * resolve to empty rather than being left in the output.
	 *
	 * @return void
	 */
	public function testBaselineThresholdClearsTheBoundTags(): void {
		$result = $this->substitute('[<HI>][<LOW>][<TRIGGER>][<DURATION>]', $this->threshold(['thold_type' => 1]), $this->device(), false);

		$this->assertSame('[][][][]', $result);
	}

	/**
	 * @return void
	 */
	public function testStaticThresholdHasNoDuration(): void {
		$result = $this->substitute('[<DURATION>]', $this->threshold(), $this->device(), false);

		$this->assertSame('[]', $result);
	}

	/**
	 * @return void
	 */
	public function testUrlTagRendersALinkToTheGraph(): void {
		CactiStubs::$configOptions['base_url'] = 'http://cacti.example.org';

		$result = $this->substitute('<URL>', $this->threshold(), $this->device(), false);

		$this->assertStringContainsString('graph.php?local_graph_id=7', $result);
	}

	/**
	 * The rendered <a href='...'>...</a> markup contains literal single
	 * quotes, so in $shell mode the whole substituted value must be quoted
	 * as one token or those quotes would terminate the command early.
	 *
	 * @return void
	 */
	public function testShellModeQuotesTheUrlTag(): void {
		CactiStubs::$configOptions['base_url'] = 'http://cacti.example.org';

		$result = $this->substitute('/usr/bin/alert <URL>', $this->threshold(), $this->device(), true);

		$this->assertStringContainsString(
			escapeshellarg("<a href='http://cacti.example.org/graph.php?local_graph_id=7'>" . __('Link to Graph in Cacti', 'thold') . '</a>'),
			$result
		);
		$this->assertStringNotContainsString("alert <a href='", $result);
	}

	/**
	 * @return void
	 */
	public function testThresholdTypeNameIsSubstituted(): void {
		$result = $this->substitute('<THOLDTYPE>', $this->threshold(), $this->device(), false);

		$this->assertSame('High / Low', $result);
	}

	/**
	 * @return void
	 */
	public function testUnknownThresholdTypeLeavesTheTagInPlace(): void {
		$result = $this->substitute('<THOLDTYPE>', $this->threshold(['thold_type' => 99]), $this->device(), false);

		$this->assertSame('<THOLDTYPE>', $result);
	}

	/**
	 * @return void
	 */
	public function testTextWithoutTagsIsReturnedUnchanged(): void {
		$result = $this->substitute('nothing to replace', $this->threshold(), $this->device(), true);

		$this->assertSame('nothing to replace', $result);
	}
}
