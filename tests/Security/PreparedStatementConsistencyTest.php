<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify files in the hardening stack use prepared helpers when they execute
 * obviously variable-interpolated SQL on a single line.
 */

it('raw-interpolated pattern matches known unsafe db call strings', function () {
	$pattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(\s*(["\']).*\$[A-Za-z_{]/';

	$unsafe = [
		'db_execute("SELECT * FROM thold WHERE id=$id")',
		"db_fetch_row('SELECT id FROM thold WHERE name=$name')",
		'db_fetch_cell("SELECT value FROM thold WHERE host_id=$host_id")',
		'db_fetch_assoc("SELECT * FROM thold WHERE template_id={$template_id}")',
	];

	foreach ($unsafe as $line) {
		expect(preg_match($pattern, $line))->toBe(1, "Expected unsafe pattern match on: {$line}");
	}
});

it('raw-interpolated pattern does not match safe db call strings', function () {
	$pattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(\s*(["\']).*\$[A-Za-z_{]/';

	$safe = [
		'db_execute_prepared("SELECT * FROM thold WHERE id = ?", array($id))',
		'db_fetch_row_prepared("SELECT id FROM thold WHERE name = ?", array($name))',
		'db_execute("SELECT * FROM thold WHERE id = ?")',
		'db_execute("SELECT COUNT(*) FROM thold")',
	];

	foreach ($safe as $line) {
		expect(preg_match($pattern, $line))->toBe(0, "Expected no pattern match on safe call: {$line}");
	}
});

it('comment-skip logic excludes full-line comments from interpolation checks', function () {
	$commentLines = [
		'// db_execute("SELECT * FROM thold WHERE id=$id")',
		'    // db_execute("SELECT * FROM thold WHERE id=$id")',
		' * db_execute("SELECT * FROM thold WHERE id=$id")',
		'  * example db_fetch_row("SELECT id FROM thold WHERE host=$host")',
		'# db_execute("SELECT * FROM thold WHERE id=$id")',
	];

	foreach ($commentLines as $line) {
		$trimmed  = ltrim($line);
		$isSkipped = strpos($trimmed, '//') === 0
			|| strpos($trimmed, '*') === 0
			|| strpos($trimmed, '#') === 0;

		expect($isSkipped)->toBeTrue("Full-line comment should be skipped: {$line}");
	}
});

it('does not introduce single-line interpolated db_* calls in hardened files', function () {
	$targetFiles = [
		'poller_thold.php',
		'setup.php',
		'thold.php',
		'thold_graph.php',
	];

	$rawInterpolatedPattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(\s*(["\']).*\$[A-Za-z_{]/';
	$preparedPattern        = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)_prepared\s*\(/';

	foreach ($targetFiles as $relativeFile) {
		$path = realpath(__DIR__ . '/../../' . $relativeFile);
		expect($path)->not->toBeFalse("Failed to resolve target file path: {$relativeFile}");

		$contents = file_get_contents($path);
		expect($contents)->not->toBeFalse("Failed to read target file: {$relativeFile}");

		$lines = explode("\n", $contents);

		foreach ($lines as $lineNumber => $line) {
			$trimmed = ltrim($line);

			if (strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0 || strpos($trimmed, '#') === 0) {
				continue;
			}

			$hasInterpolatedRawCall = preg_match($rawInterpolatedPattern, $line) === 1;
			$hasPreparedCall        = preg_match($preparedPattern, $line)        === 1;

			expect($hasInterpolatedRawCall && !$hasPreparedCall)->toBeFalse(
				sprintf('File %s contains an interpolated raw db_* call at line %d', $relativeFile, $lineNumber + 1)
			);
		}
	}
});
