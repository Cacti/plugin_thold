<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
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
include_once("./include/auth.php");
include_once($config["base_path"] . "/plugins/thold/include/top_thold_header.php");
include_once($config["base_path"] . "/plugins/thold/thold-functions.php");

if (!thold_check_dependencies()) {
	cacti_log("THOLD: You are missing a required dependency, please install the '<a href='http://cactiusers.org/'>Settings'</a> plugin.", true, "POLLER");
	print "<br><br><center><font color=red>You are missing a dependency for thold, please install the '<a href='http://cactiusers.org'>Settings</a>' plugin.</font></color>";
	exit;
}

thold_view_recent();

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION["sess_nav_level_cache"] = '';

function thold_view_recent() {
	global $title, $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	if (isset($_REQUEST["page"])) {
		$_REQUEST["i_page"] = $_REQUEST["page"];
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("i_page", "sess_thold_view_thresholds_current_page", "1");
	load_current_session_value("page", "sess_thold_view_thresholds_current_page", "1");
	load_current_session_value("sort_column", "sess_thold_view_recent_sort_column", "time");
	load_current_session_value("sort_direction", "sess_thold_view_recent_sort_direction", "DESC");

	if (isset($_REQUEST['show'])) {
		$show = $_REQUEST['show'];
	} else {
		$show = '';
	}

	$_REQUEST["i_page"] = $_REQUEST["page"];

	html_start_box("", "98%", $colors["header"], "3", "center", "");

	$sort = 'ORDER BY ' . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];
	$perpage = read_config_option("alert_num_rows");
	$limit = " LIMIT " . ($perpage*($_REQUEST["i_page"]-1)) . "," . $perpage;

	$result = db_fetch_assoc("SELECT plugin_thold_log.*, host.description as hostdesc, thold_data.rra_id, thold_data.data_id
					 FROM plugin_thold_log, thold_data, host
					 WHERE plugin_thold_log.type = 0 AND plugin_thold_log.threshold_id = thold_data.id AND plugin_thold_log.host_id = host.id $sort $limit");
	$total_rows = db_fetch_cell("SELECT count(*)
					 FROM plugin_thold_log
					 WHERE plugin_thold_log.type = 0");

	/* generate page list */

	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["i_page"], 10, $perpage, $total_rows, "graph_thold.php"));
	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["i_page"] > 1) { $nav .= "<a class='linkOverDark' href='graph_thold.php?page=" . ($_REQUEST["i_page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["i_page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (($perpage*($_REQUEST["i_page"]-1))+1) . " to " . ((($total_rows < $perpage) || ($total_rows < ($perpage*$_REQUEST["i_page"]))) ? $total_rows : ($perpage*$_REQUEST["i_page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["i_page"] * $perpage) < $total_rows) { $nav .= "<a class='linkOverDark' href='graph_thold.php?page=" . ($_REQUEST["i_page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["i_page"] * $perpage) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"threshold_id" => array("<br>ID", "ASC"),
		'time' => array('Date', 'DESC'),
		"hostdesc" => array("Hostname", "ASC"),
		"description" => array("Description", "ASC"),
		"status" => array("Status", "DESC"),
		"threshold_value" => array("Threshold Limit", "DESC"),
		"current" => array("Graph Value", "DESC"));

	$status = array(0=>'Normal', 1 => 'Failed');

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
	$i = 0;
	if (sizeof($result) > 0) {
		foreach ($result as $row) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td><?php print "<p class='linkEditMain'><a href='thold.php?rra=" . $row['rra_id'] . '&view_rrd=' . $row['data_id'] . "'>" . $row["threshold_id"] . "</a></p>";?></td>
				<td><?php print date("F j, Y, g:i a", $row["time"]);?></td>
				<td><?php print "<a href='" . $config['url_path'] . "host.php?action=edit&id=" . $row['host_id'] . "'>" . $row['hostdesc']; ?></a></td>
				<td><a href="<?php echo $config['url_path']; ?>graph.php?local_graph_id=<?php echo $row['local_graph_id']; ?>&rra_id=all"><?php print $row['description']; ?></a></td>
				<td><?php print $status[$row['status']];?></td>
				<td><?php print $row["threshold_value"];?></td>
				<td><?php print $row["current"];?></td>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Thresholds Found</em></td></tr>";
	}
	html_end_box(false);
}
