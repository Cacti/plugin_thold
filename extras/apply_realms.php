<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2019 The Cacti Group                                 |
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

include_once(dirname(__FILE__) . "/../../../include/cli_check.php");

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
	db_execute_prepared('REPLACE INTO user_auth_realm
		(realm_id, user_id) VALUES
		(?, ?)',
		array($realm, $u));
}

print "\n";

