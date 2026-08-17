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
	}

	/**
	 * @return array<string, mixed>
	 */
	private function threshold(array $overrides = array()) {
		return $overrides + array(
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
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function device(array $overrides = array()) {
		return $overrides + array(
			'description' => 'router1',
			'hostname'    => '10.0.0.1',
			'location'    => 'rack 4',
			'site_id'     => 1,
		);
	}

	/**
	 * Substitute tags into $text.
	 *
	 * @param string               $text
	 * @param array<string, mixed> $thold
	 * @param array<string, mixed> $device
	 * @param bool                 $shell
	 * @param mixed                $currentval
	 *
	 * @return string
	 */
	private function substitute($text, array $thold, array $device, $shell, $currentval = 42) {
		return thold_replace_threshold_tags($text, $thold, $device, $currentval, 7, 'traffic_in', $shell);
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function deviceDerivedTagProvider() {
		return array(
			'description' => array('<DESCRIPTION>', 'description', 'device'),
			'hostname'    => array('<HOSTNAME>', 'hostname', 'device'),
			'location'    => array('<LOCATION>', 'location', 'device'),
			'notes'       => array('<NOTES>', 'notes', 'threshold'),
			'device note' => array('<DEVICENOTE>', 'dnotes', 'threshold'),
			'external id' => array('<EXTERNALID>', 'external_id', 'threshold'),
			'name'        => array('<THRESHOLDNAME>', 'name_cache', 'threshold'),
		);
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
		$thold   = $this->threshold($source === 'threshold' ? array($column => $payload) : array());
		$device  = $this->device($source === 'device' ? array($column => $payload) : array());

		$result = $this->substitute("/usr/bin/alert $tag", $thold, $device, true);

		$this->assertStringContainsString(escapeshellarg($payload), $result);
		$this->assertStringNotContainsString("alert ; touch", $result);
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
		$thold  = $this->threshold($source === 'threshold' ? array($column => "O'Brien") : array());
		$device = $this->device($source === 'device' ? array($column => "O'Brien") : array());

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
		CactiStub::willReturn('db_fetch_cell_prepared', '$(id)');

		$result = $this->substitute('/usr/bin/alert <SITE>', $this->threshold(), $this->device(), true);

		$this->assertStringContainsString(escapeshellarg('$(id)'), $result);
	}

	/**
	 * @return void
	 */
	public function testSiteFallsBackToDefaultWhenTheDeviceHasNoSite(): void {
		CactiStub::willReturn('db_fetch_cell_prepared', '');

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
	 * @return void
	 */
	public function testGraphAndThresholdIdentifiersAreSubstituted(): void {
		$result = $this->substitute('<GRAPHID>/<THOLD_ID>', $this->threshold(array('id' => 5)), $this->device(), false);

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
		$result = $this->substitute('[<HI>][<LOW>][<TRIGGER>]', $this->threshold(array('thold_type' => 2)), $this->device(), false);

		$this->assertSame('[80][20][2]', $result);
	}

	/**
	 * A baseline threshold has neither static nor time bounds, so the tags
	 * resolve to empty rather than being left in the output.
	 *
	 * @return void
	 */
	public function testBaselineThresholdClearsTheBoundTags(): void {
		$result = $this->substitute('[<HI>][<LOW>][<TRIGGER>][<DURATION>]', $this->threshold(array('thold_type' => 1)), $this->device(), false);

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
		CactiStub::$configOptions['base_url'] = 'http://cacti.example.org';

		$result = $this->substitute('<URL>', $this->threshold(), $this->device(), false);

		$this->assertStringContainsString('graph.php?local_graph_id=7', $result);
	}

	/**
	 * @return void
	 */
	public function testTextWithoutTagsIsReturnedUnchanged(): void {
		$result = $this->substitute('nothing to replace', $this->threshold(), $this->device(), true);

		$this->assertSame('nothing to replace', $result);
	}
}
