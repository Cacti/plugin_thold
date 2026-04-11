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

describe('prepared statement safety patterns in thold', function () {
	it('does not introduce single-line interpolated db_* calls in hardened files', function () {
		$targetFiles = array(
			'notify_lists.php',
			'notify_queue.php',
			'poller_thold.php',
			'setup.php',
			'thold.php',
			'thold_graph.php',
		);

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
				$hasPreparedCall        = preg_match($preparedPattern, $line) === 1;

				expect($hasInterpolatedRawCall && !$hasPreparedCall)->toBeFalse(
					sprintf('File %s contains an interpolated raw db_* call at line %d', $relativeFile, $lineNumber + 1)
				);
			}
		}
	});
});
