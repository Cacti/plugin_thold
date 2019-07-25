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

function plugin_thold_install($upgrade = false) {
	global $config;

	if (version_compare($config['cacti_version'], '1.2') < 0) {
		return false;
	}

	$plugin = 'thold';

	// Insert CSS and JavaScript
	api_plugin_register_hook($plugin, 'page_head', 'thold_page_head', 'setup.php');

	// Add the Thold tab
	api_plugin_register_hook($plugin, 'top_header_tabs', 'thold_show_tab', 'includes/tab.php');
	api_plugin_register_hook($plugin, 'top_graph_header_tabs', 'thold_show_tab', 'includes/tab.php');

	// Settings forms and arrays
	api_plugin_register_hook($plugin, 'config_insert', 'thold_config_insert', 'includes/settings.php');
	api_plugin_register_hook($plugin, 'config_arrays', 'thold_config_arrays', 'includes/settings.php');
	api_plugin_register_hook($plugin, 'config_form', 'thold_config_form', 'includes/settings.php');
	api_plugin_register_hook($plugin, 'config_settings', 'thold_config_settings', 'includes/settings.php');

	// Breadcrums
	api_plugin_register_hook($plugin, 'draw_navigation_text', 'thold_draw_navigation_text', 'includes/settings.php');

	// Inline thold checks
	api_plugin_register_hook($plugin, 'poller_output', 'thold_poller_output', 'includes/polling.php');

	// Device Hooks
	api_plugin_register_hook($plugin, 'device_action_array', 'thold_device_action_array', 'setup.php');
	api_plugin_register_hook($plugin, 'device_action_execute', 'thold_device_action_execute', 'setup.php');
	api_plugin_register_hook($plugin, 'device_action_prepare', 'thold_device_action_prepare', 'setup.php');
	api_plugin_register_hook($plugin, 'api_device_save', 'thold_api_device_save', 'setup.php');
	api_plugin_register_hook($plugin, 'host_edit_bottom', 'thold_host_edit_bottom', 'setup.php');
	api_plugin_register_hook($plugin, 'device_threshold_autocreate', 'thold_device_autocreate', 'setup.php');

	// Automation Hooks
	api_plugin_register_hook($plugin, 'create_complete_graph_from_template', 'thold_create_graph_thold', 'setup.php');

	// Hooks to enable thold maintenance
	api_plugin_register_hook($plugin, 'poller_bottom', 'thold_poller_bottom', 'includes/polling.php');

	// Setup buttons on Graph Pages
	api_plugin_register_hook($plugin, 'graph_buttons', 'thold_graph_button', 'setup.php');
	api_plugin_register_hook($plugin, 'graph_buttons_thumbnails', 'thold_graph_button', 'setup.php');

	// Data Source Hooks
	api_plugin_register_hook($plugin, 'data_source_action_array', 'thold_data_source_action_array', 'setup.php');
	api_plugin_register_hook($plugin, 'data_source_action_prepare', 'thold_data_source_action_prepare', 'setup.php');
	api_plugin_register_hook($plugin, 'data_source_action_execute', 'thold_data_source_action_execute', 'setup.php');
	api_plugin_register_hook($plugin, 'data_source_remove', 'thold_data_source_remove', 'setup.php');

	// Create Threshold from Data Source table
	api_plugin_register_hook($plugin, 'data_sources_table', 'thold_data_sources_table', 'setup.php');

	// Follow Graph Actions
	api_plugin_register_hook($plugin, 'graphs_action_array', 'thold_graphs_action_array', 'setup.php');
	api_plugin_register_hook($plugin, 'graphs_action_prepare', 'thold_graphs_action_prepare', 'setup.php');
	api_plugin_register_hook($plugin, 'graphs_action_execute', 'thold_graphs_action_execute', 'setup.php');

	// Follow Device Template Actions
	api_plugin_register_hook($plugin, 'device_template_edit', 'thold_device_template_edit', 'setup.php');
	api_plugin_register_hook($plugin, 'device_template_top', 'thold_device_template_top', 'setup.php');

	// Display Threshold Templates in Devices
	api_plugin_register_hook($plugin, 'device_edit_pre_bottom', 'thold_device_edit_pre_bottom', 'setup.php');

	// Follow New Graph Actions
	api_plugin_register_hook($plugin, 'api_device_new', 'thold_api_device_new', 'setup.php');

	// Miscelaneious hooks
	api_plugin_register_hook($plugin, 'graphs_new_top_links', 'thold_graphs_new', 'setup.php');
	api_plugin_register_hook($plugin, 'update_host_status', 'thold_update_host_status', 'includes/polling.php');
	api_plugin_register_hook($plugin, 'user_admin_setup_sql_save', 'thold_user_admin_setup_sql_save', 'setup.php');
	api_plugin_register_hook($plugin, 'rrd_graph_graph_options', 'thold_rrd_graph_graph_options', 'setup.php');
	api_plugin_register_hook($plugin, 'snmpagent_cache_install', 'thold_snmpagent_cache_install', 'setup.php');
	api_plugin_register_hook($plugin, 'clog_regex_array', 'thold_clog_regex_array', 'setup.php');

	// Setup permissions
	api_plugin_register_realm($plugin, 'thold.php', 'Configure Thresholds', 1);
	api_plugin_register_realm($plugin, 'thold_templates.php', 'Configure Threshold Templates', 1);
	api_plugin_register_realm($plugin, 'notify_lists.php', 'Manage Notification Lists', 1);
	api_plugin_register_realm($plugin, 'thold_graph.php,graph_thold.php,thold_view_failures.php,thold_view_normal.php,thold_view_recover.php,thold_view_recent.php,thold_view_host.php', 'View Thresholds', 1);

	include_once($config['base_path'] . '/plugins/thold/includes/database.php');

	if ($upgrade) {
		thold_upgrade_database();
		if (api_plugin_is_enabled($plugin)) {
			api_plugin_enable_hooks($plugin);
		}
	} else {
		thold_setup_database();
		thold_snmpagent_cache_install();
	}
}

function plugin_thold_uninstall() {
	// Do any extra Uninstall stuff here
	thold_snmpagent_cache_uninstall();

	// Remove items from the settings table
	db_execute('DELETE FROM settings
		WHERE name LIKE "%thold%"');
}

function plugin_thold_check_config() {
	// Here we will check to ensure everything is configured
	plugin_thold_upgrade();
	return true;
}

function plugin_thold_upgrade() {
	// Here we will upgrade to the newest version
	global $config;

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('thold.php', 'thold_graph.php', 'thold_templates.php', 'poller.php', 'plugins.php', 'clog.php');
	if (!in_array(get_current_page(), $files)) {
		return false;
	}

	$info    = plugin_thold_version();
	$current = $info['version'];
	$old     = db_fetch_cell('SELECT version
		FROM plugin_config
		WHERE directory="thold"');

	if ($current != $old) {
		plugin_thold_install(true);
	}

	return true;
}

function plugin_thold_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/thold/INFO', true);

	return $info['info'];
}

function thold_check_dependencies() {
	return true;
}

function plugin_thold_check_strict() {
	$mode = db_fetch_cell("select @@global.sql_mode", false);
	if (stristr($mode, 'strict') !== FALSE) {
		return false;
	}

	return true;
}

function thold_graph_button($data) {
	global $config;

	$local_graph_id = $data[1]['local_graph_id'];

	$thold_id = db_fetch_cell_prepared('SELECT id
		FROM thold_data
		WHERE local_graph_id = ?',
		array($local_graph_id));

	$rra_id = $data[1]['rra'];
	if (isset_request_var('view_type') && !isempty_request_var('view_type')) {
		$view_type = get_request_var('view_type');
	} else {
		set_request_var('view_type', '');
		$view_type = read_config_option('dataquery_type');
	}

	if (isset_request_var('graph_start') && !isempty_request_var('graph_start')) {
		$start = get_filter_request_var('graph_start');
	} else {
		set_request_var('graph_start', '');
		$start = time() - 3600;
	}

	if (isset_request_var('graph_end') && !isempty_request_var('graph_end')) {
		$end = get_filter_request_var('graph_end');
	} else {
		set_request_var('graph_end', '');
		$end = time();
	}

	if (isset_request_var('thold_vrule') || isset($_SESSION['sess_config_array']['thold_draw_vrules'])) {
		if (isset_request_var('thold_vrule')) {
			if (get_nfilter_request_var('thold_vrule') == 'on') {
				$vrules = 'off';
			} else {
				$vrules = 'on';
			}

			$_SESSION['sess_config_array']['thold_draw_vrules'] = $vrules;
		} else {
			$vrules = $_SESSION['sess_config_array']['thold_draw_vrules'];
		}
	} else {
		$vrules = 'off';
		$_SESSION['sess_config_array']['thold_draw_vrules'] = $vrules;
	}

	$url = $_SERVER['REQUEST_URI'];
	$url = str_replace('&thold_vrule=on', '', $url);
	$url = str_replace('&thold_vrule=off', '', $url);

	if (!substr_count($url, '?')) {
		$separator = '?';
	} else {
		$separator = '&';
	}

	if (api_user_realm_auth('thold_graph.php') && !empty($thold_id)) {
		print '<a class="iconLink tholdVRule" href="' .  html_escape($url . $separator . 'thold_vrule=' . $vrules) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/reddot.png" alt="" title="' . __esc('Toggle Threshold VRULES %s', ($vrules == 'on' ? __('Off') : __('On')), 'thold') . '"></a><br>';
	}

	// Add Threshold Creation button
	if (api_user_realm_auth('thold.php')) {
		if (isset_request_var('tree_id')) {
			get_filter_request_var('tree_id');
		}

		if (isset_request_var('leaf_id')) {
			get_filter_request_var('leaf_id');
		}

		$is_aggregate = db_fetch_cell_prepared('SELECT id
			FROM aggregate_graphs
			WHERE local_graph_id = ?',
			array($local_graph_id));

		if (empty($is_aggregate)) {
			print '<a class="iconLink" href="' . html_escape($config['url_path'] . 'plugins/thold/thold.php?action=add' . '&usetemplate=1&local_graph_id=' . $local_graph_id) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" alt="" title="' . __esc('Create Threshold', 'thold') . '"></a><br>';
		}
	}
}

function thold_multiexplode($delimiters, $string) {
	$ready = str_replace($delimiters, $delimiters[0], $string);
	return  @explode($delimiters[0], $ready);
}

function thold_rrd_graph_graph_options($g) {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	/* handle thold replacement variables */
	$needles      = array();
	$replacements = array();

	/* map the data_template_rrd_id's to the datasource names */
	$defs = explode("\\\n", $g['graph_defs'], -1);
	if (is_array($defs)) {
		foreach ($defs as $def) {
			if (!substr_count($def, 'CDEF') && !substr_count($def, 'VDEF')) {
				$ddef   = thold_multiexplode(array('"', "'"), $def);
				$kdef   = explode(':', $def);
				$dsname = $kdef[2];
				$temp1  = str_replace('.rrd', '', basename($ddef[1]));
				if (substr_count(basename($ddef[1]), '_') == 0) {
					$local_data_id = $temp1;
				} else {
					$temp2 = explode('_', $temp1);
					$local_data_id = $temp2[sizeof($temp2)-1];
				}
				$dsname = trim($dsname, "'\" ");
				$data_template_rrd[$dsname] = $local_data_id;

				// Map the dsnames to def id's for percentile
				$ndef = explode('=', $kdef[1]);
				$data_defs[$dsname] = $ndef[0];
			}
		}
	}

	/* look for any variables to replace */
	$txt_items = explode("\\\n", $g['txt_graph_items']);
	foreach ($txt_items as $item) {
		if (substr_count($item, '|thold')) {
			preg_match("/\|thold\\\:(hi|low)\\\:(.+)\|/", $item, $matches);

			if (count($matches) == 3) {
				$needles[] = $matches[0];
				$data_source = explode('|', $matches[2]);

				/* look up the data_id from the data source name and data_template_rrd */
				$data_template_rrd_id = db_fetch_cell_prepared('SELECT id
					FROM data_template_rrd
					WHERE local_data_id = ?
					AND data_source_name = ?',
					array($data_template_rrd[$data_source[0]], $data_source[0]));

				$thold_type = db_fetch_cell_prepared('SELECT thold_type
					FROM thold_data
					WHERE thold_enabled="on"
					AND data_template_rrd_id = ?',
					array($data_template_rrd_id));

				/* fetch the value from thold */
				if ($thold_type == '') {
					$value = '';
				} elseif ($thold_type == 0 || $thold_type == 1) { // Hi/Low & Baseline
					$value = db_fetch_cell_prepared('SELECT thold_' . $matches[1] . '
						FROM thold_data
						WHERE data_template_rrd_id = ?',
						array($data_template_rrd_id));

				} elseif ($thold_type == 1) {  // Time Based
					$value = db_fetch_cell_prepared('SELECT time_' . $matches[1] . '
						FROM thold_data
						WHERE data_template_rrd_id = ?',
						array($data_template_rrd_id));
				}

				if ($value == '' || !is_numeric($value)) {
					$replacements['|thold\:' . $matches[1] . '\:' . $data_source[0] . '|'] = 'strip';
				} else {
					$replacements['|thold\:' . $matches[1] . '\:' . $data_source[0] . '|'] = $value;
				}
			}

			preg_match("/\|thold\\\:(warning_hi|warning_low)\\\:(.+)\|/", $item, $matches);

			if (count($matches) == 3) {
				$needles[] = $matches[0];
				$data_source = explode('|', $matches[2]);

				/* look up the data_id from the data source name and data_template_rrd_id */
				$data_template_rrd_id = db_fetch_cell_prepared('SELECT id
					FROM data_template_rrd
					WHERE local_data_id = ?
					AND data_source_name = ?',
					array($data_template_rrd[$data_source[0]], $data_source[0]));

				$thold_type = db_fetch_cell_prepared('SELECT thold_type
					FROM thold_data
					WHERE thold_enabled="on"
					AND data_template_rrd_id = ?',
					array($data_template_rrd_id));

				/* fetch the value from thold */
				if ($thold_type == '') {
					$value = '';
				} elseif ($thold_type == 0 || $thold_type == 1) { // Hi/Low & Baseline
					$value = db_fetch_cell_prepared('SELECT thold_' . $matches[1] . '
						FROM thold_data
						WHERE data_template_rrd_id = ?',
						array($data_template_rrd_id));
				} elseif ($thold_type == 1) { // Time Based
					$value = db_fetch_cell_prepared('SELECT time_' . $matches[1] . '
						FROM thold_data
						WHERE data_template_rrd_id = ?',
						array($data_template_rrd_id));
				}

				if ($value == '' || !is_numeric($value)) {
					$replacements['|thold\:' . $matches[1] . '\:' . $data_source[0] . '|'] = 'strip';
				} else {
					$replacements['|thold\:' . $matches[1] . '\:' . $data_source[0] . '|'] = $value;
				}
			}
		}
	}

	// do we have any needles to replace?
	$i = 0;
	$unsets = array();
	if (is_array($replacements)) {
		foreach($txt_items as $item) {
			foreach($replacements as $key => $replace) {
				//cacti_log('Key:' . $key . ', Replace:' . $replace, false);
				if (substr_count($item, $key)) {
					if ($replace == 'strip') {
						$unsets[] = $i;
					} else {
						$txt_items[$i] = str_replace($key, $replace, $item);
					}
				}
			}

			$i++;
		}

		if (cacti_sizeof($unsets)) {
			foreach($unsets as $i) {
				unset($txt_items[$i]);
			}
		}

		$g['txt_graph_items'] = implode("\\\n", $txt_items);
	}

	$id = $g['graph_id'];

	//print "<pre>"; print_r($g);print "</pre>";

	if (isset($_SESSION['sess_config_array']['thold_draw_vrules']) && $_SESSION['sess_config_array']['thold_draw_vrules'] == 'on') {
		$end = $g['end'];
		if ($end < 0)
			$end = time() + $end;
		$end++;

		$start = $g['start'];
		if ($start < 0)
			$start = $end + $start;
		$start--;

		if ($id) {
			$rows = db_fetch_assoc_prepared('SELECT time, status
				FROM plugin_thold_log
				WHERE local_graph_id = ?
				AND type = 0
				AND time > ?
				AND time < ?',
				array($id, $start, $end));

			if (cacti_sizeof($rows)) {
				foreach ($rows as $row) {
					switch($row['status']) {
					case '3':
						$color = '#CC6600';
						break;
					case '4':
						$color = '#FF0000';
						break;
					case '5':
						$color = '#00FF00';
						break;
					}

					$g['graph_defs'] .= 'VRULE:' . $row['time'] . $color . ' \\' . "\n";
				}
			}
		}
	}

	$tholds_w_hrule = db_fetch_assoc_prepared('SELECT *
		FROM thold_data
		WHERE thold_enabled = 1
		AND data_type IN (0, 1, 2)
		AND (thold_hrule_alert > 0 || thold_hrule_warning > 0)
		&& local_graph_id = ?',
		array($id));

	$thold_id = 0;
	$txt_graph_items = '';
	if (cacti_sizeof($tholds_w_hrule)) {
		foreach($tholds_w_hrule as $t) {
			// Adjust number for graph
			thold_modify_values_by_cdef($t);

			$baseu = db_fetch_cell_prepared('SELECT base_value
				FROM graph_templates_graph
				WHERE local_graph_id = ?',
				array($t['local_graph_id']));

			if ($t['data_type'] == 2) {
				$suffix = false;
			} else {
				$suffix = true;
			}

			switch($t['data_type']) {
			case '0': // Exact value
			case '1': // CDEF
				if ($t['thold_hrule_alert'] > 0) {
					$color = db_fetch_cell_prepared('SELECT hex
						FROM colors
						WHERE id = ?',
						array($t['thold_hrule_alert']));

					switch($t['thold_type']) {
					case '0': // Hi / Low
						if ($t['thold_hi'] != '') {
							$txt_graph_items .= 'LINE1:' . $t['thold_hi'] . '#' . $color . ':' . thold_prep_rrd_string(__('Alert Hi for %s (%s)', $t['name_cache'], thold_format_number($t['thold_hi'], 2, $baseu, $suffix), 'thold')) . ' \\' . "\n";
						}

						if ($t['thold_low'] != '') {
							$txt_graph_items .= 'LINE1:' . $t['thold_low'] . '#' . $color . ':' . thold_prep_rrd_string(__('Alert Low for %s (%s)', $t['name_cache'], thold_format_number($t['thold_low'], 2, $baseu, $suffix), 'thold')) . ' \\' . "\n";
						}

						break;
					case '2': // Time Based
						if ($t['time_hi'] != '') {
							$txt_graph_items .= 'LINE1:' . $t['time_hi'] . '#' . $color . ':' . thold_prep_rrd_string(__('Alert Hi for %s (%s)', $t['name_cache'], thold_format_number($t['time_hi'], 2, $baseu, $suffix), 'thold')) . ' \\' . "\n";
						}

						if ($t['time_low'] != '') {
							$txt_graph_items .= 'LINE1:' . $t['time_low'] . '#' . $color . ':' . thold_prep_rrd_string(__('Alert Low for %s (%s)', $t['name_cache'], thold_format_number($t['time_low'], 2, $baseu, $suffix), 'thold')) . ' \\' . "\n";
						}

						break;
					}
				}

				if ($t['thold_hrule_warning'] > 0) {
					$color = db_fetch_cell_prepared('SELECT hex
						FROM colors
						WHERE id = ?',
						array($t['thold_hrule_warning']));

					switch($t['thold_type']) {
					case '0': // Hi / Low
						if ($t['thold_warning_hi'] != '') {
							$txt_graph_items .= 'LINE1:' . $t['thold_warning_hi'] . '#' . $color . ':' . thold_prep_rrd_string(__('Warning Hi for %s (%s)', $t['name_cache'], thold_format_number($t['thold_warning_hi'], 2, $baseu, $suffix), 'thold')) . ' \\' . "\n";
						}

						if ($t['thold_warning_low'] != '') {
							$txt_graph_items .= 'LINE1:' . $t['thold_warning_low'] . '#' . $color . ':' . thold_prep_rrd_string(__('Warning Low for %s (%s)', $t['name_cache'], thold_format_number($t['thold_warning_low'], 2, $baseu, $suffix), 'thold')) . ' \\' . "\n";
						}

						break;
					case '2': // Time Based
						if ($t['time_warning_hi'] != '') {
							$txt_graph_items .= 'LINE1:' . $t['time_warning_hi'] . '#' . $color . ':' . thold_prep_rrd_string(__('Warning Hi for %s (%s)', $t['name_cache'], thold_format_number($t['time_warning_hi'], 2, $baseu, $suffix), 'thold')) . ' \\' . "\n";
						}

						if ($t['time_warning_low'] != '') {
							$txt_graph_items .= 'LINE1:' . $t['time_warning_low'] . '#' . $color . ':' . thold_prep_rrd_string(__('Warning Low for %s (%s)', $t['name_cache'], thold_format_number($t['time_warning_low'], 2, $baseu, $suffix), 'thold')) . ' \\' . "\n";
						}

						break;
					}
				}

				break;
			case '2': // Percentage
				if (isset($data_defs[$t['percent_ds']])) {
					if ($t['thold_hrule_alert'] > 0) {
						$color = db_fetch_cell_prepared('SELECT hex
							FROM colors
							WHERE id = ?',
							array($t['thold_hrule_alert']));

						switch($t['thold_type']) {
						case '0': // Hi / Low
							if ($t['thold_hi'] != '') {
								$g['graph_defs'] .= 'CDEF:th' . $thold_id . 'ahi=' . $data_defs[$t['percent_ds']] . ',' . $t['thold_hi'] . ',100,/,* \\' . "\n";
								$txt_graph_items .= 'LINE1:th' . $thold_id . 'ahi#' . $color . ':' . thold_prep_rrd_string(__('Alert Hi for %s (%s %%)', $t['name_cache'], number_format_i18n($t['thold_hi']), 'thold')) . ' \\' . "\n";
								$thold_id++;
							}

							if ($t['thold_low'] != '') {
								$g['graph_defs'] .= 'CDEF:th' . $thold_id . 'alow=' . $data_defs[$t['percent_ds']] . ',' . $t['thold_low'] . ',100,/,* \\' . "\n";
								$txt_graph_items .= 'LINE1:th' . $thold_id . 'alow#' . $color . ':' . thold_prep_rrd_string(__('Alert Low for %s (%s %%)', $t['name_cache'], number_format_i18n($t['thold_low']), 'thold')) . ' \\' . "\n";
								$thold_id++;
							}

							break;
						case '2': // Time Based
							if ($t['time_hi'] != '') {
								$g['graph_defs'] .= 'CDEF:th' . $thold_id . 'ahi=' . $data_defs[$t['percent_ds']] . ',' . $t['time_hi'] . ',100,/,* \\' . "\n";
								$txt_graph_items .= 'LINE1:th' . $thold_id . 'ahi#' . $color . ':' . thold_prep_rrd_string(__('Alert Hi for %s (%s %%)', $t['name_cache'], number_format_i18n($t['time_hi']), 'thold')) . ' \\' . "\n";
								$thold_id++;
							}

							if ($t['time_low'] != '') {
								$g['graph_defs'] .= 'CDEF:th' . $thold_id . 'alow=' . $data_defs[$t['percent_ds']] . ',' . $t['time_low'] . ',100,/,* \\' . "\n";
								$txt_graph_items .= 'LINE1:th' . $thold_id . 'alow#' . $color . ':' . thold_prep_rrd_string(__('Alert Low for %s (%s %%)', $t['name_cache'], number_format_i18n($t['time_low']), 'thold')) . ' \\' . "\n";
								$thold_id++;
							}

							break;
						}
					}

					if ($t['thold_hrule_warning'] > 0) {
						$color = db_fetch_cell_prepared('SELECT hex
							FROM colors
							WHERE id = ?',
							array($t['thold_hrule_warning']));

						switch($t['thold_type']) {
						case '0': // Hi / Low
							if ($t['thold_warning_hi'] != '') {
								$g['graph_defs'] .= 'CDEF:th' . $thold_id . 'whi=' . $data_defs[$t['percent_ds']] . ',' . $t['thold_warning_hi'] . ',100,/,* \\' . "\n";
								$txt_graph_items .= 'LINE1:th' . $thold_id . 'whi#' . $color . ':' . thold_prep_rrd_string(__('Warning Hi for %s (%s %%)', $t['name_cache'], number_format_i18n($t['thold_warning_hi']), 'thold')) . ' \\' . "\n";
								$thold_id++;
							}

							if ($t['thold_warning_low'] != '') {
								$g['graph_defs'] .= 'CDEF:th' . $thold_id . 'wlow=' . $data_defs[$t['percent_ds']] . ',' . $t['thold_warning_low'] . ',100,/,* \\' . "\n";
								$txt_graph_items .= 'LINE1:th' . $thold_id . 'wlow#' . $color . ':' . thold_prep_rrd_string(__('Warning Low for %s (%s %%)', $t['name_cache'], number_format_i18n($t['thold_warning_low']), 'thold')) . ' \\' . "\n";
								$thold_id++;
							}

							break;
						case '2': // Time Based
							if ($t['time_warning_hi'] != '') {
								$g['graph_defs'] .= 'CDEF:th' . $thold_id . 'whi=' . $data_defs[$t['percent_ds']] . ',' . $t['time_warning_hi'] . ',100,/,* \\' . "\n";
								$txt_graph_items .= 'LINE1:th' . $thold_id . 'whi#' . $color . ':' . thold_prep_rrd_string(__('Warning Hi for %s (%s %%)', $t['name_cache'], number_format_i18n($t['time_warning_hi']), 'thold')) . ' \\' . "\n";
								$thold_id++;
							}

							if ($t['time_warning_low'] != '') {
								$g['graph_defs'] .= 'CDEF:th' . $thold_id . 'wlow=' . $data_defs[$t['percent_ds']] . ',' . $t['time_warning_low'] . ',100,/,* \\' . "\n";
								$txt_graph_items .= 'LINE1:th' . $thold_id . 'wlow#' . $color . ':' . thold_prep_rrd_string(__('Warning Low for %s (%s %%)', $t['name_cache'], number_format_i18n($t['time_warning_low']), 'thold')) . ' \\' . "\n";
								$thold_id++;
							}

							break;
						}
					}
				}
			}
		}
	}

	if ($txt_graph_items) {
		$g['txt_graph_items'] .= ' \\' . "\n" . 'COMMENT:\' ' . "\\n" . '\' \\' . "\n" . 'COMMENT:\'<u><b>' . __('Threshold Alert/Warning Values', 'thold') . '</b></u>' . "\\n" . '\' \\' . "\n" . $txt_graph_items;
	}

	return $g;
}

function thold_prep_rrd_string($string) {
	return '\'' . trim(cacti_escapeshellarg(rrdtool_escape_string($string)), "'") . '\'';
}

function thold_device_action_execute($action) {
	global $config;

	if ($action != 'thold') {
		return $action;
	}

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

	if ($selected_items != false) {
		for ($i=0; ($i < count($selected_items)); $i++) {
			autocreate($selected_items[$i]);
		}
	}

	return $action;
}

function thold_api_device_new($save) {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	if (read_config_option('thold_autocreate') == 'on') {
		if (!empty($save['id'])) {
			autocreate($save['id']);
		}
	}

	return $save;
}

function thold_device_action_prepare($save) {
	global $host_list;

	if ($save['drp_action'] != 'thold') {
		return $save;
	}

	print "<tr>
		<td colspan='2' class='textArea'>
			<p>" . __('Click \'Continue\' to apply all appropriate Thresholds to these Device(s).', 'thold') . "</p>
			<ul>" . $save['host_list'] . "</ul>
		</td>
	</tr>";
}

function thold_device_action_array($device_action_array) {
	$device_action_array['thold'] = 'Apply Thresholds';

	return $device_action_array;
}

function thold_api_device_save($save) {
	global $config;

	$result = db_fetch_assoc_prepared('SELECT disabled
		FROM host
		WHERE id = ?',
		array($save['id']));

	if (!isset($result[0]['disabled'])) {
		return $save;
	}

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	if ($save['disabled'] != $result[0]['disabled']) {
		if ($save['disabled'] == '') {
			plugin_thold_log_changes($save['id'], 'enabled_host');

			db_execute_prepared('UPDATE thold_data
				SET thold_enabled = "on"
				WHERE host_id = ?',
				array($save['id']));
		} else {
			plugin_thold_log_changes($save['id'], 'disabled_host');

			db_execute_prepared('UPDATE thold_data
				SET thold_enabled = "off"
				WHERE host_id = ?',
				array($save['id']));
		}
	}

	if (isset_request_var('thold_send_email')) {
		$save['thold_send_email'] = form_input_validate(get_nfilter_request_var('thold_send_email'), 'thold_send_email', '', true, 3);
	} else {
		$save['thold_send_email'] = form_input_validate('', 'thold_send_email', '', true, 3);
	}

	if (isset_request_var('thold_host_email')) {
		$save['thold_host_email'] = form_input_validate(get_nfilter_request_var('thold_host_email'), 'thold_host_email', '', true, 3);
	} else {
		$save['thold_host_email'] = form_input_validate('', 'thold_host_email', '', true, 3);
	}

	return $save;
}

function thold_data_sources_table($ds) {
	global $config;

	if (isset($ds['local_data_id'])) {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM thold_data
			WHERE local_data_id = ?
			LIMIT 1',
			array($ds['local_data_id']));

		if ($exists) {
			$ds['data_template_name'] = "<a title='" . __esc('Create Threshold from Data Source', 'thold') . "' class='hyperLink' href='" . html_escape('plugins/thold/thold.php?action=edit&id=' . $exists) . "'>" . ((empty($ds['data_template_name'])) ? '<em>' . __('None', 'thold'). '</em>' : html_escape($ds['data_template_name'])) . '</a>';
		} else {
			$graph_exists = db_fetch_cell_prepared('SELECT DISTINCT gl.id
				FROM graph_local AS gl
				INNER JOIN graph_templates_item AS gti
				ON gl.id = gti.local_graph_id
				INNER JOIN data_template_rrd AS dtr
				ON gti.task_item_id = dtr.id
				WHERE dtr.local_data_id = ?
				LIMIT 1',
				array($ds['local_data_id']));

			$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
				FROM data_local
				WHERE id = ?',
				array($ds['local_data_id']));

			if ($graph_exists) {
				$ds['data_template_name'] = "<a title='" . __esc('Create Threshold from Data Source', 'thold') . "' class='hyperLink' href='" . html_escape('plugins/thold/thold.php?action=edit&local_data_id=' . $ds['local_data_id'] . '&host_id=' . $ds['host_id'] . '&data_template_id=' . $data_template_id . '&data_template_rrd_id=&local_graph_id=' . $graph_exists . '&thold_template_id=0') . "'>" . ((empty($ds['data_template_name'])) ? '<em>' . __('None', 'thold') . '</em>' : html_escape($ds['data_template_name'])) . '</a>';
			}
		}
	}

	return $ds;
}

function thold_graphs_new() {
	global $config;

	print '<span class="linkMarker">*</span><a class="autocreate hyperLink" href="' . html_escape($config['url_path'] . 'plugins/thold/thold.php?action=autocreate&host_id=' . get_filter_request_var('host_id')) . '">' . __('Auto-create Thresholds', 'thold'). '</a><br>';
}

function thold_user_admin_setup_sql_save($save) {
	if (is_error_message()) {
		return $save;
	}

	if (isset_request_var('email') || isset_request_var('email_address')) {
		$email = form_input_validate(get_nfilter_request_var('email_address'), 'email_address', '', true, 3);
		if ($save['id'] == 0) {
			$save['id'] = sql_save($save, 'user_auth');
		}

		$cid = db_fetch_cell_prepared('SELECT id
			FROM plugin_thold_contacts
			WHERE type = "email"
			AND user_id = ?',
			array($save['id']));

		if ($cid) {
			db_execute_prepared('REPLACE INTO plugin_thold_contacts
				(id, user_id, type, data) VALUES
				(?, ?, "email", ?)',
				array($cid, $save['id'], $email));
		} else {
			db_execute_prepared('REPLACE INTO plugin_thold_contacts
				(user_id, type, data) VALUES
				(?, "email", ?)',
				array($save['id'], $email));
		}
	}

	return $save;
}

function thold_data_source_action_execute($action) {
	global $config, $form_array;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	if ($action == 'plugin_thold_create') {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			$message = '';
			$created = 0;

			get_filter_request_var('thold_template_id');

			$template = db_fetch_row_prepared('SELECT *
				FROM thold_template
				WHERE id = ?',
				array(get_request_var('thold_template_id')));

			if (cacti_sizeof($template)) {
				foreach($selected_items as $local_data_id) {
					$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT
						dtr.id, gl.id AS local_graph_id, dtr.local_data_id
						FROM data_template_rrd AS dtr
						INNER JOIN graph_templates_item AS gti
						ON gti.task_item_id=dtr.id
						INNER JOIN graph_local AS gl
						ON gl.id=gti.local_graph_id
						WHERE dtr.local_data_id = ?
						AND dtr.data_source_name = ?',
						array($local_data_id, $template['data_source_name']));

					if (cacti_sizeof($data_sources)) {
						foreach($data_sources as $data_source) {
							$local_data_id        = $data_source['local_data_id'];
							$local_graph_id       = $data_source['local_graph_id'];
							$data_template_rrd_id = $data_source['id'];

							if (thold_create_from_template($local_data_id, $local_graph_id, $data_template_rrd_id, $template, $message)) {
								$created++;
							}
						}
					}
				}

				if (strlen($message)) {
					thold_raise_message(__('Created %s thresholds', $created) . '<br>' . $message, MESSAGE_LEVEL_INFO);
				} else {
					thold_raise_message(__('No Threshold(s) Created.  Either they already exist, or no suitable matches found.', 'thold'), MESSAGE_LEVEL_INFO);
				}
			} else {
				thold_raise_message(__('No Threshold(s) Created.  Threshold(s) Template not found.', 'thold'), MESSAGE_LEVEL_ERROR);
			}
		}
	}

	return $action;
}

function thold_data_source_action_prepare($save) {
	global $config;

	if ($save['drp_action'] == 'plugin_thold_create') {
		/* get the valid thold templates
		 * remove those hosts that do not have any valid templates
		 */
		$templates  = '';
		$found_list = '';
		$not_found  = '';

		if (cacti_sizeof($save['ds_array'])) {
			foreach($save['ds_array'] as $item) {
				$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
					FROM data_local
					WHERE id = ?',
					array($item));

				if ($data_template_id != '') {
					$templates_ids = db_fetch_assoc_prepared('SELECT id
						FROM thold_template
						WHERE data_template_id = ?',
						array($data_template_id));

					if (cacti_sizeof($templates_ids)) {
						$found_list .= '<li>' . html_escape(get_data_source_title($item)) . '</li>';
						if (strlen($templates)) {
								$templates .= ", $data_template_id";
						} else {
								$templates  = "$data_template_id";
						}
					} else {
						$not_found .= '<li>' . html_escape(get_data_source_title($item)) . '</li>';
					}
				} else {
					$not_found .= '<li>' . html_escape(get_data_source_title($item)) . '</li>';
				}
			}
		}

		if (strlen($templates)) {
			$sql = 'SELECT id, name FROM thold_template WHERE data_template_id IN (' . $templates . ') ORDER BY name';
		} else {
			$sql = 'SELECT id, name FROM thold_template ORDER BY name';
		}

		print "<tr><td colspan='2' class='textArea'>";

		if (strlen($found_list)) {
			if (strlen($not_found)) {
				print '<p>' . __('The following Data Sources have no Threshold Templates associated with them', 'thold') . '</p>';
				print '<ul>' . $not_found . '</ul>';
			}

			print '<p>' . __('Click \'Continue\' to create Thresholds for these Data Sources?', 'thold') . '</p>
					<ul>' . $found_list . "</ul>
				</td>
			</tr></table><table class='cactiTable'><tr><td>";

			$form_array = array(
				'general_header' => array(
					'friendly_name' => __('Available Threshold Templates', 'thold'),
					'method' => 'spacer',
				),
				'thold_template_id' => array(
					'method' => 'drop_sql',
					'friendly_name' => __('Select a Threshold Template', 'thold'),
					'description' => '',
					'none_value' => __('None', 'thold'),
					'value' => __('None', 'thold'),
					'sql' => $sql
				)
			);

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);
		} else {
			if (strlen($not_found)) {
				print '<p>' . __('There are no Threshold Templates associated with the following Data Sources', 'thold'). '</p>';
				print '<ul>' . $not_found . '</ul>';
			}
		}

		print '</td></tr></table><table class="cactiTable"><tr><td class="saveRow">';
	} else {
		return $save;
	}
}

function thold_data_source_action_array($action) {
	$action['plugin_thold_create'] = __('Create Threshold from Template', 'thold');
	return $action;
}

function thold_graphs_action_execute($action) {
	global $config, $form_array;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	if ($action == 'plugin_thold_create') {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			$message = '';
			$created = 0;

			get_filter_request_var('thold_template_id');

			$template = db_fetch_row_prepared('SELECT *
				FROM thold_template
				WHERE id = ?',
				array(get_request_var('thold_template_id')));

			if (cacti_sizeof($template)) {
				foreach($selected_items as $local_graph_id) {
					$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT
						dtr.id, gl.id AS local_graph_id, dtr.local_data_id
						FROM data_template_rrd AS dtr
						INNER JOIN graph_templates_item AS gti
						ON gti.task_item_id=dtr.id
						INNER JOIN graph_local AS gl
						ON gl.id=gti.local_graph_id
						WHERE gl.id = ?
						AND dtr.data_source_name = ?',
						array($local_graph_id, $template['data_source_name']));

					if (cacti_sizeof($data_sources)) {
						foreach($data_sources as $data_source) {
							$local_data_id        = $data_source['local_data_id'];
							$local_graph_id       = $data_source['local_graph_id'];
							$data_template_rrd_id = $data_source['id'];

							if (thold_create_from_template($local_data_id, $local_graph_id, $data_template_rrd_id, $template, $message)) {
								$created++;
							}
						}
					}
				}

				if (strlen($message)) {
					thold_raise_message(__('Created %s thresholds', $created) . '<br>' . $message, MESSAGE_LEVEL_INFO);
				} else {
					thold_raise_message(__('No Threshold(s) Created.  Either they already exist, or no suitable matches found.', 'thold'), MESSAGE_LEVEL_INFO);
				}
			} else {
				thold_raise_message(__('No Threshold(s) Created.  Threshold(s) Template not found.', 'thold'), MESSAGE_LEVEL_ERROR);
			}
		}
	}

	return $action;
}

function thold_graphs_action_prepare($save) {
	global $config;

	if ($save['drp_action'] == 'plugin_thold_create') {
		/* get the valid thold templates
		 * remove those hosts that do not have any valid templates
		 */
		$found_list   = '';
		$not_found    = '';
		$template_ids = array();

		if (cacti_sizeof($save['graph_array'])) {
			foreach($save['graph_array'] as $item) {
				$data_template_id = db_fetch_cell_prepared('SELECT DISTINCT dtr.data_template_id
					 FROM data_template_rrd AS dtr
					 LEFT JOIN graph_templates_item AS gti
					 ON gti.task_item_id=dtr.id
					 LEFT JOIN graph_local AS gl
					 ON gl.id=gti.local_graph_id
					 WHERE gl.id = ?',
					array($item));

				if ($data_template_id != '') {
					$templates = db_fetch_assoc_prepared('SELECT id
						FROM thold_template
						WHERE data_template_id = ?',
						array($data_template_id));

					if (cacti_sizeof($templates)) {
						$found_list .= '<li>' . html_escape(get_graph_title($item)) . '</li>';
						$template_ids[] = $data_template_id;
					} else {
						$not_found .= '<li>' . html_escape(get_graph_title($item)) . '</li>';
					}
				} else {
					$not_found .= '<li>' . html_escape(get_graph_title($item)) . '</li>';
				}
			}
		}

		if (cacti_sizeof($template_ids)) {
			$sql = 'SELECT id, name FROM thold_template WHERE data_template_id IN (' . implode(', ',  $template_ids) . ') ORDER BY name';
		} else {
			$sql = 'SELECT id, name FROM thold_template ORDER BY name';
		}

		print "<tr><td colspan='2' class='textArea'>";

		if (strlen($found_list)) {
			if (strlen($not_found)) {
				print '<p>' . __('The following Graphs have no Threshold Templates associated with them', 'thold') . '</p>';
				print '<ul>' . $not_found . '</ul>';
			}

			print '<p>' . __('Press \'Continue\' if you wish to create Threshold(s) for these Graph(s)', 'thold') . '</p>
				<ul>' . $found_list . "</ul>
				</td>
			</tr></table><table class='cactiTable'><tr><td>";

			$form_array = array(
				'general_header' => array(
					'friendly_name' => __('Available Threshold Templates', 'thold'),
					'method' => 'spacer',
				),
				'thold_template_id' => array(
					'method' => 'drop_sql',
					'friendly_name' => __('Select a Threshold Template', 'thold'),
					'description' => '',
					'value' => __('None', 'thold'),
					'sql' => $sql
				)
			);

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);
		} else {
			if (strlen($not_found)) {
				print '<p>' . __('There are no Threshold Templates associated with the following Graphs', 'thold') . '</p>';
				print '<ul>' . $not_found . '</ul>';
			}
		}

		print '</td></tr></table><table class="cactiTable"><tr><td class="saveRow">';
	} else {
		return $save;
	}
}

function thold_graphs_action_array($action) {
	$action['plugin_thold_create'] = __('Create Threshold from Template', 'thold');
	return $action;
}

function thold_host_edit_bottom() {
	?>
	<script type='text/javascript'>

	changeNotify();
	function changeNotify() {
		if ($('#thold_send_email').val() < 2) {
			$('#row_thold_host_email').hide();
		} else {
			$('#row_thold_host_email').show();
		}
	}

	</script>
	<?php
}

function thold_snmpagent_cache_install() {
	global $config;

	if (class_exists('MibCache')) {
		$mc = new MibCache('CACTI-THOLD-MIB');
		$mc->install($config['base_path'] . '/plugins/thold/CACTI-THOLD-MIB', true);
	}
}

function thold_snmpagent_cache_uninstall() {
	global $config;

	if (class_exists('MibCache')) {
		$mc = new MibCache('CACTI-THOLD-MIB');
		$mc->uninstall();
	}
}

function thold_page_head() {
	global $config;

	if (file_exists($config['base_path'] . '/plugins/thold/themes/' . get_selected_theme() . '/main.css')) {
		print "<link href='" . $config['url_path'] . 'plugins/thold/themes/' . get_selected_theme() . "/main.css' type='text/css' rel='stylesheet'>\n";
	}

	?>
	<script type='text/javascript'>
	$(function() {
		$(document).ajaxComplete(function() {
			$('.tholdVRule').unbind().click(function(event) {
				event.preventDefault();

				href = $(this).attr('href');
				href += '&header=false';

				$.get(href, function(data) {
					$('#main').empty().hide();
					$('div[class^="ui-"]').remove();
					$('#main').html(data);
					applySkin();
				});
			});
		});
	});
	</script>
	<?php
}

function thold_device_edit_pre_bottom() {
	html_start_box(__('Associated Threshold Templates', 'thold'), '100%', false, '3', 'center', '');

	$host_template_id = db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = ?' ,array(get_request_var('id')));

	$threshold_templates = db_fetch_assoc_prepared('SELECT ptdt.thold_template_id, tt.name
		FROM plugin_thold_host_template AS ptdt
		INNER JOIN thold_template AS tt
		ON tt.id=ptdt.thold_template_id
		WHERE ptdt.host_template_id = ?
		ORDER BY name',
		array($host_template_id));

	html_header(array(__('Name', 'thold'), __('Status', 'thold')));

	$i = 1;
	if (cacti_sizeof($threshold_templates)) {
		foreach ($threshold_templates as $item) {
			$exists = db_fetch_cell_prepared('SELECT id
				FROM thold_data
				WHERE host_id = ?
				AND thold_template_id = ?',
				array(get_request_var('id'), $item['thold_template_id']));

			if ($exists) {
				$exists = __('Threshold Exists', 'thold');
			} else {
				$exists = __('Threshold Does Not Exist', 'thold');
			}

			form_alternate_row("tt$i", true);
			?>
				<td class='left'>
					<strong><?php print $i;?>)</strong> <?php print html_escape($item['name']);?>
				</td>
				<td>
					<?php print $exists;?>
				</td>
			<?php
			form_end_row();

			$i++;
		}
	} else {
		print '<tr><td class="templateAdd" colspan="2"><em>' . __('No Associated Threshold Templates.', 'thold') . '</em></td></tr>';
	}

	html_end_box();
}

function thold_device_template_edit() {
	html_start_box(__('Associated Threshold Templates', 'thold'), '100%', false, '3', 'center', '');

	$threshold_templates = db_fetch_assoc_prepared('SELECT ptdt.thold_template_id, tt.name
		FROM plugin_thold_host_template AS ptdt
		INNER JOIN thold_template AS tt
		ON tt.id=ptdt.thold_template_id
		WHERE ptdt.host_template_id = ?
		ORDER BY name',
		array(get_request_var('id')));

	$i = 0;
	if (cacti_sizeof($threshold_templates)) {
		foreach ($threshold_templates as $item) {
			form_alternate_row("tt$i", true);
			?>
				<td class='left'>
					<strong><?php print $i;?>)</strong> <?php print html_escape($item['name']);?>
				</td>
				<td class='right'>
					<a class='delete deleteMarker fa fa-times' title='<?php print __esc('Delete', 'thold');?>' href='<?php print html_escape('host_templates.php?action=item_remove_tt_confirm&id=' . $item['thold_template_id'] . '&host_template_id=' . get_request_var('id'));?>'></a>
				</td>
			<?php
			form_end_row();

			$i++;
		}
	} else {
		print '<tr><td class="templateAdd" colspan="2"><em>' . __('No Associated Threshold Templates.', 'thold') . '</em></td></tr>';
	}

	$unmapped = db_fetch_assoc_prepared('SELECT DISTINCT tt.id, tt.name
		FROM thold_template AS tt
		LEFT JOIN plugin_thold_host_template AS ptdt
		ON tt.id=ptdt.thold_template_id
		WHERE ptdt.host_template_id IS NULL OR ptdt.host_template_id != ?
		ORDER BY tt.name',
		array(get_request_var('id')));

	if (cacti_sizeof($unmapped)) {
		?>
		<tr class='odd'>
			<td colspan='2'>
				<table>
					<tr style='line-height:10px;'>
						<td style='padding-right: 15px;'>
							<?php print __('Add Threshold Template', 'thold');?>
						</td>
						<td>
							<?php form_dropdown('thold_template_id', $unmapped, 'name', 'id', '', '', '');?>
						</td>
						<td>
							<input type='button' value='<?php print __esc('Add', 'thold');?>' id='add_tt' title='<?php print __esc('Add Threshold Template to Device Template', 'thold');?>'>
						</td>
					</tr>
				</table>
				<script type='text/javascript'>
				$('#add_tt').click(function() {
					$.post('host_templates.php?header=false&action=item_add_tt', {
						host_template_id: $('#id').val(),
						thold_template_id: $('#thold_template_id').val(),
						__csrf_magic: csrfMagicToken
					}).done(function(data) {
						$('div[class^="ui-"]').remove();
						$('#main').html(data);
						applySkin();
					});
				});
				</script>
			</td>
		</tr>
		<?php
	}

	html_end_box();
}

function thold_device_template_top() {
	if (get_request_var('action') == 'item_remove_tt_confirm') {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('host_template_id');
		/* ==================================================== */

		form_start('host_templates.php?action=edit&id' . get_request_var('host_template_id'));

		html_start_box('', '100%', false, '3', 'center', '');

		$template = db_fetch_row_prepared('SELECT *
			FROM thold_template
			WHERE id = ?',
			array(get_request_var('id')));

		?>
		<tr>
			<td class='topBoxAlt'>
				<p><?php print __('Click \'Continue\' to Delete the following Threshold Template will be disassociated from the Device Template.', 'thold');?></p>
				<p><?php print __('Threshold Template Name: %s', html_escape($template['name']), 'thold');?>'<br>
			</td>
		</tr>
		<tr>
			<td align='right'>
				<input id='cancel' type='button' value='<?php print __esc('Cancel', 'thold');?>' onClick='$("#cdialog").dialog("close")' name='cancel'>
				<input id='continue' type='button' value='<?php print __esc('Continue', 'thold');?>' name='continue' title='<?php print __esc('Remove Threshold Template', 'thold');?>'>
			</td>
		</tr>
		<?php

		html_end_box();

		form_end();

		?>
		<script type='text/javascript'>
		$(function() {
			$('#cdialog').dialog();
		});

	    $('#continue').click(function(data) {
			$.post('host_templates.php?action=item_remove_tt', {
				__csrf_magic: csrfMagicToken,
				host_template_id: <?php print get_request_var('host_template_id');?>,
				id: <?php print get_request_var('id');?>
			}, function(data) {
				$('#cdialog').dialog('close');
				loadPageNoHeader('host_templates.php?action=edit&header=false&id=<?php print get_request_var('host_template_id');?>');
			});
		});
		</script>
		<?php

		exit;
	} elseif (get_request_var('action') == 'item_remove_tt') {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('host_template_id');
		/* ==================================================== */

		db_execute_prepared('DELETE
			FROM plugin_thold_host_template
			WHERE thold_template_id = ?
			AND host_template_id = ?',
			array(get_request_var('id'), get_request_var('host_template_id')));

		header('Location: host_templates.php?header=false&action=edit&id=' . get_request_var('host_template_id'));

		exit;
	} elseif (get_request_var('action') == 'item_add_tt') {
		/* ================= input validation ================= */
		get_filter_request_var('host_template_id');
		get_filter_request_var('thold_template_id');
		/* ==================================================== */

		db_execute_prepared('REPLACE INTO plugin_thold_host_template
			(host_template_id, thold_template_id) VALUES (?, ?)',
			array(get_request_var('host_template_id'), get_request_var('thold_template_id')));

		header('Location: host_templates.php?header=false&action=edit&id=' . get_request_var('host_template_id'));

		exit;
	}
}

function thold_hook_device_autocreate($host_id) {
	autocreate($host_id);
}

function thold_create_graph_thold($save) {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	if (read_config_option('thold_autocreate') == 'on') {
		$graph = db_fetch_row_prepared('SELECT *
			FROM graph_local
			WHERE id = ?',
			array($save['id']));

		if (cacti_sizeof($graph)) {
			autocreate($graph['host_id'], array($graph['id']));
		}
	}

	return $save;
}

function thold_data_source_remove($data_ids) {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$tholds = array_rekey(
		db_fetch_assoc('SELECT id
			FROM thold_data
			WHERE local_data_id IN (' . implode(', ', $data_ids) . ')'),
		'id', 'id'
	);

	if (cacti_sizeof($tholds)) {
		foreach($tholds as $thold) {
			plugin_thold_log_changes($thold, 'deleted', array('message' => 'Deleted due to Data Source removal'));
			thold_api_thold_remove($thold);
		}
	}

	return $data_ids;
}

function thold_clog_regex_array($regex_array) {
	$regex_array[] = array('name' => 'TH', 'regex' => '( TH\[)([, \d]+)(\])', 'func' => 'thold_clog_regex_threshold');
	return $regex_array;
}

function thold_clog_regex_threshold($matches) {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$result = $matches[0];

	$threshold_ids = explode(',', str_replace(' ', '', $matches[2]));
	if (cacti_sizeof($threshold_ids)) {
		$result = '';
		$thresholds = db_fetch_assoc('SELECT id, name, name_cache, local_data_id
			FROM thold_data
			WHERE id IN (' . implode(',',$threshold_ids) . ')');

		$thresholdDescriptions = array();
		if (cacti_sizeof($thresholds)) {
			foreach ($thresholds as $threshold) {
				$thresholdDescriptions[$threshold['id']] = html_escape(
					!empty($threshold['name_cache']) ?
						$threshold['name_cache'] :
						thold_substitute_data_source_description($threshold['name'], $threshold['local_data_id'])
				);
			}
		}

		foreach ($threshold_ids as $threshold_id) {
			$result .= $matches[1] . '<a href=\'' . $config['url_path'] .
				'plugins/thold/thold.php?action=edit&id=' . $threshold_id . '\'>' .
				html_escape(isset($thresholdDescriptions[$threshold_id]) ? $thresholdDescriptions[$threshold_id] : $threshold_id) .
				'</a>' . $matches[3];
		}
	}

	return $result;
}

