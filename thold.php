<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2019 The Cacti Group                                 |
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
include_once($config['base_path'] . '/lib/api_graph.php');
include_once($config['base_path'] . '/lib/api_tree.php');
include_once($config['base_path'] . '/lib/api_data_source.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/html_tree.php');
include_once($config['base_path'] . '/lib/html_form_template.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/lib/template.php');
include_once($config['base_path'] . '/lib/utility.php');
include_once($config['base_path'] . '/plugins/thold/thold_webapi.php');
include_once($config['base_path'] . '/plugins/thold/includes/arrays.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

set_default_action();

if (isset($_SERVER['HTTP_REFERER'])) {
	if (preg_match('/(data_sources.php|graph_view.php|graph.php)/', $_SERVER['HTTP_REFERER'])) {
		$_SESSION['data_return'] = $_SERVER['HTTP_REFERER'];
	}
}

if (isset_request_var('drp_action')) {
	do_actions();
}

if (isset_request_var('id')) {
	get_filter_request_var('id');
}

switch(get_request_var('action')) {
	case 'ajax_hosts':
		if (isset($_SESSION['type_type_id']) && $_SESSION['thold_type_id'] == 'template') {
			if (isset($_SESSION['sess_thold_hql'])) {
				get_allowed_ajax_hosts(true, false, $_SESSION['sess_thold_hql']);
			} else {
				get_allowed_ajax_hosts(true, false, thold_template_avail_devices($_SESSION['thold_template_id']));
			}
		} else {
			get_allowed_ajax_hosts(true, false, '');
		}

		break;
	case 'ajax_hosts_noany':
		if (isset($_SESSION['type_type_id']) && $_SESSION['thold_type_id'] == 'template') {
			if (isset($_SESSION['sess_thold_hql'])) {
				get_allowed_ajax_hosts(false, false, $_SESSION['sess_thold_hql']);
			} else {
				get_allowed_ajax_hosts(false, false, thold_template_avail_devices($_SESSION['thold_template_id']));
			}
		} else {
			get_allowed_ajax_hosts(false, false, '');
		}

		break;
	case 'add':
		thold_add();

		break;
	case 'save':
		$id = save_thold();
		header('Location: thold.php?action=edit&id=' . $id . '&header=false');

		break;
	case 'autocreate':
		$c = autocreate(get_filter_request_var('host_id'));
		if ($c == 0) {
			thold_raise_message(__('Either No Templates or Threshold(s) Already Exists - No Thresholds were created.', 'thold'), MESSAGE_LEVEL_INFO);
		}

		if (isset($_SESSION['data_return'])) {
			$return_to = $_SESSION['data_return'];
			unset($_SESSION['data_return']);
			kill_session_var('data_return');

			header('Location: ' . $return_to);
		} else {
			header('Location: ' . $config['url_path'] . 'graphs_new.php?header=false&host_id=' . get_request_var('host_id'));
		}

		break;
	case 'disable':
		thold_threshold_disable(get_filter_request_var('id'));

		if (isset($_SERVER['HTTP_REFERER'])) {
			$return_to = $_SERVER['HTTP_REFERER'];
		} else {
			$return_to = 'thold.php';
		}

		header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');

		exit;
	case 'enable':
		thold_threshold_enable(get_filter_request_var('id'));

		if (isset($_SERVER['HTTP_REFERER'])) {
			$return_to = $_SERVER['HTTP_REFERER'];
		} else {
			$return_to = 'thold.php';
		}

		header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');

		exit;
	case 'edit':
		thold_update_contacts();
		top_header();
		thold_edit();
		bottom_footer();

		break;
	default:
		delete_old_thresholds();
		list_tholds();
		break;
}

function thold_add() {
	global $config;

	$host_id              = get_filter_request_var('host_id');
	$local_graph_id       = get_filter_request_var('local_graph_id');
	$data_template_rrd_id = get_filter_request_var('data_template_rrd_id');

	if (isset_request_var('local_graph_id') && !isset_request_var('host_id')) {
		$host_id = db_fetch_cell_prepared('SELECT host_id
			FROM graph_local
			WHERE id = ?',
			array($local_graph_id));
	}

	if (isset_request_var('doaction') && get_nfilter_request_var('doaction') != '') {
		if (get_nfilter_request_var('doaction') == 1) {
			set_request_var('host_id', $host_id);
			set_request_var('local_graph_id', $local_graph_id);
			unset_request_var('usetemplate');
			unset_request_var('doaction');
			unset_request_var('data_template_rrd_id');
		} else {
			$data_template_id = db_fetch_cell_prepared('SELECT dtr.data_template_id
				 FROM data_template_rrd AS dtr
				 LEFT JOIN graph_templates_item AS gti
				 ON gti.task_item_id=dtr.id
				 LEFT JOIN graph_local AS gl
				 ON gl.id=gti.local_graph_id
				 WHERE gl.id = ?',
				array($local_graph_id));

			set_request_var('data_template_id', $data_template_id);
			unset_request_var('doaction');
			unset_request_var('data_template_rrd_id');
			unset_request_var('usetemplate');
		}
	}

	if (isset_request_var('usetemplate') && get_nfilter_request_var('usetemplate') != '') {
		if (isset_request_var('thold_template_id') && get_filter_request_var('thold_template_id') != '') {
			if (get_request_var('thold_template_id') == '0') {
				thold_wizard();
			} else {
				thold_add_graphs_action_execute();
			}
		} else {
			thold_add_graphs_action_prepare();
		}
	} else {
		thold_wizard();
	}
}

function do_actions() {
	global $host_id, $thold_actions;

	$tholds = array();

	/* ================= input validation ================= */
	$drp_action = get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			foreach ($selected_items as $var) {
				$local_graph_id = db_fetch_cell_prepared('SELECT local_graph_id
					FROM thold_data
					WHERE id = ?',
					array($var));

				input_validate_input_number($var);
				$tholds[$var] = $local_graph_id;
			}

			switch ($drp_action) {
				case 1:	// Delete
					foreach ($tholds as $thold_id => $local_graph_id) {
						if (is_thold_allowed_graph($local_graph_id)) {
							plugin_thold_log_changes($thold_id, 'deleted', array('message' => 'Removed from Thold page'));

							db_execute_prepared('DELETE FROM thold_data
								WHERE id = ?',
								array($thold_id));

							db_execute_prepared('DELETE FROM plugin_thold_threshold_contact
								WHERE thold_id = ?',
								array($thold_id));

							db_execute_prepared('DELETE FROM plugin_thold_log
								WHERE threshold_id = ?',
								array($thold_id));
						}
					}
					break;
				case 2:	// Enabled
					foreach ($tholds as $thold_id => $local_graph_id) {
						if (is_thold_allowed_graph($local_graph_id)) {
							plugin_thold_log_changes($thold_id, 'enabled_threshold', array('id' => $thold_id));

							db_execute_prepared('UPDATE thold_data
								SET thold_enabled="on"
								WHERE id = ?',
								array($thold_id));
						}
					}
					break;
				case 3:	// Disabled
					foreach ($tholds as $thold_id => $local_graph_id) {
						if (is_thold_allowed_graph($local_graph_id)) {
							plugin_thold_log_changes($thold_id, 'disabled_threshold', array('id' => $thold_id));

							db_execute_prepared('UPDATE thold_data
								SET thold_enabled="off"
								WHERE id = ?',
								array($thold_id));
						}
					}
					break;
				case 4:	// Reapply Suggested Name
					$message = array();
					foreach ($tholds as $thold_id => $local_graph_id) {
						if (is_thold_allowed_graph($local_graph_id)) {
							$thold = db_fetch_row_prepared('SELECT *
								FROM thold_data
								WHERE id = ?',
								array($thold_id));

							/* check if thold templated */
							if ($thold['template_enabled'] == 'on') {
								$template = db_fetch_row_prepared('SELECT *
									FROM thold_template
									WHERE id = ?',
									array($thold['thold_template_id']));
							} else {
								$template = false;
							}

							$name_cache = thold_expand_string($thold, $thold['name']);

							plugin_thold_log_changes($thold_id, 'reapply_name', array('id' => $thold_id));

							db_execute_prepared('UPDATE thold_data
								SET name_cache = ?
								WHERE id = ?',
								array($name_cache, $thold_id));
						} else {
							$message['security'] = __('You are not authorized to modify one or more of the Thresholds selected', 'thold');
						}
					}

					if (cacti_sizeof($message)) {
						thold_raise_message(implode('<br>', $message), MESSAGE_LEVEL_ERROR);
					}

					break;
				case 5:	// Propagate Template
					foreach ($tholds as $thold_id => $local_graph_id) {
						if (is_thold_allowed_graph($local_graph_id)) {
							$template = db_fetch_row_prepared('SELECT td.thold_template_id AS id,
								td.template_enabled AS enabled
								FROM thold_data AS td
								INNER JOIN thold_template AS tt
								ON tt.id = td.thold_template_id
								WHERE td.id = ?',
								array($thold_id));

							if (isset($template['id']) && $template['id'] != 0 && $template['enabled'] != 'on') {
								thold_template_update_threshold($thold_id, $template['id']);
								plugin_thold_log_changes($thold_id, 'modified', array('id' => $thold_id, 'template_enabled' => 'on'));
							}
						}
					}
					break;
				case 6: // Acknowledgment
					foreach ($tholds as $thold_id => $local_graph_id) {
						if (is_thold_allowed_graph($local_graph_id)) {
							plugin_thold_log_changes($thold_id, 'acknowledge_threshold', array('id' => $thold_id));

							db_execute_prepared('UPDATE thold_data
								SET acknowledgment = ""
								WHERE id = ?',
								array($thold_id));

							ack_logging($thold_id, get_request_var('message'));
						}
					}

					break;
				case 7: // Resume Acknowledgment
					foreach ($tholds as $thold_id => $local_graph_id) {
						if (is_thold_allowed_graph($local_graph_id)) {
							thold_threshold_resume_ack($thold_id);
						}
					}

					break;
				case 8: // Suspend Acknowledgment
					foreach ($tholds as $thold_id => $local_graph_id) {
						if (is_thold_allowed_graph($local_graph_id)) {
							thold_threshold_suspend_ack($thold_id);
						}
					}

					break;
			}

			if (isset($host_id) && $host_id != '') {
				header('Location:thold.php?header=false&host_id=' . $host_id);
			} else {
				header('Location:thold.php?header=false');
			}

			exit;
		}
	}

	$tholds     = array();
	$thold_list = '';

	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_(.*)$/', $var, $matches)) {
			$thold_id = $matches[1];
			input_validate_input_number($thold_id);

			$thold = db_fetch_row_prepared('SELECT name, name_cache, local_data_id, local_graph_id, thold_template_id
				FROM thold_data
				WHERE id = ?',
				array($thold_id));

			$tholds[$thold_id] = html_escape($thold['name_cache']);
			$tholds_list[]     = $thold_id;
		}
	}

	if (cacti_sizeof($tholds)) {
		$thold_list = implode('</li><li>', $tholds);
	}

	top_header();

	form_start('thold.php');

	html_start_box($thold_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	$message = '';
	if (cacti_sizeof($tholds)) {
		switch ($drp_action) {
			case 1: // Delete thresholds
				$message = __('Click \'Delete\' to delete the following Threshold(s).', 'thold');
				$button = __esc('Delete Threshold(s)', 'thold');
				break;
			case 2:
				$message = __('Click \'Continue\' to enable the following Threshold(s).', 'thold');
				$button = __esc('Enable Threshold(s)', 'thold');
				break;
			case 3:
				$message = __('Click \'Continue\' to disable the following Threshold(s).', 'thold');
				$button = __esc('Disable Threshold(s)', 'thold');
				break;
			case 4:
				$message = __('Click \'Continue\' to reapply Suggested Name(s) to the following Threshold(s).', 'thold');
				$button = __esc('Apply Suggestion', 'thold');
				break;
			case 5:
				$message = __('Click \'Continue\' to update the following Threshold(s) with their associate Template\'s details.', 'thold');
				$button = __esc('Reapply Template', 'thold');
				break;
			case 6:
				$message = __('Click \'Continue\' to Acknowledge the following Threshold(s).  Thresholds that do not allows this, or that are not triggered, will be ignored.', 'thold');
				$button = __esc('Acknowledge Threshold(s)', 'thold');
				break;
			case 7:
				$message = __('Click \'Continue\' to Resume Notifications for the following Threshold(s).  Thresholds that do not allows this, or that are not triggered will be ignored.', 'thold');
				$button = __esc('Resume Notifications for Threshold(s)', 'thold');
				break;
			case 8:
				$message = __('Click \'Continue\' to Suspend Notifications for the following Threshold(s).  Thresholds that do not allows this, or that are not triggered will be ignored.', 'thold');
				$button = __esc('Suspend Notification for Threshold(s)', 'thold');
				break;
			default:
				$message = __('Invalid action detected, can not proceed', 'thold');
				$button = '';
				break;
		}

		print "<tr>
			<td colspan='2'>
				<p>$message</p>
				<div class='itemlist'><ul><li>$thold_list</li></ul></div>
			</td>
		</tr>";

		if ($drp_action == 6) {
			print "<tr><td colspan='2'><p><i>Operator Message:</i><br><textarea class='ui-state-default ui-corner-all' style='width:70%;height:50px;' area-multiline='true' rows='2' id='message' name='message'></textarea></p></td></tr>";
		}

		$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>";
		if (!empty($button)) {
			$save_html .= "&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue', 'thold') . "' title='$button'>";
		}
	} else {
		raise_message(40);
		header('Location: tholds.php?header=false');
		exit;
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . serialize($tholds_list) . "'>
			<input type='hidden' name='drp_action' value='" . $drp_action . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();

	exit;
}

/**
 *  This is a generic funtion for this page that makes sure that
 *  we have a good request.  We want to protect against people who
 *  like to create issues with Cacti.
*/
function thold_request_validation() {
	global $title, $rows_selector, $config, $reset_multi;

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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'thold_alert',
			'options' => array('options' => 'sanitize_thold_sort_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'state' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('thold_filter_default')
			),
		'data_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'thold_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_lth');
	/* ================= input validation ================= */
}

function list_tholds() {
	global $thold_actions, $thold_states, $config, $host_id, $timearray, $thold_types, $item_rows;

	thold_request_validation();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$statefilter='';
	if (isset_request_var('state')) {
		if (get_request_var('state') == '-1') {
			$statefilter = '';
		} else {
			if (get_request_var('state') == '0') { $statefilter = "td.thold_enabled='off'"; }
			if (get_request_var('state') == '2') { $statefilter = "td.thold_enabled='on'"; }
			if (get_request_var('state') == '1') { $statefilter = '(td.thold_enabled=\'on\' AND (td.thold_alert!=0 OR td.bl_alert>0))'; }
			if (get_request_var('state') == '3') { $statefilter = '(td.thold_enabled=\'on\' AND ((td.thold_alert!=0 AND td.thold_fail_count >= td.thold_fail_trigger) OR (td.bl_alert>0 AND td.bl_fail_count >= td.bl_fail_trigger)))'; }
			if (get_request_var('state') == '4') { $statefilter = "(td.acknowledgment='on')"; }
		}
	}

	top_header();

	$sql_where = '';

	if (!isempty_request_var('host_id') && get_request_var('host_id') != '-1') {
		$sql_where .= ($sql_where == '' ? '(' : ' AND ') . "td.host_id = " . get_request_var('host_id');
	}

	if (!isempty_request_var('data_template_id') && get_request_var('data_template_id') != '-1') {
		$sql_where .= ($sql_where == '' ? '(' : ' AND ') . "td.data_template_id = " . get_request_var('data_template_id');
	}

	if (!isempty_request_var('thold_template_id')) {
		if (get_request_var('thold_template_id') > 0) {
			$sql_where .= ($sql_where == '' ? '(' : ' AND ') . "td.thold_template_id = " . get_request_var('thold_template_id');
		} elseif (get_request_var('thold_template_id') == '-2') {
			$sql_where .= ($sql_where == '' ? '(' : ' AND ') . "td.template_enabled = ''";
		}
	}

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where == '' ? '(': ' AND ') . ' td.name_cache LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	}

	if ($statefilter != '') {
		$sql_where .= ($sql_where == '' ? '(' : ' AND ') . $statefilter;
	}

	if (get_request_var('site_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('site_id') == '0') {
		$sql_where .= ($sql_where == '' ? '(': ' AND') . ' h.site_id IS NULL';
	} elseif (!isempty_request_var('site_id')) {
		$sql_where .= ($sql_where == '' ? '(':' AND') . ' h.site_id=' . get_request_var('site_id');
	}

	if ($sql_where != '') {
		$sql_where .= ')';
	}

	$sql_order = get_order_string();
	$sql_limit = ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$sql_order = str_replace('ORDER BY ', '', $sql_order);

	$tholds = get_allowed_thresholds($sql_where, $sql_order, $sql_limit, $total_rows);

	$data_templates = db_fetch_assoc('SELECT DISTINCT dt.id, dt.name
		FROM data_template AS dt
		INNER JOIN thold_data AS td
		ON td.data_template_id = dt.id
		ORDER BY dt.name');

	$thold_templates = db_fetch_assoc('SELECT DISTINCT tt.id, tt.name
		FROM thold_template as tt
		ORDER BY tt.name');

	html_start_box(__('Threshold Management', 'thold'), '100%', false, '3', 'center', 'thold.php?action=add');

	?>
	<tr class='even'>
		<td>
		<form id='thold' action='thold.php' method='post'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Site', 'thold');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='0'<?php if (get_request_var('site_id') == '0') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT id, name
								FROM sites
								ORDER BY name');

							if (cacti_sizeof($sites)) {
								foreach ($sites as $sites) {
									print "<option value='" . $sites['id'] . "'"; if (get_request_var('site_id') == $sites['id']) { print ' selected'; } print '>' . html_escape($sites['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						<input type='button' id='refresh' value='<?php print __esc('Go', 'thold');?>' title='<?php print __esc('Apply Filters', 'thold');?>' onClick='applyFilter()'>
					</td>
					<td>
						<input type='button' id='clear' value='<?php print __esc('Clear', 'thold');?>' title='<?php print __esc('Return to Defaults', 'thold');?>' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Template', 'thold');?>
					</td>
					<td>
						<select id='thold_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('thold_template_id') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='-2'<?php if (get_request_var('thold_template_id') == '-2') {?> selected<?php }?>><?php print __('None', 'thold');?></option>
							<?php
							foreach ($thold_templates as $row) {
								print "<option value='" . $row['id'] . "'" . (isset_request_var('thold_template_id') && $row['id'] == get_request_var('thold_template_id') ? ' selected' : '') . '>' . html_escape($row['name']) . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Data Template', 'thold');?>
					</td>
					<td>
						<select id='data_template_id' onChange='applyFilter()'>
							<option value='-1'><?php print __('Any', 'thold');?></option>
							<?php
							foreach ($data_templates as $row) {
								print "<option value='" . $row['id'] . "'" . (isset_request_var('data_template_id') && $row['id'] == get_request_var('data_template_id') ? ' selected' : '') . '>' . html_escape($row['name']) . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status', 'thold');?>
					</td>
					<td>
						<select id='state' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('state') == '-1') {?> selected<?php }?>><?php print __('All', 'thold');?></option>
							<option value='1'<?php if (get_request_var('state') == '1') {?> selected<?php }?>><?php print __('Breached', 'thold');?></option>
							<option value='3'<?php if (get_request_var('state') == '3') {?> selected<?php }?>><?php print __('Triggered', 'thold');?></option>
							<option value='2'<?php if (get_request_var('state') == '2') {?> selected<?php }?>><?php print __('Enabled', 'thold');?></option>
							<option value='0'<?php if (get_request_var('state') == '0') {?> selected<?php }?>><?php print __('Disabled', 'thold');?></option>
							<option value='4'<?php if (get_request_var('state') == '4') {?> selected<?php }?>><?php print __('Ack Required', 'thold');?></option>
						</select>
					</td>
					<td>
						<?php print __('Thresholds', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' name='search' value='search'>
			<input type='hidden' id='page' value='<?php print get_filter_request_var('page');?>'>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'thold.php?header=false&host_id=' + $('#host_id').val();
			strURL += '&state=' + $('#state').val();
			strURL += '&thold_template_id=' + $('#thold_template_id').val();
			strURL += '&data_template_id=' + $('#data_template_id').val();
			strURL += '&site_id=' + $('#site_id').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'thold.php?header=false&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#thold').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});

			$('#filter').change(function() {
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$nav = html_nav_bar('thold.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 14, __('Thresholds', 'thold'), 'page', 'main');

	form_start('thold.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, '3', 'center', '');

	$display_text = array(
		'name_cache' => array(
			'display' => __('Name', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'id' => array(
			'display' => __('ID', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'thold_type' => array(
			'display' => __('Type', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'data_source' => array(
			'display' => __('DSName', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'flastread' => array(
			'display' => __('Current', 'thold'),
			'sort' => 'ASC',
			'align' => 'right',
			'tip' => __('The last measured value for the Data Source', 'thold')
		),
		'nosort1' => array(
			'display' => __('High', 'thold'),
			'sort' => 'ASC',
			'align' => 'right',
			'tip' => __('The High Warning / Alert values.  NOTE: Baseline values are a percent, all other values are display values that may be modified by a cdef.', 'thold')
		),
		'nosort2' => array(
			'display' => __('Low', 'thold'),
			'sort' => 'ASC',
			'align' => 'right',
			'tip' => __('The Low Warning / Alert values.  NOTE: Baseline values are a percent, all other values are display values that may be modified by a cdef.', 'thold')
		),
		'nosort3' => array(
			'display' => __('Trigger', 'thold'),
			'sort' => '',
			'align' => 'right'
		),
		'nosort4' => array(
			'display' => __('Duration', 'thold'),
			'sort' => '',
			'align' => 'right'
		),
		'repeat_alert' => array(
			'display' => __('Repeat', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'thold_alert' => array(
			'display' => __('Triggered', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'instate' => array(
			'display' => __('In State', 'thold'),
			'sort' => 'DESC',
			'align' => 'right',
			'tip' => __('The amount of time that has passed since the Threshold either Breached or was Triggered', 'thold')
		),
		'template_enabled' => array(
			'display' => __('Templated', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'nosort99' => array(
			'display' => __('Ack Required', 'thold'),
			'sort' => '',
			'align' => 'right',
			'tip' => __('Acknowledgment required for this Threshold')
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$c = 0;
	$i = 0;

	if (cacti_sizeof($tholds)) {
		foreach ($tholds as $thold_data) {
			$c++;

			$grapharr = db_fetch_row_prepared('SELECT DISTINCT gti.local_graph_id
				FROM graph_templates_item AS gti
				INNER JOIN data_template_rrd AS dtr
				ON dtr.local_data_id = ?
				AND dtr.id=gti.task_item_id',
				array($thold_data['local_data_id']));

			$local_graph_id = $grapharr['local_graph_id'];

			$alertstat = __('No', 'thold');
			$bgcolor   = 'green';

			$severity = get_thold_severity($thold_data);

			switch($severity) {
				case THOLD_SEVERITY_DISABLED:
					$bgcolor = 'grey';
					break;
				case THOLD_SEVERITY_NORMAL:
					$bgcolor = 'green';
					break;
				case THOLD_SEVERITY_ALERT:
					$bgcolor = 'red';
					break;
				case THOLD_SEVERITY_WARNING:
					$bgcolor = 'warning';
					break;
				case THOLD_SEVERITY_BASELINE:
					$bgcolor = 'orange';
					break;
				case THOLD_SEVERITY_NOTICE:
					$bgcolor = 'yellow';
					break;
				case THOLD_SEVERITY_ACKREQ:
					$bgcolor = 'purple';
					break;
			}

			if ($thold_data['thold_type'] == 0) {
				if ($thold_data['thold_alert'] != 0) {
					$alertstat = __('Yes', 'thold');
				}
			} elseif ($thold_data['thold_type'] == 2) {
				if ($thold_data['thold_alert'] != 0) {
					$alertstat = __('Yes', 'thold');
				}
			} else {
				if ($thold_data['bl_alert'] == 1) {
					$alertstat = __('baseline-LOW', 'thold');
				} elseif ($thold_data['bl_alert'] == 2)  {
					$alertstat = __('baseline-HIGH', 'thold');
				}
			}

			$data_source = db_fetch_cell_prepared('SELECT data_source_name
				FROM data_template_rrd
				WHERE id = ?',
				array($thold_data['data_template_rrd_id']));

			print "<tr class='selectable " . $thold_states[$bgcolor]['class'] . "' id='line" . $thold_data['id'] . "'>";

			if ($thold_data['name_cache'] != '') {
				$name = $thold_data['name_cache'];
			} else {
				$name = thold_expand_string($thold_data, $thold_data['name']);
			}

			// Setup base units
			$baseu = db_fetch_cell_prepared('SELECT base_value
				FROM graph_templates_graph
				WHERE local_graph_id = ?',
				array($thold_data['local_graph_id']));

			if ($thold_data['data_type'] == 2) {
				$suffix = false;
			} else {
				$suffix = true;
			}

			if ($baseu == '') {
				cacti_log('WARNING: Graph Template for local_graph_id ' . $thold_data['local_graph_id'] . ' has been removed!');
				$baseu = 1024;
			}

			// Check is the graph item has a cdef and modify the output
			thold_modify_values_by_cdef($thold_data);

			form_selectable_cell(filter_value($name, get_request_var('filter'), 'thold.php?action=edit&id=' . $thold_data['id']), $thold_data['id'], '', 'left');

			form_selectable_cell($thold_data['id'], $thold_data['id'], '', 'right');
			form_selectable_cell($thold_types[$thold_data['thold_type']], $thold_data['id'], '', 'right');
			form_selectable_cell($data_source, $thold_data['id'], '', 'right');
			form_selectable_cell(thold_format_number($thold_data['lastread'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');

			switch($thold_data['thold_type']) {
				case 0:
					form_selectable_cell(thold_format_number($thold_data['thold_warning_hi'], 2, $baseu, $suffix) . ' / ' . thold_format_number($thold_data['thold_hi'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');
					form_selectable_cell(thold_format_number($thold_data['thold_warning_low'], 2, $baseu, $suffix) . ' / ' . thold_format_number($thold_data['thold_low'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');
					form_selectable_cell('<i>' . plugin_thold_duration_convert($thold_data['local_data_id'], $thold_data['thold_fail_trigger'], 'alert') . '</i>', $thold_data['id'], '', 'right');
					form_selectable_cell(__('N/A', 'thold'),  $thold_data['id'], '', 'right');

					break;
				case 1:
					form_selectable_cell($thold_data['bl_pct_up'] . (strlen($thold_data['bl_pct_up']) ? '%':'-'), $thold_data['id'], '', 'right');
					form_selectable_cell($thold_data['bl_pct_down'] . (strlen($thold_data['bl_pct_down']) ? '%':'-'), $thold_data['id'], '', 'right');
					form_selectable_cell('<i>' . plugin_thold_duration_convert($thold_data['local_data_id'], $thold_data['bl_fail_trigger'], 'alert') . '</i>', $thold_data['id'], '', 'right');
					form_selectable_cell($timearray[$thold_data['bl_ref_time_range']/$thold_data['rrd_step']], $thold_data['id'], '', 'right');

					break;
				case 2:
					form_selectable_cell(thold_format_number($thold_data['time_warning_hi'], 2, $baseu, $suffix) . ' / ' . thold_format_number($thold_data['time_hi'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');
					form_selectable_cell(thold_format_number($thold_data['time_warning_low'], 2, $baseu, $suffix) . ' / ' . thold_format_number($thold_data['time_low'], 2, $baseu, $suffix), $thold_data['id'], '', 'right');
					form_selectable_cell('<i>' . __('%d Triggers', $thold_data['time_fail_trigger'], 'thold') . '</i>',  $thold_data['id'], '', 'right');
					form_selectable_cell('<i>' . plugin_thold_duration_convert($thold_data['local_data_id'], $thold_data['time_fail_length'], 'time') . '</i>', $thold_data['id'], '', 'right');

					break;
				default:
					form_selectable_cell('- / -',  $thold_data['id'], '', 'right');
					form_selectable_cell('- / -',  $thold_data['id'], '', 'right');
					form_selectable_cell(__('N/A', 'thold'),  $thold_data['id'], '', 'right');
					form_selectable_cell(__('N/A', 'thold'),  $thold_data['id'], '', 'right');
			}

			form_selectable_cell(($thold_data['repeat_alert'] == '' ? '' : plugin_thold_duration_convert($thold_data['local_data_id'], $thold_data['repeat_alert'], 'repeat')), $thold_data['id'], '', 'right');
			form_selectable_cell($alertstat, $thold_data['id'], '', 'right');

			// The time since this threshold was triggered
			form_selectable_cell('<i>' . get_time_since_last_event($thold_data) . '</i>',  $thold_data['id'], '', 'right');

			if ($thold_data['thold_template_id'] != 0) {
				form_selectable_cell($thold_data['template_enabled'] == '' ? __('No', 'thold'):__('Yes', 'thold'), $thold_data['id'], '', 'right');
			} else {
				form_selectable_cell(__('No', 'thold'), $thold_data['id'], '', 'right');
			}

			form_selectable_cell(($thold_data['acknowledgment'] == '' ? __('No', 'thold'):__('Yes', 'thold')), $thold_data['id'], '', 'right ' . ($thold_data['acknowledgment'] == '' ? '':'tholdAckCol'));

			form_checkbox_cell($thold_data['name_cache'], $thold_data['id'], '', 'left');

			form_end_row();
		}
	} else {
		print "<tr class='even'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><center>" . __('No Thresholds', 'thold') . '</center></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($tholds)) {
		print $nav;
	}

	thold_legend();

	draw_actions_dropdown($thold_actions);

	if (isset($host_id) && $host_id != '') {
		print "<input type='hidden' name='host_id' value='$host_id'>";
	}

	form_end();

	bottom_footer();
}

function thold_edit() {
	global $config, $syslog_facil_array, $syslog_priority_array;

	if (isset_request_var('id') && get_filter_request_var('id') > 0) {
		$thold_data = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array(get_request_var('id')));

		if (!cacti_sizeof($thold_data)) {
			raise_message('', __('Threshold Deleted, can not Edit!', 'thold'), MESSAGE_LEVEL_ERROR);
			header('Location: thold.php');
			exit;
		}

		if (!isset($thold_data['local_graph_id'])) {
			include_once($config['base_path'] . '/plugins/thold/includes/database.php');

			thold_upgrade_database(true);
		}
	} elseif (isset_request_var('local_data_id') &&
		isset_request_var('local_graph_id') &&
		isset_request_var('host_id') &&
		isset_request_var('data_template_id') &&
		isset_request_var('data_template_rrd_id')) {

		$thold_data['id']                   = '0';
		$thold_data['local_data_id']        = get_filter_request_var('local_data_id');
		$thold_data['local_graph_id']       = get_filter_request_var('local_graph_id');
		$thold_data['data_template_id']     = get_filter_request_var('data_template_id');
		$thold_data['host_id']              = get_filter_request_var('host_id');
		$thold_data['data_template_rrd_id'] = get_filter_request_var('data_template_rrd_id');
		$thold_data['thold_template_id']    = get_filter_request_var('thold_template_id');

		$thold_data['data_source_name'] = db_fetch_cell_prepared('SELECT data_source_name
			FROM data_template_rrd
			WHERE id = ?',
			array($thold_data['data_template_rrd_id']));
	} else {
		exit;
	}

	$desc = db_fetch_cell_prepared('SELECT name_cache
		FROM data_template_data
		WHERE local_data_id = ?
		LIMIT 1',
		array($thold_data['local_data_id']));

	$rrdsql = array_rekey(db_fetch_assoc_prepared('SELECT id
		FROM data_template_rrd
		WHERE local_data_id = ?
		ORDER BY id',
		array($thold_data['local_data_id'])), 'id', 'id');

	$grapharr = db_fetch_assoc('SELECT DISTINCT local_graph_id
		FROM graph_templates_item
		WHERE task_item_id IN (' . implode(', ', $rrdsql) . ')');

	if (empty($thold_data['local_graph_id'])) {
		$thold_data['local_graph_id'] = db_fetch_cell_prepared('SELECT gl.id
			FROM graph_local AS gl
			INNER JOIN graph_templates_item AS gti
			ON gl.id=gti.local_graph_id
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id=dtr.id
			WHERE dtr.local_data_id = ?
			LIMIT 1',
			array($thold_data['local_data_id']));
	}

	if (empty($thold_data['data_template_rrd_id'])) {
		$thold_data['data_template_rrd_id'] = db_fetch_cell_prepared('SELECT id
			FROM data_template_rrd AS dtr
			WHERE local_data_id = ?
			LIMIT 1',
			array($thold_data['local_data_id']));

		$thold_data['data_source_name'] = db_fetch_cell_prepared('SELECT data_source_name
			FROM data_template_rrd
			WHERE id = ?',
			array($thold_data['data_template_rrd_id']));
	}

	$dt_sql = 'SELECT DISTINCT dtr.local_data_id
		FROM data_template_rrd AS dtr
		LEFT JOIN graph_templates_item AS gti
		ON gti.task_item_id=dtr.id
		LEFT JOIN graph_local AS gl
		ON gl.id=gti.local_graph_id
		WHERE gl.id=' . $thold_data['local_graph_id'];

	$template_data_rrds = db_fetch_assoc("SELECT td.id AS thold_id, dtr.id, dtr.data_source_name, dtr.local_data_id
		FROM data_template_rrd AS dtr
		LEFT JOIN thold_data AS td
		ON dtr.id=td.data_template_rrd_id
		WHERE dtr.local_data_id IN ($dt_sql)
		ORDER BY dtr.id, td.id");

	form_start('thold.php', 'thold');

	html_start_box(__('Graph Data', 'thold'), '100%', false, '3', 'center', '');

	?>
	<tr>
		<td class='textArea'>
			<?php if (isset($banner)) { print $banner . '<br><br>'; }; ?>
			<?php print __('Data Source Description:', 'thold');?> <br><?php print $desc; ?><br><br>
			<?php print __('Associated Graph (Graphs using this RRD):', 'thold');?> <br><br>
			<select id='local_graph_id' name='local_graph_id'>
				<?php
				foreach($grapharr as $g) {
					$graph_desc = db_fetch_row_prepared('SELECT local_graph_id, title, title_cache
						FROM graph_templates_graph
						WHERE local_graph_id = ?',
						array($g['local_graph_id']));

					print "<option value='" . $graph_desc['local_graph_id'] . "'";
					if ($graph_desc['local_graph_id'] == $thold_data['local_graph_id']) print ' selected';
					print '>' . html_escape($graph_desc['local_graph_id'] . ' - ' . $graph_desc['title_cache']) . '</option>';
				} ?>
			</select>
			<br>
			<br>
		</td>
		<td class='textArea'>
			<img id='graphimage' src='<?php print html_escape($config['url_path'] . 'graph_image.php?local_graph_id=' . $thold_data['local_graph_id'] . '&rra_id=0&graph_start=-32400&graph_height=150&graph_width=600');?>'>
		</td>
	</tr>
	<?php
	html_end_box();

	$template_rrd = db_fetch_row_prepared('SELECT *
		FROM data_template_rrd
		WHERE id = ?',
		array($thold_data['data_template_rrd_id']));

	//-----------------------------
	// Tabs (if more than one item)
	//-----------------------------
	$i  = 0;
	if (isset($template_data_rrds)) {
		if (cacti_sizeof($template_data_rrds)) {
			/* draw the data source tabs on the top of the page */
			print "<br><div class='tabs'><nav><ul>";

			foreach ($template_data_rrds as $template_data_rrd) {
				if (!empty($template_data_rrd['thold_id'])) {
					$td = db_fetch_row_prepared('SELECT *
						FROM thold_data
						WHERE id = ?',
						array($template_data_rrd['thold_id']));
				} else {
					$td = array();
				}

				$cur_setting = '';
				if (!sizeof($td)) {
					$cur_setting .= "<span style='padding-right:4px;'>" . __('N/A', 'thold') . "</span>";
				} else {
					$baseu = db_fetch_cell_prepared('SELECT base_value
						FROM graph_templates_graph
						WHERE local_graph_id = ?',
						array($td['local_graph_id']));

					if ($td['data_type'] == 2) {
						$suffix = false;
					} else {
						$suffix = true;
					}

					if (empty($baseu)) {
						cacti_log('WARNING: Graph Template for local_graph_id ' . $td['local_graph_id'] . ' has been removed!');
						$baseu = 1024;
					}

					// Check is the graph item has a cdef and modify the output
					thold_modify_values_by_cdef($td);

					$severity = get_thold_severity($td);

					switch($severity) {
						case THOLD_SEVERITY_DISABLED:
							$color = 'grey';
							break;
						case THOLD_SEVERITY_NORMAL:
							$color = 'green';
							break;
						case THOLD_SEVERITY_ALERT:
							$color = 'red';
							break;
						case THOLD_SEVERITY_WARNING:
							$color = 'warning';
							break;
						case THOLD_SEVERITY_BASELINE:
							$color = 'darkorange';
							break;
						case THOLD_SEVERITY_NOTICE:
							$color = 'yellow';
							break;
						case THOLD_SEVERITY_ACKREQ:
							$color = 'purple';
							break;
					}

					$cur_setting = '<span style="padding-right:4px;">' . __('Last:', 'thold'). '</span>' .
						($td['lastread'] == '' ? "<span>" . __('N/A', 'thold') . "</span>":"<span style='color:$color'>" .
						thold_format_number($td['lastread'], 2, $baseu, $suffix) . "</span>");

					if ($td['thold_type'] != 1) {
						if ($td['thold_warning_fail_trigger'] != 0) {
							if ($td['thold_warning_hi'] != '') {
								$cur_setting .= '<span style="padding:4px">' . __('WHi:', 'thold') . '</span>' .
									($td['thold_warning_hi'] == '' ? "<span>" . __('N/A', 'thold') . "</span>" : "<span style='color:darkorange'>" .
									thold_format_number($td['thold_warning_hi'], 2, $baseu, $suffix) . '</span>');
							}

							if ($td['thold_warning_low'] != '') {
								$cur_setting .= '<span style="padding:4px">' . __('WLo:', 'thold') . '</span>' .
									($td['thold_warning_low'] == '' ? "<span>" . __('N/A', 'thold') . "</span>" : "<span style='color:darkorange'>" .
									thold_format_number($td['thold_warning_low'], 2, $baseu, $suffix) . '</span>');
							}
						}

						if ($td['thold_fail_trigger'] != 0) {
							if ($td['thold_hi'] != '') {
								$cur_setting .= '<span style="padding:4px">' . __('AHi:', 'thold') . '</span>' .
									($td['thold_hi'] == '' ? "<span>" . __('N/A', 'thold') . "</span>" : "<span style='color:red'>" .
									thold_format_number($td['thold_hi'], 2, $baseu, $suffix) . '</span>');
							}

							if ($td['thold_low'] != '') {
								$cur_setting .= '<span style="padding:4px">' . __('ALo:', 'thold') . '</span>' .
									($td['thold_low'] == '' ? "<span>" . __('N/A', 'thold') . "</span>" : "<span style='color:red'>" .
									thold_format_number($td['thold_low'], 2, $baseu, $suffix) . '</span>');
							}
						}
					} else {
						$cur_setting .= '<span style="padding:4px">' . __('BL Up:', 'thold') . '</span>' .
							"<span>" . ($td['bl_pct_up'] != '' ? __('%s%%', $td['bl_pct_up'], 'thold'):__('N/A', 'thold')) . "</span>";
						$cur_setting .= '<span style="padding:4px">' . __('BL Down:', 'thold'). '</span>' .
							"<span>" . ($td['bl_pct_down'] != '' ? __('%s%%', $td['bl_pct_down'], 'thold'):__('N/A', 'thold')) . "</span>";
					}
				}

				if (isset_request_var('id') && $template_data_rrd['thold_id'] == get_filter_request_var('id')) {
					$selected = 'selected';
				} elseif (isset_request_var('data_template_rrd_id') && $template_data_rrd['id'] == get_filter_request_var('data_template_rrd_id')) {
					$selected = 'selected';
				} else {
					$selected = '';
				}

				if (!empty($template_data_rrd['thold_id'])) {
					print "<li class=''><a class='hyperLink $selected' href='" . html_escape('thold.php?action=edit&id=' . $template_data_rrd['thold_id']) . "'>" . html_escape($template_data_rrd['data_source_name']) . '<br>' . $cur_setting . '</a></li>';
				} else {
					print "<li class=''><a class='hyperLink $selected' href='" . html_escape('thold.php?action=edit&local_data_id=' . $template_data_rrd['local_data_id'] . '&data_template_rrd_id=' . $template_data_rrd['id']) . '&local_graph_id=' . $thold_data['local_graph_id'] . '&host_id=' . $thold_data['host_id'] . '&data_template_id=' . $thold_data['data_template_id'] . '&thold_template_id=0' . "'>" . html_escape($template_data_rrd['data_source_name']) . '<br>' . $cur_setting . '</a></li>';
				}
			}

			print "<li class=''><a class='hyperLink' href='" . html_escape('thold.php?action=add' . '&local_graph_id=' . $thold_data['local_graph_id'] . '&my_host_id=' . $thold_data['host_id'] . '&type_id=thold') . "'>new thold<br>n/a</a></li>";

			print '</ul></nav></div>';
		} elseif (cacti_sizeof($template_data_rrds) == 1) {
			set_request_var('data_template_rrd_id', $template_data_rrds[0]['id']);
		}
	}

	//----------------------
	// Data Source Item Form
	//----------------------
	$template_thold = false;
	if (isset($thold_data['thold_template_id']) && $thold_data['thold_template_id'] > 0) {
		$template_thold = db_fetch_row_prepared('SELECT *
			FROM thold_template
			WHERE id = ?',
			array($thold_data['thold_template_id']));

		$thold_data['template_name'] = empty($template_thold) ? __('Unknown Template ID %d', $thold_data['thold_template_id'], 'thold') : $template_thold['name'];
	} else {
		$thold_data['template_name'] = __('Not Templated', 'thold');
	}

	$baseu = db_fetch_cell_prepared('SELECT base_value
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($thold_data['local_graph_id']));

	if ($thold_data['data_type'] == 2) {
		$suffix = false;
	} else {
		$suffix = true;
	}

	$header_text = __('Data Source Item [ %s ] - Current value: [ %s ]',
		(isset($template_rrd) ? $template_rrd['data_source_name'] : ''), thold_format_number(thold_get_column_by_cdef($thold_data, 'lastread'), 2, $baseu, $suffix), 'thold');

	html_start_box($header_text, '100%', false, '3', 'center', '');

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

	if (isset($thold_data['id'])) {
		$sql  = 'SELECT contact_id as id FROM plugin_thold_threshold_contact WHERE thold_id=' . $thold_data['id'];

		$step = db_fetch_cell_prepared('SELECT rrd_step
			FROM data_template_data
			WHERE local_data_id = ?',
			array($thold_data['local_data_id']));
	} else {
		$sql  = 'SELECT contact_id as id FROM plugin_thold_threshold_contact WHERE thold_id=0';

		$step = db_fetch_cell_prepared('SELECT rrd_step
			FROM data_template_data
			WHERE local_data_id = ?',
			array($thold_data['local_data_id']));
	}

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$data_fields = array();

	$reference_types = get_reference_types($thold_data['local_data_id']);

	$temp = db_fetch_assoc_prepared('SELECT id, local_data_template_rrd_id,
		data_source_name, data_input_field_id
		FROM data_template_rrd
		WHERE local_data_id = ?',
		array($thold_data['local_data_id']));

	foreach ($temp as $d) {
		if ($d['data_input_field_id'] != 0) {
			$name = db_fetch_cell_prepared('SELECT name
				FROM data_input_fields
				WHERE id = ?',
				array($d['data_input_field_id']));
		} else {
			$name = $d['data_source_name'];
		}

		if ($d['id'] != $thold_data['data_template_rrd_id']) {
			$data_fields[$d['data_source_name']] = $name;
		}
	}

	$replacements = db_fetch_assoc_prepared('SELECT DISTINCT field_name
		FROM data_local AS dl
		INNER JOIN host_snmp_cache AS hsc
		ON dl.snmp_query_id=hsc.snmp_query_id
		AND dl.host_id=hsc.host_id
		WHERE dl.id = ?',
		array($thold_data['data_template_id']));

	$nr = array();
	if (cacti_sizeof($replacements)) {
		foreach($replacements as $r) {
			$nr[] = "<span class='deviceUp'>|query_" . $r['field_name'] . "|</span>";
		}
	}

	$vhf = explode('|', trim(VALID_HOST_FIELDS, '()'));
	if (cacti_sizeof($vhf)) {
		foreach($vhf as $r) {
			$nr[] = "<span class='deviceUp'>|" . $r . "|</span>";
		}
	}

	$replacements = '<br>' . __('Replacement Fields: %s', implode(", ", $nr), 'thold');

	$dss = db_fetch_assoc_prepared('SELECT data_source_name
		FROM data_template_rrd
		WHERE local_data_id = ?',
		array($thold_data['local_data_id']));

	if (cacti_sizeof($dss)) {
		foreach($dss as $ds) {
			$dsname[] = "<span class='deviceUp'>|ds:" . $ds['data_source_name'] . "|</span>";
		}
	}

	$datasources = '<br>' . __('Data Sources: %s', implode(', ', $dsname), 'thold');

	$email_body = read_config_option('thold_enable_per_thold_body');

	if (cacti_sizeof($thold_data) && isset($thold_data['reset_ack'])) {
		$acknowledgment = ($thold_data['reset_ack'] == 'on' ? 'reset_ack': ($thold_data['persist_ack'] == 'on' ? 'persist_ack':'none'));
	} else {
		$acknowledgment = 'none';
	}

	$form_array = array(
		'template_header' => array(
			'friendly_name' => __('Template/Enabled Information', 'thold'),
			'method' => 'spacer',
		),
		'template_enabled' => array(
			'friendly_name' => __('Template Propagation Enabled', 'thold'),
			'method' => 'checkbox',
			'default' => '',
			'description' => __('Whether or not these settings will be propagated from the Threshold template.', 'thold'),
			'value' => !empty($thold_data['template_enabled']) ? $thold_data['template_enabled'] : '',
		),
		'template_name' => array(
			'friendly_name' => __('Template Name', 'thold'),
			'method' => '',
			'default' => '',
			'description' => __('Name of the Threshold Template the Threshold was created from.', 'thold'),
			'value' => isset($thold_data['template_name']) ? $thold_data['template_name'] : __('N/A', 'thold'),
		),
		'thold_enabled' => array(
			'friendly_name' => __('Threshold Enabled', 'thold'),
			'method' => 'checkbox',
			'default' => 'on',
			'description' => __('Whether or not this Threshold will be checked and alerted upon.', 'thold'),
			'value' => isset($thold_data['thold_enabled']) ? $thold_data['thold_enabled'] : ''
		),
		'general_header' => array(
			'friendly_name' => __('General Settings', 'thold'),
			'method' => 'spacer',
		),
		'name_cache' => array(
			'friendly_name' => __('Name (Displayed)', 'thold'),
			'method' => 'other',
			'max_length' => 100,
			'size' => '70',
			'default' => '',
			'description' => __('Provide the Thresholds a meaningful name', 'thold'),
			'value' => isset($thold_data['name_cache']) && $thold_data['name_cache'] != '' ? $thold_data['name_cache'] : ''
		),
		'name' => array(
			'friendly_name' => __('Name (Format)', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => '70',
			'default' => '|data_source_description| [|data_source_name|]',
			'description' => __('Provide the Thresholds a meaningful name', 'thold'),
			'value' => isset($thold_data['name']) && $thold_data['name'] != '' ? $thold_data['name'] : ''
		),
		'thold_hrule_warning' => array(
			'friendly_name' => __('Warning HRULE Color', 'thold'),
			'description' => __('Please choose a Color for the Graph HRULE for the Warning Thresholds.  Choose \'None\' for No HRULE.  Note: This features is supported for Data Manipulation types \'Exact Value\' and \'Percentage\' only at this time.', 'thold'),
			'method' => 'drop_color',
			'none_value' => __('None', 'thold'),
			'default' => '0',
			'value' => isset($thold_data['thold_hrule_warning']) ? $thold_data['thold_hrule_warning'] : '0'
			),
		'thold_hrule_alert' => array(
			'friendly_name' => __('Alert HRULE Color', 'thold'),
			'description' => __('Please choose a Color for the Graph HRULE for the Alert Thresholds.  Choose \'None\' for No HRULE.  Note: This features is supported for Data Manipulation types \'Exact Value\' and \'Percentage\' only at this time.', 'thold'),
			'method' => 'drop_color',
			'none_value' => __('None', 'thold'),
			'default' => '0',
			'value' => isset($thold_data['thold_hrule_alert']) ? $thold_data['thold_hrule_alert'] : '0'
			),
		'exempt' => array(
			'friendly_name' => __('Weekend Exemption', 'thold'),
			'description' => __('If this is checked, this Threshold will not alert on weekends.', 'thold'),
			'method' => 'checkbox',
			'default' => '',
			'value' => isset($thold_data['exempt']) ? $thold_data['exempt'] : ''
			),
		'restored_alert' => array(
			'friendly_name' => __('Disable Restoration Email', 'thold'),
			'description' => __('If this is checked, Threshold will not send an alert when the Threshold has returned to normal status.', 'thold'),
			'method' => 'checkbox',
			'default' => '',
			'value' => isset($thold_data['restored_alert']) ? $thold_data['restored_alert'] : ''
			),
		'acknowledgment' => array(
			'friendly_name' => __('Acknowledgment Options'),
			'description' => __('There are three Acknowledgment levels that control how you must respond to a Threshold breach condition.  They are:<br><br><ul><li><i>None Required</i> - When you select this option, no Acknowledgment is required for a Threshold breach.</li><li><i>Suspendible Notification</i> - With this option, once you Acknowledge or Suspend Notifications on the Threshold, you will no longer receive notifications while it is breached.  You may subsequently, Resume Notifications while its breached.</li><li><i>Persistent Acknowledgment</i> - With this option, even after the Threshold has returned to normal, you must Acknowledge the Threshold and provide an optional Operator Message.</li></ul>'),
			'method' => 'radio',
			'value' => $acknowledgment,
			'default' => 'none',
			'items' => array(
				0 => array(
					'radio_value' => 'none',
					'radio_caption' => __('None Required', 'thold'),
					),
				1 => array(
					'radio_value' => 'reset_ack',
					'radio_caption' => __('Suspendible Notification', 'thold'),
					),
				2 => array(
					'radio_value' => 'persist_ack',
					'radio_caption' => __('Persistent Acknowledgment', 'thold')
				)
			)
		),
		'thold_type' => array(
			'friendly_name' => __('Threshold Type', 'thold'),
			'method' => 'drop_array',
			'on_change' => 'changeTholdType()',
			'array' => $thold_types,
			'default' => read_config_option('thold_type'),
			'description' => __('The type of Threshold that will be monitored.', 'thold'),
			'value' => isset($thold_data['thold_type']) ? $thold_data['thold_type'] : ''
		),
		'repeat_alert' => array(
			'friendly_name' => __('Re-Alert Cycle', 'thold'),
			'method' => 'drop_array',
			'array' => $repeatarray,
			'default' => read_config_option('alert_repeat'),
			'description' => __('Repeat alert after this amount of time has passed since the last alert.', 'thold'),
			'value' => isset($thold_data['repeat_alert']) ? $thold_data['repeat_alert'] : ''
		),
		'thold_warning_header' => array(
			'friendly_name' => __('Warning - High / Low Settings', 'thold'),
			'method' => 'spacer',
		),
		'thold_warning_hi' => array(
			'friendly_name' => __('High Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes above this number, warning will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['thold_warning_hi']) ? $thold_data['thold_warning_hi'] : ''
		),
		'thold_warning_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes below this number, warning will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['thold_warning_low']) ? $thold_data['thold_warning_low'] : ''
		),
		'thold_warning_fail_trigger' => array(
			'friendly_name' => __('Breach Duration', 'thold'),
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => __('The amount of time the Data Source must be in breach of the Threshold for a warning to be raised.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['thold_warning_fail_trigger']) ? $thold_data['thold_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'thold_header' => array(
			'friendly_name' => __('Alert - High / Low Settings', 'thold'),
			'method' => 'spacer',
		),
		'thold_hi' => array(
			'friendly_name' => __('High Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes above this number, alert will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['thold_hi']) ? $thold_data['thold_hi'] : ''
		),
		'thold_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes below this number, alert will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['thold_low']) ? $thold_data['thold_low'] : ''
		),
		'thold_fail_trigger' => array(
			'friendly_name' => __('Breach Duration', 'thold'),
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => __('The amount of time the Data Source must be in breach of the Threshold for an alert to be raised.', 'thold'),
			'value' => isset($thold_data['thold_fail_trigger']) ? $thold_data['thold_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_header' => array(
			'friendly_name' => __('Warning - Time Based Settings', 'thold'),
			'method' => 'spacer',
		),
		'time_warning_hi' => array(
			'friendly_name' => __('High Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes above this number, warning will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['time_warning_hi']) ? $thold_data['time_warning_hi'] : ''
		),
		'time_warning_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes below this number, warning will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['time_warning_low']) ? $thold_data['time_warning_low'] : ''
		),
		'time_warning_fail_trigger' => array(
			'friendly_name' => __('Breach Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 15,
			'description' => __('The number of times the Data Source must be in breach of the Threshold.', 'thold'),
			'value' => isset($thold_data['time_warning_fail_trigger']) ? $thold_data['time_warning_fail_trigger'] : read_config_option('thold_warning_time_fail_trigger')
		),
		'time_warning_fail_length' => array(
			'friendly_name' => __('Breach Window', 'thold'),
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => __('The amount of time in the past to check for Threshold breaches.', 'thold'),
			'value' => isset($thold_data['time_warning_fail_length']) ? $thold_data['time_warning_fail_length'] : (read_config_option('thold_warning_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1)
		),
		'time_header' => array(
			'friendly_name' => __('Alert - Time Based Settings', 'thold'),
			'method' => 'spacer',
		),
		'time_hi' => array(
			'friendly_name' => __('High Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes above this number, alert will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['time_hi']) ? $thold_data['time_hi'] : ''
		),
		'time_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes below this number, alert will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['time_low']) ? $thold_data['time_low'] : ''
		),
		'time_fail_trigger' => array(
			'friendly_name' => __('Breach Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 15,
			'default' => read_config_option('thold_time_fail_trigger'),
			'description' => __('The number of times the Data Source must be in breach of the Threshold.', 'thold'),
			'value' => isset($thold_data['time_fail_trigger']) ? $thold_data['time_fail_trigger'] : read_config_option('thold_time_fail_trigger')
		),
		'time_fail_length' => array(
			'friendly_name' => __('Breach Window', 'thold'),
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => __('The amount of time in the past to check for Threshold breaches.', 'thold'),
			'value' => isset($thold_data['time_fail_length']) ? $thold_data['time_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_time_fail_length') : 1)
		),
		'baseline_header' => array(
			'friendly_name' => __('Baseline Settings', 'thold'),
			'method' => 'spacer',
		),
		'bl_ref_time_range' => array(
			'friendly_name' => __('Time range', 'thold'),
			'method' => 'drop_array',
			'array' => $reference_types,
			'description' => __('Specifies the point in the past (based on RRDfile resolution) that will be used as a reference', 'thold'),
			'value' => isset($thold_data['bl_ref_time_range']) ? $thold_data['bl_ref_time_range'] : read_config_option('alert_bl_timerange_def')
		),
		'bl_pct_up' => array(
			'friendly_name' => __('Deviation UP', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Specifies allowed deviation in percentage for the upper bound Threshold. If not set, upper bound Threshold will not be checked at all.', 'thold'),
			'value' => isset($thold_data['bl_pct_up']) ? $thold_data['bl_pct_up'] : ''
		),
		'bl_pct_down' => array(
			'friendly_name' => __('Deviation DOWN', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Specifies allowed deviation in percentage for the lower bound Threshold. If not set, lower bound Threshold will not be checked at all.', 'thold'),
			'value' => isset($thold_data['bl_pct_down']) ? $thold_data['bl_pct_down'] : ''
		),
		'bl_fail_trigger' => array(
			'friendly_name' => __('Baseline Trigger Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Number of consecutive times the Data Source must be in breach of the baseline Threshold for an alert to be raised.<br>Leave empty to use default value (<b>Default: %s cycles</b>)', read_config_option('alert_bl_trigger'), 'thold'),
			'value' => isset($thold_data['bl_fail_trigger']) ? $thold_data['bl_fail_trigger'] : read_config_option('alert_bl_trigger')
		),
		'data_manipulation' => array(
			'friendly_name' => __('Data Manipulation', 'thold'),
			'method' => 'spacer',
		),
		'data_type' => array(
			'friendly_name' => __('Data Type', 'thold'),
			'method' => 'drop_array',
			'on_change' => 'changeDataType()',
			'array' => $data_types,
			'default' => read_config_option('data_type'),
			'description' => __('Special formatting for the given data.', 'thold'),
			'value' => isset($thold_data['data_type']) ? $thold_data['data_type'] : ''
		),
		'cdef' => array(
			'friendly_name' => __('Threshold CDEF'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Apply this CDEF before returning the data.'),
			'value' => isset($thold_data['cdef']) ? $thold_data['cdef'] : 0,
			'array' => thold_cdef_select_usable_names()
		),
		'percent_ds' => array(
			'friendly_name' => __('Percent Data Source', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Second Data Source Item to use as total value to calculate percentage from.', 'thold'),
			'value' => isset($thold_data['percent_ds']) ? $thold_data['percent_ds'] : 0,
			'array' => $data_fields,
		),
		'expression' => array(
			'friendly_name' => __('RPN Expression', 'thold'),
			'method' => 'textarea',
			'textarea_rows' => 3,
			'textarea_cols' => 80,
			'default' => '',
			'description' => __('An RPN Expression is an RRDtool Compatible RPN Expression.  Syntax includes all functions below in addition to both Device and Data Query replacement expressions such as <span class="deviceUp">|query_ifSpeed|</span>.  To use a Data Source in the RPN Expression, you must use the syntax: <span class="deviceUp">|ds:dsname|</span>.  For example, <span class="deviceUp">|ds:traffic_in|</span> will get the current value of the traffic_in Data Source for the RRDfile(s) associated with the Graph. Any Data Source for a Graph can be included.<br><br>Math Operators: <span class="deviceUp">+, -, /, *, &#37;, ^</span><br>Functions: <span class="deviceUp">SIN, COS, TAN, ATAN, SQRT, FLOOR, CEIL, DEG2RAD, RAD2DEG, ABS, EXP, LOG, ATAN, ADNAN</span><br>Flow Operators: <span class="deviceUp">UN, ISINF, IF, LT, LE, GT, GE, EQ, NE</span><br>Comparison Functions: <span class="deviceUp">MAX, MIN, INF, NEGINF, NAN, UNKN, COUNT, PREV</span>%s %s', $replacements, $datasources, 'thold'),
			'value' => isset($thold_data['expression']) ? $thold_data['expression'] : '',
			'max_length' => '255',
			'size' => '80'
		),
		'notify_header' => array(
			'friendly_name' => __('Notification Settings', 'thold'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'email_body' => array(
			'friendly_name' => __('Alert Email Body', 'thold'),
			'method' => ($email_body == 'on' ? 'textarea':'hidden'),
			'textarea_rows' => 3,
			'textarea_cols' => 50,
			'default' => read_config_option('thold_alert_text'),
			'description' => __('This is the message that will be displayed at the top of all Threshold Alerts (255 Char MAX).  HTML is allowed, but will be removed for text only emails.  There are several descriptors that may be used.<br>eg. &#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062 &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062 &#060HI&#062 &#060LOW&#062 &#060DURATION&#062 &#060TRIGGER&#062 &#060DETAILS_URL&#062 &#060DATE_RFC822&#062 &#060BREACHED_ITEMS&#062', 'thold'),
			'value' => isset($thold_data['email_body']) ? $thold_data['email_body'] : ''
		),
		'email_body_warn' => array(
			'friendly_name' => __('Warning Email Body', 'thold'),
			'method' => ($email_body == 'on' ? 'textarea':'hidden'),
			'textarea_rows' => 3,
			'textarea_cols' => 50,
			'default' => read_config_option('thold_warning_text'),
			'description' => __('This is the message that will be displayed at the top of all Threshold Warnings (255 Char MAX).  HTML is allowed, but will be removed for text only emails.  There are several descriptors that may be used.<br>eg. &#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062 &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062 &#060HI&#062 &#060LOW&#062 &#060DURATION&#062 &#060TRIGGER&#062 &#060DETAILS_URL&#062 &#060DATE_RFC822&#062 &#060BREACHED_ITEMS&#062', 'thold'),
			'value' => isset($thold_data['email_body']) ? $thold_data['email_body'] : ''
		),
		'notify_warning' => array(
			'friendly_name' => __('Warning Notification List', 'thold'),
			'method' => 'drop_sql',
			'description' => __('You may specify choose a Notification List to receive Warnings for this Data Source', 'thold'),
			'value' => isset($thold_data['notify_warning']) ? $thold_data['notify_warning'] : '',
			'none_value' => __('None', 'thold'),
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		),
		'notify_alert' => array(
			'friendly_name' => __('Alert Notification List', 'thold'),
			'method' => 'drop_sql',
			'description' => __('You may specify choose a Notification List to receive Alerts for this Data Source', 'thold'),
			'value' => isset($thold_data['notify_alert']) ? $thold_data['notify_alert'] : '',
			'none_value' => __('None', 'thold'),
			'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name'
		)
	);

	if (read_config_option('thold_alert_snmp') == 'on') {
		$extra = array(
			'snmp_event_category' => array(
				'friendly_name' => __('SNMP Notification - Event Category', 'thold'),
				'method' => 'textbox',
				'description' => __('To allow a NMS to categorize different SNMP notifications more easily please fill in the category SNMP notifications for this template should make use of. E.g.: "disk_usage", "link_utilization", "ping_test", "nokia_firewall_cpu_utilization" ...', 'thold'),
				'value' => isset($thold_data['snmp_event_category']) ? $thold_data['snmp_event_category'] : '',
				'default' => '',
				'max_length' => '255',
			),
			'snmp_event_severity' => array(
				'friendly_name' => __('SNMP Notification - Alert Event Severity', 'thold'),
				'method' => 'drop_array',
				'default' => '3',
				'description' => __('Severity to be used for alerts. (low impact -> critical impact)', 'thold'),
				'value' => isset($thold_data['snmp_event_severity']) ? $thold_data['snmp_event_severity'] : 3,
				'array' => array(
					1 => __('Low', 'thold'),
					2 => __('Medium', 'thold'),
					3 => __('High', 'thold'),
					4 => __('Critical', 'thold')
				)
			)
		);

		$form_array += $extra;

		if (read_config_option('thold_alert_snmp_warning') != 'on') {
			$extra = array(
				'snmp_event_warning_severity' => array(
					'friendly_name' => __('SNMP Notification - Warning Event Severity', 'thold'),
					'method' => 'drop_array',
					'default' => '2',
					'description' => __('Severity to be used for warnings. (Low impact -> Critical impact).<br>Note: The severity of warnings has to be equal or lower than the severity being defined for alerts.', 'thold'),
					'value' => isset($thold_data['snmp_event_warning_severity']) ? $thold_data['snmp_event_warning_severity'] : 2,
					'array' => array(
						1 => __('Low', 'thold'),
						2 => __('Medium', 'thold'),
						3 => __('High', 'thold'),
						4 => __('Critical', 'thold')
					)
				)
			);
		}

		$form_array += $extra;
	}

	if (read_config_option('thold_disable_legacy') != 'on') {
		$extra = array(
			'notify_accounts' => array(
				'friendly_name' => __('Notify accounts', 'thold'),
				'method' => 'drop_multi',
				'description' => __('This is a listing of accounts that will be notified when this Threshold is breached.', 'thold'),
				'array' => $send_notification_array,
				'sql' => $sql,
			),
			'notify_extra' => array(
				'friendly_name' => __('Alert Emails', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => __('You may specify here extra Emails to receive alerts for this data source (comma separated)', 'thold'),
				'value' => isset($thold_data['notify_extra']) ? $thold_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'friendly_name' => __('Warning Emails', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => __('You may specify here extra Emails to receive warnings for this data source (comma separated)', 'thold'),
				'value' => isset($thold_data['notify_warning_extra']) ? $thold_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	} else {
		$extra = array(
			'notify_accounts' => array(
				'method' => 'hidden',
				'value' => 'ignore'
			),
			'notify_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_data['notify_extra']) ? $thold_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'method' => 'hidden',
				'value' => isset($thold_data['notify_warning_extra']) ? $thold_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	}

	if ($config['cacti_server_os'] != 'win32') {
		$extra = array(
			'syslog_settings' => array(
				'friendly_name' => __('Syslog Settings', 'thold'),
				'collapsible' => 'true',
				'method' => 'spacer',
			),
			'syslog_enabled' => array(
				'friendly_name' => __('Enabled', 'thold'),
				'description' => __('If checked, Threshold notification will be sent to your local syslog.', 'thold'),
				'method' => 'checkbox',
				'default' => read_config_option('alert_syslog'),
				'value' => isset($thold_data['syslog_enabled']) ? $thold_data['syslog_enabled'] : ''
			),
			'syslog_priority' => array(
				'friendly_name' => __('Priority/Level', 'thold'),
				'description' => __('This is the Priority Level that will be logged into your syslog messages.', 'thold'),
				'method' => 'drop_array',
				'default' => read_config_option('thold_syslog_priority'),
				'array' => $syslog_priority_array,
				'value' => isset($thold_data['syslog_priority']) ? $thold_data['syslog_priority'] : ''
			),
			'syslog_facility' => array(
				'friendly_name' => __('Facility', 'thold'),
				'description' => __('This is the Facility that will be used for this Threshold.', 'thold'),
				'method' => 'drop_array',
				'default' => read_config_option('thold_syslog_facility'),
				'array' => $syslog_facil_array,
				'value' => isset($thold_data['syslog_facility']) ? $thold_data['syslog_facility'] : ''
			)
		);
	} else {
		$extra = array(
			'syslog_settings' => array('method' => 'hidden'),
			'syslog_enabled' => array('method' => 'hidden', 'value' => ''),
			'syslog_priority' => array('method' => 'hidden', 'value' => ''),
			'syslog_facility' => array('method' => 'hidden', 'value' => '')
		);
	}

	$form_array += $extra;

	if (read_config_option('thold_enable_scripts')) {
		$extra = array(
			'event_trigger' => array(
				'friendly_name' => __('Event Triggering (Shell Command)', 'thold'),
				'collapsible' => 'true',
				'method' => 'spacer',
			),
			'trigger_cmd_high' => array(
				'friendly_name' => __('High Trigger Command', 'thold'),
				'description' => __('If set, and if a High Threshold is breached, this command will be run.  Please enter a valid command.  In addition, there are several replacement tags available that can be used to pass information from the Threshold to the script.  They include: &#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062 &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062 &#060HI&#062 &#060LOW&#062 &#060DURATION&#062 &#060TRIGGER&#062 &#060DETAILS_URL&#062 &#060DATE_RFC822&#062 &#060BREACHED_ITEMS&#062.  Finally, Host, Data Query and Data Input replacement can be made.  For example, if you have a data input custom data called pending, to perform the replacement use |pending|.  For Data Query, and Host replacement use Cacti conventions |query_xxxx|, and |host_xxxx| respectively.', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => '4',
				'textarea_cols' => '80',
				'value' => isset($thold_data['trigger_cmd_high']) ? $thold_data['trigger_cmd_high'] : ''
			),
			'trigger_cmd_low' => array(
   				'friendly_name' => __('Low Trigger Command', 'thold'),
				'description' => __('If set, and if a Low Threshold is breached, this command will be run. Please enter a valid command.  In addition, there are several replacement tags available that can be used to pass information from the Threshold to the script.  They include: &#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062 &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062 &#060HI&#062 &#060LOW&#062 &#060DURATION&#062 &#060TRIGGER&#062 &#060DETAILS_URL&#062 &#060DATE_RFC822&#062 &#060BREACHED_ITEMS&#062.  Finally, Host, Data Query and Data input replacement can be made.  For example, if you have a data input custom data called pending, to perform the replacement use |pending|.  For Data Query, and Host replacement use Cacti conventions |query_xxxx|, and |host_xxxx| respectively.', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => '4',
				'textarea_cols' => '80',
				'value' => isset($thold_data['trigger_cmd_low']) ? $thold_data['trigger_cmd_low'] : ''
			),
			'trigger_cmd_norm' => array(
				'friendly_name' => __('Norm Trigger Command', 'thold'),
				'description' => __('If set, when a thold falls back to a normal value, this command will be run.  Please enter a valid command.  In addition, there are several replacement tags available that can be used to pass information from the Threshold to the script.  They include: &#060DESCRIPTION&#062 &#060HOSTNAME&#062 &#060TIME&#062 &#060URL&#062 &#060GRAPHID&#062 &#060CURRENTVALUE&#062 &#060THRESHOLDNAME&#062 &#060DSNAME&#062 &#060SUBJECT&#062 &#060GRAPH&#062 &#060HI&#062 &#060LOW&#062 &#060DURATION&#062 &#060TRIGGER&#062 &#060DETAILS_URL&#062 &#060DATE_RFC822&#062 &#060BREACHED_ITEMS&#062.  Finally, Host, Data Query and Data input replacement can be made.  For example, if you have a data input custom data called pending, to perform the replacement use |pending|.  For Data Query, and Host replacement use Cacti conventions |query_xxxx|, and |host_xxxx| respectively.', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => '4',
				'textarea_cols' => '80',
				'value' => isset($thold_data['trigger_cmd_norm']) ? $thold_data['trigger_cmd_norm'] : ''
			),
		);

		$form_array += $extra;
	}

	$extra = array(
		'other_header' => array(
			'friendly_name' => __('Other Settings', 'thold'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'notes' => array(
			'friendly_name' => __('Operator Notes', 'thold'),
			'method' => 'textarea',
			'textarea_rows' => 3,
			'textarea_cols' => 50,
			'description' => __('Enter instructions here for an operator who may be receiving the Threshold message.', 'thold'),
			'value' => isset($thold_data['notes']) ? $thold_data['notes'] : ''
		)
	);

	$form_array += $extra;

	$form_array += array(
		'id' => array(
			'method' => 'hidden',
			'value' => !empty($thold_data['id']) ? $thold_data['id'] : '0'
		),
		'data_template_rrd_id' => array(
			'method' => 'hidden',
			'value' => (isset($template_rrd) ? $template_rrd['id'] : '0')
		),
		'host_id' => array(
			'method' => 'hidden',
			'value' => $thold_data['host_id']
		),
		'local_data_id' => array(
			'method' => 'hidden',
			'value' => $thold_data['local_data_id']
		)
	);

	// Allow plugins to hook the edit form
	$form_array = api_plugin_hook_function('thold_edit_form_array', $form_array);

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_array, isset($thold_data) ? $thold_data : array())
		)
	);

	html_end_box();

	form_save_button('thold.php' . (!empty($thold_data['id']) ? '?id=' . $thold_data['id']: ''), 'return', 'id');

	?>

	<script type='text/javascript'>

	function templateEnableDisable() {
		var status = $('#template_enabled').is(':checked');

		$('#name').prop('disabled', status);
		$('#thold_enabled').prop('disabled', status);

		// General Settings
		$('#exempt').prop('disabled', status);
		$('#repeat_alert').prop('disabled', status);
		$('#thold_hrule_warning').prop('disabled', status);
		$('#thold_hrule_alert').prop('disabled', status);
		$('#restored_alert').prop('disabled', status);

		// Acknowledgment
		$('#acknowledgment').prop('disabled', status);

		// High/Low Settings
		$('#thold_type').prop('disabled', status);
		$('#thold_hi').prop('disabled', status);
		$('#thold_low').prop('disabled', status);
		$('#thold_fail_trigger').prop('disabled', status);
		$('#thold_warning_hi').prop('disabled', status);
		$('#thold_warning_low').prop('disabled', status);
		$('#thold_warning_fail_trigger').prop('disabled', status);

		// Time Settings
		$('#time_hi').prop('disabled', status);
		$('#time_low').prop('disabled', status);
		$('#time_fail_trigger').prop('disabled', status);
		$('#time_fail_length').prop('disabled', status);
		$('#time_warning_hi').prop('disabled', status);
		$('#time_warning_low').prop('disabled', status);
		$('#time_warning_fail_trigger').prop('disabled', status);
		$('#time_warning_fail_length').prop('disabled', status);

		// Notification Emails
		$('#notify_extra').prop('disabled', status);
		$('#notify_warning_extra').prop('disabled', status);
		$('#notify_warning').prop('disabled', status);
		$('#notify_alert').prop('disabled', status);

		if ($('#notify_accounts')) $('#notify_accounts').prop('disabled', status);

		// Data Manipulation
		$('#data_type').prop('disabled', status);
		$('#cdef').prop('disabled', status);
		$('#percent_ds').prop('disabled', status);
		$('#expression').prop('disabled', status);

		// Email Body options
		$('#email_body').prop('disabled', status);
		$('#email_body_warn').prop('disabled', status);

		// Syslog settings
		$('#syslog_enabled').prop('disabled', status);
		$('#syslog_priority').prop('disabled', status);
		$('#syslog_facility').prop('disabled', status);

		// Command Execution settings
		$('#trigger_cmd_high').prop('disabled', status);
		$('#trigger_cmd_low').prop('disabled', status);
		$('#trigger_cmd_norm').prop('disabled', status);

		if ($('#snmp_event_category')) $('#snmp_event_category').prop('disabled', status);
		if ($('#snmp_event_severity')) $('#snmp_event_severity').prop('disabled', status);
		if ($('#snmp_event_warning_severity')) $('#snmp_event_warning_severity').prop('disabled', status);

		// Other options
		$('#notes').prop('disabled', status);

		if (status) {
			$('input:not(:button):not(:submit), textarea, select').each(function() {
				$(this).addClass('ui-state-disabled');
				if ($(this).selectmenu('instance')) {
					$(this).selectmenu('disable');
				}
			});
		} else {
			$('input:not(:button):not(:submit), textarea, select').each(function() {
				$(this).removeClass('ui-state-disabled');
				if ($(this).selectmenu('instance')) {
					$(this).selectmenu('enable');
				}
			});
		}
	}

	function changeTholdType() {
		switch($('#thold_type').val()) {
		case '0':
			thold_toggle_hilow('');
			thold_toggle_baseline('none');
			thold_toggle_time('none');

			$('#row_thold_hrule_warning').show();
			$('#row_thold_hrule_alert').show();

			break;
		case '1':
			thold_toggle_hilow('none');
			thold_toggle_baseline('');
			thold_toggle_time('none');

			$('#row_thold_hrule_warning').hide();
			$('#row_thold_hrule_alert').hide();

			break;
		case '2':
			thold_toggle_hilow('none');
			thold_toggle_baseline('none');
			thold_toggle_time('');

			$('#row_thold_hrule_warning').show();
			$('#row_thold_hrule_alert').show();

			break;
		}
	}

	function changeDataType () {
		switch($('#data_type').val()) {
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
		} else {
			$('#row_thold_header, #row_thold_hi, #row_thold_low, #row_thold_fail_trigger').hide();
			$('#row_thold_warning_header, #row_thold_warning_hi').hide();
			$('#row_thold_warning_low, #row_thold_warning_fail_trigger').hide();
		}
	}

	function thold_toggle_baseline(status) {
		if (status == '') {
			$('#row_baseline_header, #row_bl_ref_time_range').show();
			$('#row_bl_pct_up, #row_bl_pct_down, #row_bl_fail_trigger').show();
		} else {
			$('#row_baseline_header, #row_bl_ref_time_range').hide();
			$('#row_bl_pct_up, #row_bl_pct_down, #row_bl_fail_trigger').hide();
		}
	}

	function thold_toggle_time(status) {
		if (status == '') {
			$('#row_time_header, #row_time_hi, #row_time_low, #row_time_fail_trigger, #row_time_fail_length').show();
			$('#row_time_warning_header, #row_time_warning_hi, #row_time_warning_low').show();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').show();
		} else {
			$('#row_time_header, #row_time_hi, #row_time_low, #row_time_fail_trigger, #row_time_fail_length').hide();
			$('#row_time_warning_header, #row_time_warning_hi, #row_time_warning_low').hide();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').hide();
		}
	}

	var counter = 1;

	function graphImage() {
		var id = $('#local_graph_id').val();
		$('#graphimage').attr('src', urlPath + 'graph_image.php?local_graph_id=' + id + '&rra_id=0&graph_start=-32400&graph_height=150&graph_width=600&graph_nolegend=true&counter='+counter).change();
		counter++;
	}

	$(function() {
		if ('<?php print $thold_data['thold_template_id'];?>' == '0') {
			$('#template_enabled').prop('disabled', true);
		}

		if ($('#notify_accounts option').length == 0) {
			$('#row_notify_accounts').hide();
		}

		if ($('#notify_warning option').length == 0) {
			$('#row_notify_warning').hide();
		}

		if ($('#notify_alert option').length == 0) {
			$('#row_notify_alert').hide();
		}

		$('#notify_accounts').multiselect({
			minWidth: '400',
			noneSelectedText: '<?php print __('Select Users(s)', 'thold');?>',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' <?php print __('Users Selected', 'thold');?>';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
						myReturn='<?php print __('All Users Selected', 'thold');?>';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __('All', 'thold');?>',
			uncheckAllText: '<?php print __('None', 'thold');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			open: function() {
				size = $('#notify_accounts option').length * 18 + 20;
				if (size > 100) {
					size = 100;
				}
				$('ul.ui-multiselect-checkboxes').css('height', size + 'px');
			},
			click: function(event, ui) {
				checked=$(this).multiselect('widget').find('input:checked').length;

				if (ui.value == '0') {
					if (ui.checked == true) {
						$('#host').multiselect('uncheckAll');
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).prop('checked', true);
						});
					}
				} else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				} else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
					if (checked > 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
							$(this).prop('disable', true);
						});
					}
				}
			}
		}).multiselectfilter( {
			label: '<?php print __('Search', 'thold');?>', width: '150'
		});

		templateEnableDisable();

		$('#template_enabled').on('change', function() {
			templateEnableDisable();
		});

		<?php if (!isset($thold_data['thold_template_id']) || $thold_data['thold_template_id'] == '') { ?>
		$('#templated_enabled').prop('disabled', true);
		<?php } ?>

		changeTholdType ();
		changeDataType ();

		$('#local_graph_id').on('change', function() {
			graphImage();
		});

		<?php api_plugin_hook_function('thold_edit_javascript', $thold_data);?>
	});

	</script>
	<?php
}

