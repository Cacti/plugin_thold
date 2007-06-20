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
include_once($config["base_path"] . "/plugins/thold/thold-functions.php");



$hostid="";
if (isset($_REQUEST["hostid"])) {
	if ($_REQUEST["hostid"] == 'ALL') {
		$hostid = 'ALL';
	} else {
		input_validate_input_number(get_request_var_request("hostid"));
		$hostid = $_REQUEST["hostid"];
	}
}

if (isset($_POST['drp_action'])) {
	do_thold();
} else {
	delete_old_thresholds();
	list_tholds();
}


function do_thold() {
	global $hostid;
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_(.*)_(.*)$", $var, $matches)) {
			$del = $matches[1];
			$rra = $matches[2];
			input_validate_input_number($del);
			input_validate_input_number($rra);
			db_execute("DELETE FROM thold_data WHERE id=$del");
			db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $del);
		}
	}
	if (isset($hostid) && $hostid != '')
		Header("Location:listthold.php?hostid=$hostid");
	else
		Header("Location:listthold.php");
	exit;
}

function list_tholds() {
	global $colors, $config, $hostid;

	$ds_actions = array(1 => "Delete");

	load_current_session_value("page", "sess_thold_current_page", "1");

	include($config["include_path"] . "/top_header.php");
	if (isset($_REQUEST["search"]) && $hostid != "ALL") {
		$sql = "SELECT * FROM thold_data WHERE host_id='$hostid' ORDER BY thold_alert DESC, bl_alert DESC, rra_id ASC limit " . (read_config_option("alert_num_rows")*($_REQUEST["page"]-1)) . "," . read_config_option("alert_num_rows");
	} else {
		$sql = "SELECT thold_data.*, host.description FROM thold_data left join host on thold_data.host_id=host.id ORDER BY thold_alert DESC, bl_alert DESC, host.description, rra_id ASC limit " . (read_config_option("alert_num_rows")*($_REQUEST["page"]-1)) . "," . read_config_option("alert_num_rows");
	}

	$result = db_fetch_assoc($sql);

	html_start_box("<strong>Threshold Management</strong>" , "98%", $colors["header"], "3", "center", "");
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>
		<td class='textSubHeaderDark'><Br> &nbsp; <b> To add more elements - go to:<br>
			&nbsp; &nbsp; &nbsp; 'Data Sources' -> 'select a host' (on top) -> and click the 'Template Name / Click for THold' section for the desired element
		</td>
	 </tr>
        <tr bgcolor='#" . $colors["header_panel"] . "'>
                <td class='textSubHeaderDark'><Br> &nbsp; <b> You can also auto-create thresholds per device - go to:<br>
		&nbsp; &nbsp; &nbsp; '<a href=\"thold_templates.php\">Templates</a>' -> Click on '<a href=\"thold_templates.php?action=add\">Add</a>' in the upper right corner -> Now select a graph, and setup your threshold template<br>
		&nbsp; &nbsp; &nbsp; 'Devices' -> Click on the desired device -> Click on 'Create graphs for this host' -> Click on 'Auto-create thresholds'
		</td>
        </tr>
	 <tr bgcolor='#" . $colors["header_panel"] . "'>
        	<td class='textSubHeaderDark'><Br> &nbsp; <b>  To edit an existing element, click the Description below</td>
        </tr>	";

	$hostresult = db_fetch_assoc("SELECT id, description, hostname from host order by description");

	echo "<tr><td align=center><form action=listthold.php method=post><input type=hidden name=search value=search>Filter by host:	<select name=hostid>";
	echo "<option value=ALL>Show All</option>";
	foreach ($hostresult as $row) { 
		echo "<option value='" . $row["id"] . "'" . ($row["id"] == $hostid ? " selected" : "") . ">" . $row["description"] . " - (" . $row["hostname"] . ")" . "</option>";
	}
	echo "	</select><input type=image src='" . $config['url_path'] . "images/button_go.gif' alt='GO' align='top' action='submit'></form></td></tr>";
	html_end_box();

	print "<br><center><b>Last Poll: </b>";

	$thold_last_poll = read_config_option("thold_last_poll");

	if ($thold_last_poll > 0 && $thold_last_poll != '') {
		echo $thold_last_poll;
	} else {
		echo "Poller has not yet ran!";
	}

	print "</center><br>";
    
	define("MAX_DISPLAY_PAGES", 21);
	$total_rows = db_fetch_cell("SELECT COUNT(thold_data.id) FROM `thold_data`");
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, read_config_option("alert_num_rows"), $total_rows, "listthold.php?");

	html_start_box("", "98%", $colors["header"], "4", "center", "");
	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='10'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='listthold.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("alert_num_rows")*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("alert_num_rows")) || ($total_rows < (read_config_option("alert_num_rows")*$_REQUEST["page"]))) ? $total_rows : (read_config_option("alert_num_rows")*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * read_config_option("alert_num_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='listthold.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * read_config_option("alert_num_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;
	html_header_checkbox(array("Description", "High Threshold", "Low Threshold", "Trigger", "Repeat", "Baselining", "Current", "Currently Triggered", "Enabled"));

	$c=0;
	$i=0;
	foreach ($result as $row) {
		$c++;
		$t = db_fetch_assoc("select id,name,name_cache from data_template_data where local_data_id=" . $row["rra_id"] . " LIMIT 1");

		if (isset($t[0]["name_cache"]))
			$desc_rra = $t[0]["name_cache"];
		else
			$desc_rra = "";	
		unset($t);
		$ds_item_desc = db_fetch_assoc("select id,data_source_name from data_template_rrd where id = " . $row["data_id"]);

		$grapharr = db_fetch_row("SELECT DISTINCT graph_templates_item.local_graph_id
					FROM graph_templates_item, data_template_rrd
					where (data_template_rrd.local_data_id=" . $row["rra_id"] . " AND data_template_rrd.id=graph_templates_item.task_item_id)");
		$graph_id = $grapharr['local_graph_id'];

		if ($row["thold_alert"] != 0) {
			$alertstat="yes";
			$bgcolor=($row["thold_fail_count"] >= $row["thold_fail_trigger"] ? "red" : "yellow");
		} else {
			$alertstat="no";
			$bgcolor="green";
			if($row["bl_enabled"] == "on") {
				if($row["bl_alert"] == 1) {
					$alertstat="baseline-LOW";
					$bgcolor=($row["bl_fail_count"] >= $row["bl_fail_trigger"] ? "orange" : "yellow");
					} elseif ($row["bl_alert"] == 2)  {
					$alertstat="baseline-HIGH";
					$bgcolor=($row["bl_fail_count"] >= $row["bl_fail_trigger"] ? "orange" : "yellow");
				}
			}
		};
		if (!isset($ds_item_desc[0]["data_source_name"]))
			$ds_item_desc[0]["data_source_name"] = "Unknown Data Source";	
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
?>
       	<td><a href="thold.php?rra=<?php echo $row["rra_id"]; ?>&view_rrd=<?php echo $row["data_id"]; ?>"><b><?php echo $desc_rra; ?> [<?php echo $ds_item_desc[0]["data_source_name"]; ?>]</b></a></td>
		<td<?php echo ($row["thold_alert"] == 2 ? (" bgcolor='" . $bgcolor . "'") : ""); ?>><?php echo ($row["thold_hi"] == "" ? "n/a" : $row["thold_hi"]); ?></td>
		<td<?php echo ($row["thold_alert"] == 1 ? (" bgcolor='" . $bgcolor . "'") : ""); ?>><?php echo ($row["thold_low"] == "" ? "n/a" : $row["thold_low"]); ?></td>
		<td><?php echo ($row["thold_fail_trigger"] == "" ? 'n/a' : $row["thold_fail_trigger"]); ?></td>
		<td><?php echo ($row["repeat_alert"] == "" ? 'n/a' : $row["repeat_alert"]); ?></td>
		<td<?php echo (($row["bl_enabled"] == "on" && $row["bl_alert"] > 0) ? (" bgcolor='" . $bgcolor . "'") : ""); ?>><?php echo $row["bl_enabled"]; ?></td>
		<td<?php echo ($bgcolor != "green" ? (" bgcolor='" . $bgcolor . "'") : ""); ?>><?php echo $row["lastread"]; ?></td>
		<td<?php echo ($bgcolor != "green" ? (" bgcolor='" . $bgcolor . "'") : ""); ?>>
		<?php echo $alertstat ?> 
		</td>
		<td><?php echo (($row["thold_enabled"] == "off") ? ("<font color='red'><b>") : ""); echo $row["thold_enabled"]; echo (($row["thold_enabled"] == "off") ? ("</b></font>") : "");?></td>
		<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
			<input type='checkbox' style='margin: 0px;' name='chk_<?php print $row["id"];?>_<?php print $row["rra_id"];?>' title="<?php print $row["id"];?>">
		</td>
		</tr>
<?php
	}
	html_end_box(false);
	draw_actions_dropdown($ds_actions);
	if (isset($hostid) && $hostid != '')
	print "<input type=hidden name=hostid value=$hostid>";
	print "</form>\n";
	print "<br><br><center>For default alerting settings please click <a href='" . $config['url_path'] . "settings.php?tab=alerts'>here</a></center>";
	include_once($config["include_path"] . "/bottom_footer.php");
}

