<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2011 The Cacti Group                                      |
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

input_validate_input_number(get_request_var('view_rra'));
input_validate_input_number(get_request_var('hostid'));
input_validate_input_number(get_request_var('rra'));
input_validate_input_number(get_request_var('id'));

input_validate_input_number(get_request_var('view_rrd')); 
input_validate_input_number(get_request_var_post('data_template_rrd_id')); 
input_validate_input_number(get_request_var_post('rra')); 


$hostid = '';
if (isset($_REQUEST['rra'])) {
	$rra = $_REQUEST['rra'];
	$hostid = db_fetch_assoc('select host_id from thold_data where rra_id=' . $rra);
	if (isset($hostid[0]['host_id'])) {
		$hostid = $hostid[0]['host_id'];
	} else {
		$hostid = db_fetch_assoc('select host_id from poller_item where local_data_id=' . $rra);
		if (isset($hostid[0]['host_id'])) {
			$hostid = $hostid[0]['host_id'];
		}
	}
	if (is_array($hostid)) {
		$hostid = '';
	}
	if (!thold_user_auth_threshold ($rra)) {
		include_once($config['include_path'] . '/top_header.php');
		print '<font size=+1 color=red>Access Denied - You do not have permissions to access that threshold.</font>';
		include_once($config['include_path'] . '/bottom_footer.php');
		exit;
	}
} else {
	$_REQUEST['rra'] = '';
	$rra = '';
	if (isset($_REQUEST['hostid'])) {
		$hostid = $_REQUEST['hostid'];
	} else {
		$_REQUEST['hostid'] = '';
		if (isset($_GET['hostid'])) {
			$hostid=$_GET['hostid'];
		}
		if (isset($_POST['hostid'])) {
			$hostid=$_POST['hostid'];
		}
	}
}

if (!isset($_REQUEST['action'])) {
	$_REQUEST['action'] = '';
}

if ((substr_count($_SERVER["HTTP_REFERER"], "graph_view.php")) || (substr_count($_SERVER["HTTP_REFERER"], "graph.php"))) {
	$_SESSION["graph_return"] = $_SERVER["HTTP_REFERER"];
}

switch($_REQUEST['action']) {
	case 'save':
		save_thold();

		if (isset($_SESSION["graph_return"])) {
			$return_to = $_SESSION["graph_return"];
			unset($_SESSION["graph_return"]);
			kill_session_var("graph_return");
			header('Location: ' . $return_to);
		}else{
			include_once($config['include_path'] . '/top_header.php');
		}

		break;
	case 'autocreate':
		$c = autocreate($hostid);
		if ($c == 0) {
			$_SESSION['thold_message'] = '<font size=-1>Either No Templates or Threshold(s) Already Exists - No thresholds were created.</font>';
		}
		raise_message('thold_created');

		if (isset($_SESSION["graph_return"])) {
			$return_to = $_SESSION["graph_return"];
			unset($_SESSION["graph_return"]);
			kill_session_var("graph_return");
			header('Location: ' . $return_to);
		}else{
			header('Location: ../../graphs_new.php?host_id=' . $hostid);
		}
		exit;

		break;
	case 'disable':
		thold_threshold_disable($_REQUEST["id"]);
		header('Location: ' . $_SERVER["HTTP_REFERER"]);
		exit;
	case 'enable':
		thold_threshold_enable($_REQUEST["id"]);
		header('Location: ' . $_SERVER["HTTP_REFERER"]);
		exit;
}

include_once($config['include_path'] . '/top_header.php');

$t = db_fetch_assoc('SELECT id, name, name_cache FROM data_template_data WHERE local_data_id=' . $rra . ' LIMIT 1');
$desc = $t[0]['name_cache'];
unset($t);

$rrdsql   = array_rekey(db_fetch_assoc("SELECT id FROM data_template_rrd WHERE local_data_id=$rra ORDER BY id"), "id", "id");
$sql      = "task_item_id IN (" . implode(", ", $rrdsql) . ") AND graph_template_id>0";
$grapharr = db_fetch_assoc("SELECT DISTINCT local_graph_id FROM graph_templates_item WHERE $sql");

// Take the first one available
$graph = (isset($grapharr[0]["local_graph_id"]) ? $grapharr[0]["local_graph_id"] : "");

$dt_sql = 'SELECT DISTINCT dtr.local_data_id
		FROM data_template_rrd AS dtr
		LEFT JOIN graph_templates_item AS gti
		ON gti.task_item_id=dtr.id
		LEFT JOIN graph_local AS gl
		ON gl.id=gti.local_graph_id
		WHERE gl.id=' . $graph;

$template_data_rrds = db_fetch_assoc("SELECT id, data_source_name, local_data_id FROM data_template_rrd WHERE local_data_id IN ($dt_sql) ORDER BY id");

?>
<form name="THold" action="thold.php" method="post">
<table width="100%" align="center">
	<tr>
		<td class="textArea">
			<?php
			if (isset($banner)) {
				echo $banner . "<br><br>";
			}; ?>
			Data Source Description: <br><strong><?php echo $desc; ?></strong><br><br>
			Associated Graph (graphs that use this RRD): <br>
			<select name='element'>
				<?php
				foreach($grapharr as $g) {
					$graph_desc = db_fetch_assoc("SELECT local_graph_id,
						title,
						title_cache
						FROM graph_templates_graph
						WHERE local_graph_id=" . $g["local_graph_id"]);

					echo "<option value=" . $graph_desc[0]["local_graph_id"];
					if($graph_desc[0]["local_graph_id"] == $graph) echo " selected";
					echo "> " . $graph_desc[0]["local_graph_id"] . " - " . $graph_desc[0]["title_cache"] . " </option>\n";
				} ?>
			</select>
			<br>
			<br>
		</td>
		<td>
			<img id="graphimage" src="<?php echo htmlspecialchars($config["url_path"] . 'graph_image.php?local_graph_id=' . $graph . '&rra_id=0&graph_start=-32400&graph_height=100&graph_width=300&graph_nolegend=true');?>">
		</td>
	</tr>
</table>
<?php

/* select the first "rrd" of this data source by default */
if (empty($_GET["view_rrd"])) {
	if(isset($_POST["data_template_rrd_id"])) {
		$_GET["view_rrd"] = $_POST["data_template_rrd_id"];
	} else {
		/* Check and see if we already have a threshold set, and use that if so */
		$thold_data = db_fetch_cell("SELECT data_id FROM thold_data WHERE rra_id = $rra ORDER BY data_id");

		if ($thold_data) {
			$_GET["view_rrd"] = $thold_data;
		} else {
			$_GET["view_rrd"] = (isset($template_data_rrds[0]["id"]) ? $template_data_rrds[0]["id"] : "0");
		}
	}
}

/* get more information about the rrd we chose */
if (!empty($_GET["view_rrd"])) {
	$template_rrd = db_fetch_row("select * from data_template_rrd where id=" . $_GET["view_rrd"]);
}

//-----------------------------
// Tabs (if more than one item)
//-----------------------------
$i = 0;
$ds = 0;
if (isset($template_data_rrds)) {
	if (sizeof($template_data_rrds)) {
		/* draw the data source tabs on the top of the page */
		print "<table class='tabs' cellspacing='0' cellpadding='3' align='left'>
		<tr>\n";

		foreach ($template_data_rrds as $template_data_rrd) {
			if($template_data_rrd["id"] == $_GET["view_rrd"]) $ds = $template_data_rrd["data_source_name"];

			$item = db_fetch_assoc("SELECT * FROM thold_data WHERE data_id=" . $template_data_rrd["id"]);
			$item = count($item) > 0 ? $item[0]: $item;

			$cur_setting = '';
			if(count($item) == 0) {
				$cur_setting .= "<span style='color:red;'>n/a</span>";
			} else {
				$cur_setting = "Last: " . ($item["lastread"] == "" ? "<span style='color:red;'>n/a</span>":"<span style='color:blue;'>" . thold_format_number($item["lastread"],4) . "</span>");
				if ($item["thold_type"] != 1) {
					$cur_setting .= " WHi: " . ($item["thold_warning_hi"] == "" ? "<span style='color:red;'>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item["thold_warning_hi"],2) . "</span>");
					$cur_setting .= " WLo: " . ($item["thold_warning_low"] == "" ? "<span style='color:red;'>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item["thold_warning_low"],2) . "</span>");
					$cur_setting .= " AHi: " . ($item["thold_hi"] == "" ? "<span style='color:red;'>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item["thold_hi"],2) . "</span>");
					$cur_setting .= " ALo: " . ($item["thold_low"] == "" ? "<span style='color:red;'>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item["thold_low"],2) . "</span>");

				}else{
					$cur_setting .= " AHi: " . ($item["thold_hi"] == "" ? "<span style='color:red;'>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item["thold_hi"],2) . "</span>");
					$cur_setting .= " ALo: " . ($item["thold_low"] == "" ? "<span style='color:red;'>n/a</span>" : "<span style='color:green;'>" . thold_format_number($item["thold_low"],2) . "</span>");
					$cur_setting .= " BL: (Up " . $item["bl_pct_up"] . "%/Down " . $item["bl_pct_down"] . "%)";
				}
			}
			$tab_len = max(strlen($cur_setting), strlen($template_data_rrd["data_source_name"]));

			$i++;
			echo "	<td bgcolor=" . (($template_data_rrd["id"] == $_GET["view_rrd"]) ? "'silver'" : "'#DFDFDF'");
			echo " nowrap='nowrap' align='center' class='tab'>";
			echo "<span class='textEditTitle'><a href='" . htmlspecialchars("thold.php?rra=" . $template_data_rrd["local_data_id"] . "&view_rrd=" . $template_data_rrd["id"]) . "'>$i: " . $template_data_rrd["data_source_name"] . "</a><br>";
			echo "<span class='textEditTitle' style='white-space:nowrap;color:black;'>" . $cur_setting . "</span>";
			echo "</span>\n</td>\n<td width='1'></td>\n";
			unset($thold_item_data);
		}

		print "
		<td></td>\n
		</tr>
		</table>\n";

	}elseif (sizeof($template_data_rrds) == 1) {
		$_GET["view_rrd"] = $template_data_rrds[0]["id"];
	}
}

//----------------------
// Data Source Item Form
//----------------------
$thold_item_data = db_fetch_assoc("SELECT * 
	FROM thold_data 
	WHERE data_id=" . $_GET["view_rrd"]);

$thold_item_data = count($thold_item_data) > 0 ? $thold_item_data[0] : $thold_item_data;
$thold_item_data_cdef = (isset($thold_item_data['cdef']) ? $thold_item_data['cdef'] : 0);

if ($thold_item_data['template']) {
	$thold_item_data['template_name'] = db_fetch_cell('SELECT name FROM thold_template WHERE id = ' . $thold_item_data['template']);
}

html_start_box("", "100%", $colors["header"], "3", "center", "");
//------------------------
// Data Source Item header
//------------------------
print "	<tr>
	<td colspan=2 bgcolor='#" . $colors["header"] . "' class='textHeaderDark'>
	<strong>Data Source Item</strong> [" . (isset($template_rrd) ? $template_rrd["data_source_name"] : "") . "] " .
	" - <strong>Current value: </strong>[" . get_current_value($rra, $ds, $thold_item_data_cdef) .
	"]</td>
	</tr>\n";

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
	$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE local_data_id = ' . $thold_item_data['rra_id'], FALSE);
} else {
	$sql  = 'SELECT contact_id as id FROM plugin_thold_threshold_contact WHERE thold_id=0';
	$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE local_data_id = ' . $rra, FALSE);
}

if ($step == 60) {
	$repeatarray = array(0 => 'Never', 1 => 'Every Minute', 2 => 'Every 2 Minutes', 3 => 'Every 3 Minutes', 4 => 'Every 4 Minutes', 5 => 'Every 5 Minutes', 10 => 'Every 10 Minutes', 15 => 'Every 15 Minutes', 20 => 'Every 20 Minutes', 30 => 'Every 30 Minutes', 45 => 'Every 45 Minutes', 60 => 'Every Hour', 120 => 'Every 2 Hours', 180 => 'Every 3 Hours', 240 => 'Every 4 Hours', 360 => 'Every 6 Hours', 480 => 'Every 8 Hours', 720 => 'Every 12 Hours', 1440 => 'Every Day', 2880 => 'Every 2 Days', 10080 => 'Every Week', 20160 => 'Every 2 Weeks', 43200 => 'Every Month');
	$alertarray  = array(0 => 'Never', 1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 10 => '10 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
	$timearray   = array(1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 6 => '6 Minutes', 7 => '7 Minutes', 8 => '8 Minutes', 9 => '9 Minutes', 10 => '10 Minutes', 12 => '12 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 24 => '24 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 288 => '4.8 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
} else if ($step == 300) {
	$repeatarray = array(0 => 'Never', 1 => 'Every 5 Minutes', 2 => 'Every 10 Minutes', 3 => 'Every 15 Minutes', 4 => 'Every 20 Minutes', 6 => 'Every 30 Minutes', 8 => 'Every 45 Minutes', 12 => 'Every Hour', 24 => 'Every 2 Hours', 36 => 'Every 3 Hours', 48 => 'Every 4 Hours', 72 => 'Every 6 Hours', 96 => 'Every 8 Hours', 144 => 'Every 12 Hours', 288 => 'Every Day', 576 => 'Every 2 Days', 2016 => 'Every Week', 4032 => 'Every 2 Weeks', 8640 => 'Every Month');
	$alertarray  = array(0 => 'Never', 1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 6 => '30 Minutes', 8 => '45 Minutes', 12 => 'Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');
	$timearray   = array(1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 6 => '30 Minutes', 8 => '45 Minutes', 12 => 'Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');
} else {
	$repeatarray = array(0 => 'Never', 1 => 'Every Polling', 2 => 'Every 2 Pollings', 3 => 'Every 3 Pollings', 4 => 'Every 4 Pollings', 6 => 'Every 6 Pollings', 8 => 'Every 8 Pollings', 12 => 'Every 12 Pollings', 24 => 'Every 24 Pollings', 36 => 'Every 36 Pollings', 48 => 'Every 48 Pollings', 72 => 'Every 72 Pollings', 96 => 'Every 96 Pollings', 144 => 'Every 144 Pollings', 288 => 'Every 288 Pollings', 576 => 'Every 576 Pollings', 2016 => 'Every 2016 Pollings');
	$alertarray  = array(0 => 'Never', 1 => '1 Polling', 2 => '2 Pollings', 3 => '3 Pollings', 4 => '4 Pollings', 6 => '6 Pollings', 8 => '8 Pollings', 12 => '12 Pollings', 24 => '24 Pollings', 36 => '36 Pollings', 48 => '48 Pollings', 72 => '72 Pollings', 96 => '96 Pollings', 144 => '144 Pollings', 288 => '288 Pollings', 576 => '576 Pollings', 2016 => '2016 Pollings');
	$timearray   = array(1 => '1 Polling', 2 => '2 Pollings', 3 => '3 Pollings', 4 => '4 Pollings', 5 => '5 Pollings', 6 => '6 Pollings', 8 => '8 Pollings', 12 => '12 Pollings', 24 => '24 Pollings', 36 => '36 Pollings', 48 => '48 Pollings', 72 => '72 Pollings', 96 => '96 Pollings', 144 => '144 Pollings', 288 => '288 Pollings', 576 => '576 Pollings', 2016 => '2016 Pollings');
}

$thold_types = array (
	0 => 'High / Low Values',
	1 => 'Baseline Deviation',
	2 => 'Time Based',
);

$data_types = array (
	0 => 'Exact Value',
	1 => 'CDEF',
	2 => 'Percentage',
	3 => 'RPN Expression'
);

$data_fields = array();

$reference_types = get_reference_types($rra, $step, $timearray);

if (isset($thold_item_data['data_template_id'])) {
	$temp = db_fetch_assoc('SELECT id, local_data_template_rrd_id, data_source_name, data_input_field_id 
		FROM data_template_rrd 
		WHERE local_data_id=' . $thold_item_data['rra_id']);
} else {
	$temp = db_fetch_assoc('SELECT id, local_data_template_rrd_id, data_source_name, data_input_field_id 
		FROM data_template_rrd 
		WHERE local_data_id=' . $rra);
}

foreach ($temp as $d) {
	if ($d['data_input_field_id'] != 0) {
		$temp2 = db_fetch_assoc('SELECT name FROM data_input_fields WHERE id=' . $d['data_input_field_id']);
	} else {
		$temp2[0]['name'] = $d['data_source_name'];
	}
	if ((isset($_GET['view_rrd']) && $d['id'] != $_GET['view_rrd']) || (isset($thold_item_data['data_id']) && $d['id'] != $thold_item_data['data_id'])) {
		$data_fields[$d['data_source_name']] = $temp2[0]['name'];
	}
}

$replacements = db_fetch_assoc("SELECT DISTINCT field_name 
	FROM data_local AS dl
	INNER JOIN host_snmp_cache AS hsc
	ON dl.snmp_query_id=hsc.snmp_query_id
	AND dl.host_id=hsc.host_id
	WHERE dl.id=" . (isset($thold_item_data['data_template_id']) ? $thold_item_data['rra_id']:$rra));

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

$dss = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE local_data_id=" . $rra);

if (sizeof($dss)) {
foreach($dss as $ds) {
	$dsname[] = "<span style='color:blue;'>|ds:" . $ds["data_source_name"] . "|</span>";
}
}

$datasources = "<br><b>Data Sources:</b> " . implode(", ", $dsname);

$form_array = array(
		'template_header' => array(
			'friendly_name' => 'Template settings',
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
			'value' => isset($thold_item_data['template_name']) ? $thold_item_data['template_name'] : '<font color="red">None</font>',
		),
		'general_header' => array(
			'friendly_name' => 'Mandatory settings',
			'method' => 'spacer',
		),
		'name' => array(
			'friendly_name' => 'Threshold Name',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => '70',
			'default' => $desc . ' [' . $template_rrd['data_source_name'] . ']',
			'description' => 'Provide the THold a meaningful name',
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
			'friendly_name' => 'Warning High / Low Settings',
			'method' => 'spacer',
		),
		'thold_warning_hi' => array(
			'friendly_name' => 'Warning High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, warning will be triggered',
			'value' => isset($thold_item_data['thold_warning_hi']) ? $thold_item_data['thold_warning_hi'] : ''
		),
		'thold_warning_low' => array(
			'friendly_name' => 'Warning Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, warning will be triggered',
			'value' => isset($thold_item_data['thold_warning_low']) ? $thold_item_data['thold_warning_low'] : ''
		),
		'thold_warning_fail_trigger' => array(
			'friendly_name' => 'Warning Breach Duration',
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => 'The amount of time the data source must be in breach of the threshold for a warning to be raised.',
			'value' => isset($thold_item_data['thold_warning_fail_trigger']) ? $thold_item_data['thold_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'thold_header' => array(
			'friendly_name' => 'Alert High / Low Settings',
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
			'friendly_name' => 'Warning Time Based Settings',
			'method' => 'spacer',
		),
		'time_warning_hi' => array(
			'friendly_name' => 'Warning High Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes above this number, warning will be triggered',
			'value' => isset($thold_item_data['time_warning_hi']) ? $thold_item_data['time_warning_hi'] : ''
		),
		'time_warning_low' => array(
			'friendly_name' => 'Warning Low Threshold',
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 10,
			'description' => 'If set and data source value goes below this number, warning will be triggered',
			'value' => isset($thold_item_data['time_warning_low']) ? $thold_item_data['time_warning_low'] : ''
		),
		'time_warning_fail_trigger' => array(
			'friendly_name' => 'Warning Breach Count',
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 10,
			'description' => 'The number of times the data source must be in breach of the threshold.',
			'value' => isset($thold_item_data['time_warning_fail_trigger']) ? $thold_item_data['time_warning_fail_trigger'] : read_config_option('thold_warning_time_fail_trigger') 
		),
		'time_warning_fail_length' => array(
			'friendly_name' => 'Warning Breach Window',
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => 'The amount of time in the past to check for threshold breaches.',
			'value' => isset($thold_item_data['time_warning_fail_length']) ? $thold_item_data['time_warning_fail_length'] : (read_config_option('thold_warning_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1) 
		),		
		'time_header' => array(
			'friendly_name' => 'Time Based Settings',
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
			'friendly_name' => 'Baseline Deviation UP',
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 10,
			'description' => 'Specifies allowed deviation in percentage for the upper bound threshold. If not set, upper bound threshold will not be checked at all.',
			'value' => isset($thold_item_data['bl_pct_up']) ? $thold_item_data['bl_pct_up'] : ''
		),
		'bl_pct_down' => array(
			'friendly_name' => 'Baseline Deviation DOWN',
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
			all functions below in addition to both Host and Data Query replacement expressions such as
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

	if (read_config_option("thold_disable_legacy") != 'on') {
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
			'hostid' => array(
				'method' => 'hidden',
				'value' => $hostid
			),
			'rra' => array(
				'method' => 'hidden',
				'value' => $rra
			)
		)
	)
);

html_end_box();
form_save_button('thold.php?rra=' . $rra . '&view_rrd=' . $_GET['view_rrd'], 'save');

unset($template_data_rrds);
?>
<!-- Make it look intelligent :) -->
<script type="text/javascript">
	function Template_EnableDisable() {
		var _f = document.THold;
		var status = _f.template_enabled.checked;
		_f.name.disabled = status;
		_f.thold_type.disabled = status;
		_f.thold_hi.disabled = status;
		_f.thold_low.disabled = status;
		_f.thold_fail_trigger.disabled = status;
		_f.thold_warning_hi.disabled = status;
		_f.thold_warning_low.disabled = status;
		_f.thold_warning_fail_trigger.disabled = status;
		_f.repeat_alert.disabled = status;
		_f.notify_extra.disabled = status;
		_f.notify_warning_extra.disabled = status;
		_f.notify_warning.disabled = status;
		_f.notify_alert.disabled = status;
		_f.cdef.disabled = status;
		_f.thold_enabled.disabled = status;
		if (document.THold["notify_accounts[]"]) _f["notify_accounts[]"].disabled = status;
		_f.time_hi.disabled = status;
		_f.time_low.disabled = status;
		_f.time_fail_trigger.disabled = status;
		_f.time_fail_length.disabled = status;
		_f.time_warning_hi.disabled = status;
		_f.time_warning_low.disabled = status;
		_f.time_warning_fail_trigger.disabled = status;
		_f.time_warning_fail_length.disabled = status;
		_f.data_type.disabled = status;
		_f.percent_ds.disabled = status;
		_f.expression.disabled = status;
		_f.exempt.disabled = status;
		_f.restored_alert.disabled = status;
	}

	if (document.THold["notify_accounts[]"] && document.THold["notify_accounts[]"].length == 0) {
		document.getElementById('row_notify_accounts').style.display='none';
	}

	if (document.THold.notify_warning.length == 1) {
		document.getElementById('row_notify_warning').style.display='none';
	}

	if (document.THold.notify_alert.length == 1) {
		document.getElementById('row_notify_alert').style.display='none';
	}

	Template_EnableDisable();
	document.THold.template_enabled.onclick = Template_EnableDisable;
	<?php if (!isset($thold_item_data['template']) || $thold_item_data['template'] == '') { ?>
	document.THold.template_enabled.disabled = true;
	<?php } ?>

	function changeTholdType () {
		type = document.getElementById('thold_type').value;
		switch(type) {
		case '0':
			thold_toggle_hilow ('');
			thold_toggle_baseline ('none');
			thold_toggle_time ('none');
			break;
		case '1':
			thold_toggle_hilow ('none');
			thold_toggle_baseline ('');
			thold_toggle_time ('none');
			break;
		case '2':
			thold_toggle_hilow ('none');
			thold_toggle_baseline ('none');
			thold_toggle_time ('');
			break;
		}
	}

	function changeDataType () {
		type = document.getElementById('data_type').value;
		switch(type) {
		case '0':
			document.getElementById('row_cdef').style.display  = 'none';
			document.getElementById('row_percent_ds').style.display  = 'none';
			document.getElementById('row_expression').style.display  = 'none';
			break;
		case '1':
			document.getElementById('row_cdef').style.display  = '';
			document.getElementById('row_percent_ds').style.display  = 'none';
			document.getElementById('row_expression').style.display  = 'none';
			break;
		case '2':
			document.getElementById('row_cdef').style.display  = 'none';
			document.getElementById('row_percent_ds').style.display  = '';
			document.getElementById('row_expression').style.display  = 'none';
			break;
		case '3':
			document.getElementById('row_expression').style.display  = '';
			document.getElementById('row_cdef').style.display  = 'none';
			document.getElementById('row_percent_ds').style.display  = 'none';
			break;
		}
	}

	function thold_toggle_hilow (status) {
		document.getElementById('row_thold_header').style.display  = status;
		document.getElementById('row_thold_hi').style.display  = status;
		document.getElementById('row_thold_low').style.display  = status;
		document.getElementById('row_thold_fail_trigger').style.display  = status;

		document.getElementById('row_thold_warning_header').style.display  = status;
		document.getElementById('row_thold_warning_hi').style.display  = status;
		document.getElementById('row_thold_warning_low').style.display  = status;
		document.getElementById('row_thold_warning_fail_trigger').style.display  = status;
	}

	function thold_toggle_baseline (status) {
		document.getElementById('row_baseline_header').style.display  = status;
		document.getElementById('row_bl_ref_time_range').style.display  = status;
		document.getElementById('row_bl_pct_up').style.display  = status;
		document.getElementById('row_bl_pct_down').style.display  = status;
		document.getElementById('row_bl_fail_trigger').style.display  = status;
	}

	function thold_toggle_time (status) {
		document.getElementById('row_time_header').style.display  = status;
		document.getElementById('row_time_hi').style.display  = status;
		document.getElementById('row_time_low').style.display  = status;
		document.getElementById('row_time_fail_trigger').style.display  = status;
		document.getElementById('row_time_fail_length').style.display  = status;

		document.getElementById('row_time_warning_header').style.display  = status;
		document.getElementById('row_time_warning_hi').style.display  = status;
		document.getElementById('row_time_warning_low').style.display  = status;
		document.getElementById('row_time_warning_fail_trigger').style.display  = status;
		document.getElementById('row_time_warning_fail_length').style.display  = status;
	}

	function GraphImage() {
		var _f = document.THold;
		var id = _f.element.options[_f.element.selectedIndex].value;
		document.graphimage.src = "../../graph_image.php?local_graph_id=" + id + "&rra_id=0&graph_start=-32400&graph_height=100&graph_width=300&graph_nolegend=true";
	}

	changeTholdType ();
	changeDataType ();

	document.THold.element.onchange = GraphImage;
</script>
<?php

include_once($config["include_path"] . "/bottom_footer.php");
