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
			}else{
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
	}

	/* setup some variables */
	$list = ""; $array = array();

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
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Notification List(s)'>";
		}elseif ($_POST["drp_action"] == "2") { /* duplicate */
			print "	<tr>
					<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
						<p>When you click \"Continue\", the following Notification List(s) will be duplicated.</p>
						<ul>$list</ul>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Notification List(s)'>";
		}
	}else{
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Notification List.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

/* ----------------------------
   Notification List Edit
   ---------------------------- */

function edit() {
	global $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if (!empty($_GET["id"])) {
		$list = db_fetch_row("SELECT * FROM plugin_notification_lists WHERE id=" . $_GET["id"]);
		$header_label = "[edit: " . $list["name"] . "]";
	}else{
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
			"textarea_cols" => "60"
		),
		"emails" => array(
			"method" => "textarea",
			"friendly_name" => "Email Addresses",
			"description" => "Enter a comma separated list of Email addresses for this notification list.",
			"value" => "|arg1:emails|",
			"class" => "textAreaNotes",
			"textarea_rows" => "4",
			"textarea_cols" => "60"
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
	}else{
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
	}else{
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
	}else{
		print "<tr><td><em>No Notification Lists</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	print "</form>\n";
}

?>

