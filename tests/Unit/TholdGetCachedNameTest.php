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
 * thold_get_cached_name() resolves a threshold's display name, filling the
 * cache column from the data source description the first time it is needed.
 */
final class TholdGetCachedNameTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return void
	 */
	public function testCachedNameIsReturnedWithoutQueryingTheDatabase(): void {
		$thold = array('name' => '|data_source_description|', 'name_cache' => 'CPU load', 'local_data_id' => 4);

		$this->assertSame('CPU load', thold_get_cached_name($thold));
		$this->assertSame(array(), CactiStub::callsTo('db_fetch_cell_prepared'));
	}

	/**
	 * @return void
	 */
	public function testEmptyCacheIsFilledFromTheDataSourceDescription(): void {
		CactiStub::willReturn('db_fetch_cell_prepared', 'Router - Traffic');

		$thold = array('name' => '|data_source_description|', 'name_cache' => '', 'local_data_id' => 4);

		$this->assertSame('Router - Traffic', thold_get_cached_name($thold));
		$this->assertSame('Router - Traffic', $thold['name_cache']);
	}

	/**
	 * @return void
	 */
	public function testNameIsKeptWhenTheDataSourceHasNoDescription(): void {
		$thold = array('name' => 'Manual name', 'name_cache' => '', 'local_data_id' => 4);

		$this->assertSame('Manual name', thold_get_cached_name($thold));
	}
}
