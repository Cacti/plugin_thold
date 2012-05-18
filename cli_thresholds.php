<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2011 The Cacti Group                                      |
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

include('./include/global.php');

include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

/* set the defaults */
$force     = false;
$debug     = false;
$gtemplate = 0;
$ttemplate = 0;
$gids      = "";

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
	case "-auto":
		thold_cli_autocreate_host ($value);
		exit;
	case "--debug":
	case "-d":
		$debug = true;
		break;
	case "--force":
	case "-f":
		$force = true;
		break;
	case "--graph-template":
	case "-gt":
		$gtemplate = $value;
		break;
	case "--thold-template":
	case "-tt":
		$ttemplate = $value;
		break;
	case "--graph-ids":
	case "-ids":
		$gids = $value;
		break;
	case "-h":
	case "-v":
	case "--version":
	case "--help":
		display_help();
		exit(-1);
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit(-1);
	}
}

/* perform some checks */
if ($ttemplate == 0 && $gtemplate == 0) {
	print "ERROR: You must choose either --auto-create or a combination of Graph and Thold Templates\n\n";
	display_help();
	exit(-1);
}

/* validate values */
if (!is_numeric($ttemplate)) {
	print "ERROR: The Thold Template must be numeric.\n\n";
	display_help();
	exit(-1);
}

/* validate values */
if (!is_numeric($gtemplate)) {
	print "ERROR: The Graph Template must be numeric.\n\n";
	display_help();
	exit(-1);
}

/* validate graph ids */
if (strlen($gids)) {
	$gids = explode(" ", $gids);
	foreach($gids as $id) {
		if (!is_numeric($id)) {
			print "ERROR: The Graph ID '$id' is NOT numeric.  All Graph ID's must be numeric\n";
			display_help();
			exit(-1);
		}
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
	print "usage: cli_thresholds.php --auto-create=N | --graph-template=N [--thold-template=N] [--graph-ids='N1 N2 ...']\n\n";
	print "There are two usage methods:\n\n";
	print "The first requires you to specify the host id of the device and all existing threshold templates\n";
	print "are applied to hosts.\n\n";
	print "The second requires you to specify a minimum of a Graph Template ID, and optionally the\n";
	print "Threshold Template and Graph ID's of the Graphs to be impacted.\n\n";
	print "--auto-create=N         - Auto Create all thresholds for this host id using current templates\n";
	print "--graph-template=N      - The Graph Template to create thresholds for\n";
	print "--thold-template=N      - The Threshold Template to use for creating thresholds\n";
	print "--graph-ids='N1 N2 ...' - The Threshold Template to use for creating thresholds\n";
	print "-h --help -V --version  - Display this help message\n\n";
}
