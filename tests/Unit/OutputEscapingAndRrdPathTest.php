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
 * A data source RRD path whose file name holds whitespace or quote
 * characters must not be placed in an rrdtool command line, where it would
 * split into several arguments.
 */
final class OutputEscapingAndRrdPathTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function pathProvider(): array {
		return [
			'plain'                    => ['/var/lib/cacti/rra/host_1.rrd', true],
			'space in directory only'  => ['/var/lib/cacti/my rra/host_1.rrd', true],
			'space in file'            => ['/var/lib/cacti/rra/host 1.rrd', false],
			'tab in file'              => ["/var/lib/cacti/rra/host\t1.rrd", false],
			'newline in file'          => ["/var/lib/cacti/rra/host\n1.rrd", false],
			'apostrophe in file'       => ["/var/lib/cacti/rra/a'b.rrd", false],
			'double quote in file'     => ['/var/lib/cacti/rra/a"b.rrd', false],
			'backtick in file'         => ['/var/lib/cacti/rra/a`b.rrd', false],
			'windows path with space'  => ['C:\\rra\\host 1.rrd', false],
			'empty'                    => ['', false],
		];
	}

	/**
	 * @dataProvider pathProvider
	 *
	 * @param string $path     RRD path.
	 * @param bool   $expected Whether the path may be passed to rrdtool.
	 *
	 * @return void
	 */
	public function testPathSafety(string $path, bool $expected): void {
		$this->assertSame($expected, thold_rrd_path_is_safe($path));
	}

	/**
	 * @return void
	 */
	public function testStatisticsSkipsAnUnsafePathWithoutCallingRrdtool(): void {
		CactiStubs::willReturn('db_fetch_cell_prepared', "/var/lib/cacti/rra/a 'b.rrd");

		$this->assertFalse(thold_get_rrd_statistics(4, 1700000300, 1700000000, 'AVERAGE'));
		$this->assertSame([], CactiStubs::callsTo('rrdtool_execute'));
	}

	public function testLastUpdateSkipsAFileNameWithSpaces(): void {
		CactiStubs::willReturn('get_data_source_path', '/var/lib/cacti/rra/router 4.rrd');

		$this->assertSame('', thold_rrd_last(4));
		$this->assertSame([], CactiStubs::callsTo('rrdtool_execute'));
	}

	/**
	 * The data template name is user-editable, so the Template filter must
	 * print it escaped as option text.
	 *
	 * @return void
	 */
	public function testNotifyListTemplateOptionEscapesTheName(): void {
		$source = file_get_contents(__DIR__ . '/../../notify_lists.php');

		$this->assertStringContainsString("'>' . html_escape(\$row['name']) . '</option>'", $source);
		$this->assertStringNotContainsString("'>' . \$row['name'] . '</option>'", $source);
	}
}
