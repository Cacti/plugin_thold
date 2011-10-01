<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2011 The Cacti Group                                 |
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

chdir("../..");
include("./include/auth.php");

define("MAX_DISPLAY_PAGES", 21);

$actions = array(
	1 => "Delete",
	2 => "Duplicate"
);

$device_actions = array(
    1 => "Associate",
    2 => "Disassociate"
);

//print "<pre>";print_r($_POST);print "</pre>";exit;

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		include_once ("./include/top_header.php");
		edit();
		include_once ("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		lists();
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component"])) {
		$save["id"] = $_POST["id"];
		$save["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save["description"] = form_input_validate($_POST["description"], "description", "", false, 3);
		$save["emails"] = form_input_validate($_POST["emails"], "emails", "", false, 3);

		if (!is_error_message()) {
			$id = sql_save($save, "plugin_notification_lists");

			if ($id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}
	}

	header("Location: notify_lists.php?action=edit&id=" . (empty($id) ? $_POST["id"] : $id));
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		if (isset($_POST["save_list"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));

			if ($_POST["drp_action"] == "1") { /* delete */
				db_execute("DELETE FROM plugin_notification_lists WHERE " . array_to_sql_or($selected_items, "id"));

				$hosts = db_fetch_assoc("SELECT id FROM host WHERE " . array_to_sql_or($selected_items, "thold_email_notify"));

				if (sizeof($hosts)) {
				foreach ($hosts as $host) {
					db_execute("UPDATE host SET thold_email_notify=1 WHERE id=" . $host["id"]);
				}
				}
			}elseif ($_POST["drp_action"] == "2") { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */
				}
			}

			header("Location: notify_lists.php");
			exit;
		}elseif (isset($_POST["save_associate"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));
			input_validate_input_number(get_request_var_request('notification_action'));

			if ($_POST["drp_action"] == "1") { /* associate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					/* set the notification list */
					db_execute("UPDATE host SET thold_host_email=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
					/* set the global/list election */
					db_execute("UPDATE host SET thold_send_email=" . get_request_var_request("notification_action") . " WHERE id=" . $selected_items[$i]);
				}
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					/* set the notification list */
					db_execute("UPDATE host SET thold_host_email=0 WHERE id=" . $selected_items[$i]);
					/* set the global/list election */
					db_execute("UPDATE host SET thold_send_email=" . get_request_var_request("notification_action") . " WHERE id=" . $selected_items[$i]);
				}
			}

			header("Location: notify_lists.php?action=edit&id=" . get_request_var_request("id"));
			exit;
		}
	}

	/* setup some variables */
	$list = ""; $array = array();

	if (isset($_POST["save_list"])) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= "<li>" . db_fetch_cell("SELECT name FROM plugin_notification_lists WHERE id=" . $matches[1]) . "</li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='notify_lists.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* delete */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the following Notification Lists(s) will be deleted.  Any Hosts(s) associated with the List(s) will be reverted to the default.</p>
							<ul>$list</ul>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Notification List(s)'>";
			}elseif ($_POST["drp_action"] == "2") { /* duplicate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the following Notification List(s) will be duplicated.</p>
							<ul>$list</ul>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Notification List(s)'>";
		}
		} else {
			print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Notification List.</span></td></tr>\n";
			$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
		}

		print "	<tr>
				<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='save_list' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		include_once("./include/bottom_footer.php");
	}else{
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= "<li>" . db_fetch_cell("SELECT description FROM host WHERE id=" . $matches[1]) . "</li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='notify_lists.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* associate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the following Notification Lists will be associated with the host(s) below.</p>
							<ul>$list</ul>
							<p>Resulting List Membership:<br>"; form_dropdown("notification_action", array(2 => "Selected List Only", 3 => "Selected and Global Lists"), "", "", "", "", ""); print "</p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Notification List(s)'>";
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the following Notification List will be disassociated from the host(s) below.</p>
							<ul>$list</ul>
							<p>Resulting List Membership:<br>"; form_dropdown("notification_action", array(1 => "Global List", 0 => "Disabled"), "", "", "", "", ""); print "</p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Notification List(s)'>";
		}
		} else {
			print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one host.</span></td></tr>\n";
			$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
		}

		print "	<tr>
				<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var_request('id') . "'>
				<input type='hidden' name='save_associate' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		include_once("./include/bottom_footer.php");
	}
}

/* ----------------------------
   Notification List Edit
   ---------------------------- */

function edit() {
	global $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	/* ==================================================== */

	if (!empty($_REQUEST["id"])) {
		$_GET["id"] = $_REQUEST["id"];
		$list = db_fetch_row("SELECT * FROM plugin_notification_lists WHERE id=" . $_REQUEST["id"]);
		$header_label = "[edit: " . $list["name"] . "]";
	} else {
		$header_label = "[new]";
	}

	html_start_box("<strong>Notification List</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

	$fields_notification = array(
		"name" => array(
			"method" => "textbox",
			"friendly_name" => "Name",
			"description" => "Enter a name for this Notification List",
			"value" => "|arg1:name|",
			"max_length" => "80"
		),
		"description" => array(
			"method" => "textarea",
			"friendly_name" => "Description",
			"description" => "Enter a description for this Notification List",
			"value" => "|arg1:description|",
			"class" => "textAreaNotes",
			"textarea_rows" => "2",
			"textarea_cols" => "80"
		),
		"emails" => array(
			"method" => "textarea",
			"friendly_name" => "Email Addresses",
			"description" => "Enter a comma separated list of Email addresses for this notification list.",
			"value" => "|arg1:emails|",
			"class" => "textAreaNotes",
			"textarea_rows" => "4",
			"textarea_cols" => "80"
		),
		"id" => array(
			"method" => "hidden_zero",
			"value" => "|arg1:id|"
		),
		"save_component" => array(
			"method" => "hidden",
			"value" => "1"
		)
	);

	draw_edit_form(array(
		"config" => array(),
		"fields" => inject_form_variables($fields_notification, (isset($list) ? $list : array()))
		));

	html_end_box();

	form_save_button("notify_lists.php", "return");

	if (isset($_REQUEST['id'])) {
		print "<br>";

		hosts();
	}
}

function hosts() {
	global $colors, $device_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("host_template_id"));
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up associated string */
	if (isset($_REQUEST["associated"])) {
		$_REQUEST["associated"] = sanitize_search_string(get_request_var_request("associated"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clearf"])) {
		kill_session_var("sess_nlh_current_page");
		kill_session_var("sess_nlh_filter");
		kill_session_var("sess_nlh_host_template_id");
		kill_session_var("sess_nlh_rows");
		kill_session_var("sess_nlh_associated");

		unset($_REQUEST["page"]);
		unset($_POST["page"]);
		unset($_REQUEST["filter"]);
		unset($_POST["filter"]);
		unset($_REQUEST["associated"]);
		unset($_POST["associated"]);
		unset($_REQUEST["host_template_id"]);
		unset($_POST["host_template_id"]);
		unset($_REQUEST["rows"]);
		unset($_POST["rows"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_nlh_current_page", "1");
	load_current_session_value("filter", "sess_nlh_filter", "");
	load_current_session_value("associated", "sess_nlh_associated", "true");
	load_current_session_value("host_template_id", "sess_nlh_host_template_id", "-1");
	load_current_session_value("rows", "sess_nlh_rows", read_config_option("num_rows_device"));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_device");
	}

	?>
	<script type="text/javascript">
	<!--

	function applyViewDeviceFilterChange(objForm) {
		strURL = '?action=edit&id=<?php print get_request_var_request('id');?>&rows=' + objForm.rows.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&associated=' + objForm.associated.checked;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	function clearViewDeviceFilterChange(objForm) {
		strURL = '?action=edit&id=<?php print get_request_var_request('id');?>&clearf=true'
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box("<strong>Associated Devices</strong>", "100%", $colors["header"], "3", "center", "");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_devices" method="post" action="notify_lists.php">
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Type:&nbsp;
					</td>
					<td width="1">
						<select name="host_template_id" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if (get_request_var_request("host_template_id") == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request("host_template_id") == "0") {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc("select id,name from host_template order by name");

							if (sizeof($host_templates) > 0) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template["id"] . "'"; if (get_request_var_request("host_template_id") == $host_template["id"]) { print " selected"; } print ">" . htmlspecialchars($host_template["name"]) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="20">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>" onChange="applyViewDeviceFilterChange(document.form_devices)">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' name='associated' id='associated' onChange='applyViewDeviceFilterChange(document.form_devices)' <?php print ($_REQUEST['associated'] == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'>Associated with List</label>
					</td>
					<td nowrap>
						&nbsp;<input type="button" value="Go" onClick='applyViewDeviceFilterChange(document.form_devices)' title="Set/Refresh Filters">
					</td>
					<td nowrap>
						<input type="button" name="clearf" value="Clear" onClick='clearViewDeviceFilterChange(document.form_devices)' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE (host.hostname LIKE '%%" . get_request_var_request("filter") . "%%' OR host.description LIKE '%%" . get_request_var_request("filter") . "%%')";
	} else {
		$sql_where = "";
	}

	if (get_request_var_request("host_template_id") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("host_template_id") == "0") {
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " host.host_template_id=0";
	}elseif (!empty($_REQUEST["host_template_id"])) {
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " host.host_template_id=" . get_request_var_request("host_template_id");
	}

	if (get_request_var_request("associated") == "false") {
		/* Show all items */
	} else {
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " (host.thold_send_email>1 AND host.thold_host_email=" . get_request_var_request("id") . ")";
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='notify_lists.php?action=edit&id=" . get_request_var_request("id") . "'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("select
		COUNT(host.id)
		from host
		$sql_where");

	$host_graphs       = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id"), "host_id", "graphs");
	$host_data_sources = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id"), "host_id", "data_sources");

	$sql_query = "SELECT * 
		FROM host $sql_where 
		LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows");

	$hosts = db_fetch_assoc($sql_query);

	/* generate page list */
	$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rows"), $total_rows, "notify_lists.php?action=edit&id=" . get_request_var_request('id'));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='11'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("notify_lists.php?action=edit&id=" . get_request_var_request("id") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((get_request_var_request("rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (get_request_var_request("rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
									<strong>"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("notify_lists.php?action=edit&id=" . get_request_var_request("id") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array("Description", "ID", "Associated Lists", "Graphs", "Data Sources", "Status", "Hostname");

	html_header_checkbox($display_text);

	$i = 0;
	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $host["id"]); $i++;
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($host["description"])) : htmlspecialchars($host["description"])), $host["id"], 250);
			form_selectable_cell(round(($host["id"]), 2), $host["id"]);
			if ($host['thold_send_email'] == 0) {
				form_selectable_cell('<span style="color:blue;font-weight:bold;">Disabled</span>', $host['id']);
			}elseif ($host['thold_send_email'] == 1) {
				form_selectable_cell('<span style="color:purple;font-weight:bold;">Global List</span>', $host['id']);
			}elseif ($host['thold_host_email'] == get_request_var_request('id')) {
				if ($host['thold_send_email'] == 2) {
					form_selectable_cell('<span style="color:green;font-weight:bold;">Current List Only</span>', $host['id']);
				}else{
					form_selectable_cell('<span style="color:green;font-weight:bold;">Current and Global List(s)</span>', $host['id']);
				}
			}elseif ($host['thold_host_email'] == '0') {
				form_selectable_cell('<span style="color:green;font-weight:bold;">None</span>', $host['id']);
			}else{
				form_selectable_cell('<span style="color:red;font-weight:bold;">' . db_fetch_cell("SELECT name FROM plugin_notification_lists WHERE id=" . get_request_var_request('id')) . '</span>', $host['id']);
			}
			form_selectable_cell((isset($host_graphs[$host["id"]]) ? $host_graphs[$host["id"]] : 0), $host["id"]);
			form_selectable_cell((isset($host_data_sources[$host["id"]]) ? $host_data_sources[$host["id"]] : 0), $host["id"]);
			form_selectable_cell(get_colored_device_status(($host["disabled"] == "on" ? true : false), $host["status"]), $host["id"]);
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($host["hostname"])) : htmlspecialchars($host["hostname"])), $host["id"]);
			form_checkbox_cell($host["description"], $host["id"]);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	} else {
		print "<tr><td><em>No Associated Hosts Found</em></td></tr>";
	}
	html_end_box(false);

	form_hidden_box("action", "edit");
	form_hidden_box("id", get_request_var_request("id"));
	form_hidden_box("save_associate", "1");

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($device_actions);

	print "</form>\n";
}

function lists() {
	global $colors, $actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear"])) {
		kill_session_var("sess_lists_current_page");
		kill_session_var("sess_lists_filter");
		kill_session_var("sess_lists_sort_column");
		kill_session_var("sess_lists_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);

	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_lists_current_page", "1");
	load_current_session_value("filter", "sess_lists_filter", "");
	load_current_session_value("sort_column", "sess_lists_sort_column", "name");
	load_current_session_value("sort_direction", "sess_lists_sort_direction", "ASC");

	html_start_box("<strong>Notification Lists</strong>", "100%", $colors["header"], "3", "center", "notify_lists.php?action=edit");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="lists" action="notify_lists.php">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST['filter'])) {
		$sql_where = "WHERE (name LIKE '%%" . get_request_var_request("filter") . "%%' OR
		description LIKE '%%" . get_request_var_request("filter") . "%%' OR
		emails LIKE '%%" . get_request_var_request("filter") . "%%')";
	} else {
		$sql_where = '';
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='notify_lists.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM plugin_notification_lists
		$sql_where");

	$lists = db_fetch_assoc("SELECT id, name, description, emails
		FROM plugin_notification_lists
		$sql_where
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") .
		" LIMIT " . (read_config_option("num_rows_device")*(get_request_var_request("page")-1)) . "," . read_config_option("num_rows_device"));

	if ($total_rows > 0) {
		/* generate page list */
		$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, read_config_option("num_rows_device"), $total_rows, "notify_lists.php?filter=" . get_request_var_request("filter"));

		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("notify_lists.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_device")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_device")) || ($total_rows < (read_config_option("num_rows_device")*get_request_var_request("page")))) ? $total_rows : (read_config_option("num_rows_device")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * read_config_option("num_rows_device")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("notify_lists.php?filter=" . get_request_var_request("filter") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * read_config_option("num_rows_device")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
			</tr>\n";
	} else {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='center' class='textHeaderDark'>
							No Rows Found
						</td>\n
					</tr>
				</table>
			</td>
			</tr>\n";
	}

	print $nav;

	$display_text = array(
		"name" => array("List Name", "ASC"),
		"description" => array("Description", "ASC"),
		"emails" => array("Emails", "ASC"));

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($lists)) {
		foreach ($lists as $item) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $item["id"]);$i++;
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("notify_lists.php?action=edit&id=" . $item["id"]) . "'>" . (strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($item["name"])) : htmlspecialchars($item["name"])) . "</a>", $item["id"], "25%");
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["description"]) : $item["description"]) . "</a>", $item["id"], "35%");
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $item["emails"]) : $item["emails"]) . "</a>", $item["id"]);
			form_checkbox_cell($item["name"], $item["id"]);
			form_end_row();
		}
		print $nav;
	} else {
		print "<tr><td><em>No Notification Lists</em></td></tr>\n";
	}
	html_end_box(false);

	form_hidden_box("save_list", "1");

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	print "</form>\n";
}

?>

