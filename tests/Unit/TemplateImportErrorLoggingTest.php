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

/** Regression for template error logs reusing a previous success message. */
final class TemplateImportErrorLoggingTest extends TestCase {
	public function testEachImportErrorUsesItsOwnMessage(): void {
		$source = file_get_contents(dirname(__DIR__, 2) . '/thold_templates.php');
		preg_match('/foreach \(\$return_data\[\'errors\'\] as \$error\) \{(.*?)\n\t\t\}/s', $source, $loop);
		$this->assertNotEmpty($loop);
		$return_data = ['errors' => ['missing field', 'invalid template']];
		$message = 'previous success';
		$debug_data = [];
		// Run the production loop body using the existing logging recorder.
		foreach ($return_data['errors'] as $error) {
			eval($loop[1]);
		}
		$this->assertSame([
			'NOTE: Template Import Error!.  Message: missing field',
			'NOTE: Template Import Error!.  Message: invalid template',
		], CactiStubs::$log);
	}
}
