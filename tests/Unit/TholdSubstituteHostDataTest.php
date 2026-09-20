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
 * thold_substitute_host_data() and thold_substitute_custom_data() resolve
 * |host_*| and |custom_*| tokens from device/data-source-input tables that
 * an operator (not an admin) controls, so $shell mode must escape them.
 */
final class TholdSubstituteHostDataTest extends TestCase {
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

		unset($_SESSION['sess_host_cache_array']);
	}

	/**
	 * A device column already cached from an earlier lookup is returned
	 * straight out of $_SESSION, without hitting the database again.
	 *
	 * @return void
	 */
	public function testCachedHostColumnIsReturnedUnescapedOutsideShellMode(): void {
		CactiStubs::willReturn('db_fetch_row_prepared', ['description' => 'router1', 'hostname' => '10.0.0.1']);

		$this->assertSame('router1', thold_substitute_host_data('|host_description|', '|', '|', 2));

		// The second call must not query again: it is served from the cache
		// populated by the first.
		$this->assertCount(1, CactiStubs::callsTo('db_fetch_row_prepared'));
		$this->assertSame('router1', thold_substitute_host_data('|host_description|', '|', '|', 2));
		$this->assertCount(1, CactiStubs::callsTo('db_fetch_row_prepared'));
	}

	/**
	 * @return void
	 */
	public function testCachedHostColumnIsEscapedInShellMode(): void {
		$malicious = "evil'; touch /tmp/pwned; echo '";
		CactiStubs::willReturn('db_fetch_row_prepared', ['description' => $malicious, 'hostname' => '10.0.0.1']);

		$result = thold_substitute_host_data('|host_description|', '|', '|', 3, true);

		$this->assertSame(escapeshellarg($malicious), $result);
	}

	/**
	 * |host_management_ip| is not a plain device column: it falls through to
	 * the hostname-substitution path rather than the cache-hit return.
	 *
	 * @return void
	 */
	public function testManagementIpFallsThroughToTheHostnameSubstitution(): void {
		CactiStubs::willReturn('db_fetch_row_prepared', ['description' => 'router1', 'hostname' => '10.0.0.1']);

		$this->assertSame('ping 10.0.0.1', thold_substitute_host_data('ping |host_management_ip|', '|', '|', 4));
	}

	/**
	 * @return void
	 */
	public function testManagementIpIsEscapedInShellMode(): void {
		$malicious = "10.0.0.1'; touch /tmp/pwned; echo '";
		CactiStubs::willReturn('db_fetch_row_prepared', ['description' => 'router1', 'hostname' => $malicious]);

		$result = thold_substitute_host_data('ping |host_management_ip|', '|', '|', 5, true);

		$this->assertSame('ping ' . escapeshellarg($malicious), $result);
	}

	/**
	 * The custom_* tokens come from data source input fields (e.g. an SNMP
	 * community string), which is data an operator, not an admin, controls.
	 *
	 * @return void
	 */
	public function testCustomDataValueIsEscapedInShellMode(): void {
		$malicious = "public'; touch /tmp/pwned; echo '";

		CactiStubs::willReturn('db_fetch_assoc', [['id' => 9]]);
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			['name' => 'snmp_community', 'value' => $malicious],
		]);

		$result = thold_substitute_custom_data('alert |custom_snmp_community|', '|', '|', 4, true);

		$this->assertSame('alert ' . escapeshellarg($malicious), $result);
	}

	/**
	 * @return void
	 */
	public function testCustomDataValueIsUnescapedOutsideShellMode(): void {
		CactiStubs::willReturn('db_fetch_assoc', [['id' => 9]]);
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			['name' => 'snmp_community', 'value' => 'public'],
		]);

		$result = thold_substitute_custom_data('alert |custom_snmp_community|', '|', '|', 4, false);

		$this->assertSame('alert public', $result);
	}
}
