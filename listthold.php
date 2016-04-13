<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2016 The Cacti Group                                 |
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

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include($config['base_path'] . '/plugins/thold/includes/arrays.php');

set_default_action();

if (isset_request_var('drp_action')) {
	do_thold();
} else {
	switch(get_request_var('action')) {
	case 'ajax_hosts':
		get_allowed_ajax_hosts(true, false, 'h.id IN (SELECT host_id FROM thold_data)');
		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false, false, 'h.id IN (SELECT host_id FROM thold_data)');
		break;
	default:
		delete_old_thresholds();
		list_tholds();
		break;
	}
}

function do_thold() {
	global $host_id;

	$tholds = array();
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_(.*)$", $var, $matches)) {
			$del = $matches[1];
			$rra = db_fetch_cell("SELECT rra_id FROM thold_data WHERE id=$del");

			input_validate_input_number($del);
			$tholds[$del] = $rra;
		}
	}

	switch (get_nfilter_request_var('drp_action')) {
		case 1:	// Delete
			foreach ($tholds as $del => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					plugin_thold_log_changes($del, 'deleted', array('id' => $del));
					db_execute("DELETE FROM thold_data WHERE id=$del");
					db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $del);
					db_execute('DELETE FROM plugin_thold_log WHERE threshold_id=' . $del);
				}
			}
			break;
		case 2:	// Disabled
			foreach ($tholds as $del => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					plugin_thold_log_changes($del, 'disabled_threshold', array('id' => $del));
					db_execute("UPDATE thold_data SET thold_enabled='off' WHERE id=$del");
				}
			}
			break;
		case 3:	// Enabled
			foreach ($tholds as $del => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					plugin_thold_log_changes($del, 'enabled_threshold', array('id' => $del));
					db_execute("UPDATE thold_data SET thold_enabled='on' WHERE id=$del");
				}
			}
			break;
		case 4:	// Reapply Suggested Name
			foreach ($tholds as $del => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					$thold = db_fetch_row("SELECT * FROM thold_data WHERE id=$del");
					/* check if thold templated */
					if ($thold['template_enabled'] == "on") {
						$template = db_fetch_row("SELECT * FROM thold_template WHERE id=" . $thold["template"]);
						$name = thold_format_name($template, $thold["graph_id"], $thold["data_id"], $template['data_source_name']);
						plugin_thold_log_changes($del, 'reapply_name', array('id' => $del));
						db_execute("UPDATE thold_data SET name='$name' WHERE id=$del");
					}
				}
			}
			break;
		case 5:	// Propagate Template
			foreach ($tholds as $thold_id => $rra) {
				if (thold_user_auth_threshold ($rra)) {
					$template = db_fetch_row("SELECT td.template id, td.template_enabled enabled
						FROM thold_data td
						INNER JOIN thold_template tt ON tt.id = td.template
						WHERE td.id = $thold_id");
					if (isset($template['id']) && $template['id'] != 0 && $template['enabled'] != 'on') {
						thold_template_update_threshold($thold_id, $template['id']);
						plugin_thold_log_changes($thold_id, 'modified', array('id' => $thold_id, 'template_enabled' => 'on'));
					}
				}
			}
			break;
	}

	if (isset($host_id) && $host_id != '')
		Header('Location:listthold.php?host_id=$host_id');
	else
		Header('Location:listthold.php');

	exit;
}

/**
 *  This is a generic funtion for this page that makes sure that
 *  we have a good request.  We want to protect against people who
 *  like to create issues with Cacti.
*/
function thold_request_validation() {
	global $title, $rows_selector, $config, $reset_multi;

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
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'thold_alert',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'state' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('thold_filter_default')
			),
		'template' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_lth');
	/* ================= input validation ================= */
}

function list_tholds() {
	global $thold_states, $config, $host_id, $timearray, $thold_types;

	$thold_actions = array(
		1 => 'Delete', 
		2 => 'Disable', 
		3 => 'Enable', 
		4 => 'Reapply Suggested Names', 
		5 => 'Propagate Template'
	);

	thold_request_validation();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$statefilter='';
	if (isset_request_var('state')) {
		if (get_request_var('state') == '-1') {
			$statefilter = '';
		} else {
			if(get_request_var('state') == '0') { $statefilter = "thold_data.thold_enabled='off'"; }
			if(get_request_var('state') == '2') { $statefilter = "thold_data.thold_enabled='on'"; }
			if(get_request_var('state') == '1') { $statefilter = '(thold_data.thold_alert!=0 OR thold_data.bl_alert>0)'; }
			if(get_request_var('state') == '3') { $statefilter = '((thold_data.thold_alert!=0 AND thold_data.thold_fail_count >= thold_data.thold_fail_trigger) OR (thold_data.bl_alert>0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger))'; }
		}
	}

	top_header();

	$sql_where = '';

	$sort = get_request_var('sort_column');
	$limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ", $rows";

	if (!isempty_request_var('host_id') && get_request_var('host_id') != '-1') {
		$sql_where .= (!strlen($sql_where) ? '(' : ' AND ') . "td.host_id = " . get_request_var('host_id');
	}

	if (!isempty_request_var('template') && get_request_var('template') != '-1') {
		$sql_where .= (!strlen($sql_where) ? '(' : ' AND ') . "td.data_template = " . get_request_var('template');
	}

	if ($statefilter != '') {
		$sql_where .= (!strlen($sql_where) ? '(' : ' AND ') . "$statefilter";
	}

	if ($sql_where != '') {
		$sql_where .= ')';
	}

	$tholds = get_allowed_thresholds($sql_where, $sort . ' ' . get_request_var('sort_direction'), ($rows*(get_request_var('page')-1)) . ", $rows", $total_rows);

	$data_templates = db_fetch_assoc("SELECT DISTINCT data_template.id, data_template.name
		FROM data_template
		INNER JOIN thold_data 
		ON thold_data.data_template = data_template.id
		ORDER BY data_template.name");

	html_start_box('Threshold Management' , '100%', '', '3', 'center', 'thold_add.php');

	?>
	<tr class='even'>
		<td>
		<form id='listthold' action='listthold.php' method='post'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						Template
					</td>
					<td>
						<select id='template' onChange='applyFilter()'>
							<option value='-1'>Any</option>
							<?php
							foreach ($data_templates as $row) {
								echo "<option value='" . $row['id'] . "'" . (isset_request_var('template') && $row['id'] == get_request_var('template') ? ' selected' : '') . '>' . $row['name'] . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						State
					</td>
					<td>
						<select id='state' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('state') == '-1') {?> selected<?php }?>>All</option>
							<option value='1'<?php if (get_request_var('state') == '1') {?> selected<?php }?>>Breached</option>
							<option value='3'<?php if (get_request_var('state') == '3') {?> selected<?php }?>>Triggered</option>
							<option value='2'<?php if (get_request_var('state') == '2') {?> selected<?php }?>>Enabled</option>
							<option value='0'<?php if (get_request_var('state') == '0') {?> selected<?php }?>>Disabled</option>
						</select>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Apply Filters' onClick='applyFilter()'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Return to Defaults' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='search' value='search'>
			<input type='hidden' id='page' value='<?php print get_filter_request_var('page');?>'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'listthold.php?header=false&host_id=' + $('#host_id').val();
			strURL += '&state=' + $('#state').val();
			strURL += '&template=' + $('#template').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'listthold.php?header=false&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#listthold').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
	
		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	form_start('listthold.php', 'chk');

	html_start_box('', '100%', '', '4', 'center', '');

	$nav = html_nav_bar('listthold.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 12, 'Thresholds', 'page', 'main');

	print $nav;

	$display_text = array(
		'name'             => array('Name', 'ASC'),
		'thold_type'       => array('Type', 'ASC'),
		'thold_hi'         => array('High', 'ASC'),
		'thold_low'        => array('Low', 'ASC'),
		'nosort3'          => array('Trigger', ''),
		'nosort4'          => array('Duration', ''),
		'repeat_alert'     => array('Repeat', 'ASC'),
		'lastread'         => array('Current', 'ASC'),
		'thold_alert'      => array('Triggered', 'ASC'),
		'thold_enabled'    => array('Enabled', 'ASC'),
		'template_enabled' => array('Templated', 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$c=0;
	$i=0;

	if (sizeof($tholds)) {
		foreach ($tholds as $row) {
			$c++;

			$grapharr = db_fetch_row('SELECT DISTINCT graph_templates_item.local_graph_id
				FROM graph_templates_item, data_template_rrd
				WHERE (data_template_rrd.local_data_id=' . $row['rra_id'] . ' 
				AND data_template_rrd.id=graph_templates_item.task_item_id)');

			$graph_id = $grapharr['local_graph_id'];

			$alertstat='no';
			$bgcolor='green';
			if ($row['thold_type'] == 0) {
				if ($row['thold_alert'] != 0) {
					$alertstat='yes';
					if ($row['thold_fail_count'] >= $row['thold_fail_trigger']) {
						$bgcolor = 'red';
					} elseif ($row['thold_warning_fail_count'] >= $row['thold_warning_fail_trigger']) {
						$bgcolor = 'warning';
					} else {
						$bgcolor = 'yellow';
					}
				}
			} elseif ($row['thold_type'] == 2) {
				if ($row['thold_alert'] != 0) {
					$alertstat='yes';
					if ($row['thold_fail_count'] >= $row['time_fail_trigger']) {
						$bgcolor = 'red';
					} elseif ($row['thold_warning_fail_count'] >= $row['time_warning_fail_trigger']) {
						$bgcolor = 'warning';
					} else {
						$bgcolor = 'yellow';
					}
				}
			} else {
				if($row['bl_alert'] == 1) {
					$alertstat='baseline-LOW';
					$bgcolor=($row['bl_fail_count'] >= $row['bl_fail_trigger'] ? 'orange' : 'yellow');
				} elseif ($row['bl_alert'] == 2)  {
					$alertstat='baseline-HIGH';
					$bgcolor=($row['bl_fail_count'] >= $row['bl_fail_trigger'] ? 'orange' : 'yellow');
				}
			};

			if ($row['thold_enabled'] == 'off') {
				print "<tr class='" . $thold_states['grey']['class'] . "' id='line" . $row['id'] . "'>\n";
			}else{
				print "<tr class='" . $thold_states[$bgcolor]['class'] . "' id='line" . $row['id'] . "'>\n";
			}

			form_selectable_cell(filter_value(($row['name'] != '' ? $row['name'] : $row['name_cache'] . ' [' . $row['data_source_name'] . ']'), get_request_var('filter'), 'thold.php?rra=' . $row['rra_id'] . "&view_rrd=" . $row['data_id']) . '</a>', $row['id'], '', 'text-align:left');

			form_selectable_cell($thold_types[$row['thold_type']], $row['id'], '', 'text-align:left');

			switch($row['thold_type']) {
				case 0:
					form_selectable_cell(thold_format_number($row['thold_hi']), $row['id'], '', 'text-align:left');
					form_selectable_cell(thold_format_number($row['thold_low']), $row['id'], '', 'text-align:left');
					form_selectable_cell('<i>' . plugin_thold_duration_convert($row['rra_id'], $row['thold_fail_trigger'], 'alert') . '</i>', $row['id'], '', 'text-align:left');
					form_selectable_cell('',  $row['id'], '', 'text-align:left');
					break;
				case 1:
					form_selectable_cell(thold_format_number($row['thold_hi']), $row['id'], '', 'text-align:left');
					form_selectable_cell(thold_format_number($row['thold_low']), $row['id'], '', 'text-align:left');
					form_selectable_cell('<i>' . plugin_thold_duration_convert($row['rra_id'], $row['bl_fail_trigger'], 'alert') . '</i>', $row['id'], '', 'text-align:left');
					form_selectable_cell($timearray[$row['bl_ref_time_range']/300], $row['id'], '', 'text-align:left');
					break;
				case 2:
					form_selectable_cell(thold_format_number($row['time_hi']), $row['id'], '', 'text-align:left');
					form_selectable_cell(thold_format_number($row['time_low']), $row['id'], '', 'text-align:left');
					form_selectable_cell('<i>' . $row['time_fail_trigger'] . ' Triggers</i>',  $row['id'], '', 'text-align:left');
					form_selectable_cell(plugin_thold_duration_convert($row['rra_id'], $row['time_fail_length'], 'time'), $row['id'], '', 'text-align:left');
					break;
				default:
					form_selectable_cell('',  $row['id'], '', 'text-align:left');
					form_selectable_cell('',  $row['id'], '', 'text-align:left');
					form_selectable_cell('',  $row['id'], '', 'text-align:left');
					form_selectable_cell('',  $row['id'], '', 'text-align:left');
			}

			form_selectable_cell(($row['repeat_alert'] == '' ? '' : plugin_thold_duration_convert($row['rra_id'], $row['repeat_alert'], 'repeat')), $row['id'], '', 'text-align:left');
			form_selectable_cell(thold_format_number($row['lastread']), $row['id'], '', 'text-align:left');
			form_selectable_cell($alertstat, $row['id'], '', 'text-align:left');
			form_selectable_cell((($row['thold_enabled'] == 'off') ? 'Disabled': 'Enabled'), $row['id'], '', 'text-align:left');

			if ($row['template'] != 0) {
				form_selectable_cell((($row['template_enabled'] == 'off' ) ? 'No': "<span title='{$row['template_name']}'>Yes</span>"), $row['id'], '', 'text-align:left');
			} else {
				form_selectable_cell('', $row['id'], '', 'text-align:left');
			}

			form_checkbox_cell($row['name'], $row['id'], '', 'text-align:left');
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan=12><center>No Thresholds</center></td></tr>';
	}
	print $nav;

	html_end_box(false);

	thold_legend();

	draw_actions_dropdown($thold_actions);

	if (isset($host_id) && $host_id != '') {
		print "<input type='hidden' name='host_id' value='$host_id'>";
	}

	form_end();

	bottom_footer();
}
