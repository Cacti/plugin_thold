<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
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

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include_once($config['base_path'] . '/plugins/thold/setup.php');
include_once($config['base_path'] . '/plugins/thold/includes/database.php');
include($config['base_path'] . '/plugins/thold/includes/arrays.php');

thold_initialize_rusage();

plugin_thold_upgrade();

if (!plugin_thold_check_strict()) {
	cacti_log('THOLD: You are running MySQL in Strict Mode, which is not supported by Thold.', true, 'POLLER');
	print '<br><br><center><font color=red>You are running MySQL in Strict Mode, which is not supported by Thold.</font></color>';
	exit;
}

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
case 'hoststat':
	general_header();
	thold_tabs();
	hosts();
	bottom_footer();
	break;
default:
	general_header();
	thold_tabs();
	thold_show_log();
	bottom_footer();
	break;
}

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION['sess_nav_level_cache'] = '';

function form_thold_filter() {
	global $item_rows, $config;

	?>
	<tr class='even'>
		<td>
		<form id='form_thold' action='thold_graph.php'>
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
						<select id='data_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('data_template_id') == '-1') {?> selected<?php }?>>All</option>
							<option value='0'<?php if (get_request_var('data_template_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$data_templates = db_fetch_assoc('SELECT DISTINCT data_template.id, data_template.name 
								FROM thold_data 
								LEFT JOIN data_template ON thold_data.data_template=data_template.id ' .
								(get_request_var('host_id') > 0 ? 'WHERE thold_data.host_id=' . get_request_var('host_id'):'') .
								' ORDER by data_template.name');

							if (sizeof($data_templates)) {
								foreach ($data_templates as $data_template) {
									print "<option value='" . $data_template['id'] . "'"; if (get_request_var('data_template_id') == $data_template['id']) { print ' selected'; } print '>' . $data_template['name'] . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Status
					</td>
					<td>
						<select id='triggered' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('triggered') == '-1') {?> selected<?php }?>>All</option>
							<option value='1'<?php if (get_request_var('triggered') == '1') {?> selected<?php }?>>Breached</option>
							<option value='3'<?php if (get_request_var('triggered') == '3') {?> selected<?php }?>>Triggered</option>
							<option value='2'<?php if (get_request_var('triggered') == '2') {?> selected<?php }?>>Enabled</option>
							<option value='0'<?php if (get_request_var('triggered') == '0') {?> selected<?php }?>>Disabled</option>
						</select>
					</td>
					<td>
						Thresholds
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' value='Go' onClick='applyFilter()'>
					</td>
					<td>
						<input id='clear' name='clear' type='button' value='Clear' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' id='tab' value='thold'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=thold&triggered=' + $('#triggered').val();
			strURL += '&data_template_id=' + $('#data_template_id').val();
			strURL += '&host_id=' + $('#host_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'thold_graph.php?header=false&action=thold&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#form_thold').submit(function(event) {
				event.preventDefault();
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
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'data_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'triggered' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('thold_filter_default')
			)
	);

	validate_store_request_vars($filters, 'sess_thold');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box('Threshold Status', '100%', '', '3', 'center', '');
	form_thold_filter();
	html_end_box();

	/* build the SQL query and WHERE clause */
	if (get_request_var('sort_column') == 'lastread') {
		$sort = get_request_var('sort_column') . "/1";
	}else{
		$sort = get_request_var('sort_column');
	}

	$limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql_where = '';

	/* triggered filter */
	if (get_request_var('triggered') == '-1') {
		/* return all rows */
	} else {
		if (get_request_var('triggered') == '0') { $sql_where = "(td.thold_enabled='off'"; } /*disabled*/
		if (get_request_var('triggered') == '2') { $sql_where = "(td.thold_enabled='on'"; } /* enabled */
		if (get_request_var('triggered') == '1') { $sql_where = "((td.thold_alert!=0 OR td.bl_alert>0)"; } /* breached */
		if (get_request_var('triggered') == '3') { $sql_where = "(((td.thold_alert!=0 AND td.thold_fail_count >= td.thold_fail_trigger) OR (td.bl_alert>0 AND td.bl_fail_count >= td.bl_fail_trigger))"; } /* triggered */
	}

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (strlen($sql_where) ? ' AND': '(') . " td.name LIKE '%" . get_request_var('filter') . "%'";
	}

	/* data template id filter */
	if (get_request_var('data_template_id') != '-1') {
		$sql_where .= (strlen($sql_where) ? ' AND': '(') . ' td.data_template=' . get_request_var('data_template_id');
	}

	/* host id filter */
	if (get_request_var('host_id') != '-1') {
		$sql_where .= (strlen($sql_where) ? ' AND': '(') . ' td.host_id=' . get_request_var('host_id');
	}

	if ($sql_where != '') {
		$sql_where .= ')';
	}

cacti_log($sql_where);
cacti_log($sort . ' ' . get_request_var('sort_direction'));
cacti_log(get_request_var('page'));
cacti_log(($rows*(get_request_var('page')-1)) . ", $rows");

	$tholds = get_allowed_thresholds($sql_where, $sort . ' ' . get_request_var('sort_direction'), ($rows*(get_request_var('page')-1)) . ", $rows", $total_rows);

	html_start_box('', '100%', '', '4', 'center', '');

	$nav = html_nav_bar('thold_graph.php?action=thold', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 13, 'Thresholds', 'page', 'main');

	print $nav;

	$display_text = array(
		'nosort' => array('Actions', ''),
		'name' => array('Name', 'ASC'),
		'id' => array('ID', 'ASC'),
		'thold_type' => array('Type', 'ASC'),
		'nosort2' => array('Trigger', 'ASC'),
		'nosort3' => array('Duration', 'ASC'),
		'repeat_alert' => array('Repeat', 'ASC'),
		'nosort4' => array('Warn Hi/Lo', 'ASC'),
		'nosort5' => array('Alert Hi/Lo', 'ASC'),
		'nosort6' => array('BL Hi/Lo', 'ASC'),
		'lastread' => array('Current', 'ASC'),
		'thold_alert' => array('Triggered', 'ASC'),
		'thold_enabled' => array('Enabled', 'ASC'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=thold');

	$step = read_config_option('poller_interval');

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$c=0;
	$i=0;

	if (sizeof($tholds)) {
		foreach ($tholds as $row) {
			$c++;
			$alertstat = 'no';
			$bgcolor   = 'green';
			if ($row['thold_type'] == 0) {
				if ($row['thold_alert'] != 0) {
					$alertstat='yes';
					if ( $row['thold_fail_count'] >= $row['thold_fail_trigger'] ) {
						$bgcolor = 'red';
					} elseif ( $row['thold_warning_fail_count'] >= $row['thold_warning_fail_trigger'] ) {
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
				if ($row['bl_alert'] == 1) {
					$alertstat = 'baseline-LOW';
					$bgcolor   = ($row['bl_fail_count'] >= $row['bl_fail_trigger'] ? 'orange' : 'yellow');
				} elseif ($row['bl_alert'] == 2)  {
					$alertstat = 'baseline-HIGH';
					$bgcolor   = ($row['bl_fail_count'] >= $row['bl_fail_trigger'] ? 'orange' : 'yellow');
				}
			};

			if ($row['thold_enabled'] == 'off') {
				print "<tr class='" . $thold_states['grey']['class'] . "' id='line" . $row['id'] . "'>\n";
			}else{
				print "<tr class='" . $thold_states[$bgcolor]['class'] . "' id='line" . $row['id'] . "'>\n";
			}

			print "<td width='1%' style='white-space:nowrap;'>";
			if (api_user_realm_auth('thold_add.php')) {
				print '<a href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?rra=' . $row["rra_id"] . '&view_rrd=' . $row["data_id"]) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="Edit Threshold"></a>';
			}
			if ($row["thold_enabled"] == 'on') {
				print '<a href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?id=' . $row["id"] .'&action=disable') . '"><img src="' . $config['url_path'] . 'plugins/thold/images/disable_thold.png" border="0" alt="" title="Disable Threshold"></a>';
			}else{
				print '<a href="' .  htmlspecialchars($config['url_path'] . 'plugins/thold/thold.php?id=' . $row["id"] . '&action=enable') . '"><img src="' . $config['url_path'] . 'plugins/thold/images/enable_thold.png" border="0" alt="" title="Enable Threshold"></a>';
			}
			print "<a href='". htmlspecialchars($config['url_path'] . "graph.php?local_graph_id=" . $row['graph_id'] . "&rra_id=all") . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='View Graph'></a>";
			print "<a href='". htmlspecialchars($config['url_path'] . "plugins/thold/thold_graph.php?action=log&threshold_id=" . $row["id"] . "&status=-1") . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_log.gif' border='0' alt='' title='View Threshold History'></a>";

			print "</td>";
			print "<td class='nowrap'>" . ($row['name'] != '' ? $row['name'] : 'No name set') . "</td>";
			print "<td class='right'>" . $row["id"] . "</td>";
			print "<td class='nowrap'>" . $thold_types[$row['thold_type']] . "</td>";
			switch($row['thold_type']) {
				case 0:
					print "<td class='nowrap'><i>" . plugin_thold_duration_convert($row['rra_id'], $row['thold_fail_trigger'], 'alert') . "</i></td>";
					print "<td>N/A</td>";
					break;
				case 1:
					print "<td class='nowrap'><i>" . plugin_thold_duration_convert($row['rra_id'], $row['bl_fail_trigger'], 'alert') . "</i></td>";
					print "<td class='nowrap'>" . $timearray[$row['bl_ref_time_range']/300]. "</td>";;
					break;
				case 2:
					print "<td class='nowrap'><i>" . $row['time_fail_trigger'] . " Triggers</i></td>";
					print "<td class='nowrap'>" . plugin_thold_duration_convert($row['rra_id'], $row['time_fail_length'], 'time') . "</td>";;
					break;
				default:
					print "<td>N/A</td>";
					print "<td>N/A</td>";
			}
			print "<td class='nowrap'>" . ($row['repeat_alert'] == '' ? '' : plugin_thold_duration_convert($row['rra_id'], $row['repeat_alert'], 'repeat')) . "</td>";
			print "<td class='nowrap'>" . ($row['thold_type'] == 1 ? "N/A":($row['thold_type'] == 2 ? thold_format_number($row['time_warning_hi']) . '/' . thold_format_number($row['time_warning_low']) : thold_format_number($row['thold_warning_hi']) . '/' . thold_format_number($row['thold_warning_low']))) . "</td>";
			print "<td>" . ($row['thold_type'] == 1 ? "N/A":($row['thold_type'] == 2 ? thold_format_number($row['time_hi']) . '/' . thold_format_number($row['time_low']) : thold_format_number($row['thold_hi']) . '/' . thold_format_number($row['thold_low']))) . "</td>";
			print "<td>" . ($row['thold_type'] == 1 ? $row['bl_pct_up'] . (strlen($row['bl_pct_up']) ? '%':'-') . '/' . $row['bl_pct_down'] . (strlen($row['bl_pct_down']) ? '%':'-'): 'N/A') . "</td>";
			print "<td>" . thold_format_number($row['lastread']) . "</td>";
			print "<td>" . ($row['thold_alert'] ? "yes":"no") . "</td>";
			if ($row['thold_enabled'] == 'off') {
				print "<td><b>Disabled</b></td>";
			}else{
				print "<td>Enabled</td>";
			}
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td class="center" colspan=13>No Thresholds</td></tr>';
	}
	print $nav;
	html_end_box(false);

	thold_legend();

	//thold_display_rusage();
}


/* form_host_status_row_color - returns a color to use based upon the host's current status*/
function form_host_status_row_color($status, $disabled) {
	global $thold_host_states;

	// Determine the color to use
	if ($disabled) {
		$class = $thold_host_states['disabled']['class'];
	} else {
		$class = $thold_host_states[$status]['class'];
	}

	print "<tr class='$class'>\n";

	return $class;
}

function get_uncolored_device_status($disabled, $status) {
	if ($disabled) {
		return "Disabled";
	}else{
		switch ($status) {
			case HOST_DOWN:
				return "Down";
				break;
			case HOST_RECOVERING:
				return "Recovering";
				break;
			case HOST_UP:
				return "Up";
				break;
			case HOST_ERROR:
				return "Error";
				break;
			default:
				return "Unknown";
				break;
		}
	}
}

function hosts() {
	global $config, $device_actions, $item_rows, $host_colors, $notmon_color;

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
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
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
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box('Device Status', '100%', '', '3', 'center', '');
	form_host_filter();
	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if (get_request_var('filter') != '') {
		$sql_where = "((h.hostname LIKE '%" . get_request_var('filter') . "%' OR h.description LIKE '%" . get_request_var('filter') . "%')";
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_status') == '-2') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "h.disabled='on'";
	}elseif (get_request_var('host_status') == '-3') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "h.disabled=''";
	}elseif (get_request_var('host_status') == '-4') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "(h.status!='3' OR h.disabled='on')";
	}elseif (get_request_var('host_status') == '-5') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "(h.availability_method=0)";
	}elseif (get_request_var('host_status') == '3') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "(h.availability_method!=0 AND h.status=3 AND h.disabled='')";
	}else {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "(h.status=" . get_request_var('host_status') . " AND h.disabled = '')";
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "h.host_template_id=0'";
	}elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= (strlen($sql_where) ? ' AND ':'(') . "h.host_template_id=" . get_request_var('host_template_id');
	}

	$sql_where .= (strlen($sql_where) ? ')':'');

	html_start_box('', '100%', '', '3', 'center', '');

	$sortby = get_request_var('sort_column');
	if ($sortby=='hostname') {
		$sortby = 'INET_ATON(hostname)';
	}

	$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
	$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

	$hosts = get_allowed_devices($sql_where, $sortby . ' ' . get_request_var('sort_direction'), ($rows*(get_request_var('page')-1)) . ',' . $rows, $total_rows);

	$nav = html_nav_bar('thold_graph.php?action=hoststat', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, 'Devices', 'page', 'main');

	print $nav;

	$display_text = array(
		'nosort'             => array('display' => 'Actions',      'align' => 'left'),
		'description'        => array('display' => 'Description',  'align' => 'left',   'sort' => 'ASC'),
		'id'                 => array('display' => 'ID',           'align' => 'right',  'sort' => 'ASC'),
		'nosort1'            => array('display' => 'Graphs',       'align' => 'right',  'sort' => 'ASC'),
		'nosort2'            => array('display' => 'Data Sources', 'align' => 'right',  'sort' => 'ASC'),
		'status'             => array('display' => 'Status',       'align' => 'center', 'sort' => 'ASC'),
		'status_event_count' => array('display' => 'Event Count',  'align' => 'right',  'sort' => 'ASC'),
		'hostname'           => array('display' => 'Hostname',     'align' => 'right',   'sort' => 'ASC'),
		'cur_time'           => array('display' => 'Current (ms)', 'align' => 'right',  'sort' => 'DESC'),
		'avg_time'           => array('display' => 'Average (ms)', 'align' => 'right',  'sort' => 'DESC'),
		'availability'       => array('display' => 'Availability', 'align' => 'right',  'sort' => 'ASC'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=hoststat');

	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			if (isset($host_graphs[$host['id']])) {
				$graphs = $host_graphs[$host['id']];
			}else{
				$graphs = 0;
			}

			if (isset($host_data_sources[$host['id']])) {
				$ds = $host_data_sources[$host['id']];
			}else{
				$ds = 0;
			}

			if ($host['availability_method'] != 0) {
				form_host_status_row_color($host['status'], $host['disabled']); 
				print "<td width='1%' class='nowrap'>";
				if (api_user_realm_auth('host.php')) {
					print '<a href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="Edit Device"></a>';
				}
				print "<a href='" . htmlspecialchars($config['url_path'] . 'graph_view.php?action=preview&graph_template_id=0&filter=&host_id=' . $host['id']) . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='View Graphs'></a>";
				print '</td>';
				?>
				<td style='text-align:left'>
					<?php print filter_value($host['description'], get_request_var('filter'));?>
				</td>
				<td style='text-align:right'><?php print round(($host['id']), 2);?></td>
				<td style='text-align:right'><i><?php print $graphs;?></i></td>
				<td style='text-align:right'><i><?php print $ds;?></i></td>
				<td style='text-align:center'><?php print get_uncolored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']);?></td>
				<td style='text-align:right'><?php print round(($host['status_event_count']), 2);?></td>
				<td style='text-align:right'><?php print filter_value($host['hostname'], get_request_var('filter'));?></td>
				<td style='text-align:right'><?php print round(($host['cur_time']), 2);?></td>
				<td style='text-align:right'><?php print round(($host['avg_time']), 2);?></td>
				<td style='text-align:right'><?php print round($host['availability'], 2);?></td>
				<?php
			}else{
				print "<tr class='deviceNotMonFull'>\n";
				print "<td width='1%' class='nowrap'>\n";
				if (api_user_realm_auth('host.php')) {
					print '<a href="' . htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host["id"]) . '"><img src="' . $config['url_path'] . 'plugins/thold/images/edit_object.png" border="0" alt="" title="Edit Device"></a>';
				}
				print "<a href='" . htmlspecialchars($config['url_path'] . "graph_view.php?action=preview&graph_template_id=0&filter=&host_id=" . $host["id"]) . "'><img src='" . $config['url_path'] . "plugins/thold/images/view_graphs.gif' border='0' alt='' title='View Graphs'></a>";
				print "</td>";
				?>
				<td style='text-align:left'>
					<?php print filter_value($host['description'], get_request_var('filter'));?>
				</td>
				<td style='text-align:right'><?php print $host['id'];?></td>
				<td style='text-align:right'><i><?php print $graphs;?></i></td>
				<td style='text-align:right'><i><?php print $ds;?></i></td>
				<td style='text-align:center'><?php print 'Not Monitored';?></td>
				<td style='text-align:right'><?php print 'N/A';?></td>
				<td style='text-align:right'><?php print filter_value($host['hostname'], get_request_var('filter'));?></td>
				<td style='text-align:right'><?php print 'N/A';?></td>
				<td style='text-align:right'><?php print 'N/A';?></td>
				<td style='text-align:right'><?php print 'N/A';?></td>
				<?php
			}

			form_end_row();
		}

		print $nav;
	}else{
		print '<tr><td class="center" colspan="11">No Devices</td></tr>';
	}

	html_end_box(false);

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
						Search
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						Type
					</td>
					<td>
						<select id='host_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>>All</option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc('select id,name from host_template order by name');

							if (sizeof($host_templates)) {
							foreach ($host_templates as $host_template) {
								print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . $host_template['name'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Status
					</td>
					<td>
						<select id='host_status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_status') == '-1') {?> selected<?php }?>>All</option>
							<option value='-3'<?php if (get_request_var('host_status') == '-3') {?> selected<?php }?>>Enabled</option>
							<option value='-2'<?php if (get_request_var('host_status') == '-2') {?> selected<?php }?>>Disabled</option>
							<option value='-4'<?php if (get_request_var('host_status') == '-4') {?> selected<?php }?>>Not Up</option>
							<option value='-5'<?php if (get_request_var('host_status') == '-5') {?> selected<?php }?>>Not Monitored</option>
							<option value='3'<?php if (get_request_var('host_status') == '3') {?> selected<?php }?>>Up</option>
							<option value='1'<?php if (get_request_var('host_status') == '1') {?> selected<?php }?>>Down</option>
							<option value='2'<?php if (get_request_var('host_status') == '2') {?> selected<?php }?>>Recovering</option>
							<option value='0'<?php if (get_request_var('host_status') == '0') {?> selected<?php }?>>Unknown</option>
						</select>
					</td>
					<td>
						Devices
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input id='refresh' type='button' value='Go' onClick='applyFilter()'>
					</td>
					<td>
						<input id='clear' type='button' value='Clear' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' name='tab' value='hoststat'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=hoststat&host_status=' + $('#host_status').val();
			strURL += '&host_template_id=' + $('#host_template_id').val();
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
		});

		</script>
		</td>
	</tr>
	<?php
}

function thold_show_log() {
	global $config, $item_rows, $thold_log_states;

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
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'time',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'threshold_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_id' => array(
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
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box('Threshold Log [last 30 days]', '100%', '', '3', 'center', '');
	form_thold_log_filter();
	html_end_box();

	$sql_where = '';

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND':'WHERE') . ' host.id IS NULL';
	}elseif (!isempty_request_var('host_id')) {
		$sql_where .= (strlen($sql_where) ? ' AND':'WHERE') . ' plugin_thold_log.host_id=' . get_request_var('host_id');
	}

	if (get_request_var('threshold_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('threshold_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND':'WHERE') . ' thold_data.id IS NULL';
	}elseif (!isempty_request_var('threshold_id')) {
		$sql_where .= (strlen($sql_where) ? ' AND':'WHERE') . ' plugin_thold_log.threshold_id=' . get_request_var('threshold_id');
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	}else{
		$sql_where .= (strlen($sql_where) ? ' AND':'WHERE') . ' plugin_thold_log.status=' . get_request_var('status');
	}

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (strlen($sql_where) ? ' AND':'WHERE') . " plugin_thold_log.description LIKE '%" . get_request_var('filter') . "%'";
	}

	html_start_box('', '100%', '', '3', 'center', '');

	$sortby = get_request_var('sort_column');

	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);

	$sql_where .= ' AND ' . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

	$total_rows = db_fetch_cell('SELECT
		COUNT(*)
		FROM plugin_thold_log
		LEFT JOIN host ON plugin_thold_log.host_id=host.id
		LEFT JOIN thold_data ON plugin_thold_log.threshold_id=thold_data.id
		LEFT JOIN graph_templates_graph AS gtg ON plugin_thold_log.graph_id=gtg.local_graph_id
		LEFT JOIN user_auth_perms
		ON (host.id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ")
		$sql_where");

	$sql_query = 'SELECT plugin_thold_log.*, host.description AS hdescription, thold_data.name AS name, gtg.title_cache
		FROM plugin_thold_log
		LEFT JOIN host ON plugin_thold_log.host_id=host.id
		LEFT JOIN thold_data ON plugin_thold_log.threshold_id=thold_data.id
		LEFT JOIN graph_templates_graph AS gtg ON plugin_thold_log.graph_id=gtg.local_graph_id
		LEFT JOIN user_auth_perms
		ON (host.id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ")
		$sql_where
		ORDER BY " . $sortby . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	//print $sql_query;

	$logs = db_fetch_assoc($sql_query);

	$nav = html_nav_bar('thold_graph.php?action=log', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, 'Log Entries', 'page', 'main');

	print $nav;

	$display_text = array(
		'hdescription'    => array('Device', 'ASC'),
		'name'            => array('Threshold', 'ASC'),
		'time'            => array('Time', 'ASC'),
		'threshold_value' => array('Alarm Value', 'ASC'),
		'current'         => array('Current Value', 'ASC'),
		'status'          => array('Status', 'DESC'),
		'type'            => array('Type', 'DESC'),
		'description'     => array('Event Description', 'ASC'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'thold_graph.php?action=log');

	$i = 0;
	if (sizeof($logs)) {
		foreach ($logs as $l) {
			?>
			<tr class='<?php print $thold_log_states[$thold_status]['class'];?>'>
			<td class='nowrap'><?php print $l['hdescription'];?></td>
			<td class='nowrap'><?php print $l['name'];?></td>
			<td class='nowrap'><?php print date('Y-m-d H:i:s', $l['time']);?></td>
			<td><?php print ($l['threshold_value'] != '' ? thold_format_number($l['threshold_value']):'N/A');?></td>
			<td><?php print ($l['current'] != '' ? thold_format_number($l['current']):'N/A');?></td>
			<td class='nowrap'><?php print $thold_status[$l['status']];?></td>
			<td class='nowrap'><?php print $thold_types[$l['type']];?></td>
			<td class='nowrap'><?php print (strlen($l['description']) ? $l['description']:'Restoral Event');?></td>
			<?php

			form_end_row();
		}
	}else{
		print '<tr><td class="center" colspan="8">No Threshold Logs Found</td></tr>';
	}

	/* put the nav bar on the bottom as well */
	print $nav;

	html_end_box(false);

	log_legend();

	//thold_display_rusage();
}

function form_thold_log_filter() {
	global $item_rows, $config;

	?>
	<tr class='even'>
		<td>
		<form id='form_log' action='thold_graph.php?action=log'>
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
						Threshold
					</td>
					<td>
						<select id='threshold_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('threshold_id') == '-1') {?> selected<?php }?>>All</option>
							<option value='0'<?php if (get_request_var('threshold_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$tholds = db_fetch_assoc('SELECT DISTINCT thold_data.id, thold_data.name
								FROM thold_data
								INNER JOIN plugin_thold_log ON thold_data.id=plugin_thold_log.threshold_id ' .
								(get_request_var('host_id') > 0 ? 'WHERE thold_data.host_id=' . get_request_var('host_id'):'') .
								' ORDER by thold_data.name');

							if (sizeof($tholds)) {
								foreach ($tholds as $thold) {
									print "<option value='" . $thold['id'] . "'"; if (get_request_var('threshold_id') == $thold['id']) { print ' selected'; } print '>' . $thold['name'] . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Status
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('status') == '-1') {?> selected<?php }?>>All</option>
							<option value='4'<?php if (get_request_var('status') == '4') {?> selected<?php }?>>Notify - Alarm</option>
							<option value='7'<?php if (get_request_var('status') == '7') {?> selected<?php }?>>Notify - Alarm2Warning</option>
							<option value='3'<?php if (get_request_var('status') == '3') {?> selected<?php }?>>Notify - Warning</option>
							<option value='2'<?php if (get_request_var('status') == '2') {?> selected<?php }?>>Notify - ReTriggers</option>
							<option value='5'<?php if (get_request_var('status') == '5') {?> selected<?php }?>>Notify - Restoral</option>
							<option value='1'<?php if (get_request_var('status') == '1') {?> selected<?php }?>>Triggers - Alert</option>
							<option value='6'<?php if (get_request_var('status') == '6') {?> selected<?php }?>>Triggers - Warning</option>
							<option value='0'<?php if (get_request_var('status') == '0') {?> selected<?php }?>>Restorals</option>
						</select>
					</td>
					<td>
						Log Entries
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input id='refresh' type='submit' value='Go'>
					</td>
					<td>
						<input id='clear' type='submit' value='Clear'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print get_request_var('filter');?>'>
			<input type='hidden' name='tab' value='log'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold_graph.php?header=false&action=log&status=' + $('#status').val();
			strURL += '&threshold_id=' + $('#threshold_id').val();
			strURL += '&host_id=' + $('#host_id').val();
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
		});

		</script>
		</td>
	</tr>
	<?php
}
