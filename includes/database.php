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

function thold_upgrade_database($force = false) {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');

	thold_setup_database();

	include_once($config['base_path'] . '/plugins/thold/setup.php');
	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$v = plugin_thold_version();

	$oldv = db_fetch_cell('SELECT version
		FROM plugin_config
		WHERE directory="thold"');

	if ($force) {
		$oldv = '0.1';
	}

	db_execute('DELETE FROM settings WHERE name="plugin_thold_version"');

	// Added in thold v0.4
	if (cacti_version_compare($oldv, '0.4', '<') && !db_column_exists('thold_data', 'local_graph_id')) {
		// Check for needed Cacti Indexes
		db_add_index('graph_templates_item', 'INDEX', 'task_item_id', array('task_item_id'));
		db_add_index('data_local', 'INDEX', 'data_template_id', array('data_template_id'));
		db_add_index('data_local', 'INDEX', 'snmp_query_id', array('snmp_query_id'));
		db_add_index('host_snmp_cache', 'INDEX', 'snmp_query_id', array('snmp_query_id'));
		db_add_index('data_template_rrd', 'INDEX', 'data_source_name', array('data_source_name'));

		db_add_column('thold_data', array(
			'name' => 'name',
			'type' => 'varchar(100)',
			'NULL' => false,
			'default' => '',
			'after' => 'id'));

		db_add_column('thold_data', array(
			'name' => 'time_hi',
			'type' => 'varchar(100)',
			'NULL' => true,
			'after' => 'thold_fail_trigger'));

		db_add_column('thold_data', array(
			'name' => 'time_low',
			'type' => 'varchar(100)',
			'NULL' => true,
			'after' => 'time_hi'));

		db_add_column('thold_data', array(
			'name' => 'time_fail_trigger',
			'type' => 'int (12)',
			'NULL' => false,
			'default' => 1,
			'after' => 'time_low'));

		db_add_column('thold_data', array(
			'name' => 'time_fail_length',
			'type' => 'int (12)',
			'NULL' => false,
			'default' => 1,
			'after' => 'time_fail_trigger'));

		db_add_column('thold_data', array(
			'name' => 'thold_type',
			'type' => 'int (3)',
			'NULL' => false,
			'default' => 0,
			'after' => 'thold_enabled'));

		db_add_column('thold_data', array(
			'name' => 'data_type',
			'type' => 'int (3)',
			'NULL' => false,
			'default' => 0,
			'after' => 'notify_extra'));

		db_add_column('thold_data', array(
			'name' => 'percent_ds',
			'type' => 'varchar(64)',
			'NULL' => false,
			'default' => 0,
			'after' => 'cdef'));

		db_add_column('thold_data', array(
			'name' => 'tcheck',
			'type' => 'int(1)',
			'NULL' => false,
			'default' => 0));

		db_add_column('thold_data', array(
			'name' => 'exempt',
			'type' => 'char(3)',
			'NULL' => false,
			'default' => ''));

		db_add_column('thold_data', array(
			'name' => 'graph_id',
			'type' => 'int(11)',
			'NULL' => false,
			'default' => 0,
			'after' => 'data_id'));

		db_add_column('thold_data', array(
			'name' => 'graph_template',
			'type' => 'int(11)',
			'NULL' => false,
			'default' => 0,
			'after' => 'graph_id'));

		db_add_column('thold_data', array(
			'name' => 'data_template',
			'type' => 'int(11)',
			'NULL' => false,
			'default' => 0,
			'after' => 'graph_template_id'));

		db_add_column('thold_data', array(
			'name' => 'restored_alert',
			'type' => 'char(3)',
			'NULL' => false,
			'default' => ''));

		db_add_column('thold_template', array(
			'name' => 'name',
			'type' => 'varchar(100)',
			'NULL' => false, 'default' => '',
			'after' => 'id'));

		db_add_column('thold_template', array(
			'name' => 'time_hi',
			'type' => 'varchar(100)',
			'NULL' => true,
			'after' => 'thold_fail_trigger'));

		db_add_column('thold_template', array(
			'name' => 'time_low',
			'type' => 'varchar(100)',
			'NULL' => true,
			'after' => 'time_hi'));

		db_add_column('thold_template', array(
			'name' => 'time_fail_trigger',
			'type' => 'int (12)',
			'NULL' => false, 'default' => 1,
			'after' => 'time_low'));

		db_add_column('thold_template', array(
			'name' => 'time_fail_length',
			'type' => 'int (12)',
			'NULL' => false, 'default' => 1,
			'after' => 'time_fail_trigger'));

		db_add_column('thold_template', array(
			'name' => 'thold_type',
			'type' => 'int (3)',
			'NULL' => false, 'default' => 0,
			'after' => 'thold_enabled'));

		db_add_column('thold_template', array(
			'name' => 'data_type',
			'type' => 'int (3)',
			'NULL' => false,
			'default' => 0,
			'after' => 'syslog_priority'));

		db_add_column('thold_template', array(
			'name' => 'percent_ds',
			'type' => 'varchar(64)',
			'NULL' => false,
			'default' => 0,
			'after' => 'cdef'));

		db_add_column('thold_template', array(
			'name' => 'exempt',
			'type' => 'char(3)',
			'NULL' => false,
			'default' => ''));

		db_add_column('thold_template', array(
			'name' => 'restored_alert',
			'type' => 'char(3)',
			'NULL' => false,
			'default' => ''));

		// Update our hooks
		db_execute('UPDATE plugin_hooks
			SET file = "includes/settings.php"
			WHERE name = "thold"
			AND hook = "config_arrays"');

		db_execute('UPDATE plugin_hooks
			SET file = "includes/settings.php"
			WHERE name = "thold"
			AND hook = "config_settings"');

		db_execute('UPDATE plugin_hooks
			SET file = "includes/settings.php"
			WHERE name = "thold"
			AND hook = "draw_navigation_text"');

		db_execute('UPDATE plugin_hooks
			SET function = "thold_poller_bottom", file = "includes/polling.php"
			WHERE name = "thold"
			AND hook = "poller_bottom"');

		// Register the new hooks
		api_plugin_register_hook('thold', 'rrd_graph_graph_options', 'thold_rrd_graph_graph_options', 'setup.php', '1');
		api_plugin_register_hook('thold', 'graph_buttons', 'thold_graph_button', 'setup.php', '1');
		api_plugin_register_hook('thold', 'data_source_action_array', 'thold_data_source_action_array', 'setup.php', '1');
		api_plugin_register_hook('thold', 'data_source_action_prepare', 'thold_data_source_action_prepare', 'setup.php', '1');
		api_plugin_register_hook('thold', 'data_source_action_execute', 'thold_data_source_action_execute', 'setup.php', '1');
		api_plugin_register_hook('thold', 'graphs_action_array', 'thold_graphs_action_array', 'setup.php', '1');
		api_plugin_register_hook('thold', 'graphs_action_prepare', 'thold_graphs_action_prepare', 'setup.php', '1');
		api_plugin_register_hook('thold', 'graphs_action_execute', 'thold_graphs_action_execute', 'setup.php', '1');

		// Fix our realms
		db_execute('UPDATE plugin_realms
			SET file = "thold.php"
			WHERE display = "Configure Thresholds"');

		api_plugin_register_realm('thold', 'thold_templates.php', 'Configure Threshold Templates', 1);

		api_plugin_enable_hooks('thold');
	}
	// End 0.4 Upgrade

	if (cacti_version_compare($oldv, '0.4.3', '<')) {
		// Fix a few hooks
		db_execute('DELETE FROM plugin_hooks WHERE name = "thold" AND hook = "config_insert"');
		db_execute('DELETE FROM plugin_hooks WHERE name = "thold" AND hook = "config_arrays"');

		api_plugin_register_hook('thold', 'config_insert', 'thold_config_insert', 'includes/settings.php', '1');
		api_plugin_register_hook('thold', 'config_arrays', 'thold_config_arrays', 'includes/settings.php', '1');

		api_plugin_enable_hooks('thold');

		$e = strtolower(db_fetch_cell("SELECT `value` FROM settings WHERE `name` = 'thold_from_email'"));
		if ($e == 'cacti@cactiusers.org') {
			db_execute("UPDATE settings SET `value`='cacti@localhost' WHERE `name`='thold_from_email'");
		}
	}

	if (cacti_version_compare($oldv, '0.4.4', '<')) {
		db_add_column('thold_data', array(
			'name'    => 'lasttime',
			'type'    => 'TIMESTAMP',
			'NULL'    => false,
			'after'   => 'lastread'));

		db_add_column('thold_data', array(
			'name'    => 'bl_thold_valid',
			'type'    => 'int(11)', 'unsigned' => true,
			'NULL'    => false,
			'default' => '0',
			'after'   => 'bl_alert'));

		db_add_column('thold_data', array(
			'name'     => 'expression',
			'type'     => 'varchar(70)',
			'NULL'     => false,
			'default'  => '',
			'after'    => 'percent_ds'));

		db_add_column('thold_template', array(
			'name'     => 'expression',
			'type'     => 'varchar(70)',
			'NULL'     => false,
			'default'  => '',
			'after'    => 'percent_ds'));

		db_execute('ALTER TABLE thold_data MODIFY name varchar(150) default NULL');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN bl_pct_down varchar(100)');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN bl_pct_up varchar(100)');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN bl_pct_down varchar(100)');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN bl_pct_up varchar(100)');
	}

	if (cacti_version_compare($oldv, '0.4.5', '<')) {
		db_add_column('thold_template', array(
			'name'    => 'thold_warning_hi',
			'type'    => 'varchar(100)',
			'NULL'    => true,
			'after'   => 'time_fail_length'));

		db_add_column('thold_template', array(
			'name'    => 'thold_warning_low',
			'type'    => 'varchar(100)',
			'NULL'    => true,
			'after'   => 'thold_warning_hi'));

		db_add_column('thold_template', array(
			'name'    => 'thold_warning_fail_trigger',
			'type'    => 'int(10)',
			'NULL'    => true, 'unsigned' => true,
			'after'   => 'thold_warning_low'));

		db_add_column('thold_template', array(
			'name'    => 'thold_warning_fail_count',
			'type'    => 'int(11)',
			'NULL'    => false,
			'default' => '0',
			'after'   => 'thold_warning_fail_trigger'));

		db_add_column('thold_template', array(
			'name'    => 'time_warning_hi',
			'type'    => 'varchar(100)',
			'NULL'    => true,
			'after'   => 'thold_warning_fail_count'));

		db_add_column('thold_template', array(
			'name'    => 'time_warning_low',
			'type'    => 'varchar(100)',
			'NULL'    => true,
			'after'   => 'time_warning_hi'));

		db_add_column('thold_template', array(
			'name'    => 'time_warning_fail_trigger',
			'type'    => 'int (12)',
			'NULL'    => false,
			'default' => 1,
			'after'   => 'time_warning_low'));

		db_add_column('thold_template', array(
			'name'    => 'time_warning_fail_length',
			'type'    => 'int (12)',
			'NULL'    => false,
			'default' => 1,
			'after'   => 'time_warning_fail_trigger'));

		db_add_column('thold_template', array(
			'name'    => 'notify_warning_extra',
			'type'    => 'text',
			'NULL'    => true,
			'after'   => 'time_warning_fail_length'));

		db_add_column('thold_data', array(
			'name'    => 'thold_warning_hi',
			'type'    => 'varchar(100)',
			'NULL'    => true,
			'after'   => 'time_fail_length'));

		db_add_column('thold_data', array(
			'name'    => 'thold_warning_low',
			'type'    => 'varchar(100)',
			'NULL'    => true,
			'after'   => 'thold_warning_hi'));

		db_add_column('thold_data', array(
			'name'    => 'thold_warning_fail_trigger',
			'type'    => 'int(10)',
			'NULL'    => true, 'unsigned' => true,
			'after'   => 'thold_warning_low'));

		db_add_column('thold_data', array(
			'name'    => 'thold_warning_fail_count',
			'type'    => 'int(11)',
			'NULL'    => false,
			'default' => '0',
			'after'   => 'thold_warning_fail_trigger'));

		db_add_column('thold_data', array(
			'name'    => 'time_warning_hi',
			'type'    => 'varchar(100)',
			'NULL'    => true,
			'after'   => 'thold_warning_fail_count'));

		db_add_column('thold_data', array(
			'name'    => 'time_warning_low',
			'type'    => 'varchar(100)',
			'NULL'    => true,
			'after'   => 'time_warning_hi'));

		db_add_column('thold_data', array(
			'name'    => 'time_warning_fail_trigger',
			'type'    => 'int (12)',
			'NULL'    => false,
			'default' => 1,
			'after'   => 'time_warning_low'));

		db_add_column('thold_data', array(
			'name'    => 'time_warning_fail_length',
			'type'    => 'int (12)',
			'NULL'    => false,
			'default' => 1,
			'after'   => 'time_warning_fail_trigger'));

		db_add_column('thold_data', array(
			'name'    => 'notify_warning_extra',
			'type'    => 'text',
			'NULL'    => true,
			'after'   => 'time_warning_fail_length'));

		db_execute('ALTER TABLE thold_data MODIFY COLUMN notify_extra text');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN notify_extra text');

		$data = array();
		$data['columns'][] = array(
			'name' => 'id',
			'type' => 'int(12)',
			'NULL' => false, 'auto_increment' => true);

		$data['columns'][] = array(
			'name' => 'name',
			'type' => 'varchar(128)',
			'NULL' => false);

		$data['columns'][] = array(
			'name' => 'description',
			'type' => 'varchar(512)',
			'NULL' => false);

		$data['columns'][] = array(
			'name' => 'emails',
			'type' => 'varchar(512)',
			'NULL' => false);

		$data['primary'] = 'id';
		$data['type'] = 'InnoDB';
		$data['comment'] = 'Table of Notification Lists';
		api_plugin_db_table_create('thold', 'plugin_notification_lists', $data);

		api_plugin_db_add_column('thold', 'host', array(
			'name' => 'thold_send_email',
			'type' => 'int(10)',
			'unsigned' => true,
			'NULL' => false,
			'default' => '1',
			'after' => 'disabled'));

		api_plugin_db_add_column('thold', 'host', array(
			'name'     => 'thold_host_email',
			'type'     => 'int(10)',
			'unsigned' => true,
			'NULL'     => true,
			'after'    => 'thold_send_email'));

		db_add_column('thold_data', array(
			'name'     => 'notify_warning',
			'type'     => 'int(10)',
			'unsigned' => true,
			'NULL'     => false,
			'default'  => '1',
			'after'    => 'notify_warning_extra'));

		db_add_column('thold_data', array(
			'name'     => 'notify_alert',
			'type'     => 'int(10)',
			'unsigned' => true,
			'NULL'     => false,
			'default'  => '1',
			'after'    => 'notify_warning_extra'));

		db_add_column('thold_template', array(
			'name'     => 'notify_warning',
			'type'     => 'int(10)',
			'unsigned' => true,
			'NULL'     => false,
			'default'  => '1',
			'after'    => 'notify_warning_extra'));

		db_add_column('thold_template', array(
			'name'     => 'notify_alert',
			'type'     => 'int(10)',
			'unsigned' => true,
			'NULL'     => false,
			'default'  => '1',
			'after'    => 'notify_warning_extra'));

		db_add_column('thold_template', array(
			'name'     => 'hash',
			'type'     => 'varchar(32)',
			'NULL'     => true,
			'after'    => 'id'));

		if (db_column_exists('thold_data', 'bl_enabled', false)) {
			db_execute('ALTER TABLE thold_data REMOVE COLUMN bl_enabled', FALSE);
		}

		if (db_column_exists('thold_template', 'bl_enabled', false)) {
			db_execute('ALTER TABLE thold_template REMOVE COLUMN bl_enabled', FALSE);
		}

		api_plugin_register_hook('thold', 'config_form', 'thold_config_form', 'includes/settings.php', '1');
		api_plugin_register_realm('thold', 'notify_lists.php', 'Manage Notification Lists', 1);

		/* set unique hash values for all thold templates */
		$templates = db_fetch_assoc('SELECT id FROM thold_template');
		if (sizeof($templates)) {
			foreach($templates as $t) {
				$hash = get_hash_thold_template($t['id']);
				db_execute_prepared('UPDATE thold_template
					SET hash = ?
					WHERE id = ?
					AND (hash = "" OR hash IS NULL)',
					array($hash, $t['id']));
			}
		}
	}

	if (cacti_version_compare($oldv, '0.4.7', '<')) {
		$data = array();
		$data['columns'][] = array(
			'name'     => 'id',
			'type'     => 'int(12)',
			'NULL'     => false,
			'unsigned' => true,
			'auto_increment' => true);

		$data['columns'][] = array(
			'name'     => 'poller_id',
			'type'     => 'int(10)',
			'unsigned' => true,
			'NULL'     => false,
			'default'  => '1');

		$data['columns'][] = array(
			'name'     => 'host_id',
			'type'     => 'int(12)',
			'unsigned' => true,
			'NULL'     => false);

		$data['primary'] = 'id';
		$data['type'] = 'InnoDB';
		$data['comment'] = 'Table of Devices in a Down State';
		api_plugin_db_table_create('thold', 'plugin_thold_host_failed', $data);

		db_execute('DELETE FROM settings WHERE name="thold_failed_hosts"');

		/* increase the size of the settings table */
		db_execute('ALTER TABLE settings
			MODIFY column `value`
			varchar(1024) NOT NULL default ""');
	}

	if (cacti_version_compare($oldv, '0.6', '<')) {
		db_add_column('thold_data', array(
			'name'    => 'snmp_event_category',
			'type'    => 'varchar(255)',
			'NULL'    => true,
			'after'   => 'notify_alert'));

		db_add_column('thold_data', array(
			'name'    => 'snmp_event_severity',
			'type'    => 'tinyint(1)',
			'NULL'    => false,
			'default' => '3',
			'after'   => 'snmp_event_category'));

		db_add_column('thold_data', array(
			'name'    => 'snmp_event_warning_severity',
			'type'    => 'tinyint(1)',
			'NULL'    => false,
			'default' => '2',
			'after'   => 'snmp_event_severity'));

		db_add_column('thold_data', array(
			'name'    => 'thold_daemon_pid',
			'type'    => 'varchar(25)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'snmp_event_warning_severity'));

		db_add_column('thold_template', array(
			'name'    => 'snmp_event_category',
			'type'    => 'varchar(255)',
			'NULL'    => true,
			'after'   => 'notify_alert'));

		db_add_column('thold_template', array(
			'name'    => 'snmp_event_severity',
			'type'    => 'tinyint(1)',
			'NULL'    => false,
			'default' => '3',
			'after'   => 'snmp_event_category'));

		db_add_column('thold_template', array(
			'name'    => 'snmp_event_warning_severity',
			'type'    => 'tinyint(1)',
			'NULL'    => false,
			'default' => '2',
			'after'   => 'snmp_event_severity'));

		$data = array();
		$data['columns'][] = array(
			'name'     => 'id',
			'type'     => 'int(11)',
			'NULL'     => false);

		$data['columns'][] = array(
			'name'     => 'poller_id',
			'type'     => 'int(10)',
			'unsigned' => true,
			'NULL'     => false,
			'default'  => '1');

		$data['columns'][] = array(
			'name'     => 'pid',
			'type'     => 'varchar(25)',
			'NULL'     => false);

		$data['columns'][] = array(
			'name'     => 'rrd_reindexed',
			'type'     => 'varchar(600)',
			'NULL'     => false);

		$data['columns'][] = array(
			'name'     => 'rrd_time_reindexed',
			'type'     => 'int(10)',
			'unsigned' => true,
			'NULL'     => false);

		$data['keys'][]  = array('name' => 'id', 'columns' => 'id`, `pid');
		$data['type']    = 'InnoDB';
		$data['comment'] = 'Table of Poller Outdata needed for queued daemon processes';
		api_plugin_db_table_create('thold', 'plugin_thold_daemon_data', $data);

		$data = array();
		$data['columns'][] = array(
			'name'    => 'pid',
			'type'    => 'varchar(25)',
			'NULL'    => false);

		$data['columns'][] = array(
			'name'    => 'start',
			'type'    => 'double',
			'NULL'    => false,
			'default' => '0');

		$data['columns'][] = array(
			'name'    => 'end',
			'type'    => 'double',
			'NULL'    => false,
			'default' => '0');

		$data['columns'][] = array(
			'name'    => 'processed_items',
			'type'    => 'mediumint(8)',
			'NULL'    => false,
			'default' => '0');

		$data['primary'] = 'pid';
		$data['type']    = 'InnoDB';
		$data['comment'] = 'Table of Thold Daemon Processes being queued';
		api_plugin_db_table_create('thold', 'plugin_thold_daemon_processes', $data);

		// Rename some columns
		if (db_column_exists('thold_data', 'rra_id')) {
			db_execute('ALTER TABLE thold_data
				CHANGE COLUMN rra_id local_data_id
				int(11) UNSIGNED NOT NULL default "0"');
		}

		if (db_column_exists('thold_data', 'data_id')) {
			db_execute('ALTER TABLE thold_data
				CHANGE COLUMN data_id data_template_rrd_id
				int(11) UNSIGNED NOT NULL default "0"');
		}

		if (db_column_exists('thold_data', 'template')) {
			db_execute('ALTER TABLE thold_data
				CHANGE COLUMN template thold_template_id
				int(11) UNSIGNED NOT NULL default "0"');
		}

		if (db_column_exists('thold_data', 'data_template')) {
			db_execute('ALTER TABLE thold_data
				CHANGE COLUMN data_template data_template_id
				int(11) UNSIGNED NOT NULL default "0"');
		}

		if (db_column_exists('thold_data', 'graph_id')) {
			db_execute('ALTER TABLE thold_data
				CHANGE COLUMN graph_id local_graph_id
				int(11) UNSIGNED NOT NULL default "0"');
		}

		if (db_column_exists('thold_data', 'graph_template')) {
			db_execute('ALTER TABLE thold_data
				CHANGE COLUMN graph_template graph_template_id
				int(11) UNSIGNED NOT NULL default "0"');
		}

		if (db_column_exists('plugin_thold_log', 'graph_id')) {
			db_execute('ALTER TABLE plugin_thold_log
				CHANGE COLUMN graph_id local_graph_id
				int(11) UNSIGNED NOT NULL default "0"');
		}

		db_add_index('thold_data', 'INDEX', 'tcheck', array('tcheck'));
		db_add_index('thold_data', 'INDEX', 'local_graph_id', array('local_graph_id'));
		db_add_index('thold_data', 'INDEX', 'graph_template_id', array('graph_template_id'));
		db_add_index('thold_data', 'INDEX', 'data_template_id', array('data_template_id'));

		/* Set the default names on threshold and templates */
		db_execute("UPDATE thold_data, data_template_data, data_template_rrd
			SET thold_data.name = CONCAT_WS('',data_template_data.name_cache, ' [', data_template_rrd.data_source_name, ']', '')
			WHERE data_template_data.local_data_id = thold_data.local_data_id
			AND data_template_rrd.id = thold_data.data_template_rrd_id
			AND thold_data.name = ''");

		db_execute("UPDATE thold_template
			SET name = CONCAT_WS('', data_template_name, ' [', data_source_name, ']', '')
			WHERE name = ''");

		/* Set the graph_ids for all thresholds */
		db_execute('UPDATE thold_data, graph_templates_item, data_template_rrd
			SET thold_data.local_graph_id = graph_templates_item.local_graph_id,
				thold_data.graph_template_id = graph_templates_item.graph_template_id,
				thold_data.data_template_id = data_template_rrd.data_template_id
			WHERE data_template_rrd.local_data_id=thold_data.local_data_id
			AND data_template_rrd.id=graph_templates_item.task_item_id');
	}

	if (cacti_version_compare($oldv, '1.0', '<')) {
		$data = array();
		$data['columns'][] = array(
			'name'     => 'id',
			'type'     => 'int(11)',
			'unsigned' => true,
			'NULL'     => false,
			'auto_increment' => true);

		$data['columns'][] = array(
			'name'     => 'device_template_id',
			'type'     => 'int(11)',
			'unsigned' => true,
			'NULL'     => false,
			'default'  => '0');

		$data['columns'][] = array(
			'name'     => 'thold_template_id',
			'type'     => 'int(11)',
			'unsigned' => true,
			'NULL'     => false,
			'default'  => '0');

		$data['primary'] = 'id';
		$data['type'] = 'InnoDB';
		$data['comment'] = 'Table of Device Template Threshold Templates';
		api_plugin_db_table_create('thold', 'plugin_thold_device_template', $data);

		api_plugin_register_hook('thold', 'device_template_edit', 'thold_device_template_edit', 'setup.php', '1');
		api_plugin_register_hook('thold', 'device_template_top', 'thold_device_template_top', 'setup.php', '1');
		api_plugin_register_hook('thold', 'device_edit_pre_bottom', 'thold_device_edit_pre_bottom', 'setup.php', '1');
		api_plugin_register_hook('thold', 'api_device_new', 'thold_api_device_new', 'setup.php', '1');
		api_plugin_register_hook('thold', 'page_head', 'thold_page_head', 'setup.php');

		if (api_plugin_is_enabled('thold')) {
			api_plugin_enable_hooks('thold');
		}
	}

	if (cacti_version_compare($oldv, '1.0.1', '<')) {
		db_add_column('thold_data', array(
			'name'     => 'thold_hrule_alert',
			'type'     => 'int(11)',
			'unsigned' => true,
			'NULL'     => true,
			'after'    => 'exempt'));

		db_add_column('thold_data', array(
			'name'     => 'thold_hrule_warning',
			'type'     => 'int(11)',
			'unsigned' => true,
			'NULL'     => true,
			'after'    => 'thold_hrule_alert'));

		db_add_column('thold_template', array(
			'name'     => 'thold_hrule_alert',
			'type'     => 'int(11)',
			'unsigned' => true,
			'NULL'     => true,
			'after'    => 'exempt'));

		db_add_column('thold_template', array(
			'name'     => 'thold_hrule_warning',
			'type'     => 'int(11)',
			'unsigned' => true,
			'NULL'     => true,
			'after'    => 'thold_hrule_alert'));
	}

	if (cacti_version_compare($oldv, '1.0.2', '<')) {
		db_execute('ALTER TABLE thold_data MODIFY COLUMN expression VARCHAR(512) NOT NULL DEFAULT ""');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN expression VARCHAR(512) NOT NULL DEFAULT ""');
	}

	if (cacti_version_compare($oldv, '1.0.3', '<')) {
		db_add_column('thold_data', array(
			'name'     => 'notes',
			'type'     => 'varchar(1024)',
			'NULL'     => true,
			'default'  => '',
			'after'    => 'thold_daemon_pid'));

		db_add_column('thold_template', array(
			'name'     => 'notes',
			'type'     => 'varchar(1024)',
			'NULL'     => true,
			'default'  => '',
			'after'    => 'snmp_event_warning_severity'));
	}

	if (cacti_version_compare($oldv, '1.0.4', '<')) {
		if (!db_column_exists('plugin_thold_daemon_processes', 'poller_id')) {
			db_execute("ALTER TABLE plugin_thold_daemon_processes
				ADD COLUMN poller_id int(10) unsigned NOT NULL default '1' FIRST,
				MODIFY COLUMN start double NOT NULL default '0',
				MODIFY COLUMN end double NOT NULL default '0',
				DROP PRIMARY KEY, ADD PRIMARY KEY (`poller_id`, `pid`)");
		}

		if (!db_column_exists('plugin_thold_daemon_data', 'poller_id')) {
			db_execute("ALTER TABLE plugin_thold_daemon_data
				ADD COLUMN poller_id int(10) unsigned NOT NULL default '1' AFTER `id`,
				ADD KEY `poller_id` (`poller_id`),
				ADD PRIMARY KEY (`id`, `pid`),
				DROP KEY `id`");
		}

		if (!db_column_exists('plugin_thold_host_failed', 'poller_id')) {
			db_execute("ALTER TABLE plugin_thold_host_failed
				ADD COLUMN poller_id int(10) unsigned NOT NULL default '1' AFTER `id`,
				ADD KEY `poller_id` (`poller_id`)");
		}

		if (!db_index_exists('thold_data', 'thold_daemon_pid')) {
			db_execute('ALTER TABLE thold_data
				ADD INDEX thold_daemon_pid (thold_daemon_pid)');
		}

		if (!db_column_exists('thold_template', 'suggested_name')) {
			db_execute("ALTER TABLE thold_template
				ADD COLUMN `suggested_name` varchar(255) NOT NULL default '' AFTER `name`");
		}
	}

	if (cacti_version_compare($oldv, '1.0.5', '<')) {
		db_execute('ALTER TABLE thold_data MODIFY COLUMN expression VARCHAR(512) NOT NULL DEFAULT ""');
		db_execute('ALTER TABLE thold_template MODIFY COLUMN expression VARCHAR(512) NOT NULL DEFAULT ""');
	}

	if (cacti_version_compare($oldv, '1.2', '<')) {
		// Add additional columns for new features
		db_add_column('thold_data', array(
			'name'    => 'name_cache',
			'type'    => 'varchar(100)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'name'));

		db_add_column('thold_data', array(
			'name'    => 'data_source_name',
			'type'    => 'varchar(100)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'data_template_id'));

		// Acknowledgement
		db_add_column('thold_data', array(
			'name'    => 'acknowledgment',
			'type'    => 'char(3)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'exempt'));

		db_add_column('thold_data', array(
			'name'    => 'prev_thold_alert',
			'type'    => 'int(1)',
			'NULL'    => false,
			'default' => '0',
			'after'   => 'thold_alert'));

		db_add_column('thold_data', array(
			'name'    => 'reset_ack',
			'type'    => 'char(3)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'restored_alert'));

		db_add_column('thold_data', array(
			'name'    => 'persist_ack',
			'type'    => 'char(3)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'reset_ack'));

		// Populate the data source name
		db_execute("UPDATE thold_data, data_template_data, data_template_rrd SET
			thold_data.data_source_name = data_template_rrd.data_source_name
			WHERE data_template_data.local_data_id = thold_data.local_data_id
			AND data_template_rrd.id = thold_data.data_template_rrd_id
			AND thold_data.data_source_name = ''");

		// Required for backward compatibility
		if (db_column_exists('thold_data', 'acknowledgement')) {
			if (!db_column_exists('thold_data', 'acknowledgment')) {
				db_execute('ALTER TABLE thold_data
					CHANGE COLUMN acknowledgement acknowledgment
					char(3) NOT NULL default ""');
			} else {
				db_execute('UPDATE thold_data
					SET acknowledgment = acknowledgement
					WHERE acknowledgment = acknowledgement');

				db_execute('ALTER TABLE thold_data DROP COLUMN acknowledgement');
			}
		}

		// For backport legacy support
		if (db_column_exists('thold_data', 'email_body')) {
			set_config_option('thold_enable_per_thold_body', 'on');
		}

		// For backport legacy support
		if (db_column_exists('thold_data', 'trigger_cmd_high')) {
			set_config_option('thold_enable_scripts', 'on');
		}

		// Move modifyable Email body into thold
		db_add_column('thold_data', array(
			'name' => 'email_body',
			'type' => 'varchar(1024)',
			'NULL' => false,
			'default' => '',
			'after' => 'persist_ack'));

		db_add_column('thold_data', array(
			'name' => 'email_body_warn',
			'type' => 'varchar(1024)',
			'NULL' => false,
			'default' => '',
			'after' => 'email_body'));

		// If these columns were added before, change attributes
		db_execute('ALTER TABLE thold_data MODIFY COLUMN email_body varchar(1024) default ""');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN restored_alert char(3) NOT NULL default ""');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN exempt char(3) NOT NULL default ""');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN data_type int(12) NOT NULL default "0"');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN notify_extra varchar(512) default ""');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN trigger_cmd_high varchar(512) NOT NULL default ""');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN trigger_cmd_low varchar(512) NOT NULL default ""');
		db_execute('ALTER TABLE thold_data MODIFY COLUMN trigger_cmd_norm varchar(512) NOT NULL default ""');

		if (db_column_exists('thold_template', 'trigger_cmd_high')) {
			db_execute('ALTER TABLE thold_template MODIFY COLUMN trigger_cmd_high varchar(512) NOT NULL default ""');
		}

		if (db_column_exists('thold_template', 'trigger_cmd_low')) {
			db_execute('ALTER TABLE thold_template MODIFY COLUMN trigger_cmd_low varchar(512) NOT NULL default ""');
		}

		if (db_column_exists('thold_template', 'trigger_cmd_norm')) {
			db_execute('ALTER TABLE thold_template MODIFY COLUMN trigger_cmd_norm varchar(512) NOT NULL default ""');
		}

		// Trigger commands
		db_add_column('thold_data', array(
			'name'    => 'trigger_cmd_high',
			'type'    => 'varchar(512)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'email_body_warn'));

		db_add_column('thold_data', array(
			'name'    => 'trigger_cmd_low',
			'type'    => 'varchar(512)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'trigger_cmd_high'));

		db_add_column('thold_data', array(
			'name'    => 'trigger_cmd_norm',
			'type'    => 'varchar(512)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'trigger_cmd_low'));

		// Additional syslog columns
		db_add_column('thold_data', array(
			'name'    => 'syslog_facility',
			'type'    => 'int(2)',
			'NULL'    => true,
			'after'   => 'syslog_priority'));

		db_add_column('thold_data', array(
			'name'    => 'syslog_enabled',
			'type'    => 'char(3)',
			'NULL'    => false,
			'default' => '',
			'after'   => 'syslog_facility'));

		// Remove these columns if they exist
		db_remove_column('thold_data', 'bl_ref_time');
		db_remove_column('thold_data', 'notify_default');
		db_remove_column('thold_template', 'notify_default');

		// Acknowledgement columns
		db_add_column('thold_template', array(
			'name' => 'reset_ack',
			'type' => 'char(3)',
			'NULL' => false,
			'default' => 'off',
			'after' => 'restored_alert'));

		db_add_column('thold_template', array(
			'name' => 'persist_ack',
			'type' => 'char(3)',
			'NULL' => false,
			'default' => 'off',
			'after' => 'reset_ack'));

		// Move modifyable Email body into thold
		db_add_column('thold_template', array(
			'name' => 'email_body',
			'type' => 'varchar(1024)',
			'NULL' => false,
			'default' => '',
			'after' => 'persist_ack'));

		db_add_column('thold_template', array(
			'name' => 'email_body_warn',
			'type' => 'varchar(1024)',
			'NULL' => false,
			'default' => '',
			'after' => 'email_body'));

		// Trigger commands
		db_add_column('thold_template', array(
			'name' => 'trigger_cmd_high',
			'type'=> 'varchar(512)',
			'NULL' => false,
			'default' => '',
			'after' => 'email_body_warn'));

		db_add_column('thold_template', array(
			'name' => 'trigger_cmd_low',
			'type'=> 'varchar(512)',
			'NULL' => false,
			'default' => '',
			'after' => 'trigger_cmd_high'));

		db_add_column('thold_template', array(
			'name' => 'trigger_cmd_norm',
			'type'=> 'varchar(512)',
			'NULL' => false,
			'default' => '',
			'after' => 'trigger_cmd_low'));

		// Additional syslog columns
		db_add_column('thold_template', array(
			'name' => 'syslog_priority',
			'type'=> 'int(2)',
			'NULL' => true,
			'after' => 'trigger_cmd_norm'));

		db_add_column('thold_template', array(
			'name' => 'syslog_facility',
			'type'=> 'int(2)',
			'NULL' => true,
			'after' => 'syslog_priority'));

		db_add_column('thold_template', array(
			'name' => 'syslog_enabled',
			'type'=> 'char(3)',
			'NULL' => false,
			'default' => '',
			'after' => 'syslog_facility'));

		// Remove this column if it exists
		db_remove_column('thold_template', 'bl_enabled');
		db_remove_column('thold_template', 'bl_ref_time');

		// If these columns were added before, change attributes
		db_execute('ALTER TABLE thold_template
			MODIFY COLUMN email_body
			varchar(1024) default ""');

		db_execute('ALTER TABLE thold_template
			MODIFY COLUMN data_template_id
			int(10) default "0"');

		db_execute('ALTER TABLE thold_template
			MODIFY COLUMN thold_fail_trigger
			int(10) unsigned default NULL');

		db_execute('ALTER TABLE thold_template
			MODIFY COLUMN repeat_alert
			int(10) default NULL');

		db_execute('ALTER TABLE thold_template
			MODIFY COLUMN notify_extra
			varchar(512) default NULL');

		db_execute('ALTER TABLE thold_template
			MODIFY COLUMN percent_ds
			varchar(64) NOT NULL default ""');

		db_execute('ALTER TABLE thold_template
			MODIFY COLUMN exempt
			char(3) NOT NULL default ""');

		db_execute('ALTER TABLE thold_template
			MODIFY COLUMN restored_alert
			char(3) NOT NULL default ""');

		// Move email notification settings to thresholds and templates
		$warning_text = read_config_option('thold_warning_text');
		$alert_text   = read_config_option('thold_warning_text');

		db_execute_prepared('UPDATE thold_data
			SET email_body = ?
			WHERE email_body = ""',
			array($alert_text));

		db_execute_prepared('UPDATE thold_template
			SET email_body = ?
			WHERE email_body = ""',
			array($alert_text));

		db_execute_prepared('UPDATE thold_data
			SET email_body_warn = ?
			WHERE email_body_warn = ""',
			array($warning_text));

		db_execute_prepared('UPDATE thold_template
			SET email_body_warn = ?
			WHERE email_body_warn = ""',
			array($warning_text));
	}

	if (cacti_version_compare($oldv, '1.2.1', '<')) {
		// Required for backward compatibility (was previously misspelled on exists check)
		if (db_column_exists('thold_data', 'acknowledgement')) {
			if (!db_column_exists('thold_data', 'acknowledgment')) {
				db_execute('ALTER TABLE thold_data
					CHANGE COLUMN acknowledgement acknowledgment
					char(3) NOT NULL default ""');
			} else {
				db_execute('UPDATE thold_data
					SET acknowledgment = acknowledgement
					WHERE acknowledgment = acknowledgement');

				db_execute('ALTER TABLE thold_data DROP COLUMN acknowledgement');
			}
		}
	}

	if (cacti_version_compare($oldv, '1.2.3', '<')) {
		db_add_column('thold_data', array(
			'name'     => 'data_template_hash',
			'type'     => 'varchar(32)',
			'NULL'     => true,
			'default'  => '',
			'after'    => 'graph_template_id'));

		db_add_column('thold_template', array(
			'name'     => 'data_template_hash',
			'type'     => 'varchar(32)',
			'NULL'     => true,
			'default'  => '',
			'after'    => 'suggested_name'));

		db_execute('UPDATE thold_data
			SET name = "|data_source_description| [|data_source_name|]"
			WHERE name = ""');

		db_execute('UPDATE thold_template
			SET suggested_name = "|data_source_description| [|data_source_name|]"
			WHERE suggested_name = ""');

		// Update thold name from template
		db_execute('UPDATE thold_data AS td
			LEFT JOIN thold_template AS tt
			ON tt.id = td.thold_template_id
			SET td.name = IF(ISNULL(tt.suggested_name), "|data_source_description| [|data_source_name|]", tt.suggested_name)
			WHERE td.name = ""');

		// Setup the name cache with the correct information
		$tholds = db_fetch_assoc('SELECT *
			FROM thold_data
			WHERE name_cache = ""');

		if (cacti_sizeof($tholds)) {
			foreach($tholds as $thold) {
				if ($thold['name_cache'] == '' || $thold['name'] == '') {
					if ($thold['name'] == '') {
						$thold['name'] = '|data_source_description| [|data_source_name|]';
					}

					$name = thold_expand_string($thold, $thold['name']);

					plugin_thold_log_changes($thold['id'], 'reapply_name', array('id' => $thold['id']));

					db_execute_prepared('UPDATE thold_data
						SET name, name_cache = ?
						WHERE id = ?',
						array($thold['name'], $name, $thold['id']));
				}
			}
		}

		// Update hashes for templates if they are incorrect
		$thold_templates = array_rekey(
			db_fetch_assoc('SELECT id, data_template_id, data_template_name, data_source_name
				FROM thold_template
				WHERE data_template_hash = ""'),
			'id', array('data_template_id', 'data_template_name', 'data_source_name')
		);

		if (cacti_sizeof($thold_templates)) {
			foreach($thold_templates as $thold_template_id => $t) {
				$template_hints = db_fetch_assoc_prepared('SELECT DISTINCT id, data_template_id
					FROM data_template_rrd
					WHERE data_source_name = ?
					AND local_data_id = 0',
					array($t['data_source_name']));

				$found = false;

				if (cacti_sizeof($template_hints)) {
					foreach($template_hints as $h) {
						$template_details = db_fetch_row_prepared('SELECT *
							FROM data_template
							WHERE id = ?
							AND name = ?',
							array($h['data_template_id'], $t['data_template_name']));

						// Update if exact match else search
						if (cacti_sizeof($template_details)) {
							db_execute_prepared('UPDATE thold_template
								SET data_source_id = ?, data_template_hash = ?, data_template_id = ?
								WHERE id = ?',
								array($h['id'], $template_details['hash'], $template_details['id'], $thold_template_id));

							$found = true;

							break;
						} else {
							$template_details = db_fetch_row_prepared('SELECT *
								FROM data_template
								WHERE name = ?',
								array($t['data_template_name']));

							if (cacti_sizeof($template_details)) {
								db_execute_prepared('UPDATE thold_template
									SET data_template_hash = ?, data_template_id = ?
									WHERE id = ?',
									array($template_details['hash'], $template_details['id'], $thold_template_id));

								$found = true;

								break;
							}
						}
					}
				}

				if (!$found) {
					cacti_log(sprintf('WARNING: Threshold Template with Name %s and ID %d Aligns with no matching Data Template', $t['name'], $t['id']), false, 'THOLD');
				}
			}
		}

		// Update thold data hashes
		db_execute('UPDATE thold_data AS td
			INNER JOIN thold_template AS tt
			ON tt.id = td.thold_template_id
			SET td.data_template_hash = tt.data_template_hash');
	}

	if (cacti_version_compare($oldv, '1.2.4', '<')) {
		db_add_column('thold_data', array(
			'name'     => 'lastchanged',
			'type'     => 'timestamp',
			'NULL'     => false,
			'default'  => '0000-00-00',
			'after'    => 'lasttime'));

		// Last Change event in the thold_data table
		db_execute('UPDATE thold_data AS td
			LEFT JOIN (
				SELECT threshold_id, MAX(time) AS time
				FROM plugin_thold_log AS ptl
				WHERE ptl.status IN (4,3,5,0,1,6)
			) AS ptl
			ON td.id = ptl.threshold_id
			SET td.lastchanged = IF(IFNULL(ptl.time, "") = "", "0000-00-00", FROM_UNIXTIME(ptl.time))');

		// Add switch to hardwire notification lists
		db_add_column('thold_template', array(
			'name'     => 'notify_templated',
			'type'     => 'char(3)',
			'NULL'     => false,
			'default'  => 'on',
			'after'    => 'notify_warning_extra'));
	}

	$tables = db_fetch_assoc("SELECT DISTINCT TABLE_NAME
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = SCHEMA()
		AND TABLE_NAME LIKE '%thold%'");

	if (sizeof($tables)) {
		foreach ($tables as $table) {
			$columns = db_fetch_assoc("SELECT *
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA=SCHEMA()
				AND TABLE_NAME='" . $table['TABLE_NAME'] . "'
				AND DATA_TYPE LIKE '%char%'
				AND COLUMN_DEFAULT IS NULL");

			if (cacti_sizeof($columns)) {
				$alter = 'ALTER TABLE `' . $table['TABLE_NAME'] . '` ';

				$i = 0;
				foreach($columns as $column) {
					$alter .= ($i == 0 ? '': ', ') . ' MODIFY COLUMN `' . $column['COLUMN_NAME'] . '` ' . $column['COLUMN_TYPE'] . ($column['IS_NULLABLE'] == 'NO' ? ' NOT NULL' : '') . ' DEFAULT ""';
					$i++;
				}

				db_execute($alter);
			}
		}
	}

	db_execute_prepared('UPDATE plugin_config
		SET version = ?
		WHERE directory = "thold"',
		array($v['version']));
}

function thold_setup_database() {
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'name_cache', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'local_data_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template_rrd_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'local_graph_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'graph_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template_hash', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_source_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'thold_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'time_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_low',	'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_warning_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_warning_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_warning_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'thold_warning_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'time_warning_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_warning_low',	'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_warning_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_warning_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_alert', 'type' => 'int(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'prev_thold_alert', 'type' => 'int(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'thold_enabled', 'type' => "enum('on','off')", 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'bl_ref_time_range', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_pct_down', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'bl_pct_up', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'bl_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_fail_count', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_alert', 'type' => 'int(2)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'bl_thold_valid', 'type' => 'int(10)', 'NULL' => false, 'default' => '0', 'unsigned' => true);
	$data['columns'][] = array('name' => 'lastread', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'lasttime', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'lastchanged', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'oldvalue', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'repeat_alert', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'notify_extra', 'type' => 'varchar(512)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_warning_extra', 'type' => 'varchar(512)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_warning', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'notify_alert', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'snmp_event_category', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_event_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'snmp_event_warning_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '2');
	$data['columns'][] = array('name' => 'thold_daemon_pid', 'type' => 'varchar(25)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(10)', 'NULL' => true);
	$data['columns'][] = array('name' => 'syslog_priority', 'type' => 'int(2)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'syslog_facility', 'type' => 'int(2)', 'NULL' => true);
	$data['columns'][] = array('name' => 'syslog_enabled', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_type', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'cdef', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'expression', 'type' => 'varchar(512)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'template_enabled', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'tcheck', 'type' => 'int(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'acknowledgment', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_hrule_alert', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_hrule_warning', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true);
	$data['columns'][] = array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'reset_ack', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'persist_ack', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'email_body', 'type' => 'varchar(1024)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'email_body_warn', 'type' => 'varchar(1024)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'trigger_cmd_high', 'type'=> 'varchar(512)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'trigger_cmd_low', 'type'=> 'varchar(512)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'trigger_cmd_norm', 'type'=> 'varchar(512)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'notes', 'type' => 'varchar(1024)', 'NULL' => true, 'default' => '');

	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][] = array('name' => 'local_data_id', 'columns' => 'local_data_id');
	$data['keys'][] = array('name' => 'data_template_rrd_id', 'columns' => 'data_template_rrd_id');
	$data['keys'][] = array('name' => 'local_graph_id', 'columns' => 'local_graph_id');
	$data['keys'][] = array('name' => 'thold_template_id', 'columns' => 'thold_template_id');
	$data['keys'][] = array('name' => 'thold_enabled', 'columns' => 'thold_enabled');
	$data['keys'][] = array('name' => 'template_enabled', 'columns' => 'template_enabled');
	$data['keys'][] = array('name' => 'tcheck', 'columns' => 'tcheck');
	$data['keys'][] = array('name' => 'thold_daemon_pid', 'columns' => 'thold_daemon_pid');
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Threshold data';
	api_plugin_db_table_create('thold', 'thold_data', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'suggested_name', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_template_id', 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_template_hash', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_template_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_source_id', 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'data_source_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'data_source_friendly', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'time_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_warning_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_warning_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_warning_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'thold_warning_fail_count', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'time_warning_hi', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_warning_low', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'time_warning_fail_trigger', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'time_warning_fail_length', 'type' => 'int (12)', 'NULL' => false, 'default' => 1);
	$data['columns'][] = array('name' => 'thold_enabled', 'type' => "enum('on','off')", 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'thold_type', 'type' => 'int (3)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'bl_ref_time_range', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_pct_down', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'bl_pct_up', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'bl_fail_trigger', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_fail_count', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'bl_alert', 'type' => 'int(2)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'repeat_alert', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'notify_extra', 'type' => 'varchar(512)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_warning_extra', 'type' => 'varchar(512)', 'NULL' => true);
	$data['columns'][] = array('name' => 'notify_templated', 'type' => 'char(3)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'notify_warning', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'notify_alert', 'type' => 'int(10)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'snmp_event_category', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_event_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'snmp_event_warning_severity', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '2');
	$data['columns'][] = array('name' => 'data_type', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'cdef', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'percent_ds', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'expression', 'type' => 'varchar(512)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'exempt', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'thold_hrule_alert', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true);
	$data['columns'][] = array('name' => 'thold_hrule_warning', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true);
	$data['columns'][] = array('name' => 'restored_alert', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'reset_ack', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'persist_ack', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'email_body', 'type' => 'varchar(1024)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'email_body_warn', 'type' => 'varchar(1024)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'trigger_cmd_high', 'type'=> 'varchar(512)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'trigger_cmd_low', 'type'=> 'varchar(512)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'trigger_cmd_norm', 'type'=> 'varchar(512)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'syslog_priority', 'type' => 'int(2)', 'NULL' => true);
	$data['columns'][] = array('name' => 'syslog_facility', 'type' => 'int(2)', 'NULL' => true);
	$data['columns'][] = array('name' => 'syslog_enabled', 'type' => 'char(3)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'notes', 'type' => 'varchar(1024)', 'NULL' => true, 'default' => '');

	$data['primary']   = 'id';
	$data['keys'][]    = array('name' => 'id', 'columns' => 'id');
	$data['keys'][]    = array('name' => 'data_source_id', 'columns' => 'data_source_id');
	$data['keys'][]    = array('name' => 'data_template_id', 'columns' => 'data_template_id');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Thresholds defaults for graphs';
	api_plugin_db_table_create('thold', 'thold_template', $data);

	$data = array();
	$data['columns'][]     = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][]     = array('name' => 'user_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][]     = array('name' => 'type', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][]     = array('name' => 'data', 'type' => 'text', 'NULL' => false);
	$data['primary']       = 'id';
	$data['keys'][]        = array('name' => 'type', 'columns' => 'type');
	$data['keys'][]        = array('name' => 'user_id', 'columns' => 'user_id');
	$data['unique_keys'][] = array('name' => 'user_id_type', 'columns' => 'user_id`, `type');
	$data['type']          = 'InnoDB';
	$data['comment']       = 'Table of Threshold contacts';
	api_plugin_db_table_create('thold', 'plugin_thold_contacts', $data);

	$data = array();
	$data['columns'][] = array('name' => 'template_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'contact_id', 'type' => 'int(12)', 'NULL' => false);
	$data['keys'][]    = array('name' => 'template_id', 'columns' => 'template_id');
	$data['keys'][]    = array('name' => 'contact_id', 'columns' => 'contact_id');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Tholds Template Contacts';
	api_plugin_db_table_create('thold', 'plugin_thold_template_contact', $data);

	$data = array();
	$data['columns'][] = array('name' => 'thold_id', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'contact_id', 'type' => 'int(12)', 'NULL' => false);

	$data['keys'][]    = array('name' => 'thold_id', 'columns' => 'thold_id');
	$data['keys'][]    = array('name' => 'contact_id', 'columns' => 'contact_id');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Tholds Threshold Contacts';
	api_plugin_db_table_create('thold', 'plugin_thold_threshold_contact', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'time', 'type' => 'int(24)', 'NULL' => false);
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'local_graph_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'threshold_id', 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'threshold_value', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'current', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'status', 'type' => 'int(5)', 'NULL' => false);
	$data['columns'][] = array('name' => 'type', 'type' => 'int(5)', 'NULL' => false);
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(255)', 'NULL' => false);

	$data['primary']   = 'id';
	$data['keys'][]    = array('name' => 'time', 'columns' => 'time');
	$data['keys'][]    = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][]    = array('name' => 'local_graph_id', 'columns' => 'local_graph_id');
	$data['keys'][]    = array('name' => 'threshold_id', 'columns' => 'threshold_id');
	$data['keys'][]    = array('name' => 'status', 'columns' => 'status');
	$data['keys'][]    = array('name' => 'type', 'columns' => 'type');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of All Threshold Breaches';
	api_plugin_db_table_create('thold', 'plugin_thold_log', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(128)', 'NULL' => false);
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(512)', 'NULL' => false);
	$data['columns'][] = array('name' => 'emails', 'type' => 'varchar(512)', 'NULL' => false);

	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Notification Lists';
	api_plugin_db_table_create('thold', 'plugin_notification_lists', $data);

	api_plugin_register_hook('thold', 'host_edit_bottom', 'thold_host_edit_bottom', 'setup.php', '1');

	api_plugin_db_add_column('thold', 'host', array('name' => 'thold_send_email', 'type' => 'int(10)', 'NULL' => false, 'default' => '1', 'after' => 'disabled'));
	api_plugin_db_add_column('thold', 'host', array('name' => 'thold_host_email', 'type' => 'int(10)', 'NULL' => true, 'after' => 'thold_send_email'));

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'unsigned' => true, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(12)', 'unsigned' => true, 'NULL' => false);

	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Devices in a Down State';
	api_plugin_db_table_create('thold', 'plugin_thold_host_failed', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'pid', 'type' => 'varchar(25)', 'NULL' => false);
	$data['columns'][] = array('name' => 'rrd_reindexed', 'type' => 'varchar(600)', 'NULL' => false);
	$data['columns'][] = array('name' => 'rrd_time_reindexed', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false);

	$data['keys'][]    = array('name' => 'id', 'columns' => 'id`, `pid');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Poller Outdata needed for queued daemon processes';
	api_plugin_db_table_create('thold', 'plugin_thold_daemon_data', $data);

	$data = array();
	$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'pid', 'type' => 'varchar(25)', 'NULL' => false);
	$data['columns'][] = array('name' => 'start', 'type' => 'double', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'end', 'type' => 'double', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'processed_items', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');

	$data['primary']   = 'pid';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table of Thold Daemon Processes being queued';
	api_plugin_db_table_create('thold', 'plugin_thold_daemon_processes', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'host_template_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'thold_template_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Table of Device Template Threshold Templates';
	api_plugin_db_table_create('thold', 'plugin_thold_host_template', $data);

	db_add_index('data_local', 'INDEX', 'data_template_id', array('data_template_id'));
	db_add_index('data_local', 'INDEX', 'snmp_query_id', array('snmp_query_id'));
	db_add_index('host_snmp_cache', 'INDEX', 'snmp_query_id', array('snmp_query_id'));

	/* increase the size of the settings table */
	db_execute("ALTER TABLE settings
		MODIFY COLUMN `value`
		varchar(4096) NOT NULL default ''");

	db_execute('ALTER TABLE plugin_thold_log
		MODIFY COLUMN current varchar(64) NOT NULL default ""');
}

