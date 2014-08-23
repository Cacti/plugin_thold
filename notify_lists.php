<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2014 The Cacti Group                                      |
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
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

define("MAX_DISPLAY_PAGES", 21);

$actions = array(
	1 => "Delete",
	2 => "Duplicate"
);

$assoc_actions = array(
    1 => "Associate",
    2 => "Disassociate"
);

/* global colors */
$thold_bgcolors = array(
    'red'     => 'F21924',
    'orange'  => 'FB4A14',
    'warning' => 'FF7A30',
    'yellow'  => 'FAFD9E',
    'green'   => 'CCFFCC',
    'grey'    => 'CDCFC4'
);

//print "<pre>";print_r($_POST);print "</pre>";exit;

/* present a tabbed interface */
$tabs_thold = array(
    "general" => "General",
    "hosts" => "Hosts",
    "tholds" => "Thresholds",
    "templates" => "Templates"
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
	global $colors, $actions, $assoc_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		if (isset($_POST["save_list"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));

			if ($_POST["drp_action"] == "1") { /* delete */
				db_execute("DELETE FROM plugin_notification_lists WHERE " . array_to_sql_or($selected_items, "id"));
				db_execute("UPDATE host SET thold_send_email=0 WHERE thold_send_email=2 AND " . array_to_sql_or($selected_items, "thold_host_email"));
				db_execute("UPDATE host SET thold_send_email=1 WHERE thold_send_email=3 AND " . array_to_sql_or($selected_items, "thold_host_email"));
				db_execute("UPDATE host SET thold_host_email=0 WHERE " . array_to_sql_or($selected_items, "thold_host_email"));
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

			header("Location: notify_lists.php?action=edit&tab=hosts&id=" . get_request_var_request("id"));
			exit;
		}elseif (isset($_POST["save_templates"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));
			input_validate_input_number(get_request_var_request('notification_action'));

			if ($_POST["drp_action"] == "1") { /* associate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					if ($_POST["notification_warning_action"] > 0) {
						/* clear other settings */
						if ($_POST["notification_warning_action"] == 1) {
							/* set the notification list */
							db_execute("UPDATE thold_template SET notify_warning=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
							/* clear other items */
							db_execute("UPDATE thold_template SET notify_warning_extra='' WHERE id=" . $selected_items[$i]);
						}else{
							/* set the notification list */
							db_execute("UPDATE thold_template SET notify_warning=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
						}
					}

					if ($_POST["notification_alert_action"] > 0) {
						/* clear other settings */
						if ($_POST["notification_alert_action"] == 1) {
							/* set the notification list */
							db_execute("UPDATE thold_template SET notify_alert=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
							/* clear other items */
							db_execute("UPDATE thold_template SET notify_extra='' WHERE id=" . $selected_items[$i]);
							db_execute("DELETE FROM plugin_thold_template_contact WHERE template_id=" . $selected_items[$i]);
						}else{
							/* set the notification list */
							db_execute("UPDATE thold_template SET notify_alert=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
						}
					}
				}
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					if ($_POST["notification_warning_action"] > 0) {
						/* set the notification list */
						db_execute("UPDATE thold_template SET notify_warning=0 WHERE id=" . $selected_items[$i] . " AND notify_warning=" . get_request_var_request('id'));
					}

					if ($_POST["notification_alert_action"] > 0) {
						/* set the notification list */
						db_execute("UPDATE thold_template SET notify_alert=0 WHERE id=" . $selected_items[$i] . " AND notify_alert=" . get_request_var_request('id'));
					}
				}
			}

			header("Location: notify_lists.php?action=edit&tab=templates&id=" . get_request_var_request("id"));
			exit;
		}elseif (isset($_POST["save_tholds"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));
			input_validate_input_number(get_request_var_request('notification_action'));

			if ($_POST["drp_action"] == "1") { /* associate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					if ($_POST["notification_warning_action"] > 0) {
						/* clear other settings */
						if ($_POST["notification_warning_action"] == 1) {
							/* set the notification list */
							db_execute("UPDATE thold_data SET notify_warning=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
							/* clear other items */
							db_execute("UPDATE thold_data SET notify_warning_extra='' WHERE id=" . $selected_items[$i]);
						}else{
							/* set the notification list */
							db_execute("UPDATE thold_data SET notify_warning=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
						}
					}

					if ($_POST["notification_alert_action"] > 0) {
						/* clear other settings */
						if ($_POST["notification_alert_action"] == 1) {
							/* set the notification list */
							db_execute("UPDATE thold_data SET notify_alert=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
							/* clear other items */
							db_execute("UPDATE thold_data SET notify_extra='' WHERE id=" . $selected_items[$i]);
							db_execute("DELETE FROM plugin_thold_threshold_contact WHERE thold_id=" . $selected_items[$i]);
						}else{
							/* set the notification list */
							db_execute("UPDATE thold_data SET notify_alert=" . get_request_var_request("id") . " WHERE id=" . $selected_items[$i]);
						}
					}
				}
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					if ($_POST["notification_warning_action"] > 0) {
						/* set the notification list */
						db_execute("UPDATE thold_data SET notify_warning=0 WHERE id=" . $selected_items[$i] . " AND notify_warning=" . get_request_var_request('id'));
					}

					if ($_POST["notification_alert_action"] > 0) {
						/* set the notification list */
						db_execute("UPDATE thold_data SET notify_alert=0 WHERE id=" . $selected_items[$i] . " AND notify_alert=" . get_request_var_request('id'));
					}
				}
			}

			header("Location: notify_lists.php?action=edit&tab=tholds&id=" . get_request_var_request("id"));
			exit;
		}
	}

	/* setup some variables */
	$list = ""; $array = array(); $list_name = "";
	if (isset($_POST["id"])) {
		$list_name = db_fetch_cell("SELECT name FROM plugin_notification_lists WHERE id=" . $_POST["id"]);
	}

	if (isset($_POST["save_list"])) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= "<li><b>" . db_fetch_cell("SELECT name FROM plugin_notification_lists WHERE id=" . $matches[1]) . "</b></li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $actions{$_POST["drp_action"]} . " $list_name</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='notify_lists.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* delete */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the following Notification Lists(s) will be Deleted.  Any Hosts(s) or Threshold(s) associated with the List(s) will be reverted to the default.</p>
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
	}elseif (isset($_POST["save_templates"])) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= "<li><b>" . db_fetch_cell("SELECT name FROM thold_template WHERE id=" . $matches[1]) . "</b></li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $assoc_actions{$_POST["drp_action"]} . " Threshold Template(s)</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='notify_lists.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* associate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Notification List '<b>" . $list_name . "</b>' will be associated with the Threshold Template(s) below.</p>
							<ul>$list</ul>
							<p><b>Warning Membership:</b><br>"; form_dropdown("notification_warning_action", array(0 => "No Change", 1 => "Notification List Only", 2 => "Notification List, Retain Other Settings"), "", "", 1, "", ""); print "</p>
							<p><b>Alert Membership:</b><br>"; form_dropdown("notification_alert_action", array(0 => "No Change", 1 => "Notification List Only", 2 => "Notification List, Retain Other Settings"), "", "", 1, "", ""); print "</p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Associate Notification List(s)'>";
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Notification List '<b>" . $list_name . "</b>' will be disassociated from the Thresholds Template(s) below.</p>
							<ul>$list</ul>
							<p><b>Warning Membership:</b><br>"; form_dropdown("notification_warning_action", array(0 => "No Change", 1 => "Remove List"), "", "", 1, "", ""); print "</p>
							<p><b>Alert Membership:</b><br>"; form_dropdown("notification_alert_action", array(0 => "No Change", 1 => "Remove List"), "", "", 1, "", ""); print "</p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disassociate Notification List(s)'>";
			}
		} else {
			print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Threshold Template.</span></td></tr>\n";
			$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
		}

		print "	<tr>
				<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var_request('id') . "'>
				<input type='hidden' name='save_templates' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($array) ? serialize($array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				$save_html
			</td>
		</tr>\n";

		html_end_box();

		include_once("./include/bottom_footer.php");
	}elseif (isset($_POST["save_tholds"])) {
		/* loop through each of the notification lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$list .= "<li><b>" . db_fetch_cell("SELECT name FROM thold_data WHERE id=" . $matches[1]) . "</b></li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $assoc_actions{$_POST["drp_action"]} . " Threshold(s)</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='notify_lists.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* associate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Notification List '<b>" . $list_name . "</b>' will be associated with the Threshold(s) below.</p>
							<ul>$list</ul>
							<p><b>Warning Membership:</b><br>"; form_dropdown("notification_warning_action", array(0 => "No Change", 1 => "Notification List Only", 2 => "Notification List, Retain Other Settings"), "", "", 1, "", ""); print "</p>
							<p><b>Alert Membership:</b><br>"; form_dropdown("notification_alert_action", array(0 => "No Change", 1 => "Notification List Only", 2 => "Notification List, Retain Other Settings"), "", "", 1, "", ""); print "</p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Associate Notification List(s)'>";
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Notification List '<b>" . $list_name . "</b>' will be disassociated from the Thresholds(s) below.</p>
							<ul>$list</ul>
							<p><b>Warning Membership:</b><br>"; form_dropdown("notification_warning_action", array(0 => "No Change", 1 => "Remove List"), "", "", 1, "", ""); print "</p>
							<p><b>Alert Membership:</b><br>"; form_dropdown("notification_alert_action", array(0 => "No Change", 1 => "Remove List"), "", "", 1, "", ""); print "</p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disassociate Notification List(s)'>";
			}
		} else {
			print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Threshold.</span></td></tr>\n";
			$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
		}

		print "	<tr>
				<td align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='id' value='" . get_request_var_request('id') . "'>
				<input type='hidden' name='save_tholds' value='1'>
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

				$list .= "<li><b>" . db_fetch_cell("SELECT description FROM host WHERE id=" . $matches[1]) . "</b></li>";
				$array[] = $matches[1];
			}
		}

		include_once("./include/top_header.php");

		html_start_box("<strong>" . $assoc_actions{$_POST["drp_action"]} . " Host(s)</strong>", "60%", $colors["header_panel"], "3", "center", "");

		print "<form action='notify_lists.php' method='post'>\n";

		if (sizeof($array)) {
			if ($_POST["drp_action"] == "1") { /* associate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Notification List '<b>" . $list_name . "</b>' will be associated with the Host(s) below.</p>
							<ul>$list</ul>
							<p><b>Resulting Membership:<br>"; form_dropdown("notification_action", array(2 => "Notification List Only", 3 => "Notification and Global Lists"), "", "", 2, "", ""); print "</p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Associate Notification List(s)'>";
			}elseif ($_POST["drp_action"] == "2") { /* disassociate */
				print "	<tr>
						<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
							<p>When you click \"Continue\", the Notification List '<b>" . $list_name . "</b>' will be disassociated from the Host(s) below.</p>
							<ul>$list</ul>
							<p><b>Resulting Membership:</b><br>"; form_dropdown("notification_action", array(1 => "Global List", 0 => "Disabled"), "", "", 1, "", ""); print "</p>
						</td>
					</tr>\n";
				$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disassociate Notification List(s)'>";
			}
		} else {
			print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Host.</span></td></tr>\n";
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

function get_notification_header_label() {
	if (!empty($_REQUEST["id"])) {
		$_GET["id"] = $_REQUEST["id"];
		$list = db_fetch_row("SELECT * FROM plugin_notification_lists WHERE id=" . $_REQUEST["id"]);
		$header_label = "[edit: " . $list["name"] . "]";
	} else {
		$header_label = "[new]";
	}

	return $header_label;
}

function edit() {
	global $colors, $tabs_thold, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	/* ==================================================== */

	/* set the default tab */
	load_current_session_value("tab", "sess_thold_notify_tab", "general");
	$current_tab = $_REQUEST["tab"];

	if (sizeof($tabs_thold) && isset($_REQUEST['id'])) {
		/* draw the tabs */
		print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

		foreach (array_keys($tabs_thold) as $tab_short_name) {
			print "<td style='padding:3px 10px 2px 5px;background-color:" . (($tab_short_name == $current_tab) ? "silver;" : "#DFDFDF;") .
				"white-space:nowrap;'" .
				" width='1%' " .
				" align='center' class='tab'>
				<span class='textHeader'><a href='" . htmlspecialchars($config['url_path'] .
				"plugins/thold/notify_lists.php?action=edit&id=" . get_request_var_request('id') .
				"&tab=" . $tab_short_name) .
				"'>$tabs_thold[$tab_short_name]</a></span>
				</td>\n
				<td width='1'></td>\n";
		}

		print "<td></td>\n</tr></table>\n";
	}

	$header_label = get_notification_header_label();

	if (isset($_REQUEST['id'])) {
		$list = db_fetch_row('SELECT * FROM plugin_notification_lists WHERE id=' . $_REQUEST['id']);
	} else {
		$list = array();
		$current_tab = 'general';
	}

	if ($current_tab == "general") {
		html_start_box("<strong>General Settings</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

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
	}elseif ($current_tab == "hosts") {
		hosts($header_label);
	}elseif ($current_tab == "tholds") {
		tholds($header_label);
	}else{
		templates($header_label);
	}
}

function hosts($header_label) {
	global $colors, $assoc_actions, $item_rows;

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
		unset($_REQUEST["filter"]);
		unset($_REQUEST["associated"]);
		unset($_REQUEST["host_template_id"]);
		unset($_REQUEST["rows"]);
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
		strURL = '?action=edit&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&rows=' + objForm.rows.value;
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

	html_start_box("<strong>Associated Devices</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

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
						<input type='checkbox' name='associated' id='associated' onChange='applyViewDeviceFilterChange(document.form_devices)' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'>Associated</label>
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
	print "<form name='chk' method='post' action='notify_lists.php?action=edit&tab=hosts&id=" . get_request_var_request("id") . "'>\n";

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
	if ($total_rows > 0) {
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
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='11'>
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

	form_hidden_box("action", "edit", "");
	form_hidden_box("id", get_request_var_request("id"), "");
	form_hidden_box("save_associate", "1", "");

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($assoc_actions);

	print "</form>\n";
}

function tholds($header_label) {
	global $colors, $thold_bgcolors, $item_rows, $config;

	$thold_actions = array(1 => 'Associate', 2 => 'Disassociate');

	thold_request_validation();

	$statefilter='';
	if (isset($_REQUEST['state'])) {
		if ($_REQUEST['state'] == '-1') {
			$statefilter = '';
		} else {
			if ($_REQUEST['state'] == '0') { $statefilter = "thold_data.thold_enabled='off'"; }
			if ($_REQUEST['state'] == '2') { $statefilter = "thold_data.thold_enabled='on'"; }
			if ($_REQUEST['state'] == '1') { $statefilter = 'thold_data.thold_alert!=0 OR thold_data.bl_alert>0'; }
			if ($_REQUEST['state'] == '3') { $statefilter = '(thold_data.thold_alert!=0 AND thold_data.thold_fail_count >= thold_data.thold_fail_trigger) OR (thold_data.bl_alert>0 AND thold_data.bl_fail_count >= thold_data.bl_fail_trigger)'; }
		}
	}

	$alert_num_rows = read_config_option('alert_num_rows');
	if ($alert_num_rows < 1 || $alert_num_rows > 5000) {
		db_execute("REPLACE INTO settings VALUES ('alert_num_rows', 30)");
		/* pull it again so it updates the cache */
		$alert_num_rows = read_config_option('alert_num_rows', true);
	}

	$sql_where = "WHERE template_enabled='off'";

	$sort = $_REQUEST['sort_column'];
	$limit = ' LIMIT ' . ($alert_num_rows*($_REQUEST['page']-1)) . ",$alert_num_rows";

	if (!empty($_REQUEST['template']) && $_REQUEST['template'] != 'ALL') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "thold_data.data_template = " . $_REQUEST['template'];
	}

	if (strlen($_REQUEST['filter'])) {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "thold_data.name LIKE '%" . $_REQUEST['filter'] . "%'";
	}

	if ($statefilter != '') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "$statefilter";
	}

	if ($_REQUEST["associated"] == 'true') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "(notify_warning=" . get_request_var_request('id') . " OR notify_alert=" . get_request_var_request('id') . ")";
	}

	$current_user = db_fetch_row('SELECT * FROM user_auth WHERE id=' . $_SESSION['sess_user_id']);
	$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

	$sql = "SELECT * FROM thold_data
		LEFT JOIN user_auth_perms
		ON ((thold_data.graph_id=user_auth_perms.item_id
		AND user_auth_perms.type=1
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		OR (thold_data.host_id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		OR (thold_data.graph_template=user_auth_perms.item_id
		AND user_auth_perms.type=4
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . "))
		$sql_where
		ORDER BY $sort " . $_REQUEST['sort_direction'] .
		$limit;

	$result = db_fetch_assoc($sql);

	$data_templates = db_fetch_assoc("SELECT DISTINCT data_template.id, data_template.name
		FROM data_template
		INNER JOIN thold_data ON (thold_data.data_template = data_template.id)
		ORDER BY data_template.name");

	?>
	<script type="text/javascript">
	<!--
	function applyTHoldFilterChange(objForm) {
		strURL = '?action=edit&tab=tholds&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&associated=' + objForm.associated.checked;
		strURL = strURL + '&state=' + objForm.state.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&template=' + objForm.template.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	function clearTHoldFilterChange(objForm) {
		strURL = '?action=edit&tab=tholds&id=<?php print get_request_var_request('id');?>&clearf=true'
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box('<strong>Associated Thresholds</strong> ' . htmlspecialchars($header_label) , '100%', $colors['header'], '3', 'center', '');
	?>
	<tr bgcolor='#<?php print $colors["panel"];?>' class='noprint'>
		<td class='noprint'>
			<form name="listthold" method="get" action="notify_lists.php">
			<table cellpadding='0' cellspacing='0'>
				<tr class='noprint'>
					<td width='1'>
						&nbsp;Template:&nbsp;
					</td>
					<td width='1'>
						<select name='template' onChange='applyTHoldFilterChange(document.listthold)'>
							<option value='ALL'>Any</option>
							<?php
							foreach ($data_templates as $row) {
								echo "<option value='" . $row['id'] . "'" . (isset($_REQUEST['template']) && $row['id'] == $_REQUEST['template'] ? ' selected' : '') . '>' . $row['name'] . '</option>';
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="20">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>" onChange="applyTHoldFilterChange(document.listthold)">
					</td>
					<td width='1'>
						&nbsp;State:&nbsp;
					</td>
					<td width='1'>
						<select name='state' onChange='applyTHoldFilterChange(document.listthold)'>
							<option value='-1'<?php if ($_REQUEST["state"] == "-1") {?> selected<?php }?>>All</option>
							<option value='1'<?php if ($_REQUEST["state"] == "1") {?> selected<?php }?>>Breached</option>
							<option value='3'<?php if ($_REQUEST["state"] == "3") {?> selected<?php }?>>Triggered</option>
							<option value='2'<?php if ($_REQUEST["state"] == "2") {?> selected<?php }?>>Enabled</option>
							<option value='0'<?php if ($_REQUEST["state"] == "0") {?> selected<?php }?>>Disabled</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyTHoldFilterChange(document.listthold)">
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
						<input type='checkbox' name='associated' id='associated' onChange='applyTHoldFilterChange(document.listthold)' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'>Associated</label>
					</td>
					<td nowrap>
						&nbsp;<input type="button" value="Go" onClick='applyTHoldFilterChange(document.listthold)' title="Set/Refresh Filters">
					</td>
					<td nowrap>
						<input type="button" name="clearf" value="Clear" onClick='clearTHoldFilterChange(document.listthold)' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='tholds'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$total_rows = count(db_fetch_assoc("SELECT thold_data.id
		FROM thold_data
		LEFT JOIN user_auth_perms
		ON ((thold_data.graph_id=user_auth_perms.item_id
		AND user_auth_perms.type=1
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		OR (thold_data.host_id=user_auth_perms.item_id
		AND user_auth_perms.type=3
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . ")
		OR (thold_data.graph_template=user_auth_perms.item_id
		AND user_auth_perms.type=4
		AND user_auth_perms.user_id=" . $_SESSION['sess_user_id'] . "))
		$sql_where"));

	$url_page_select = get_page_list($_REQUEST['page'], MAX_DISPLAY_PAGES, $alert_num_rows, $total_rows, 'notify_lists.php?action=edit&tab=tholds');

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='notify_lists.php'>\n";

	html_start_box('', '100%', $colors['header'], '4', 'center', '');

	if ($total_rows) {
		$nav = "<tr bgcolor='#" . $colors['header'] . "'>
				<td colspan='8'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("notify_lists.php?page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($alert_num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $alert_num_rows) || ($total_rows < ($alert_num_rows*$_REQUEST["page"]))) ? $total_rows : ($alert_num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $alert_num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("notify_lists.php?page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $alert_num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors['header'] . "'>
				<td colspan='8'>
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
		'name' => array('Name', 'ASC'),
		'id' => array('ID', 'ASC'),
		'nosort1' => array('Warning Lists', 'ASC'),
		'nosort2' => array('Alert Lists', 'ASC'),
		'thold_type' => array('Type', 'ASC'),
		'thold_alert' => array('Triggered', 'ASC'),
		'thold_enabled' => array('Enabled', 'ASC'));

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	$c=0;
	$i=0;
	$types = array('High/Low', 'Baseline Deviation', 'Time Based');
	if (count($result)) {
		foreach ($result as $row) {
			$c++;
			$alertstat='no';
			$bgcolor='green';
			if ($row['thold_type'] != 1) {
				if ($row['thold_alert'] != 0) {
					$alertstat='yes';
				}
			} else {
				if ($row['bl_alert'] == 1) {
					$alertstat='baseline-LOW';
				} elseif ($row['bl_alert'] == 2)  {
					$alertstat='baseline-HIGH';
				}
			};

			/* show alert stats first */
			$alert_stat = '';
			$list = db_fetch_cell("SELECT count(*) FROM plugin_thold_threshold_contact WHERE thold_id=" . $row["id"]);
			if ($list > 0) {
				$alert_stat = "<span style='font-weight:bold;color:green;'>Select Users</span>";
			}

			if (strlen($row["notify_extra"])) {
				$alert_stat .= (strlen($alert_stat) ? ", ":"") . "<span style='font-weight:bold;color:purple;'>Specific Emails</span>";
			}

			if (!empty($row["notify_alert"])) {
				if (get_request_var_request('id') == $row["notify_alert"]) {
					$alert_stat .= (strlen($alert_stat) ? ", ":"") . "<span style='font-weight:bold;color:green;'>Current List</span>";
				}else{
					$alert_list = db_fetch_cell("SELECT name FROM plugin_notification_lists WHERE id=" . $row["notify_alert"]);
					$alert_stat .= (strlen($alert_stat) ? ", ":"") . "<span style='font-weight:bold;color:red;'>" . $alert_list . "</span>";
				}
			}

			if (!strlen($alert_stat)) {
				$alert_stat = "<span style='font-weight:bold;color:blue;'>Log Only</span>";
			}

			/* show warning stats first */
			$warn_stat = '';
			if (strlen($row["notify_warning_extra"])) {
				$warn_stat .= (strlen($warn_stat) ? ", ":"") . "<span style='font-weight:bold;color:purple;'>Specific Emails</span>";
			}

			if (!empty($row["notify_warning"])) {
				if (get_request_var_request('id') == $row["notify_warning"]) {
					$warn_stat .= (strlen($warn_stat) ? ", ":"") . "<span style='font-weight:bold;color:green;'>Current List</span>";
				}else{
					$warn_list = db_fetch_cell("SELECT name FROM plugin_notification_lists WHERE id=" . $row["notify_warning"]);
					$warn_stat .= (strlen($warn_stat) ? ", ":"") . "<span style='font-weight:bold;color:red;'>" . $warn_list . "</span>";
				}
			}

			if ((!strlen($warn_stat)) &&
				(($row["thold_type"] == 0 && $row["thold_warning_hi"] == '' && $row["thold_warning_low"] == '') ||
				($row["thold_type"] == 2 && $row["time_warning_hi"] == '' && $row["time_warning_low"] == ''))) {
				$warn_stat  = "<span style='font-weight:bold;color:red;'>None</span>";
			}elseif (!strlen($warn_stat)) {
				$warn_stat  = "<span style='font-weight:bold;color:blue;'>Log Only</span>";
			}

			if ($row['name'] != '') {
				$name = $row['name'];
			}else{
				$name = $row['name_cache'] . " [" . $row['data_source_name'] . "]";
			}

			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $row["id"]); $i++;
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($name)) : htmlspecialchars($name)), $row['id']);
			form_selectable_cell($row['id'], $row["id"]);
			form_selectable_cell($warn_stat, $row["id"]);
			form_selectable_cell($alert_stat, $row["id"]);
			form_selectable_cell($types[$row['thold_type']], $row["id"]);
			form_selectable_cell($alertstat, $row["id"]);
			form_selectable_cell((($row['thold_enabled'] == 'off') ? "Disabled": "Enabled"), $row["id"]);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}

		print $nav;
	} else {
		form_alternate_row_color($colors['alternate'],$colors['light'],0);
		print '<td colspan="8"><i>No Thresholds</i></td></tr>';
	}

	html_end_box(false);

	form_hidden_box("action", "edit", "");
	form_hidden_box("tab", "tholds", "");
	form_hidden_box("id", get_request_var_request("id"), "");
	form_hidden_box("save_tholds", "1", "");

	draw_actions_dropdown($thold_actions);

	print "</form>\n";
}

function templates($header_label) {
	global $colors, $thold_bgcolors, $config, $item_rows;

	$thold_actions = array(1 => 'Associate', 2 => 'Disassociate');

	thold_template_request_validation();

	$alert_num_rows = read_config_option('alert_num_rows');
	if ($alert_num_rows < 1 || $alert_num_rows > 5000) {
		db_execute("REPLACE INTO settings VALUES ('alert_num_rows', 30)");
		/* pull it again so it updates the cache */
		$alert_num_rows = read_config_option('alert_num_rows', true);
	}

	$sql_where = '';

	$sort = $_REQUEST['sort_column'];
	$limit = ' LIMIT ' . ($alert_num_rows*($_REQUEST['page']-1)) . ",$alert_num_rows";

	if ($_REQUEST["associated"] == 'true') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "(notify_warning=" . get_request_var_request('id') . " OR notify_alert=" . get_request_var_request('id') . ")";
	}

	if (strlen($_REQUEST['filter'])) {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . "thold_template.name LIKE '%" . $_REQUEST['filter'] . "%'";
	}

	$sql = "SELECT * FROM thold_template
		$sql_where
		ORDER BY $sort " . $_REQUEST['sort_direction'] .
		$limit;

	$result = db_fetch_assoc($sql);

	?>
	<script type="text/javascript">
	<!--
	function applyTHoldFilterChange(objForm) {
		strURL = '?action=edit&tab=templates&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&associated=' + objForm.associated.checked;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	function clearTHoldFilterChange(objForm) {
		strURL = '?action=edit&tab=templates&id=<?php print get_request_var_request('id');?>&clearf=true'
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box('<strong>Associated Templates</strong> ' . htmlspecialchars($header_label), '100%', $colors['header'], '3', 'center', '');
	?>
	<tr bgcolor='#<?php print $colors["panel"];?>' class='noprint'>
		<td class='noprint'>
			<form name="listthold" method="get" action="notify_lists.php">
			<table cellpadding='0' cellspacing='0'>
				<tr class='noprint'>
					<td nowrap style='white-space: nowrap;' width="20">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>" onChange="applyTHoldFilterChange(document.listthold)">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyTHoldFilterChange(document.listthold)">
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
						<input type='checkbox' name='associated' id='associated' onChange='applyTHoldFilterChange(document.listthold)' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'>Associated</label>
					</td>
					<td nowrap>
						&nbsp;<input type="button" value="Go" onClick='applyTHoldFilterChange(document.listthold)' title="Set/Refresh Filters">
					</td>
					<td nowrap>
						<input type="button" name="clearf" value="Clear" onClick='clearTHoldFilterChange(document.listthold)' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='templates'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$total_rows = db_fetch_cell("SELECT count(*)
		FROM thold_template
		$sql_where");

	$url_page_select = get_page_list($_REQUEST['page'], MAX_DISPLAY_PAGES, $alert_num_rows, $total_rows, 'notify_lists.php?action=edit&tab=templates');

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='notify_lists.php'>\n";

	html_start_box('', '100%', $colors['header'], '4', 'center', '');

	if ($total_rows) {
		$nav = "<tr bgcolor='#" . $colors['header'] . "'>
				<td colspan='8'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("notify_lists.php?page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($alert_num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $alert_num_rows) || ($total_rows < ($alert_num_rows*$_REQUEST["page"]))) ? $total_rows : ($alert_num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $alert_num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("notify_lists.php?page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $alert_num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors['header'] . "'>
				<td colspan='8'>
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
		'name' => array('Name', 'ASC'),
		'id' => array('ID', 'ASC'),
		'nosort1' => array('Warning Lists', 'ASC'),
		'nosort2' => array('Alert Lists', 'ASC'),
		'thold_type' => array('Type', 'ASC'));

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	$c=0;
	$i=0;
	$types = array('High/Low', 'Baseline Deviation', 'Time Based');
	if (count($result)) {
		foreach ($result as $row) {
			$c++;

			/* show alert stats first */
			$alert_stat = '';
			$list = db_fetch_cell("SELECT count(*) FROM plugin_thold_template_contact WHERE template_id=" . $row["id"]);
			if ($list > 0) {
				$alert_stat = "<span style='font-weight:bold;color:green;'>Select Users</span>";
			}

			if (strlen($row["notify_extra"])) {
				$alert_stat .= (strlen($alert_stat) ? ", ":"") . "<span style='font-weight:bold;color:purple;'>Specific Emails</span>";
			}

			if (!empty($row["notify_alert"])) {
				if (get_request_var_request('id') == $row["notify_alert"]) {
					$alert_stat .= (strlen($alert_stat) ? ", ":"") . "<span style='font-weight:bold;color:green;'>Current List</span>";
				}else{
					$alert_list = db_fetch_cell("SELECT name FROM plugin_notification_lists WHERE id=" . $row["notify_alert"]);
					$alert_stat .= (strlen($alert_stat) ? ", ":"") . "<span style='font-weight:bold;color:red;'>" . $alert_list . "</span>";
				}
			}

			if (!strlen($alert_stat)) {
				$alert_stat = "<span style='font-weight:bold;color:blue;'>Log Only</span>";
			}

			/* show warning stats first */
			$warn_stat = '';
			if (strlen($row["notify_warning_extra"])) {
				$warn_stat .= (strlen($warn_stat) ? ", ":"") . "<span style='font-weight:bold;color:purple;'>Specific Emails</span>";
			}

			if (!empty($row["notify_warning"])) {
				if (get_request_var_request('id') == $row["notify_warning"]) {
					$warn_stat .= (strlen($warn_stat) ? ", ":"") . "<span style='font-weight:bold;color:green;'>Current List</span>";
				}else{
					$warn_list = db_fetch_cell("SELECT name FROM plugin_notification_lists WHERE id=" . $row["notify_warning"]);
					$warn_stat .= (strlen($warn_stat) ? ", ":"") . "<span style='font-weight:bold;color:red;'>" . $warn_list . "</span>";
				}
			}

			if ((!strlen($warn_stat)) &&
				(($row["thold_type"] == 0 && $row["thold_warning_hi"] == '' && $row["thold_warning_low"] == '') ||
				($row["thold_type"] == 2 && $row["thold_time_warning_hi"] == '' && $row["thold_time_warning_low"] == ''))) {
				$warn_stat  = "<span style='font-weight:bold;color:red;'>None</span>";
			}elseif (!strlen($warn_stat)) {
				$warn_stat  = "<span style='font-weight:bold;color:blue;'>Log Only</span>";
			}

			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $row["id"]); $i++;
			form_selectable_cell((strlen(get_request_var_request("filter")) ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($row['name'])) : htmlspecialchars($row['name'])), $row['id']);
			form_selectable_cell($row['id'], $row["id"]);
			form_selectable_cell($warn_stat, $row["id"]);
			form_selectable_cell($alert_stat, $row["id"]);
			form_selectable_cell($types[$row['thold_type']], $row["id"]);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}

		print $nav;
	} else {
		form_alternate_row_color($colors['alternate'],$colors['light'],0);
		print '<td colspan="8"><i>No Templates</i></td></tr>';
	}

	html_end_box(false);

	form_hidden_box("action", "edit", "");
	form_hidden_box("tab", "templates", "");
	form_hidden_box("id", get_request_var_request("id"), "");
	form_hidden_box("save_templates", "1", "");

	draw_actions_dropdown($thold_actions);

	print "</form>\n";
}

function thold_template_request_validation() {
	global $title, $colors, $rows_selector, $config, $reset_multi;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* clean up associated */
	if (isset($_REQUEST['associated'])) {
		$_REQUEST['associated'] = sanitize_search_string(get_request_var('associated'));
	}

	/* clean up filter */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_thold_nltt_rows');
		kill_session_var('sess_thold_nltt_page');
		kill_session_var('sess_thold_nltt_sort_column');
		kill_session_var('sess_thold_nltt_sort_direction');
		kill_session_var('sess_thold_nltt_associated');
		kill_session_var('sess_thold_nltt_filter');

		$_REQUEST['page'] = 1;
		unset($_REQUEST['rows']);
		unset($_REQUEST['page']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['filter']);
		$reset_multi = true;
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += thold_request_check_changed('rows', 'sess_thold_nltt_rows');
		$changed += thold_request_check_changed('sort_column', 'sess_thold_nltt_sort_column');
		$changed += thold_request_check_changed('sort_direction', 'sess_thold_nltt_sort_direction');
		$changed += thold_request_check_changed('associated', 'sess_thold_nltt_associated');
		$changed += thold_request_check_changed('filter', 'sess_thold_nltt_filter');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}

		$reset_multi = false;
	}

	/* remember search fields in session vars */
	load_current_session_value('rows', 'sess_thold_nltt_rows', read_config_option('num_rows_thold'));
	load_current_session_value('page', 'sess_thold_nltt_current_page', '1');
	load_current_session_value('associated', 'sess_thold_nltt_associated', 'true');
	load_current_session_value('filter', 'sess_thold_nltt_filter', '');
	load_current_session_value('sort_column', 'sess_thold_nltt_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_thold_nltt_sort_direction', 'ASC');
}

function thold_request_validation() {
	global $title, $colors, $rows_selector, $config, $reset_multi;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* clean up associated */
	if (isset($_REQUEST['associated'])) {
		$_REQUEST['associated'] = sanitize_search_string(get_request_var('associated'));
	}

	/* clean up filter */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_thold_nlt_rows');
		kill_session_var('sess_thold_nlt_page');
		kill_session_var('sess_thold_nlt_sort_column');
		kill_session_var('sess_thold_nlt_sort_direction');
		kill_session_var('sess_thold_nlt_associated');
		kill_session_var('sess_thold_nlt_filter');
		kill_session_var('sess_thold_nlt_state');
		kill_session_var('sess_thold_nlt_template');

		$_REQUEST['page'] = 1;
		unset($_REQUEST['rows']);
		unset($_REQUEST['page']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['template']);
		unset($_REQUEST['state']);
		$reset_multi = true;
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += thold_request_check_changed('rows', 'sess_thold_nlt_rows');
		$changed += thold_request_check_changed('sort_column', 'sess_thold_nlt_sort_column');
		$changed += thold_request_check_changed('sort_direction', 'sess_thold_nlt_sort_direction');
		$changed += thold_request_check_changed('state', 'sess_thold_nlt_state');
		$changed += thold_request_check_changed('associated', 'sess_thold_nlt_associated');
		$changed += thold_request_check_changed('filter', 'sess_thold_nlt_filter');
		$changed += thold_request_check_changed('template', 'sess_thold_nlt_template');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}

		$reset_multi = false;
	}

	/* remember search fields in session vars */
	load_current_session_value('rows', 'sess_thold_nlt_rows', read_config_option('num_rows_thold'));
	load_current_session_value('page', 'sess_thold_nlt_current_page', '1');
	load_current_session_value('associated', 'sess_thold_nlt_associated', 'true');
	load_current_session_value('filter', 'sess_thold_nlt_filter', '');
	load_current_session_value('sort_column', 'sess_thold_nlt_sort_column', 'thold_alert');
	load_current_session_value('sort_direction', 'sess_thold_nlt_sort_direction', 'DESC');
	load_current_session_value('state', 'sess_thold_nlt_state', read_config_option('thold_filter_default'));
	load_current_session_value('template', 'sess_thold_nlt_template', '');
}

function tholds_old() {
	global $colors, $assoc_actions, $item_rows;

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
		unset($_REQUEST["filter"]);
		unset($_REQUEST["associated"]);
		unset($_REQUEST["host_template_id"]);
		unset($_REQUEST["rows"]);
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
						<input type='checkbox' name='associated' id='associated' onChange='applyViewDeviceFilterChange(document.form_devices)' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label for='associated'>Associated</label>
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
			<input type='hidden' name='tab' value='hosts'>
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
	print "<form name='chk' method='post' action='notify_lists.php?action=edit&tab=hosts&id=" . get_request_var_request("id") . "'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM host
		$sql_where");

	$host_graphs       = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id"), "host_id", "graphs");
	$host_data_sources = array_rekey(db_fetch_assoc("SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id"), "host_id", "data_sources");

	$sql_query = "SELECT *
		FROM host $sql_where
		LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows");

	$hosts = db_fetch_assoc($sql_query);

	/* generate page list */
	if ($total_rows > 0) {
		$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rows"), $total_rows, "notify_lists.php?action=edit&tab=hosts&id=" . get_request_var_request('id'));

		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='8'>
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
	} else {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='8'>
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
	draw_actions_dropdown($assoc_actions);

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
	print "<form name='chk' method='post' action='notify_lists.php?action=edit&tab=tholds&id=" . get_request_var_request("id") . "'>\n";

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
			<td colspan='5'>
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
			<td colspan='5'>
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

	form_hidden_box('save_list', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	print "</form>\n";
}

?>

