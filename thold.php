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

chdir('../../');

include_once('./include/auth.php');
include_once($config['library_path'] . '/rrd.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

get_filter_request_var('view_rra');
get_filter_request_var('host_id');
get_filter_request_var('local_data_id');
get_filter_request_var('id');

get_filter_request_var('view_rrd');
get_filter_request_var('data_template_rrd_id');
get_filter_request_var('local_data_id');

set_default_action();

$host_id = '';
if (isset_request_var('local_data_id')) {
	$local_data_id = get_request_var('local_data_id');
	$host_id        = db_fetch_cell('SELECT host_id FROM thold_data WHERE local_data_id=' . $local_data_id . ' LIMIT 1');

	if (empty($host_id)) {
		$host_id = db_fetch_cell('SELECT host_id FROM poller_item WHERE local_data_id=' . $local_data_id . ' LIMIT 1');
	}

	if (!thold_user_auth_threshold($local_data_id, $host_id)) {
		top_header();
		print '<span class="textError">Access Denied - You do not have permissions to access that threshold.</span>';
		bottom_footer();
		exit;
	}
} else {
	set_request_var('local_data_id', '');

	$local_data_id = '';
	if (isset_request_var('host_id')) {
		$host_id = get_filter_request_var('host_id');
	}
}

if (isset($_SERVER['HTTP_REFERER'])) {
	if (preg_match('/(graph_view.php|graph.php)/', $_SERVER['HTTP_REFERER'])) {
		$_SESSION['graph_return'] = $_SERVER['HTTP_REFERER'];
	}
}

switch(get_request_var('action')) {
	case 'save':
		save_thold();

		if (isset($_SESSION['graph_return'])) {
			$return_to = $_SESSION['graph_return'];
			unset($_SESSION['graph_return']);
			kill_session_var('graph_return');

			header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');
		}else{
			top_header();
		}

		break;
	case 'autocreate':
		$c = autocreate($host_id);
		if ($c == 0) {
			$_SESSION['thold_message'] = '<font size=-1>Either No Templates or Threshold(s) Already Exists - No thresholds were created.</font>';
		}
		raise_message('thold_created');

		if (isset($_SESSION['graph_return'])) {
			$return_to = $_SESSION['graph_return'];
			unset($_SESSION['graph_return']);
			kill_session_var('graph_return');
			header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');
		}else{
			header('Location: ../../graphs_new.php?host_id=' . $host_id);
		}
		exit;

		break;
	case 'disable':
		thold_threshold_disable(get_filter_request_var('id'));

		if (isset($_SERVER['HTTP_REFERER'])) {
			$return_to = $_SERVER['HTTP_REFERER'];
		}else{
			$return_to = 'thold.php';
		}

		header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');

		exit;
	case 'enable':
		thold_threshold_enable(get_filter_request_var('id'));

		if (isset($_SERVER['HTTP_REFERER'])) {
			$return_to = $_SERVER['HTTP_REFERER'];
		}else{
			$return_to = 'thold.php';
		}

		header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');

		exit;
}

top_header();

$t = db_fetch_assoc('SELECT id, name, name_cache FROM data_template_data WHERE local_data_id=' . $local_data_id . ' LIMIT 1');
$desc = $t[0]['name_cache'];
unset($t);

$rrdsql   = array_rekey(db_fetch_assoc("SELECT id FROM data_template_rrd WHERE local_data_id=$local_data_id ORDER BY id"), 'id', 'id');
$sql      = 'task_item_id IN (' . implode(', ', $rrdsql) . ') AND graph_template_id>0';
$grapharr = db_fetch_assoc("SELECT DISTINCT local_graph_id FROM graph_templates_item WHERE $sql");

// Take the first one available
$graph = (isset($grapharr[0]['local_graph_id']) ? $grapharr[0]['local_graph_id'] : '');

$dt_sql = 'SELECT DISTINCT dtr.local_data_id
	FROM data_template_rrd AS dtr
	LEFT JOIN graph_templates_item AS gti
	ON gti.task_item_id=dtr.id
	LEFT JOIN graph_local AS gl
	ON gl.id=gti.local_graph_id
	WHERE gl.id=' . $graph;

$template_data_rrds = db_fetch_assoc("SELECT id, data_source_name, local_data_id 
	FROM data_template_rrd 
	WHERE local_data_id IN ($dt_sql) 
	ORDER BY id");

form_start('thold.php', 'thold');

html_start_box('Graph Data', '100%', '', '3', 'center', '');

?>
<tr>
	<td class='textArea'>
		<?php if (isset($banner)) { echo $banner . '<br><br>'; }; ?>
		Data Source Description: <br><?php echo $desc; ?><br><br>
		Associated Graph (graphs that use this RRD): <br>
		<select name='element'>
			<?php
			foreach($grapharr as $g) {
				$graph_desc = db_fetch_assoc('SELECT local_graph_id,
					title,
					title_cache
					FROM graph_templates_graph
					WHERE local_graph_id=' . $g['local_graph_id']);

				echo "<option value='" . $graph_desc[0]['local_graph_id'] . "'";
				if ($graph_desc[0]['local_graph_id'] == $graph) echo ' selected';
				echo '>' . $graph_desc[0]['local_graph_id'] . ' - ' . $graph_desc[0]['title_cache'] . " </option>\n";
			} ?>
		</select>
		<br>
		<br>
	</td>
	<td class='textArea'>
		<img id='graphimage' src='<?php echo htmlspecialchars($config['url_path'] . 'graph_image.php?local_graph_id=' . $graph . '&rra_id=0&graph_start=-32400&graph_height=140&graph_width=500');?>'>
	</td>
</tr>
<?php
html_end_box();

/* select the first 'rrd' of this data source by default */
if (isempty_request_var('view_rrd')) {
	if (isset_request_var('data_template_rrd_id')) {
		set_request_var('view_rrd', get_filter_request_var('data_template_rrd_id'));
	} else {
		/* Check and see if we already have a threshold set, and use that if so */
		$thold_data = db_fetch_cell("SELECT data_template_rrd_id FROM thold_data WHERE local_data_id = $local_data_id ORDER BY data_template_rrd_id");

		if ($thold_data) {
			set_request_var('view_rrd', $thold_data);
		} else {
			set_request_var('view_rrd', (isset($template_data_rrds[0]['id']) ? $template_data_rrds[0]['id'] : '0'));
		}
	}
}

/* get more information about the rrd we chose */
if (!isempty_request_var('view_rrd')) {
	$template_rrd = db_fetch_row('SELECT * FROM data_template_rrd WHERE id=' . get_filter_request_var('view_rrd'));
}

//-----------------------------
// Tabs (if more than one item)
//-----------------------------
$i  = 0;
$ds = 0;
if (isset($template_data_rrds)) {
	if (sizeof($template_data_rrds)) {
		/* draw the data source tabs on the top of the page */
		print "<br><div class='tabs'><nav><ul>\n";

		foreach ($template_data_rrds as $template_data_rrd) {
			if ($template_data_rrd['id'] == get_request_var('view_rrd')) $ds = $template_data_rrd['data_source_name'];

			$item = db_fetch_assoc('SELECT * FROM thold_data WHERE data_template_rrd_id=' . $template_data_rrd['id']);
			$item = count($item) > 0 ? $item[0]: $item;

			$cur_setting = '';
			if (count($item) == 0) {
				$cur_setting .= "<span style='padding-right:4px;'>n/a</span>";
			} else {
				$cur_setting = '<span style="padding-right:4px;">Last:</span>' . ($item['lastread'] == '' ? "<span>n/a</span>":"<span style='color:blue;'>" . thold_format_number($item['lastread'],4) . "</span>");
				if ($item['thold_type'] != 1) {
					$cur_setting .= '<span style="padding:4px">WHi:</span>' . ($item['thold_warning_hi'] == '' ? "<span>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item['thold_warning_hi'],2) . '</span>');
					$cur_setting .= '<span style="padding:4px">WLo:</span>' .($item['thold_warning_low'] == '' ? "<span>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item['thold_warning_low'],2) . '</span>');
					$cur_setting .= '<span style="padding:4px">AHi:</span>' . ($item['thold_hi'] == '' ? "<span>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item['thold_hi'],2) . '</span>');
					$cur_setting .= '<span style="padding:4px">ALo:</span>' .($item['thold_low'] == '' ? "<span>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item['thold_low'],2) . '</span>');

				}else{
					$cur_setting .= '<span style="padding:4px">AHi:</span>' .($item['thold_hi'] == '' ? "<span>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item['thold_hi'],2) . '</span>');
					$cur_setting .= '<span style="padding:4px">ALo:</span>' .($item['thold_low'] == '' ? "<span>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item['thold_low'],2) . '</span>');
					$cur_setting .= '<span>BL: (Up ' . $item['bl_pct_up'] . '%/Down ' . $item['bl_pct_down'] . '%)</span>';
				}
			}

			if ($template_data_rrd['local_data_id'] == $local_data_id) {
				$selected = 'selected';
			}else{
				$selected = '';
			}

			echo "<li class='textEditTitle'><a class='hyperLink $selected' href='" . htmlspecialchars('thold.php?local_data_id=' . $template_data_rrd['local_data_id'] . '&view_rrd=' . $template_data_rrd['id']) . "'>" . $template_data_rrd['data_source_name'] . '<br>' . $cur_setting . '</a></li>';
			unset($thold_item_data);
		}

		print "</ul></nav></div>\n";
	}elseif (sizeof($template_data_rrds) == 1) {
		set_request_var('view_rrd', $template_data_rrds[0]['id']);
	}
}

//----------------------
// Data Source Item Form
//----------------------
$thold_item_data = db_fetch_assoc("SELECT * FROM thold_data WHERE data_template_rrd_id=" . get_filter_request_var('view_rrd'));

$thold_item_data = count($thold_item_data) > 0 ? $thold_item_data[0] : $thold_item_data;
$thold_item_data_cdef = (isset($thold_item_data['cdef']) ? $thold_item_data['cdef'] : 0);

if (isset($thold_item_data['template'])) {
	$thold_item_data['template_name'] = db_fetch_cell('SELECT name FROM thold_template WHERE id = ' . $thold_item_data['template']);
}

$header_text = "Data Source Item [" . (isset($template_rrd) ? $template_rrd["data_source_name"] : "") . "] " .
    " - Current value: [" . get_current_value($local_data_id, $ds, $thold_item_data_cdef) . "]";

html_start_box($header_text, "100%", $colors["header"], "3", "center", "");

$send_notification_array = array();

$users = db_fetch_assoc("SELECT plugin_thold_contacts.id, plugin_thold_contacts.data,
	plugin_thold_contacts.type, user_auth.full_name
	FROM plugin_thold_contacts, user_auth
	WHERE user_auth.id=plugin_thold_contacts.user_id
	AND plugin_thold_contacts.data!=''
	ORDER BY user_auth.full_name ASC, plugin_thold_contacts.type ASC");

if (!empty($users)) {
	foreach ($users as $user) {
		$send_notification_array[$user['id']] = $user['full_name'] . ' - ' . ucfirst($user['type']);
	}
}

if (isset($thold_item_data['id'])) {
	$sql  = 'SELECT contact_id as id FROM plugin_thold_threshold_contact WHERE thold_id=' . $thold_item_data['id'];
	$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE local_data_id = ' . $thold_item_data['local_data_id'], FALSE);
} else {
	$sql  = 'SELECT contact_id as id FROM plugin_thold_threshold_contact WHERE thold_id=0';
	$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE local_data_id = ' . $local_data_id, FALSE);
}

include($config['base_path'] . '/plugins/thold/includes/arrays.php');

$data_fields = array();

$reference_types = get_reference_types($local_data_id, $step, $timearray);

if (isset($thold_item_data['data_template_id'])) {
	$temp = db_fetch_assoc('SELECT id, local_data_template_rrd_id, data_source_name, data_input_field_id
		FROM data_template_rrd
		WHERE local_data_id=' . $thold_item_data['local_data_id']);
} else {
	$temp = db_fetch_assoc('SELECT id, local_data_template_rrd_id, data_source_name, data_input_field_id
		FROM data_template_rrd
		WHERE local_data_id=' . $local_data_id);
}

foreach ($temp as $d) {
	if ($d['data_input_field_id'] != 0) {
		$temp2 = db_fetch_assoc('SELECT name FROM data_input_fields WHERE id=' . $d['data_input_field_id']);
	} else {
		$temp2[0]['name'] = $d['data_source_name'];
	}
	if ((isset_request_var('view_rrd') && $d['id'] != get_filter_request_var('view_rrd')) || (isset($thold_item_data['data_template_rrd_id']) && $d['id'] != $thold_item_data['data_template_rrd_id'])) {
		$data_fields[$d['data_source_name']] = $temp2[0]['name'];
	}
}

$replacements = db_fetch_assoc("SELECT DISTINCT field_name
	FROM data_local AS dl
	INNER JOIN host_snmp_cache AS hsc
	ON dl.snmp_query_id=hsc.snmp_query_id
	AND dl.host_id=hsc.host_id
	WHERE dl.id=" . (isset($thold_item_data['data_template_id']) ? $thold_item_data['local_data_id']:$local_data_id));

$nr = array();
if (sizeof($replacements)) {
	foreach($replacements as $r) {
		$nr[] = "<span style='color:blue;'>|query_" . $r['field_name'] . "|</span>";
	}
}

$vhf = explode("|", trim(VALID_HOST_FIELDS, "()"));
if (sizeof($vhf)) {
	foreach($vhf as $r) {
		$nr[] = "<span style='color:blue;'>|" . $r . "|</span>";
	}
}

$replacements = "<br><b>Replacement Fields:</b> " . implode(", ", $nr);

$dss = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE local_data_id=" . $local_data_id);

if (sizeof($dss)) {
	foreach($dss as $ds) {
		$dsname[] = "<span style='color:blue;'>|ds:" . $ds["data_source_name"] . "|</span>";
	}
}

$datasources = "<br><b>Data Sources:</b> " . implode(", ", $dsname);

$form_array = array(
		'template_header' => array(
			'friendly_name' => 'Template Settings',
			'method' => 'spacer',
		),
		'template_enabled' => array(
			'friendly_name' => 'Template Propagation Enabled',
			'method' => 'checkbox',
			'default' => '',
			'description' => 'Whether or not these settings will be propagates from the threshold template.',
			'value' => isset($thold_item_data['template_enabled']) ? $thold_item_data['template_enabled'] : '',
		),
		'template_name' => array(
			'friendly_name' => 'Template Name',
			'method' => 'custom',
			'default' => '',
			'description' => 'Name of the Threshold Template the threshold was created from.',
			'value' => isset($thold_item_data['template_name']) ? $thold_item_data['template_name'] : 'N/A',
		),
		'general_header' => array(
			'friendly_name' => 'General Settings',
			'method' => 'spacer',
		),
		'name' => array(
			'friendly_name' => 'Threshold Name',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => '70',
			'default' => $desc . ' [' . $template_rrd['data_source_name'] . ']',
			'description' => 'Provide the Thresholds a meaningful name',
			'value' => isset($thold_item_data['name']) ? $thold_item_data['name'] : ''
		),
		'thold_enabled' => array(
			'friendly_name' => 'Threshold Enabled',
			'method' => 'checkbox',
			'default' => 'on',
			'description' => 'Whether or not this threshold will be checked and alerted upon.',
			'value' => isset($thold_item_data['thold_enabled']) ? $thold_item_data['thold_enabled'] : ''
		),
		'exempt' => array(
			'friendly_name' => 'Weekend Exemption',
			'description' => 'If this is checked, this Threshold will not alert on weekends.',
			'method' => 'checkbox',
			'default' => 'off',
			'value' => isset($thold_item_data['exempt']) ? $thold_item_data['exempt'] : ''
			),
		'restored_alert' => array(
			'friendly_name' => 'Disable Restoration Email',
			'description' => 'If this is checked, Thold will not send an alert when the threshold has returned to normal status.',
			'method' => 'checkbox',
			'default' => 'off',
			'value' => isset($thold_item_data['restored_alert']) ? $thold_item_data['restored_alert'] : ''
			),
		'thold_type' => array(
			'friendly_name' => 'Threshold Type',
			'method' => 'drop_array',
			'on_change' => 'changeTholdType()',
			'array' => $thold_types,
			'default' => read_config_option('thold_type'),
			'description' => 'The type of Threshold that will be monitored.',
			'value' => isset($thold_item_data['thold_type']) ? $thold_item_data['thold_type'] : ''
		),
		'repeat_alert' => array(
			'friendly_name' => 'Re-Alert Cycle',
			'method' => 'drop_array',
			'array' => $repeatarray,
			'default' => read_config_option('alert_repeat'),
			'description' => 'Repeat alert after this amount of time has pasted since the last alert.',
			'value' => isset($thold_item_data['repeat_alert']) ? $thold_item_data['repeat_alert'] : ''
		),
		'thold_warning_header' => array(
			'friendly_name' => 'Warning - High / Low Settings',
			'method' => 'spacer',
		),
		'thold_warning_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, warning will be triggered',
			'value' => isset($thold_item_data['thold_warning_hi']) ? $thold_item_data['thold_warning_hi'] : ''
		),
		'thold_warning_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, warning will be triggered',
			'value' => isset($thold_item_data['thold_warning_low']) ? $thold_item_data['thold_warning_low'] : ''
		),
		'thold_warning_fail_trigger' => array(
			'friendly_name' => 'Breach Duration',
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => 'The amount of time the data source must be in breach of the threshold for a warning to be raised.',
			'value' => isset($thold_item_data['thold_warning_fail_trigger']) ? $thold_item_data['thold_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'thold_header' => array(
			'friendly_name' => 'Alert - High / Low Settings',
			'method' => 'spacer',
		),
		'thold_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_hi']) ? $thold_item_data['thold_hi'] : ''
		),
		'thold_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['thold_low']) ? $thold_item_data['thold_low'] : ''
		),
		'thold_fail_trigger' => array(
			'friendly_name' => 'Breach Duration',
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => 'The amount of time the data source must be in breach of the threshold for an alert to be raised.',
			'value' => isset($thold_item_data['thold_fail_trigger']) ? $thold_item_data['thold_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_header' => array(
			'friendly_name' => 'Warning - Time Based Settings',
			'method' => 'spacer',
		),
		'time_warning_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, warning will be triggered',
			'value' => isset($thold_item_data['time_warning_hi']) ? $thold_item_data['time_warning_hi'] : ''
		),
		'time_warning_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, warning will be triggered',
			'value' => isset($thold_item_data['time_warning_low']) ? $thold_item_data['time_warning_low'] : ''
		),
		'time_warning_fail_trigger' => array(
			'friendly_name' => 'Breach Count',
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 10,
			'description' => 'The number of times the data source must be in breach of the threshold.',
			'value' => isset($thold_item_data['time_warning_fail_trigger']) ? $thold_item_data['time_warning_fail_trigger'] : read_config_option('thold_warning_time_fail_trigger')
		),
		'time_warning_fail_length' => array(
			'friendly_name' => 'Breach Window',
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => 'The amount of time in the past to check for threshold breaches.',
			'value' => isset($thold_item_data['time_warning_fail_length']) ? $thold_item_data['time_warning_fail_length'] : (read_config_option('thold_warning_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1)
		),
		'time_header' => array(
			'friendly_name' => 'Alert - Time Based Settings',
			'method' => 'spacer',
		),
		'time_hi' => array(
			'friendly_name' => 'High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, alert will be triggered',
			'value' => isset($thold_item_data['time_hi']) ? $thold_item_data['time_hi'] : ''
		),
		'time_low' => array(
			'friendly_name' => 'Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, alert will be triggered',
			'value' => isset($thold_item_data['time_low']) ? $thold_item_data['time_low'] : ''
		),
		'time_fail_trigger' => array(
			'friendly_name' => 'Breach Count',
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 10,
			'default' => read_config_option('thold_time_fail_trigger'),
			'description' => 'The number of times the data source must be in breach of the threshold.',
			'value' => isset($thold_item_data['time_fail_trigger']) ? $thold_item_data['time_fail_trigger'] : read_config_option('thold_time_fail_trigger')
		),
		'time_fail_length' => array(
			'friendly_name' => 'Breach Window',
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => 'The amount of time in the past to check for threshold breaches.',
			'value' => isset($thold_item_data['time_fail_length']) ? $thold_item_data['time_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_time_fail_length') : 1)
		),
		'baseline_header' => array(
			'friendly_name' => 'Baseline Settings',
			'method' => 'spacer',
		),
		'bl_ref_time_range' => array(
			'friendly_name' => 'Time range',
			'method' => 'drop_array',
			'array' => $reference_types,
			'description' => 'Specifies the point in the past (based on rrd resolution) that will be used as a reference',
			'value' => isset($thold_item_data['bl_ref_time_range']) ? $thold_item_data['bl_ref_time_range'] : read_config_option('alert_bl_timerange_def')
		),
		'bl_pct_up' => array(
			'friendly_name' => 'Deviation UP',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Specifies allowed deviation in percentage for the upper bound threshold. If not set, upper bound threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_up']) ? $thold_item_data['bl_pct_up'] : ''
		),
		'bl_pct_down' => array(
			'friendly_name' => 'Deviation DOWN',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Specifies allowed deviation in percentage for the lower bound threshold. If not set, lower bound threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_down']) ? $thold_item_data['bl_pct_down'] : ''
		),
		'bl_fail_trigger' => array(
			'friendly_name' => 'Baseline Trigger Count',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Number of consecutive times the data source must be in breach of the baseline threshold for an alert to be raised.<br>Leave empty to use default value (<b>Default: ' . read_config_option('alert_bl_trigger') . ' cycles</b>)',
			'value' => isset($thold_item_data['bl_fail_trigger']) ? $thold_item_data['bl_fail_trigger'] : read_config_option("alert_bl_trigger")
		),
		'data_manipulation' => array(
			'friendly_name' => 'Data Manipulation',
			'method' => 'spacer',
		),
		'data_type' => array(
			'friendly_name' => 'Data Type',
			'method' => 'drop_array',
			'on_change' => 'changeDataType()',
			'array' => $data_types,
			'default' => read_config_option('data_type'),
			'description' => 'Special formatting for the given data.',
			'value' => isset($thold_item_data['data_type']) ? $thold_item_data['data_type'] : ''
		),
		'cdef' => array(
			'friendly_name' => 'Threshold CDEF',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Apply this CDEF before returning the data.',
			'value' => isset($thold_item_data['cdef']) ? $thold_item_data['cdef'] : 0,
			'array' => thold_cdef_select_usable_names()
		),
		'percent_ds' => array(
			'friendly_name' => 'Percent Datasource',
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => 'Second Datasource Item to use as total value to calculate percentage from.',
			'value' => isset($thold_item_data['percent_ds']) ? $thold_item_data['percent_ds'] : 0,
			'array' => $data_fields,
		),
		'expression' => array(
			'friendly_name' => 'RPN Expression',
			'method' => 'textarea',
			'textarea_rows' => 3,
			'textarea_cols' => 80,
			'default' => '',
			'description' => 'An RPN Expression is an RRDtool Compatible RPN Expression.  Syntax includes
			all functions below in addition to both Device and Data Query replacement expressions such as
			<span style="color:blue;">|query_ifSpeed|</span>.  To use a Data Source in the RPN Expression, you must use the syntax: <span style="color:blue;">|ds:dsname|</span>.  For example, <span style="color:blue;">|ds:traffic_in|</span> will get the current value
			of the traffic_in Data Source for the RRDfile(s) associated with the Graph. Any Data Source for a Graph can be included.<br><b>Math Operators:</b> <span style="color:blue;">+, -, /, *, %, ^</span><br><b>Functions:</b> <span style="color:blue;">SIN, COS, TAN, ATAN, SQRT, FLOOR, CEIL, DEG2RAD, RAD2DEG, ABS, EXP, LOG, ATAN, ADNAN</span><br><b>Flow Operators:</b> <span style="color:blue;">UN, ISINF, IF, LT, LE, GT, GE, EQ, NE</span><br><b>Comparison Functions:</b> <span style="color:blue;">MAX, MIN, INF, NEGINF, NAN, UNKN, COUNT, PREV</span>'.$replacements.$datasources,
			'value' => isset($thold_item_data['expression']) ? $thold_item_data['expression'] : '',
			'max_length' => '255',
			'size' => '80'
		),
		'other_header' => array(
			'friendly_name' => 'Other Settings',
			'method' => 'spacer',
		),
		'notify_warning' => array(
			'friendly_name' => 'Warning Notification List',
			'method' => 'drop_sql',
			'description' => 'You may specify choose a Notification List to receive Warnings for this Data Source',
			'value' => isset($thold_item_data['notify_warning']) ? $thold_item_data['notify_warning'] : '',
			'none_value' => 'None',
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		),
		'notify_alert' => array(
			'friendly_name' => 'Alert Notification List',
			'method' => 'drop_sql',
			'description' => 'You may specify choose a Notification List to receive Alerts for this Data Source',
			'value' => isset($thold_item_data['notify_alert']) ? $thold_item_data['notify_alert'] : '',
			'none_value' => 'None',
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		)
	);

	if (read_config_option("thold_alert_snmp") == 'on') {
		$extra = array(
			'snmp_event_category' => array(
				'friendly_name' => 'SNMP Notification - Event Category',
				'method' => 'textbox',
				'description' => 'To allow a NMS to categorize different SNMP notifications more easily please fill in the category SNMP notifications for this template should make use of. E.g.: "disk_usage", "link_utilization", "ping_test", "nokia_firewall_cpu_utilization" ...',
				'value' => isset($thold_item_data['snmp_event_category']) ? $thold_item_data['snmp_event_category'] : '',
				'default' => '',
				'max_length' => '255',
			),
			'snmp_event_severity' => array(
				'friendly_name' => 'SNMP Notification - Alert Event Severity',
				'method' => 'drop_array',
				'default' => '3',
				'description' => 'Severity to be used for alerts. (low impact -> critical impact)',
				'value' => isset($thold_item_data['snmp_event_severity']) ? $thold_item_data['snmp_event_severity'] : 3,
				'array' => array(1 => 'low', 2 => 'medium', 3 => 'high', 4 => 'critical'),
			),
		);

		$form_array += $extra;

		if (read_config_option('thold_alert_snmp_warning') != 'on') {
			$extra = array(
				'snmp_event_warning_severity' => array(
					'friendly_name' => 'SNMP Notification - Warning Event Severity',
					'method' => 'drop_array',
					'default' => '2',
					'description' => 'Severity to be used for warnings. (low impact -> critical impact).<br>Note: The severity of warnings has to be equal or lower than the severity being defined for alerts.',
					'value' => isset($thold_item_data['snmp_event_warning_severity']) ? $thold_item_data['snmp_event_warning_severity'] : 2,
					'array' => array(1 => 'low', 2 => 'medium', 3 => 'high', 4 => 'critical'),
				),
			);
		}
		$form_array += $extra;
	}
	if (read_config_option('thold_disable_legacy') != 'on') {
		$extra = array(
			'notify_accounts' => array(
				'friendly_name' => 'Notify accounts',
				'method' => 'drop_multi',
				'description' => 'This is a listing of accounts that will be notified when this threshold is breached.<br><br><br><br>',
				'array' => $send_notification_array,
				'sql' => $sql,
			),
			'notify_extra' => array(
				'friendly_name' => 'Alert Emails',
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => 'You may specify here extra Emails to receive alerts for this data source (comma separated)',
				'value' => isset($thold_item_data['notify_extra']) ? $thold_item_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'friendly_name' => 'Warning Emails',
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => 'You may specify here extra Emails to receive warnings for this data source (comma separated)',
				'value' => isset($thold_item_data['notify_warning_extra']) ? $thold_item_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	}else{
		$extra = array(
			'notify_accounts' => array(
				'method' => 'hidden',
				'value' => 'ignore'
			),
			'notify_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_item_data['notify_extra']) ? $thold_item_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_item_data['notify_warning_extra']) ? $thold_item_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	}

draw_edit_form(
	array(
		'config' => array(
			'no_form_tag' => true
			),
		'fields' => $form_array + array(
			'data_template_rrd_id' => array(
				'method' => 'hidden',
				'value' => (isset($template_rrd) ? $template_rrd['id'] : '0')
			),
			'host_id' => array(
				'method' => 'hidden',
				'value' => $host_id
			),
			'local_data_id' => array(
				'method' => 'hidden',
				'value' => $local_data_id
			)
		)
	)
);

html_end_box();

form_save_button('thold.php?local_data_id=' . $local_data_id . '&view_rrd=' . get_filter_request_var('view_rrd'), 'save');

unset($template_data_rrds);
?>

<script type='text/javascript'>
	function templateEnableDisable() {
		var status = $('#template_enabled').is(':checked');

		$('#name').prop('disabled', status);
		$('#thold_type').prop('disabled', status);
		$('#thold_hi').prop('disabled', status);
		$('#thold_low').prop('disabled', status);
		$('#thold_fail_trigger').prop('disabled', status);
		$('#thold_warning_hi').prop('disabled', status);
		$('#thold_warning_low').prop('disabled', status);
		$('#thold_warning_fail_trigger').prop('disabled', status);
		$('#repeat_alert').prop('disabled', status);
		$('#notify_extra').prop('disabled', status);
		$('#notify_warning_extra').prop('disabled', status);
		$('#notify_warning').prop('disabled', status);
		$('#notify_alert').prop('disabled', status);
		$('#cdef').prop('disabled', status);
		$('#thold_enabled').prop('disabled', status);
		if ($('#notify_accounts')) $('#notify_accounts').prop('disabled', status);
		$('#time_hi').prop('disabled', status);
		$('#time_low').prop('disabled', status);
		$('#time_fail_trigger').prop('disabled', status);
		$('#time_fail_length').prop('disabled', status);
		$('#time_warning_hi').prop('disabled', status);
		$('#time_warning_low').prop('disabled', status);
		$('#time_warning_fail_trigger').prop('disabled', status);
		$('#time_warning_fail_length').prop('disabled', status);
		$('#data_type').prop('disabled', status);
		$('#percent_ds').prop('disabled', status);
		$('#expression').prop('disabled', status);
		$('#exempt').prop('disabled', status);
		$('#restored_alert').prop('disabled', status);
		if ($('#snmp_event_category')) $('#snmp_event_category').prop('disabled', status);
		if ($('#snmp_event_severity')) $('#snmp_event_severity').prop('disabled', status);
		if ($('#snmp_event_warning_severity')) $('#snmp_event_warning_severity').prop('disabled', status);
	}

	function changeTholdType() {
		type = $('#thold_type').val();
		switch(type) {
		case '0':
			thold_toggle_hilow('');
			thold_toggle_baseline('none');
			thold_toggle_time('none');
			break;
		case '1':
			thold_toggle_hilow('none');
			thold_toggle_baseline('');
			thold_toggle_time('none');
			break;
		case '2':
			thold_toggle_hilow('none');
			thold_toggle_baseline('none');
			thold_toggle_time('');
			break;
		}
	}

	function changeDataType () {
		type = $('#data_type').val();
		switch(type) {
		case '0':
			$('#row_cdef').hide();
			$('#row_percent_ds').hide();
			$('#row_expression').hide();
			break;
		case '1':
			$('#row_cdef').show();
			$('#row_percent_ds').hide();
			$('#row_expression').hide();
			break;
		case '2':
			$('#row_cdef').hide()
			$('#row_percent_ds').show();
			$('#row_expression').hide();
			break;
		case '3':
			$('#row_expression').show();
			$('#row_cdef').hide();
			$('#row_percent_ds').hide();
			break;
		}
	}

	function thold_toggle_hilow (status) {
		if (status == '') {
			$('#row_thold_header, #row_thold_hi, #row_thold_low, #row_thold_fail_trigger').show();
			$('#row_thold_warning_header, #row_thold_warning_hi').show();
			$('#row_thold_warning_low, #row_thold_warning_fail_trigger').show();
		}else{
			$('#row_thold_header, #row_thold_hi, #row_thold_low, #row_thold_fail_trigger').hide();
			$('#row_thold_warning_header, #row_thold_warning_hi').hide();
			$('#row_thold_warning_low, #row_thold_warning_fail_trigger').hide();
		}
	}

	function thold_toggle_baseline (status) {
		if (status == '') {
			$('#row_baseline_header, #row_bl_ref_time_range').show();
			$('#row_bl_pct_up, #row_bl_pct_down, #row_bl_fail_trigger').show();
		}else{
			$('#row_baseline_header, #row_bl_ref_time_range').hide();
			$('#row_bl_pct_up, #row_bl_pct_down, #row_bl_fail_trigger').hide();
		}
	}

	function thold_toggle_time(status) {
		if (status == '') {
			$('#row_time_header, #row_time_hi, #row_time_low, #row_time_fail_trigger, #row_time_fail_length').show();
			$('#row_time_warning_header, #row_time_warning_hi, #row_time_warning_low').show();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').show();
		}else{
			$('#row_time_header, #row_time_hi, #row_time_low, #row_time_fail_trigger, #row_time_fail_length').hide();
			$('#row_time_warning_header, #row_time_warning_hi, #row_time_warning_low').hide();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').hide();
		}
	}

	function graphImage() {
		var id = $('#element').val();
		$('#graphimage').attr(src, '../../graph_image.php?local_graph_id=' + id + '&rra_id=0&graph_start=-32400&graph_height=100&graph_width=300&graph_nolegend=true').change();
	}

	$(function() {
		if ($('#notify_accounts') && $('#notify_accounts').length == 0) {
			$('#row_notify_accounts').hide();
		}

		if ($('#notify_warning options').size() == 1) {
			$('#row_notify_warning').hide();
		}

		if ($('#notify_alert options').size == 1) {
			$('#row_notify_alert').hide();
		}

		templateEnableDisable();

		$('#template_enabled').click(function() {
			templateEnableDisable();
		});

		<?php if (!isset($thold_item_data['template']) || $thold_item_data['template'] == '') { ?>
		$('#templated_enabled').prop('disabled', true);
		<?php } ?>

		changeTholdType ();
		changeDataType ();

		$('#element').change(function() {
			graphImage;
		});
	});
</script>
<?php

bottom_footer();
