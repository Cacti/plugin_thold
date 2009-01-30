<?php

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

error_reporting(E_ALL);

include_once(dirname(__FILE__) . "/../../../include/global.php");

// Get the current users
$users = db_fetch_assoc("SELECT id FROM user_auth");

// Get the realm for threshold viewing, increase 100 per plugin realm standards
$realm = db_fetch_cell("SELECT id FROM plugin_realms WHERE display = 'View Thresholds'");
$realm = $realm + 100;

print "Threshold Viewing Realm: $realm\n";
print "Found " . count($users) . " users\n";

// Loop through and update each users permissions
print "Updating Realm Permissions\n";
foreach ($users as $user) {
	print ".";
	$u = $user['id'];
	db_execute("REPLACE INTO user_auth_realm (realm_id, user_id) VALUES ($realm, $u)");
}
print "\n";