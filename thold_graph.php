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

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

require_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include_once($config['base_path'] . '/plugins/thold/setup.php');
include_once($config['base_path'] . '/plugins/thold/includes/database.php');
include($config['base_path'] . '/plugins/thold/includes/arrays.php');

thold_initialize_rusage();

plugin_thold_upgrade();

delete_old_thresholds();

set_default_action('thold');

switch(get_request_var('action')) {
	case 'ajax_hosts':
		get_allowed_ajax_hosts(true, false, 'h.id IN (SELECT host_id FROM thold_data)');

		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false, false, 'h.id IN (SELECT host_id FROM thold_data)');

		break;
	case 'thold':
		general_header();
		thold_tabs();
		tholds();
		bottom_footer();

		break;
	case 'ack':
		thold_threshold_ack_prompt(get_filter_request_var('threshold_id'));

		break;
	case 'ack_confirm':
		thold_threshold_ack(get_filter_request_var('threshold_id'));
		raise_message('reset_ack', __('The Threshold has been Ascknowledged.', 'thold'), MESSAGE_LEVEL_INFO);

		header('Location: thold_graph.php');

		break;
	case 'reset_ack':
		thold_threshold_suspend_ack(get_filter_request_var('threshold_id'));
		raise_message('reset_ack', __('The Threshold will no longer generate Alerts, Notifications, or execute commands.', 'thold'), MESSAGE_LEVEL_INFO);

		header('Location: thold_graph.php');

		break;
	case 'resume_ack':
		thold_threshold_resume_ack(get_filter_request_var('threshold_id'));
		raise_message('reset_ack', __('The Threshold will restart generating Alerts.', 'thold'), MESSAGE_LEVEL_INFO);

		header('Location: thold_graph.php');

		break;
	case 'disable':
		thold_threshold_disable(get_filter_request_var('id'));

		header('Location: thold_graph.php');

		exit;
	case 'enable':
		thold_threshold_enable(get_filter_request_var('id'));

		header('Location: thold_graph.php');

		exit;
	case 'hoststat':
		general_header();
		thold_tabs();
		hosts();
		bottom_footer();

		break;
	default:
		if (api_plugin_hook_function('thold_graph_view', false) === false) {
			general_header();
			thold_tabs();
			thold_show_log();
			bottom_footer();
		}

		break;
}

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION['sess_nav_level_cache'] = array();

function form_thold_filter() {
	global $item_rows, $config;

	?>
	<tr class='even'>
		<td>
		<form id='thold' action='thold_graph.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Site', 'thold');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='0'<?php if (get_request_var('site_id') == '0') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT id, name
								FROM sites
								ORDER BY name');

							if (cacti_sizeof($sites)) {
								foreach ($sites as $sites) {
									print "<option value='" . $sites['id'] . "'"; if (get_request_var('site_id') == $sites['id']) { print ' selected'; } print '>' . html_escape($sites['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<span>
							<input id='refresh' type='button' value='<?php print __esc('Go', 'thold');?>' onClick='applyFilter()'>
							<input id='clear' type='button' value='<?php print __esc('Clear', 'thold');?>' onClick='clearFilter()'>
						</span>
					</td>
				</table>
				<table class='filterTable'>
					<td>
						<?php print __('Template', 'thold');?>
					</td>
					<td>
						<select id='thold_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('thold_template_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='-2'<?php if (get_request_var('thold_template_id') == '-2') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$thold_templates = db_fetch_assoc('SELECT DISTINCT tt.id, tt.name
								FROM thold_template as tt
								ORDER BY tt.name');

							foreach ($thold_templates as $row) {
								print "<option value='" . $row['id'] . "'" . (isset_request_var('thold_template_id') && $row['id'] == get_request_var('thold_template_id') ? ' selected' : '') . '>' . html_escape($row['name']) . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Data Template', 'thold');?>
					</td>
					<td>
						<select id='data_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('data_template_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='0'<?php if (get_request_var('data_template_id') == '0') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$data_templates = db_fetch_assoc('SELECT DISTINCT data_template.id, data_template.name
								FROM thold_data
								INNER JOIN data_template
								ON thold_data.data_template_id=data_template.id ' .
								(get_request_var('host_id') > 0 ? 'WHERE thold_data.host_id=' . get_request_var('host_id'):'') .
								' ORDER by data_template.name');

							if (cacti_sizeof($data_templates)) {
								foreach ($data_templates as $data_template) {
									print "<option value='" . $data_template['id'] . "'"; if (get_request_var('data_template_id') == $data_template['id']) { print ' selected'; } print '>' . html_escape($data_template['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status', 'thold');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>><?php print __('Breached', 'thold');?></option>
							<option value='3'<?php if (get_request_var('status') == '3') {?> selected<?php }?>><?php print __('Triggered', 'thold');?></option>
							<option value='2'<?php if (get_request_var('status') == '2') {?> selected<?php }?>><?php print __('Enabled', 'thold');?></option>
							<option value='0'<?php if (get_request_var('status') == '0') {?> selected<?php }?>><?php print __('Disabled', 'thold');?></option>
							<option value='4'<?php if (get_request_var('status') == '4') {?> selected<?php }?>><?php print __('Ack Required', 'thold');?></option>
						</select>
					</td>
					<td>
						<?php print __('Thresholds', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' id='tab' value='thold'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=thold';
			strURL += '&status=' + $('#status').val();
			strURL += '&thold_template_id=' + $('#thold_template_id').val();
			strURL += '&data_template_id=' + $('#data_template_id').val();
			strURL += '&host_id=' + $('#host_id').val();
			strURL += '&site_id=' + $('#site_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'thold_graph.php?header=false&action=thold&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#thold').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});

			$('#filter').change(function() {
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php
}

function tholds() {
	global $config, $device_actions, $item_rows, $thold_classes, $thold_states;

	$default_status = read_config_option('thold_filter_default');
	if (empty($default_status)) {
		set_config_option('thold_filter_default', '-1');
		$default_status = '-1';
	}

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name_cache',
			'options' => array('options' => 'sanitize_thold_sort_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'data_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'thold_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => $default_status
			)
	);

	validate_store_request_vars($filters, 'sess_thold');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Threshold Status', 'thold'), '100%', false, '3', 'center', '');
	form_thold_filter();
	html_end_box();

	$sql_order = get_order_string();
	$sql_limit = ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$sql_order = str_replace('ORDER BY ', '', $sql_order);

	$sql_where = '';

	/* status filter */
	if (get_request_var('status') == '-1') {
		/* return all rows */
	} else {
		if (get_request_var('status') == '0') { $sql_where = "(td.thold_enabled = 'off'"; } /*disabled*/
		if (get_request_var('status') == '2') { $sql_where = "(td.thold_enabled = 'on'"; } /* enabled */
		if (get_request_var('status') == '1') { $sql_where = "(td.thold_enabled = 'on' AND ((td.thold_alert != 0 OR td.bl_alert > 0))"; } /* breached */
		if (get_request_var('status') == '3') { $sql_where = "(td.thold_enabled = 'on' AND (((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger) OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))"; } /* status */
		if (get_request_var('status') == '4') { $sql_where = "(td.acknowledgment = 'on')"; } /* status */
	}

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where == '' ? '(':' AND') . ' td.name_cache LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	}

	/* data template id filter */
	if (get_request_var('data_template_id') != '-1') {
		$sql_where .= ($sql_where == '' ? '(':' AND') . ' td.data_template_id = ' . get_request_var('data_template_id');
	}

	/* thold template id filter */
	if (!isempty_request_var('thold_template_id')) {
		if (get_request_var('thold_template_id') > 0) {
			$sql_where .= ($sql_where == '' ? '(' : ' AND ') . 'td.thold_template_id = ' . get_request_var('thold_template_id');
		} elseif (get_request_var('thold_template_id') == '-2') {
			$sql_where .= ($sql_where == '' ? '(' : ' AND ') . 'td.template_enabled = ""';
		}
	}

	/* host id filter */
	if (get_request_var('host_id') != '-1') {
		$sql_where .= ($sql_where == '' ? '(':' AND') . ' td.host_id = ' . get_request_var('host_id');
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('site_id') == '0') {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . 'h.site_id IS NULL';
	} elseif (!isempty_request_var('site_id')) {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . 'h.site_id = ' . get_request_var('site_id');
	}

	if ($sql_where != '') {
		$sql_where .= ')';
	}

	$tholds = get_allowed_thresholds($sql_where, $sql_order, $sql_limit, $total_rows);

	$nav = html_nav_bar('thold_graph.php?action=thold', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 13, 'Thresholds', 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, '3', 'center', '');

	$display_text = array(
		'nosort' => array(
			'display' => __('Actions', 'thold'),
			'sort' => '',
			'align' => 'left'
		),
		'name_cache' => array(
			'display' => __('Name', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'id' => array(
			'display' => __('ID', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'thold_type' => array(
			'display' => __('Type', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'flastread' => array(
			'display' => __('Current', 'thold'),
			'sort' => 'ASC',
			'align' => 'right',
			'tip' => __('The last measured value for the Data Source', 'thold')
		),
		'nosort4' => array(
			'display' => __('High', 'thold'),
			'sort' => 'ASC',
			'align' => 'right',
			'tip' => __('The High Warning / Alert values.  NOTE: Baseline values are a percent, all other values are display values that may be modified by a cdef.', 'thold')
		),
		'nosort5' => array(
			'display' => __('Low', 'thold'),
			'sort' => 'ASC',
			'align' => 'right',
			'tip' => __('The Low Warning / Alert values.  NOTE: Baseline values are a percent, all other values are display values that may be modified by a cdef.', 'thold')
		),
		'nosort2' => array(
			'display' => __('Trigger', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'nosort3' => array(
			'display' => __('Duration', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'repeat_alert' => array(
			'display' => __('Repeat', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'thold_alert' => array(
			'display' => __('Triggered', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'instate' => array(
			'display' => __('In State', 'thold'),
			'sort' => 'DESC',
			'align' => 'right',
			'tip' => __('The amount of time that has passed since the Threshold either Breached or was Triggered', 'thold')
		),
		'acknowledgment' => array(
			'display' => __('Ack Required', 'thold'),
			'sort' => 'ASC',
			'align' => 'right',
			'tip' => __('Acknowledgment required for this Threshold', 'thold')
		)
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=thold');

	$step = read_config_option('poller_interval');

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$c=0;
	$i=0;

	if (cacti_sizeof($tholds)) {
		foreach ($tholds as $thold_data) {
			$c++;
			$alertstat = __('No', 'thold');
			$bgcolor   = 'green';

			$severity = get_thold_severity($thold_data);

			switch($severity) {
				case THOLD_SEVERITY_DISABLED:
					$bgcolor = 'grey';
					break;
				case THOLD_SEVERITY_NORMAL:
					$bgcolor = 'green';
					break;
				case THOLD_SEVERITY_ALERT:
					$bgcolor = 'red';
					break;
				case THOLD_SEVERITY_WARNING:
					$bgcolor = 'warning';
					break;
				case THOLD_SEVERITY_BASELINE:
					$bgcolor = 'orange';
					break;
				case THOLD_SEVERITY_NOTICE:
					$bgcolor = 'yellow';
					break;
				case THOLD_SEVERITY_ACKREQ:
					$bgcolor = 'purple';
					break;
			}

			if ($thold_data['thold_type'] == 0) {
				if ($thold_data['thold_alert'] != 0) {
					$alertstat = __('Yes', 'thold');
				}
			} elseif ($thold_data['thold_type'] == 2) {
				if ($thold_data['thold_alert'] != 0) {
					$alertstat = __('Yes', 'thold');
				}
			} else {
				if ($thold_data['bl_alert'] == 1) {
					$alertstat = __('baseline-LOW', 'thold');
				} elseif ($thold_data['bl_alert'] == 2)  {
					$alertstat = __('baseline-HIGH', 'thold');
				}
			}

			print "<tr class='selectable " . $thold_states[$bgcolor]['class'] . "' id='line" . $thold_data['id'] . "'>";

			$baseu = db_fetch_cell_prepared('SELECT base_value
				FROM graph_templates_graph
				WHERE local_graph_id = ?',
				array($thold_data['local_graph_id']));

			if ($thold_data['data_type'] == 2) {
				$suffix = false;
			} else {
				$suffix = true;
			}

			if (empty($baseu)) {
				cacti_log('WARNING: Graph Template for local_graph_id ' . $thold_data['local_graph_id'] . ' has been removed!');
				$baseu = 1024;
			}

			// Check is the graph item has a cdef and modify the output
			thold_modify_values_by_cdef($thold_data);

			$actions_url = '';

			if (api_user_realm_auth('thold.php')) {
				$actions_url .= '<a href="' .  html_escape($config['url_path'] . 'plugins/thold/thold.php?action=edit&id=' . $thold_data['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" alt="" title="' . __esc('Edit Threshold', 'thold') . '"></a>';
			}

			if (api_user_realm_auth('thold.php')) {
				if ($thold_data['thold_enabled'] == 'on') {
					$actions_url .= '<a class="pic" href="' .  html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=disable&id=' . $thold_data['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/disable_thold.png" alt="" title="' . __esc('Disable Threshold', 'thold') . '"></a>';
				} else {
					$actions_url .= '<a class="pic" href="' .  html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=enable&id=' . $thold_data['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/enable_thold.png" alt="" title="' . __esc('Enable Threshold', 'thold') . '"></a>';
				}
			}

			thold_get_cached_name($thold_data);

			$actions_url .= "<a href='". html_escape($config['url_path'] . 'graph.php?local_graph_id=' . $thold_data['local_graph_id'] . '&rra_id=all') . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' alt='' title='" . __esc('View Graph', 'thold') . "'></a>";

			$actions_url .= "<a class='pic' href='". html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=log&reset=1&threshold_id=' . $thold_data['id'] . '&host_id=' . $thold_data['host_id'] . '&status=-1') . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_log.gif' alt='' title='" . __esc('View Threshold History', 'thold') . "'></a>";

			if (api_user_realm_auth('thold.php')) {
				if ($thold_data['acknowledgment'] == 'on') {
					$actions_url .= "<a class='pic' href='". html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=ack&threshold_id=' . $thold_data['id']) . "'><img src='" . $config['url_path'] . "images/accept.png' alt='' title='" . __esc('Acknowledge Threshold', 'thold') . "'></a>";

					if ($thold_data['reset_ack'] == 'on') {
						$actions_url .= "<a class='pic' href='". html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=reset_ack&threshold_id=' . $thold_data['id']) . "'><img src='" . $config['url_path'] . "images/stop.png' alt='' title='" . __esc('Suspend Notifications until the Threshold clears', 'thold') . "'></a>";
					}
				} elseif ($thold_data['thold_alert'] > 0 && $thold_data['reset_ack'] == 'on') {
					$actions_url .= "<a class='pic' href='". html_escape($config['url_path'] . 'plugins/thold/thold_graph.php?action=resume_ack&threshold_id=' . $thold_data['id']) . "'><img src='" . $config['url_path'] . "images/accept.png' alt='' title='" . __esc('Resume Notifications for this breached Threshold', 'thold') . "'></a>";
				}
			}

			$data = array(
				'thold_data' => $thold_data,
				'actions_url' => $actions_url
			);

			$data = api_plugin_hook_function('thold_graph_actions_url', $data);
			if (isset($data['actions_url'])) {
				$actions_url = $data['actions_url'];
			}

			form_selectable_cell($actions_url, $thold_data['id'], '', 'left');

			form_selectable_cell($thold_data['name_cache'] != '' ? filter_value($thold_data['name_cache'], get_request_var('filter')) : __('No name set', 'thold'), $thold_data['id'], '', 'left');

			form_selectable_cell($thold_data['id'], $thold_data['id'], '', 'right');

			form_selectable_cell($thold_types[$thold_data['thold_type']], $thold_data['id'], '', 'right');

			form_selectable_cell(thold_format_number($thold_data['lastread'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');

			switch($thold_data['thold_type']) {
				case 0:
					form_selectable_cell(thold_format_number($thold_data['thold_warning_hi'], 2, $baseu, $suffix) . ' / ' . thold_format_number($thold_data['thold_hi'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');
					form_selectable_cell(thold_format_number($thold_data['thold_warning_low'], 2, $baseu, $suffix) . ' / ' . thold_format_number($thold_data['thold_low'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');
					form_selectable_cell('<i>' . plugin_thold_duration_convert($thold_data['local_data_id'], $thold_data['thold_fail_trigger'], 'alert') . '</i>', $thold_data['id'], '', 'right');
					form_selectable_cell(__('N/A', 'thold'),  $thold_data['id'], '', 'right');

					break;
				case 1:
					form_selectable_cell($thold_data['bl_pct_up'] . (strlen($thold_data['bl_pct_up']) ? '%':'-'), $thold_data['id'], '', 'right');
					form_selectable_cell($thold_data['bl_pct_down'] . (strlen($thold_data['bl_pct_down']) ? '%':'-'), $thold_data['id'], '', 'right');
					form_selectable_cell('<i>' . plugin_thold_duration_convert($thold_data['local_data_id'], $thold_data['bl_fail_trigger'], 'alert') . '</i>', $thold_data['id'], '', 'right');
					form_selectable_cell($timearray[$thold_data['bl_ref_time_range']/$thold_data['rrd_step']], $thold_data['id'], '', 'right');

					break;
				case 2:
					form_selectable_cell(thold_format_number($thold_data['time_warning_hi'], 2, $baseu, $suffix) . ' / ' . thold_format_number($thold_data['time_hi'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');
					form_selectable_cell(thold_format_number($thold_data['time_warning_low'], 2, $baseu, $suffix) . ' / ' . thold_format_number($thold_data['time_low'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');
					form_selectable_cell('<i>' . __('%d Triggers', $thold_data['time_fail_trigger'], 'thold') . '</i>',  $thold_data['id'], '', 'right');
					form_selectable_cell('<i>' . plugin_thold_duration_convert($thold_data['local_data_id'], $thold_data['time_fail_length'], 'time') . '</i>', $thold_data['id'], '', 'right');

					break;
				default:
					form_selectable_cell('- / -',  $thold_data['id'], '', 'right');
					form_selectable_cell('- / -',  $thold_data['id'], '', 'right');
					form_selectable_cell(__('N/A', 'thold'),  $thold_data['id'], '', 'right');
					form_selectable_cell(__('N/A', 'thold'),  $thold_data['id'], '', 'right');
			}

			form_selectable_cell(($thold_data['repeat_alert'] == '' ? '' : plugin_thold_duration_convert($thold_data['local_data_id'], $thold_data['repeat_alert'], 'repeat')), $thold_data['id'], '', 'right');

			form_selectable_cell($alertstat, $thold_data['id'], '', 'right');

			// The time since this threshold was triggered
			form_selectable_cell('<i>' . get_time_since_last_event($thold_data) . '</i>',  $thold_data['id'], '', 'right');

			form_selectable_cell(($thold_data['acknowledgment'] == '' ? __('No', 'thold'):__('Yes', 'thold')), $thold_data['id'], '', 'right ' . ($thold_data['acknowledgment'] == '' ? '':'tholdAckCol'));

			form_end_row();
		}
	} else {
		print '<tr class="even"><td class="center" colspan="13">' . __('No Thresholds', 'thold'). '</td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($tholds)) {
		print $nav;
	}

	thold_legend();

	//thold_display_rusage();
}


/* form_host_status_row_color - returns a color to use based upon the host's current status*/
function form_host_status_row_color($status, $disabled, $id) {
	global $thold_host_states;

	// Determine the color to use
	if ($disabled) {
		$class = $thold_host_states['disabled']['class'];
	} else {
		$class = $thold_host_states[$status]['class'];
	}

	print "<tr class='selectable $class' id='line" . $id . "'>";

	return $class;
}

function get_uncolored_device_status($disabled, $status) {
	if ($disabled) {
		return __('Disabled', 'thold');
	} else {
		switch ($status) {
			case HOST_DOWN:
				return __('Down', 'thold');
				break;
			case HOST_RECOVERING:
				return __('Recovering', 'thold');
				break;
			case HOST_UP:
				return __('Up', 'thold');
				break;
			case HOST_ERROR:
				return __('Error', 'thold');
				break;
			default:
				return __('Unknown', 'thold');
				break;
		}
	}
}

function hosts() {
	global $config, $device_actions, $item_rows;

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_thold_sort_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-4'
			)
	);

	validate_store_request_vars($filters, 'sess_thold_hstatus');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Device Status', 'thold'), '100%', false, '3', 'center', '');
	form_host_filter();
	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if (get_request_var('filter') != '') {
		$sql_where = ' ((
			h.hostname LIKE '       . db_qstr('%' . get_request_var('filter') . '%') . '
			OR h.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_status') == '-2') {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . "h.disabled = 'on'";
	} elseif (get_request_var('host_status') == '-3') {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . "h.disabled = ''";
	} elseif (get_request_var('host_status') == '-4') {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . "(h.status != '3' OR h.disabled = 'on')";
	} elseif (get_request_var('host_status') == '-5') {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . '(h.availability_method = 0)';
	} elseif (get_request_var('host_status') == '3') {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . "(h.availability_method != 0 AND h.status = 3 AND h.disabled = '')";
	} else {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . '(h.status = ' . get_request_var('host_status') . " AND h.disabled = '')";
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . 'h.host_template_id = 0';
	} elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . 'h.host_template_id = ' . get_request_var('host_template_id');
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('site_id') == '0') {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . 'h.site_id = 0';
	} elseif (!isempty_request_var('site_id')) {
		$sql_where .= ($sql_where == '' ? '(':' AND ') . 'h.site_id = ' . get_request_var('site_id');
	}

	$sql_where .= ($sql_where != '' ? ')':'');

	$sql_order = get_order_string();
	$sql_limit = ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$sql_order = str_replace('ORDER BY ', '', $sql_order);

	$hosts = thold_get_allowed_devices($sql_where, $sql_order, $sql_limit, $total_rows);

	$nav = html_nav_bar('thold_graph.php?action=hoststat', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 12, __('Devices', 'thold'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, '3', 'center', '');

	$display_text = array(
		'nosort' => array(
			'display' => __('Actions', 'thold'),
			'align' => 'left',
			'sort' => '',
			'tip' => __('Hover over icons for help', 'thold')
		),
		'description' => array(
			'display' => __('Description', 'thold'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('A description for the Device', 'thold')
		),
		'id' => array(
			'display' => __('ID', 'thold'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('A Cacti unique identifier for the Device', 'thold')
		),
		'graphs' => array(
			'display' => __('Graphs', 'thold'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The number of Graphs for this Device', 'thold')
		),
		'data_sources' => array(
			'display' => __('Data Sources', 'thold'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The number of Data Sources for this Device', 'thold')
		),
		'status' => array(
			'display' => __('Status', 'thold'),
			'align' => 'center',
			'sort' => 'ASC',
			'tip' => __('The status for this Device as of the last time it was polled', 'thold')
		),
		'instate' => array(
			'display' => __('In State', 'thold'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The last time Cacti found an issue with this Device.  It can be higher than the Uptime for the Device, if it was rebooted between Cacti polling cycles', 'thold')
		),
		'snmp_sysUpTimeInstance' => array(
			'display' => __('Uptime', 'thold'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The official uptime of the Device as reported by SNMP', 'thold')
		),
		'hostname' => array(
			'display' => __('Hostname', 'thold'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The official hostname for this Device', 'thold')
		),
		'cur_time' => array(
			'display' => __('Current (ms)', 'thold'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The current response time for the Cacti Availability check', 'thold')
		),
		'avg_time' => array(
			'display' => __('Average (ms)', 'thold'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The average response time for the Cacti Availability check', 'thold')
		),
		'availability' => array(
			'display' => __('Availability', 'thold'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The overall Availability of this Device since the last counter reset in Cacti', 'thold')
		)
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=hoststat');

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			if ($host['disabled'] == '' &&
				($host['status'] == HOST_RECOVERING || $host['status'] == HOST_UP) &&
				($host['availability_method'] != AVAIL_NONE && $host['availability_method'] != AVAIL_PING)) {
				$snmp_uptime = $host['snmp_sysUpTimeInstance'];
				$days      = intval($snmp_uptime / (60*60*24*100));
				$remainder = $snmp_uptime % (60*60*24*100);
				$hours     = intval($remainder / (60*60*100));
				$remainder = $remainder % (60*60*100);
				$minutes   = intval($remainder / (60*100));
				$uptime    = $days . 'd:' . substr('00' . $hours, -2) . 'h:' . substr('00' . $minutes, -2) . 'm';
			} else {
				$uptime    = __('N/A', 'thold');
			}

			if ($host['availability_method'] != 0) {
				form_host_status_row_color($host['status'], $host['disabled'], $host['id']);

				$actions_url = '';

				if (api_user_realm_auth('host.php')) {
					$actions_url .= '<a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" alt="" title="' . __esc('Edit Device', 'thold') . '"></a>';
				}
				$actions_url .= "<a href='" . html_escape($config['url_path'] . 'graph_view.php?action=preview&reset=true&host_id=' . $host['id']) . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' alt='' title='" . __esc('View Graphs', 'thold') . "'></a>";

				form_selectable_cell($actions_url, $host['id'], '', 'left');

				form_selectable_cell(filter_value($host['description'], get_request_var('filter')), $host['id'], '', 'left');

				form_selectable_cell(number_format_i18n($host['id']), $host['id'], '', 'right');
				form_selectable_cell(number_format_i18n($host['graphs']), $host['id'], '', 'right');
				form_selectable_cell(number_format_i18n($host['data_sources']), $host['id'], '', 'right');

				form_selectable_cell(get_uncolored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['id'], '', 'right');

				form_selectable_cell(get_timeinstate($host), $host['id'], '', 'right');
				form_selectable_cell($uptime, $host['id'], '', 'right');
				form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id'], '', 'right');
				form_selectable_cell(number_format_i18n(($host['cur_time']), 2), $host['id'], '', 'right');
				form_selectable_cell(number_format_i18n(($host['avg_time']), 2), $host['id'], '', 'right');
				form_selectable_cell(number_format_i18n($host['availability'], 2), $host['id'], '', 'right');
			} else {
				print "<tr class='selectable deviceNotMonFull' id='line" . $host['id'] . "'>";

				$actions_url = '';
				if (api_user_realm_auth('host.php')) {
					$actions_url .= '<a href="' . html_escape($config['url_path'] . 'host.php?action=edit&id=' . $host["id"]) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" alt="" title="' . __esc('Edit Device', 'thold') . '"></a>';
				}
				$actions_url .= "<a href='" . html_escape($config['url_path'] . 'graph_view.php?action=preview&reset=true&host_id=' . $host['id']) . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' alt='' title='" . __esc('View Graphs', 'thold') . "'></a>";

				form_selectable_cell($actions_url, $host['id'], '', 'left');
				form_selectable_cell(filter_value($host['description'], get_request_var('filter')), $host['id'], '', 'left');
				form_selectable_cell(number_format_i18n($host['id']), $host['id'], '', 'right');
				form_selectable_cell('<i>' . number_format_i18n($host['graphs']) . '</i>', $host['id'], '', 'right');
				form_selectable_cell('<i>' . number_format_i18n($host['data_sources']) . '</i>', $host['id'], '', 'right');
				form_selectable_cell(__('Not Monitored', 'thold'), $host['id'], '', 'center');
				form_selectable_cell(__('N/A', 'thold'), $host['id'], '', 'right');
				form_selectable_cell($uptime, $host['id'], '', 'right');
				form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id'], '', 'right');
				form_selectable_cell(__('N/A', 'thold'), $host['id'], '', 'right');
				form_selectable_cell(__('N/A', 'thold'), $host['id'], '', 'right');
				form_selectable_cell(__('N/A', 'thold'), $host['id'], '', 'right');
			}

			form_end_row();
		}
	} else {
		print '<tr><td class="center" colspan="12">' . __('No Devices', 'thold') . '</td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($hosts)) {
		print $nav;
	}

	host_legend();

	//thold_display_rusage();
}

function form_host_filter() {
	global $item_rows, $config;

	?>
	<tr class='even'>
		<td>
		<form id='form_devices' action='thold_graph.php?action=hoststat'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Site', 'thold');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='0'<?php if (get_request_var('site_id') == '0') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT id, name
								FROM sites
								ORDER BY name');

							if (cacti_sizeof($sites)) {
								foreach ($sites as $sites) {
									print "<option value='" . $sites['id'] . "'"; if (get_request_var('site_id') == $sites['id']) { print ' selected'; } print '>' . html_escape($sites['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status', 'thold');?>
					</td>
					<td>
						<select id='host_status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_status') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='-3'<?php if (get_request_var('host_status') == '-3') {?> selected<?php }?>><?php print __('Enabled', 'thold');?></option>
							<?php print (read_user_setting('hide_disabled') == '' ? "<option value='-2'" . (get_request_var('host_status') == '-2' ? ' selected':'') . "'>" . __('Disabled', 'thold') . '</option>':'');?>
							<option value='-4'<?php if (get_request_var('host_status') == '-4') {?> selected<?php }?>><?php print __('Not Up', 'thold');?></option>
							<option value='-5'<?php if (get_request_var('host_status') == '-5') {?> selected<?php }?>><?php print __('Not Monitored', 'thold');?></option>
							<option value='3'<?php if (get_request_var('host_status') == '3') {?> selected<?php }?>><?php print __('Up', 'thold');?></option>
							<option value='1'<?php if (get_request_var('host_status') == '1') {?> selected<?php }?>><?php print __('Down', 'thold');?></option>
							<option value='2'<?php if (get_request_var('host_status') == '2') {?> selected<?php }?>><?php print __('Recovering', 'thold');?></option>
							<option value='0'<?php if (get_request_var('host_status') == '0') {?> selected<?php }?>><?php print __('Unknown', 'thold');?></option>
						</select>
					</td>
					<td>
						<span>
							<input id='refresh' type='button' value='<?php print __esc('Go', 'thold');?>' onClick='applyFilter()'>
							<input id='clear' type='button' value='<?php print __esc('Clear', 'thold');?>' onClick='clearFilter()'>
						</span>
					</td>
				</table>
				<table class='filterTable'>
					<td>
						<?php print __('Type', 'thold');?>
					</td>
					<td>
						<select id='host_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$host_templates = db_fetch_assoc('SELECT id, name
								FROM host_template
								ORDER BY name');

							if (cacti_sizeof($host_templates)) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . html_escape($host_template['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Devices', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' name='tab' value='hoststat'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=hoststat';
			strURL += '&host_status=' + $('#host_status').val();
			strURL += '&host_template_id=' + $('#host_template_id').val();
			strURL += '&site_id=' + $('#site_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'thold_graph.php?header=false&action=hoststat&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#form_devices').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});

			$('#filter').change(function() {
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php
}

function thold_show_log() {
	global $config, $item_rows, $thold_log_states, $thold_status, $thold_types, $thold_log_retention;

	$step = read_config_option('poller_interval');

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'time',
			'options' => array('options' => 'sanitize_thold_sort_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'threshold_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'thold_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_thold_log');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$days = read_config_option('thold_log_storage');

	if (empty($days)) {
		set_config_option('thold_log_storage', '31');

		$days = 31;
	}

	$days = __('%d Days', $days, 'thold');

	html_start_box(__('Threshold Log for [ %s ]', $days, 'thold'), '100%', false, '3', 'center', '');
	form_thold_log_filter();
	html_end_box();

	$sql_where = '';

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ($sql_where == '' ? '':' AND') . ' h.id IS NULL';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ($sql_where == '' ? '':' AND') . ' tl.host_id=' . get_request_var('host_id');
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('site_id') == '0') {
		$sql_where .= ($sql_where == '' ? '':' AND') . ' h.site_id IS NULL';
	} elseif (!isempty_request_var('site_id')) {
		$sql_where .= ($sql_where == '' ? '':' AND') . ' h.site_id=' . get_request_var('site_id');
	}

	if (get_request_var('threshold_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('threshold_id') == '0') {
		$sql_where .= ($sql_where == '' ? '':' AND') . ' td.id IS NULL';
	} elseif (get_request_var('threshold_id') > 0) {
		$sql_where .= ($sql_where == '' ? '':' AND') . ' td.id=' . get_request_var('threshold_id');
	}

	/* thold template id filter */
	if (!isempty_request_var('thold_template_id')) {
		if (get_request_var('thold_template_id') > 0) {
			$sql_where .= ($sql_where == '' ? '' : ' AND ') . 'td.thold_template_id = ' . get_request_var('thold_template_id');
		} elseif (get_request_var('thold_template_id') == '-2') {
			$sql_where .= ($sql_where == '' ? '' : ' AND ') . 'td.template_enabled = ""';
		}
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	} else {
		$sql_where .= ($sql_where == '' ? '':' AND') . ' tl.status=' . get_request_var('status');
	}

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where == '' ? '':' AND') . ' tl.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	}

	$sql_order = get_order_string();
	$sql_limit = ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$sql_order = str_replace('ORDER BY ', '', $sql_order);

	$logs = get_allowed_threshold_logs($sql_where, $sql_order, $sql_limit, $total_rows);

	$nav = html_nav_bar('thold_graph.php?action=log', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Log Entries', 'thold'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, '3', 'center', '');

	$display_text = array(
		'hdescription' => array(
			'display' => __('Device', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'time' => array(
			'display' => __('Time', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'type' => array(
			'display' => __('Type', 'thold'),
			'sort' => 'DESC',
			'align' => 'left'
		),
		'description' => array(
			'display' => __('Event Description', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'threshold_value' => array(
			'display' => __('Alert Value', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'current' => array(
			'display' => __('Measured Value', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		)
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=log');

	$thold_types[99] = __('Acknowledgment', 'thold');

	$i = 0;
	if (cacti_sizeof($logs)) {
		foreach ($logs as $l) {
			$baseu = db_fetch_cell_prepared('SELECT base_value
				FROM graph_templates_graph
				WHERE local_graph_id = ?',
				array($l['local_graph_id']));

			$data_type = db_fetch_cell_prepared('SELECT data_type FROM thold_data WHERE id = ?', array($l['threshold_id']));

			if ($data_type == 2) {
				$suffix = false;
			} else {
				$suffix = true;
			}

			if (empty($baseu)) {
				cacti_log('WARNING: Graph Template for local_graph_id ' . $l['local_graph_id'] . ' has been removed!');
				$baseu = 1024;
			}

			print "<tr class='selectable " . $thold_log_states[$l['status']]['class'] . "' id='" . $l['id'] . "'>";

			form_selectable_cell($l['hdescription'], $l['id'], '', 'left');
			form_selectable_cell(date('Y-m-d H:i:s', $l['time']), $l['id'], '', 'left');
			form_selectable_cell($thold_types[$l['type']], $l['id'], '', 'left');
			form_selectable_cell((strlen($l['description']) ? $l['description']:__('Restoral Event', 'thold')), $l['id'], '', 'left');
			form_selectable_cell($l['threshold_value'] != '' ? thold_format_number($l['threshold_value'], 2, $baseu, $suffix):__('N/A', 'thold'), $l['id'], '', 'right');
			form_selectable_cell($l['current'] != '' ? thold_format_number($l['current'], 2, $baseu, $suffix):__('N/A', 'thold'), $l['id'], '', 'right');
			form_end_row();
		}
	} else {
		print '<tr><td class="center" colspan="8">' . __('No Threshold Logs Found', 'thold'). '</td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($logs)) {
		print $nav;
	}

	log_legend();
}

function form_thold_log_filter() {
	global $item_rows, $thold_log_states, $config;

	?>
	<tr class='even'>
		<td>
		<form id='form_log' action='thold_graph.php?action=log'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Site', 'thold');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='0'<?php if (get_request_var('site_id') == '0') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT id, name
								FROM sites
								ORDER BY name');

							if (cacti_sizeof($sites)) {
								foreach ($sites as $sites) {
									print "<option value='" . $sites['id'] . "'"; if (get_request_var('site_id') == $sites['id']) { print ' selected'; } print '>' . html_escape($sites['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<span>
							<input id='refresh' type='button' value='<?php print __esc('Go', 'thold');?>' onClick='applyFilter()'>
							<input id='clear' type='button' value='<?php print __esc('Clear', 'thold');?>' onClick='clearFilter()'>
						</span>
					</td>
				</table>
				<table class='filterTable'>
					<td>
						<?php print __('Template', 'thold');?>
					</td>
					<td>
						<select id='thold_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('thold_template_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='-2'<?php if (get_request_var('thold_template_id') == '-2') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$thold_templates = db_fetch_assoc('SELECT DISTINCT tt.id, tt.name
								FROM thold_template as tt
								ORDER BY tt.name');

							foreach ($thold_templates as $row) {
								print "<option value='" . $row['id'] . "'" . (isset_request_var('thold_template_id') && $row['id'] == get_request_var('thold_template_id') ? ' selected' : '') . '>' . html_escape($row['name']) . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Threshold', 'thold');?>
					</td>
					<td>
						<select id='threshold_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('threshold_id') == '-1') {?> selected<?php }?>><?php print __('All');?></option>
							<?php
							$tholds = db_fetch_assoc('SELECT DISTINCT td.id, td.name_cache
								FROM thold_data AS td
								INNER JOIN plugin_thold_log AS tl
								ON td.id = tl.threshold_id ' .
								(get_request_var('host_id') > 0 ? 'WHERE td.host_id=' . get_request_var('host_id'):'') .
								' ORDER by td.name_cache');

							if (cacti_sizeof($tholds)) {
								foreach ($tholds as $thold) {
									print "<option value='" . $thold['id'] . "'"; if (get_request_var('threshold_id') == $thold['id']) { print ' selected'; } print '>' . html_escape($thold['name_cache']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status', 'thold');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<?php
							if (cacti_sizeof($thold_log_states)) {
								foreach ($thold_log_states as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('status') == $key) { print ' selected'; } print '>' . html_escape($value['display']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Entries', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' name='tab' value='log'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=log';
			strURL += '&status=' + $('#status').val();
			strURL += '&threshold_id=' + $('#threshold_id').val();
			strURL += '&thold_template_id=' + $('#thold_template_id').val();
			strURL += '&host_id=' + $('#host_id').val();
			strURL += '&site_id=' + $('#site_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'thold_graph.php?header=false&action=log&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#form_log').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});

			$('#filter').change(function() {
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php
}

