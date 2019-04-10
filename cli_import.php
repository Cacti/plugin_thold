<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2019 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting('E_ALL');

include(dirname(__FILE__) . '/../../include/cli_check.php');
include_once($config['base_path'] . '/lib/xml.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

/* set the defaults */
$force    = false;
$debug    = false;
$filename = false;
$errors   = 0;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--debug':
			case '-d':
				$debug = true;
				break;
			case '--filename':
			case '-f':
				$filename = $value;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			exit(-1);
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(-1);
		}
	}
}

/* validate filename set */
if ($filename === false) {
	print 'ERROR: You must specify a filename using either the --filename of -f options.' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-1);
}

/* validate exists */
if (!file_exists($filename)) {
	print 'ERROR: The filename \'' . $filename . '\' does not exist.' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-1);
}

/* validate readable */
if (!is_readable($filename)) {
	print 'ERROR: The filename \'' . $filename . '\' is not readable.' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-1);
}

/* validate good xml */
$data = file_get_contents($filename);

$xml  = xml2array($data);

if (!is_array($xml) || !cacti_sizeof($xml)) {
	print 'ERROR: The filename \'' . $filename . '\' is not a valid thold XML file.' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-1);
}

$return_data = thold_template_import($data);

if (sizeof($return_data) && isset($return_data['success'])) {
	foreach($return_data['success'] as $message) {
		print 'NOTE: ' . $message . PHP_EOL;
	}
}

if (isset($return_data['errors'])) {
	foreach($return_data['errors'] as $error) {
		print 'ERROR: ' . $error . PHP_EOL;
	}
}

if (isset($return_data['failure'])) {
	foreach($return_data['failure'] as $error) {
		print 'ERROR: ' . $error . PHP_EOL;
	}

	$errors++;
}

if ($errors == 0) {
	$message = 'NOTE: Import of Threshold Template file \'' . $filename . '\' succeeded.';

	cacti_log($message, false, 'THOLD');

	print $message . PHP_EOL;

	exit(0);
} else {
	$message = 'ERROR: Import of Threshold Template file \'' . $filename . '\' failed. There were \'' . $errors . '\' encountered.';

	cacti_log($message, false, 'THOLD');

	print $message . PHP_EOL;

	exit(1);
}

function display_version() {
	global $config;

	if (!function_exists('plugin_thold_version')) {
		include_once($config['base_path'] . '/plugins/thold/setup.php');
	}

	$info = plugin_thold_version();
	print 'Threshold Template Import Utility, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}


/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL;

	print 'usage: cli_import.php --filename=N' . PHP_EOL . PHP_EOL;

	print 'Simple CLI command to import a threshold template.' . PHP_EOL . PHP_EOL;
	print '--filename=N  - A valid threshold template exported using the thold plugin.' . PHP_EOL . PHP_EOL;
}

