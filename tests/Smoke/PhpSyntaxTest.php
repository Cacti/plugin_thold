<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Smoke test: verify PHP syntax of all plugin source files.
 *
 * Catches parse errors introduced by edits before they reach a running
 * Cacti instance.
 */

$phpBin = PHP_BINARY;
$root   = realpath(__DIR__ . '/../..');

// Collect all plugin PHP files, excluding vendor and test directories.
$files = [];
$iter  = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iter as $file) {
	if ($file->getExtension() !== 'php') {
		continue;
	}

	$rel = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);

	if (strncmp($rel, 'vendor' . DIRECTORY_SEPARATOR, strlen('vendor' . DIRECTORY_SEPARATOR)) === 0) {
		continue;
	}

	if (strncmp($rel, 'tests' . DIRECTORY_SEPARATOR, strlen('tests' . DIRECTORY_SEPARATOR)) === 0) {
		continue;
	}

	$files[] = $file->getPathname();
}

sort($files);

it('all plugin PHP files parse without errors', function () use ($phpBin, $files) {
	$failures = [];

	foreach ($files as $file) {
		// bare escapeshellarg(): cacti_escapeshellarg() requires the Cacti bootstrap; $file is a local path, not user input
		exec(escapeshellarg($phpBin) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code); // nosemgrep: php.lang.security.exec-use.exec-use -- lint check only; $file is a glob-returned server-local path with no user input
		if ($code !== 0) {
			$failures[] = basename($file) . ': ' . implode(' ', $output);
		}
		$output = [];
	}

	expect($failures)->toBe([]);
});
