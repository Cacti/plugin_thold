<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2009 The Cacti Group                                      |
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

function thold_upgrade_database () {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');

	thold_setup_database ();

	include_once($config['base_path'] . '/plugins/thold/setup.php');
	$v = plugin_thold_version();

	$oldv = read_config_option('plugin_thold_version');

	if ($oldv < .1) {
		db_execute('INSERT INTO settings (name, value) VALUES ("plugin_thold_version", "' . $v['version'] . '")');
	}

	// Added in thold v0.3.9
	$result = db_fetch_assoc('SHOW INDEXES FROM graph_templates_item');
	$found = false;
	foreach($result as $row) {
		if ($row['Column_name'] == 'task_item_id')
			$found = true;
	}
	if (!$found) {
		db_execute('ALTER TABLE `graph_templates_item` ADD INDEX ( `task_item_id` )');
	}

	// Added in thold v0.4
	if ($oldv < 0.4) {
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '', 'after' => 'id'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_hi', 'type' => 'varchar(100)', 'NULL' => true, 'after' => 'thold_fail_trigger'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_low',	'type' => 'varchar(100)', 'NULL' => true, 'after' => 'time_hi'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1, 'after' => 'time_low'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'time_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1, 'after' => 'time_fail_trigger'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0, 'after' => 'thold_enabled'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'data_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0, 'after' => 'notify_extra'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => 0, 'after' => 'cdef'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'tcheck', 'type' => 'int(1)', 'NULL' => false, 'default' => 0));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => 'off'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'graph_id', 'type' => 'int(11)', 'NULL' => false, 'default' => 0, 'after' => 'data_id'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'graph_template', 'type' => 'int(11)', 'NULL' => false, 'default' => 0, 'after' => 'graph_id'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'data_template', 'type' => 'int(11)', 'NULL' => false, 'default' => 0, 'after' => 'graph_template'));

		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '', 'after' => 'id'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_hi', 'type' => 'varchar(100)', 'NULL' => true, 'after' => 'thold_fail_trigger'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_low',	'type' => 'varchar(100)', 'NULL' => true, 'after' => 'time_hi'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1, 'after' => 'time_low'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'time_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1, 'after' => 'time_fail_trigger'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0, 'after' => 'notify_extra'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'data_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0, 'after' => 'notify_extra'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => 0, 'after' => 'cdef'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => 'off'));

		// Update our hooks
		db_execute('UPDATE plugin_hooks SET file = "includes/settings.php" WHERE name = "thold" AND hook = "config_arrays"');
		db_execute('UPDATE plugin_hooks SET file = "includes/settings.php" WHERE name = "thold" AND hook = "config_settings"');
		db_execute('UPDATE plugin_hooks SET file = "includes/settings.php" WHERE name = "thold" AND hook = "draw_navigation_text"');
		db_execute('UPDATE plugin_hooks SET function = "thold_poller_bottom", file = "includes/polling.php" WHERE name = "thold" AND hook = "poller_bottom"');

		// Register the new hooks
		api_plugin_register_hook('thold', 'rrd_graph_graph_options', 'thold_rrd_graph_graph_options', 'setup.php');
		api_plugin_register_hook('thold', 'graph_buttons', 'thold_graph_button', 'setup.php');
		api_plugin_register_hook('thold', 'data_source_action_array', 'thold_data_source_action_array', 'setup.php');
		api_plugin_register_hook('thold', 'data_source_action_prepare', 'thold_data_source_action_prepare', 'setup.php');
		api_plugin_register_hook('thold', 'data_source_action_execute', 'thold_data_source_action_execute', 'setup.php');
		api_plugin_register_hook('thold', 'graphs_action_array', 'thold_graphs_action_array', 'setup.php');
		api_plugin_register_hook('thold', 'graphs_action_prepare', 'thold_graphs_action_prepare', 'setup.php');
		api_plugin_register_hook('thold', 'graphs_action_execute', 'thold_graphs_action_execute', 'setup.php');

		db_execute('UPDATE plugin_hooks SET status = 1 WHERE name=\'thold\'');

		// Fix our realms
		db_execute('UPDATE plugin_realms SET file = "thold.php,listthold.php,thold_add.php" WHERE display = "Configure Thresholds"');
		api_plugin_register_realm('thold', 'thold_templates.php', 'Configure Threshold Templates', 1);

		db_execute('ALTER TABLE `data_template_rrd` ADD INDEX ( `data_source_name` )', FALSE);
		db_execute('ALTER TABLE `thold_data` ADD INDEX ( `tcheck` )', FALSE);
		db_execute('ALTER TABLE `thold_data` ADD INDEX ( `graph_id` )', FALSE);
		db_execute('ALTER TABLE `thold_data` ADD INDEX ( `graph_template` )', FALSE);
		db_execute('ALTER TABLE `thold_data` ADD INDEX ( `data_template` )', FALSE);

		/* Set the default names on threshold and templates */
		db_execute("UPDATE thold_data,data_template_data,data_template_rrd SET
			 thold_data.name = CONCAT_WS('',data_template_data.name_cache, ' [', data_template_rrd.data_source_name, ']', '')
			 WHERE data_template_data.local_data_id = thold_data.rra_id AND data_template_rrd.id = thold_data.data_id AND thold_data.name = ''");
		db_execute("UPDATE thold_template SET name = CONCAT_WS('', data_template_name, ' [', data_source_name, ']', '') WHERE name = ''");

		/* Set the graph_ids for all thresholds */
		db_execute('UPDATE thold_data, graph_templates_item, data_template_rrd
			 SET thold_data.graph_id = graph_templates_item.local_graph_id, thold_data.graph_template = graph_templates_item.graph_template_id, thold_data.data_template = data_template_rrd.data_template_id
			 WHERE data_template_rrd.local_data_id=thold_data.rra_id AND data_template_rrd.id=graph_templates_item.task_item_id');
	}

	if ($oldv < 0.5) {
		# Add new columns
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'expression', 'type' => 'varchar(70)', 'NULL' => false, 'default' => '', 'after' => 'percent_ds'));
		api_plugin_db_add_column ('thold', 'thold_template', array('name' => 'expression', 'type' => 'varchar(70)', 'NULL' => false, 'default' => '', 'after' => 'percent_ds'));

		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'rrd_step', 'type' => 'mediumint(6)', 'NULL' => false, 'default' => '0', 'after' => 'data_template'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'data_source_name', 'type' => 'varchar(19)', 'NULL' => false, 'default' => '', 'after' => 'rrd_step'));
		api_plugin_db_add_column ('thold', 'thold_data', array('name' => 'data_source_type_id', 'type' => 'smallint(5)', 'NULL' => false, 'default' => '0', 'after' => 'rrd_step'));

		api_plugin_db_add_column ('thold', 'plugin_thold_alerts', array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => 'off'));
		api_plugin_db_add_column ('thold', 'plugin_thold_template_alerts', array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => 'off'));

		# Add new data to thold_data table (for speeding up polling)
		db_execute('UPDATE thold_data, data_template_rrd
			 SET thold_data.rrd_step = data_template_rrd.rrd_heartbeat,
			 thold_data.data_source_name = data_template_rrd.data_source_name,
			 thold_data.data_source_type_id = data_template_rrd.data_source_type_id
			 WHERE data_template_rrd.local_data_id = thold_data.rra_id AND data_template_rrd.id = thold_data.data_id');

		# Convert Templates to use Alerts
		$templates = db_fetch_assoc("SELECT * FROM thold_template");
		foreach ($templates as $t) {
			$c = db_fetch_assoc("SELECT * FROM plugin_thold_template_contact WHERE template_id = " . $t['id']);
			$contacts = array();
			foreach ($c as $d) {
				$contacts[] = $d['contact_id'];
			}
			$contacts = implode(',', $contacts);

			$save = array();
			$save['id'] = 0;
			$save['type'] = 'email';
			$save['template_id'] = $t['id'];
			$save['repeat_alert'] = $t['repeat_alert'];
			$save['repeat_fail'] = $t['thold_fail_trigger'];
			$save['restored_alert'] = $t['restored_alert'];
			$save['data'] = base64_encode(serialize(array('notify_accounts' => $contacts, 'notify_extra' => $t['notify_extra'])));
			$aid = sql_save($save , 'plugin_thold_template_alerts');
		}

		# Convert Thresholds to use Alerts
		$thresholds = db_fetch_assoc("SELECT * FROM thold_data");
		foreach ($thresholds as $t) {
			$c = db_fetch_assoc("SELECT * FROM plugin_thold_threshold_contact WHERE thold_id = " . $t['id']);
			$contacts = array();
			foreach ($c as $d) {
				$contacts[] = $d['contact_id'];
			}
			$contacts = implode(',', $contacts);
			$save = array();
			$save['id'] = 0;
			$save['type'] = 'email';
			$save['threshold_id'] = $t['id'];
			$save['repeat_alert'] = $t['repeat_alert'];
			$save['repeat_fail'] = $t['thold_fail_trigger'];
			$save['restored_alert'] = $t['restored_alert'];
			$save['data'] = base64_encode(serialize(array('notify_accounts' => $contacts, 'notify_extra' => $t['notify_extra'])));
			$aid = sql_save($save , 'plugin_thold_alerts');
		}
	}

	if ($oldv < $v['version']) {
		db_execute('UPDATE plugin_config SET version = "' . $v['version'] . '" WHERE directory = "thold"');
		db_execute('UPDATE settings SET value = "' . $v['version'] . '" WHERE name = "plugin_thold_version"');
	}
}

function thold_setup_database () {
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'rra_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'graph_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'graph_template', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'rrd_step', 'type' => 'mediumint(6)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_source_name', 'type' => 'varchar(19)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_source_type_id', 'type' => 'smallint(5)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'thold_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'time_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_low',	'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_alert', 'type' => 'int(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'thold_enabled', 'type' => "enum('on','off')", 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'bl_enabled', 'type' => "enum('on','off')", 'NULL' => false, 'default' => 'off');
	$data['columns'][] = array('name' => 'bl_ref_time', 'type' => 'int(50)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_ref_time_range', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_pct_down', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_pct_up', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_fail_count', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_alert', 'type' => 'int(2)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'lastread', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'oldvalue', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_default', 'type' => "enum('on','off')", 'NULL' => true);
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(10)', 'NULL' => true);
	$data['columns'][] = array('name' => 'syslog_priority', 'type' => 'int(2)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'data_type', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'cdef', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'expression', 'type' => 'varchar(70)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'template', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'template_enabled', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'tcheck', 'type' => 'int(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => 'off');
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][] = array('name' => 'rra_id', 'columns' => 'rra_id');
	$data['keys'][] = array('name' => 'data_id', 'columns' => 'data_id');
	$data['keys'][] = array('name' => 'graph_id', 'columns' => 'graph_id');
	$data['keys'][] = array('name' => 'template', 'columns' => 'template');
	$data['keys'][] = array('name' => 'thold_enabled', 'columns' => 'thold_enabled');
	$data['keys'][] = array('name' => 'template_enabled', 'columns' => 'template_enabled');
	$data['keys'][] = array('name' => 'tcheck', 'columns' => 'tcheck');

	$data['type'] = 'MyISAM';
	$data['comment'] = 'Threshold data';
	api_plugin_db_table_create ('thold', 'thold_data', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_template_id', 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_source_id', 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_source_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_source_friendly', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_low',	'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_enabled', 'type' => "enum('on','off')", 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'bl_enabled', 'type' => "enum('on','off')", 'NULL' => false, 'default' => 'off');
	$data['columns'][] = array('name' => 'bl_ref_time', 'type' => 'int(50)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_ref_time_range', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_pct_down', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_pct_up', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_fail_count', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_alert', 'type' => 'int(2)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'notify_default', 'type' => "enum('on','off')", 'NULL' => true);
	$data['columns'][] = array('name' => 'data_type', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'cdef', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'expression', 'type' => 'varchar(70)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => 'off');
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'id', 'columns' => 'id');
	$data['keys'][] = array('name' => 'data_source_id', 'columns' => 'data_source_id');
	$data['keys'][] = array('name' => 'data_template_id', 'columns' => 'data_template_id');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Table of thresholds defaults for graphs';
	api_plugin_db_table_create ('thold', 'thold_template', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'type', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'data', 'type' => 'text', 'NULL' => false);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'type', 'columns' => 'type');
	$data['keys'][] = array('name' => 'user_id', 'columns' => 'user_id');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Table of threshold contacts';
	api_plugin_db_table_create ('thold', 'plugin_thold_contacts', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'threshold_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'repeat_fail', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'repeat_alert', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => 'off');
	$data['columns'][] = array('name' => 'type', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'data', 'type' => 'text', 'NULL' => false);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'threshold_id', 'columns' => 'threshold_id');
	$data['keys'][] = array('name' => 'repeat_fail', 'columns' => 'repeat_fail');
	$data['keys'][] = array('name' => 'repeat_alert', 'columns' => 'repeat_alert');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Table of threshold alerts';
	api_plugin_db_table_create ('thold', 'plugin_thold_alerts', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'template_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'repeat_fail', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'repeat_alert', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => 'off');
	$data['columns'][] = array('name' => 'type', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'data', 'type' => 'text', 'NULL' => false);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'template_id', 'columns' => 'template_id');
	$data['keys'][] = array('name' => 'repeat_fail', 'columns' => 'repeat_fail');
	$data['keys'][] = array('name' => 'repeat_alert', 'columns' => 'repeat_alert');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Table of threshold template_alerts';
	api_plugin_db_table_create ('thold', 'plugin_thold_template_alerts', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'time', 'type' => 'int(24)', 'NULL' => false);
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'graph_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'threshold_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'threshold_value', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'current', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'status', 'type' => 'int(5)', 'NULL' => false);
	$data['columns'][] = array('name' => 'type', 'type' => 'int(5)', 'NULL' => false);
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(255)', 'NULL' => false);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'time', 'columns' => 'time');
	$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][] = array('name' => 'graph_id', 'columns' => 'graph_id');
	$data['keys'][] = array('name' => 'threshold_id', 'columns' => 'threshold_id');
	$data['keys'][] = array('name' => 'status', 'columns' => 'status');
	$data['keys'][] = array('name' => 'type', 'columns' => 'type');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Table of All Threshold Breaches';
	api_plugin_db_table_create ('thold', 'plugin_thold_log', $data);

}
