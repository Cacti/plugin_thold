<?php
/*******************************************************************************

    Author ......... Aurelio DeSimone (Copyright 2005)
    Home Site ...... http://www.ciscoconfigbuilder.com

    Modified By .... Jimmy Conner
    Contact ........ jimmy@sqmail.org
    Home Site ...... http://cactiusers.org
    Program ........ Thresholds for Cacti

    Many contributions from Ranko Zivojnovic <ranko@spidernet.net>

*******************************************************************************/

chdir('../../');

include_once("./include/auth.php");
include_once($config["library_path"] . "/rrd.php");
include_once($config["base_path"] . "/plugins/thold/thold-functions.php");

input_validate_input_number(get_request_var("view_rra"));
input_validate_input_number(get_request_var("hostid"));
input_validate_input_number(get_request_var("rra"));

if (isset($_REQUEST["hostid"])) {
	$hostid=$_REQUEST["hostid"];
	$_SESSION['hostid'] = $hostid;
} else {
	$_REQUEST["hostid"] = "";
	if (isset($_SESSION['hostid'])) {
		$hostid=$_SESSION['hostid'];
	}
	if (isset($_GET['hostid'])) {
		$hostid=$_GET['hostid'];
		$_SESSION['hostid'] = $hostid;
	}
	if (isset($_POST['hostid'])) {
		$hostid=$_POST['hostid'];
		$_SESSION['hostid'] = $hostid;
	}
}

if (isset($_REQUEST["rra"])) {  
	$rra=$_REQUEST['rra'];
} else {
	$_REQUEST["rra"] = ""; 
	$rra = "";
}

if (!isset($hostid)) {
	$hostid = db_fetch_assoc("select host_id from thold_data where rra_id = '" . $rra . "'");
	$hostid = $hostid[0]['host_id'];
	$_SESSION['hostid'] = $hostid;
}

if (!isset($_REQUEST["action"])) {  $_REQUEST["action"] = ""; }

switch($_REQUEST["action"]) {
	case "save":
		include_once($config["include_path"] . "/top_header.php");
		save_thold();
		break;
	case "autocreate":
		$c = autocreate($hostid);
		if ($c == 0) {
			$_SESSION['thold_message'] = "<font size=-1>No thresholds were created.</font>";
		}
		raise_message('thold_created');
		Header("Location: ../../graphs_new.php?host_id=" . $hostid);
		exit;
		break;
}

include_once($config["include_path"] . "/top_header.php");

$t = db_fetch_assoc("SELECT id, name, name_cache FROM data_template_data WHERE local_data_id=" . $rra . " LIMIT 1");
$desc = $t[0]["name_cache"];
unset($t);

$rrdsql = db_fetch_assoc("SELECT id FROM data_template_rrd WHERE local_data_id=$rra ORDER BY id");
$sql = '';
foreach ($rrdsql as $r) {
	if ($sql == '') {
		$sql = ' task_item_id = ' . $r['id'];
	} else {
		$sql .= ' or task_item_id = ' . $r['id'];
	}
}

$rrdlookup = $rrdsql[0]["id"];

$template_data_rrds = db_fetch_assoc("SELECT id, data_source_name FROM data_template_rrd WHERE local_data_id=" . $rra . " ORDER BY id");

$grapharr = db_fetch_assoc("SELECT DISTINCT local_graph_id FROM graph_templates_item WHERE $sql");

// Take the first one available
$graph = (isset($grapharr[0]["local_graph_id"]) ? $grapharr[0]["local_graph_id"] : "");

?>
<table width="98%" align="center">
        <tr>
                <td class="textArea">
	<?php 
		if (isset($banner)) { 
			echo $banner . "<br><br>";
			
		}; ?>

<form name="THold" action=thold.php method=post>
	<input type='hidden' name='rra' value='<?php echo $rra?>'>
	<input type='hidden' name='hostid' value='<?php echo $hostid?>'>
	Data Source Description: <br><strong><?php echo $desc?></strong><br><br>
	Associated Graph (graphs that use this RRD): <br>
	<select name='element'>
<?php
foreach($grapharr as $g) {
	$graph_desc = db_fetch_assoc("SELECT local_graph_id, title, title_cache FROM graph_templates_graph WHERE local_graph_id = " . $g["local_graph_id"]);

	echo "<option value=" . $graph_desc[0]["local_graph_id"];
	if($graph_desc[0]["local_graph_id"] == $graph) echo " selected";
	echo "> " . $graph_desc[0]["local_graph_id"] . " - " . $graph_desc[0]["title_cache"] . " </option>\n";

}
?>
	</select>
	<br>
<br>
                </td>
	<td>
	<img id=graphimage src="<?php echo $config["url_path"]; ?>graph_image.php?local_graph_id=<?php echo $graph ?>&rra_id=0&graph_start=-32400&graph_height=100&graph_width=300&graph_nolegend=true">
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
	if (sizeof($template_data_rrds) > 1) {
		/* draw the data source tabs on the top of the page */
		print "	<table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'>
		<tr>\n";
		
		foreach ($template_data_rrds as $template_data_rrd) {
			if($template_data_rrd["id"] == $_GET["view_rrd"]) $ds = $template_data_rrd["data_source_name"];

			$item = db_fetch_assoc("select * from thold_data where data_id = " . $template_data_rrd["id"]);
			$item = count($item) > 0 ? $item[0] : $item;
			
			if(count($item) == 0) {
				$cur_setting = "n/a";
			} else {
				$cur_setting = "Hi: " . ($item["thold_hi"] == "" ? "n/a" : $item["thold_hi"]);
				$cur_setting .= " Lo: " . ($item["thold_low"] == "" ? "n/a" : $item["thold_low"]);
				$cur_setting .= " BL: " . $item["bl_enabled"];
			}
			$tab_len = max(strlen($cur_setting), strlen($template_data_rrd["data_source_name"]));
			if($cur_setting == "n/a") { $cur_setting = "<font color='red'>" . $cur_setting . "</font>"; }

			$i++;
			echo "	<td bgcolor=" . (($template_data_rrd["id"] == $_GET["view_rrd"]) ? "'silver'" : "'#DFDFDF'");
			echo " nowrap='nowrap' width='" . (($tab_len * 8) + 30) . "' align='center' class='tab'>";
			echo "<span class='textHeader'><a href='thold.php?rra=" . $rra . "&view_rrd=" . $template_data_rrd["id"] . "'>$i: " . $template_data_rrd["data_source_name"] . "</a><br>";
			echo $cur_setting;
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
$thold_item_data = db_fetch_assoc("select * from thold_data where data_id = " . $_GET["view_rrd"]);
$thold_item_data = count($thold_item_data) > 0 ? $thold_item_data[0] : $thold_item_data;
$thold_item_data_cdef = (isset($thold_item_data['cdef']) ? $thold_item_data['cdef'] : 0);


html_start_box("", "98%", $colors["header"], "3", "center", "");
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

$users = db_fetch_assoc("SELECT plugin_thold_contacts.id, plugin_thold_contacts.data, plugin_thold_contacts.type, user_auth.full_name FROM plugin_thold_contacts, user_auth WHERE user_auth.id = plugin_thold_contacts.user_id AND plugin_thold_contacts.data != '' ORDER BY user_auth.full_name ASC, plugin_thold_contacts.type ASC");
if (!empty($users)) {
	foreach ($users as $user) {
		$send_notification_array[$user['id']] = $user['full_name'] . ' - ' . ucfirst($user['type']);
	}
}

if (isset($thold_item_data['id'])) {
	$sql = 'SELECT contact_id as id FROM plugin_thold_threshold_contact WHERE thold_id=' . $thold_item_data['id'];
} else {
	$sql = 'SELECT contact_id as id FROM plugin_thold_threshold_contact WHERE thold_id=0';
}

$form_array = array(
		"template_header" => array(
			"friendly_name" => "Template settings",
			"method" => "spacer",
		),
		"template_enabled" => array(
			"friendly_name" => "Enabled",
			"method" => "checkbox",
			"default" => "",
			"description" => "Whether or not these settings will be propigates from the threshold template.",
			"value" => isset($thold_item_data["template_enabled"]) ? $thold_item_data["template_enabled"] : "",
		),
		"general_header" => array(
			"friendly_name" => "Mandatory settings",
			"method" => "spacer",
		),
		"thold_enabled" => array(
			"friendly_name" => "Enabled",
			"method" => "checkbox",
			"default" => "on",
			"description" => "Whether or not this threshold will be checked and alerted upon.",
			"value" => isset($thold_item_data["thold_enabled"]) ? $thold_item_data["thold_enabled"] : ""
		),
		"thold_hi" => array(
			"friendly_name" => "High Threshold",
			"method" => "textbox",
			"max_length" => 100,
			"description" => "If set and data source value goes above this number, alert will be triggered",
			"value" => isset($thold_item_data["thold_hi"]) ? $thold_item_data["thold_hi"] : ""
		),
		
		"thold_low" => array(
			"friendly_name" => "Low Threshold",
			"method" => "textbox",
			"max_length" => 100,
			"description" => "If set and data source value goes below this number, alert will be triggered",
			"value" => isset($thold_item_data["thold_low"]) ? $thold_item_data["thold_low"] : ""
		),
		
		"thold_fail_trigger" => array(
			"friendly_name" => "Trigger Count",
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"default" => read_config_option("alert_trigger"),
			"description" => "Number of consecutive times the data source must be in breach of the threshold for an alert to be raised.<br>Leave empty to use default value (<b>Default: " . read_config_option("alert_trigger") . " cycles</b>)",
			"value" => isset($thold_item_data["thold_fail_trigger"]) ? $thold_item_data["thold_fail_trigger"] : ""
		),
		
		"baseline_header" => array(
			"friendly_name" => "Baseline monitoring",
			"method" => "spacer",
		),
		
		"bl_enabled" => array(
			"friendly_name" => "Baseline monitoring",
			"method" => "checkbox",
			"default" => "off",
			"description" => "When enabled, baseline monitoring checks the current data source value against a value in the past. The available range of values is retrieved and a minimum and maximum values are taken as a respective baseline reference. The precedence however is on the &quot;hard&quot; thresholds above.",
			"value" => isset($thold_item_data["bl_enabled"]) ? $thold_item_data["bl_enabled"] : ""
		),
		
		"bl_ref_time" => array(
			"friendly_name" => "Reference in the past",
			"method" => "textbox",
			"max_length" => 20,
			"default" => read_config_option("alert_bl_past_default"),
			"description" => "Specifies the relative point in the past that will be used as a reference. The value represents seconds, so for a day you would specify 86400, for a week 604800, etc.",
			"value" => isset($thold_item_data["bl_ref_time"]) ? $thold_item_data["bl_ref_time"] : ""
		),
		
		"bl_ref_time_range" => array(
			"friendly_name" => "Time range",
			"method" => "textbox",
			"max_length" => 20,
			"default" => read_config_option("alert_bl_timerange_def"),
			"description" => "Specifies the time range of values in seconds to be taken from the reference in the past",
			"value" => isset($thold_item_data["bl_ref_time_range"]) ? $thold_item_data["bl_ref_time_range"] : ""
		),
		
		"bl_pct_up" => array(
			"friendly_name" => "Baseline deviation UP",
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"description" => "Specifies allowed deviation in percentage for the upper bound threshold. If not set, upper bound threshold will not be checked at all.",
			"value" => isset($thold_item_data["bl_pct_up"]) ? $thold_item_data["bl_pct_up"] : ""
		),
		
		"bl_pct_down" => array(
			"friendly_name" => "Baseline deviation DOWN",
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"description" => "Specifies allowed deviation in percentage for the lower bound threshold. If not set, lower bound threshold will not be checked at all.",
			"value" => isset($thold_item_data["bl_pct_down"]) ? $thold_item_data["bl_pct_down"] : ""
		),
		
		"bl_fail_trigger" => array(
			"friendly_name" => "Baseline Trigger Count",
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"default" => read_config_option("alert_bl_trigger"),
			"description" => "Number of consecutive times the data source must be in breach of the baseline threshold for an alert to be raised.<br>Leave empty to use default value (<b>Default: " . read_config_option("alert_bl_trigger") . " cycles</b>)",
			"value" => isset($thold_item_data["bl_fail_trigger"]) ? $thold_item_data["bl_fail_trigger"] : ""
		),
		
		"other_header" => array(
			"friendly_name" => "Other setting",
			"method" => "spacer",
		),

		"cdef" => array(
			"friendly_name" => "Threshold CDEF",
			"method" => "drop_array",
			"default" => "NULL",
			"description" => "Apply this CDEF before returning the data.",
			"value" => isset($thold_item_data["cdef"]) ? $thold_item_data["cdef"] : 0,
			"array" => thold_cdef_select_usable_names()
		),

		"repeat_alert" => array(
			"friendly_name" => "Re-Alert Cycle",
			"method" => "textbox",
			"max_length" => 3,
			"size" => 3,
			"default" => read_config_option("alert_repeat"),
			"description" => "Repeat alert after specified number of cycles.<br>Leave empty to use default value (<b>Default: " . read_config_option("alert_repeat") . " cycles</b>)",
			"value" => isset($thold_item_data["repeat_alert"]) ? $thold_item_data["repeat_alert"] : ""
		),
		"notify_accounts" => array(
			"friendly_name" => "Notify accounts",
			"method" => "drop_multi",
			"description" => "This is a listing of accounts that will be notified when this threshold is breached.<br><br><br><br>",
			"array" => $send_notification_array,
			"sql" => $sql,
		),
		"notify_extra" => array(
			"friendly_name" => "Extra Alert Emails",
			"method" => "textbox",
			"max_length" => 255,
			"description" => "You may specify here extra e-mails to receive alerts for this data source (comma separated)",
			"value" => isset($thold_item_data["notify_extra"]) ? $thold_item_data["notify_extra"] : ""
		),
		
	);

draw_edit_form(
	array(
		"config" => array(
			"no_form_tag" => true
			),
		"fields" => $form_array + array(
			"data_template_rrd_id" => array(
				"method" => "hidden",
				"value" => (isset($template_rrd) ? $template_rrd["id"] : "0")
			),
			"hostid" => array(
				"method" => "hidden",
				"value" => $_SESSION['hostid']
			)
		)
		)
	);

html_end_box();
form_save_button("thold.php?rra=" . $rra . "&view_rrd=" . $_GET["view_rrd"], "save");

unset($template_data_rrds);
?>
<!-- Make it look intelligent :) -->
<script language="JavaScript">
function BL_EnableDisable()
{
	var _f = document.THold;
	var status = !_f.bl_enabled.checked;
	if (_f.bl_enabled.disabled)
		status = true;
	
	_f.bl_ref_time.disabled = status;
	_f.bl_ref_time_range.disabled = status;
	_f.bl_pct_down.disabled = status;
	_f.bl_pct_up.disabled = status;
	_f.bl_fail_trigger.disabled = status;
}

BL_EnableDisable();
document.THold.bl_enabled.onclick = BL_EnableDisable;

function Template_EnableDisable()
{
	var _f = document.THold;
	var status = _f.template_enabled.checked;
	_f.thold_hi.disabled = status;
	_f.thold_low.disabled = status;
	_f.thold_fail_trigger.disabled = status;
	_f.bl_enabled.disabled = status;
	_f.repeat_alert.disabled = status;
	_f.notify_extra.disabled = status;
	_f.cdef.disabled = status;
	_f.thold_enabled.disabled = status;
	_f["notify_accounts[]"].disabled = status;
	BL_EnableDisable();

}

Template_EnableDisable();
document.THold.template_enabled.onclick = Template_EnableDisable;
<?php
if (!isset($thold_item_data['template']) || $thold_item_data['template'] == '') {
?>
	document.THold.template_enabled.disabled = true;

<?php
}
?>

</script>
<?php

include_once($config["include_path"] . "/bottom_footer.php");
?>

<script language="JavaScript">
function GraphImage()
{
	var _f = document.THold;
	var id = _f.element.options[_f.element.selectedIndex].value;
	document.graphimage.src = "../../graph_image.php?local_graph_id=" + id + "&rra_id=0&graph_start=-32400&graph_height=100&graph_width=300&graph_nolegend=true";
}

document.THold.element.onchange = GraphImage;

</script>
