<?php

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

error_reporting(E_ALL);

include_once(dirname(__FILE__) . "/../../../include/global.php");

chdir($config['base_path']);

include_once('./plugins/thold/includes/database.php');

print "Faking Low Thold Version\n";
$_SESSION['sess_config_array']['plugin_thold_version'] = '.1';

print "Running Thold Upgrade\n";
thold_upgrade_database ();

print "Upgrade Complete\n";





