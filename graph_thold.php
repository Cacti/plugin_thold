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

$guest_account = true;

chdir('../../');
include_once("./include/auth.php");
include_once($config["include_path"] . "/top_graph_header.php");
include_once($config["base_path"] . "/plugins/thold/thold-functions.php");

delete_old_thresholds();

$sql = "SELECT thold_data.*, host.description FROM thold_data LEFT JOIN host ON thold_data.host_id=host.id WHERE thold_enabled='on' ORDER BY thold_alert DESC, bl_alert DESC, host.description ASC, rra_id ASC";
$result = db_fetch_assoc($sql);

$alerts_only = (read_config_option("alert_show_alerts_only") == "on");
$show_hosts = (read_config_option("alert_show_host_status") == "on");

print '<center> Last Poll: ';

$thold_last_poll = read_config_option("thold_last_poll", true);

if ($thold_last_poll > 0 && $thold_last_poll != '') {
	echo $thold_last_poll;
} else {
	echo "Poller has not yet ran!";
}

print '</center><br>';
print '<table width="98%" border="0" align="center">';
print '<thead><tr bgcolor="#a0a0ff">';

if ($show_hosts) {
	print '<td align="center">Hosts</td>';
}

print '<td align="center">Thresholds</td></tr></thead><tbody><tr>';

if ($show_hosts) {
	print '<td width="25%" valign="top">';
	print '<table width="100%" class="list" align="center" cellpadding="3">';
	print '<thead><tr bgcolor="#a0a0ff"><td>Host</td><td>Status</td></tr>';
	print '</thead><tbody>';

	$host_table = db_fetch_assoc("select * from host order by status asc, description asc");
	$c = 0;
	foreach($host_table as $host) {
		if ($host["disabled"] == "on") {
			continue;
		}
		$c++;

		switch ($host["status"]) {
			case HOST_UNKNOWN:
				$color = "gray";
				$status = "Unknown";
				break;
			case HOST_DOWN:
				$color = "red";
				$status = "DOWN";
				break;
			case HOST_RECOVERING:
				$color = "yellow";
				$status = "Recovering";
				break;
			case HOST_UP:
				$color = "green";
				$status = "up";
				break;
		}
		if ($alerts_only && $color == "green") {
			continue;
		}

		echo "<tr" . ($c % 2 == 1 ? " bgcolor='#E5E5E5'" : "") . ">\n";
		echo "  <td>\n";
		echo $host["description"];
		echo "  </td>\n";
		echo "  <td bgcolor='$color'>\n";
		echo $status;
		echo "  </td>\n";
		echo "</tr>\n";
	}
	print '</tbody></table></td>';
}

print '<td width="75%" valign="top">';
print '<table class="list" align="center" cellpadding="3">';
print '<thead>';
print '<tr bgcolor="#a0a0ff"><td>ID</td><td>Description / Click for graph</td><td>High Threshold</td><td>Low Threshold</td><td>Baselining</td><td>Current</td><td>Currently Triggered</td></tr>';
print '</thead><tbody>';

$c = 0;
foreach ($result as $row) {
	$c++;
	$t = db_fetch_assoc("select id, name, name_cache from data_template_data where local_data_id = " . $row["rra_id"] . " LIMIT 1");
	if (isset($t[0]["name_cache"])) {
		$desc_rra = $t[0]["name_cache"];
	} else {
		$desc_rra = "";
	}
	unset($t);
	$ds_item_desc = db_fetch_assoc("select id, data_source_name from data_template_rrd where id = " . $row["data_id"]);

//	$rrdsql = db_fetch_row("SELECT id FROM data_template_rrd WHERE local_data_id=" . $row["rra_id"] . " ORDER BY id ASC LIMIT 1");
//	$grapharr = db_fetch_assoc("SELECT DISTINCT local_graph_id FROM graph_templates_item WHERE task_item_id=" . $rrdsql["id"]);
//	$graph_id = $grapharr[0]['local_graph_id'];

	$grapharr = db_fetch_row("SELECT DISTINCT graph_templates_item.local_graph_id
			FROM graph_templates_item, data_template_rrd
			where (data_template_rrd.local_data_id=" . $row["rra_id"] . " AND data_template_rrd.id=graph_templates_item.task_item_id)");
	$graph_id = $grapharr['local_graph_id'];


	if ($row["thold_alert"] != 0) {
		$alertstat="YES";
		$bgcolor=($row["thold_fail_count"] >= $row["thold_fail_trigger"] ? "red" : "yellow");
	} else {
		$alertstat="no";
		$bgcolor="green";
		
		if ($row["bl_enabled"] == "on") {
			if ($row["bl_alert"] == 1) {
				$alertstat = "baseline-LOW";
				$bgcolor = ($row["bl_fail_count"] >= $row["bl_fail_trigger"] ? "orange" : "yellow");
			} else if ($row["bl_alert"] == 2) {
				$alertstat = "baseline-HIGH";
				$bgcolor = ($row["bl_fail_count"] >= $row["bl_fail_trigger"] ? "orange" : "yellow");
			}
		}
	}
	
	if ($alerts_only && $bgcolor == "green") {
		continue;
	}
	if (isset($ds_item_desc[0]["data_source_name"])) {
		?>
		<tr<?php echo ($c % 2 == 1 ? " bgcolor='#E5E5E5'" : ''); ?>><td><?php echo $row['id']; ?></td>
		<td><a href="<?php echo $config['url_path']; ?>graph.php?local_graph_id=<?php echo $graph_id; ?>&rra_id=all"><?php echo $desc_rra; ?> [<?php echo $ds_item_desc[0]['data_source_name']; ?>]</a></td>
		<td<?php echo ($row['thold_alert'] == 2 ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php echo ($row["thold_hi"] == '' ? '' : $row['thold_hi']); ?></td>
		<td<?php echo ($row['thold_alert'] == 1 ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php echo ($row['thold_low'] == '' ? '' : $row['thold_low']); ?></td>
		<td<?php echo (($row['bl_enabled'] == 'on' && $row['bl_alert'] > 0) ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php echo $row['bl_enabled']; ?></td>
		<td<?php echo ($bgcolor ? (" bgcolor='" . $bgcolor . "'") : ''); ?>><?php echo $row['lastread']; ?></td>
		<td bgcolor="<?php echo $bgcolor ?>"><?php echo $alertstat ?></td>
		</tr>
		<?php
	}
}

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION["sess_nav_level_cache"] = '';

?>

</tbody>
</table>
</td>
</tr>
</tbody>
</table>
