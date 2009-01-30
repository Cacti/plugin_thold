<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
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


/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting('E_ALL');
$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

if (file_exists('./include/global.php')) {
	include('./include/global.php');
} else {
	include('./include/config.php');
}

include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
	case "-auto":
		thold_cli_autocreate_host ($value);
		exit;
	case "-h":
	case "-v":
	case "--version":
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

function thold_cli_autocreate_host ($id) {
	$id = intval($id);
	if ($id < 1)
		return;

	echo "Auto Creating Thresholds for Host #$id\n";
	autocreate($id);
	if (isset($_SESSION['thold_message'])) {
		echo strip_tags(str_replace(array('<br>', 'Created threshold'), array("\n", '     Created threshold'), $_SESSION['thold_message']));
	}
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Threshold Command Line Interface\n";
	print "usage: cli_thresholds.php [-d] [-h] [--help] [-v] [--version] [-auto=#]\n";
	print "-auto=#       - Auto Create all thresholds for this host id using current templates\n";
	print "-h --help     - Display this help message\n";
}
