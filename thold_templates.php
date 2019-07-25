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
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include_once($config['base_path'] . '/plugins/thold/includes/arrays.php');
include_once($config['base_path'] . '/lib/xml.php');

set_default_action();

$action = get_nfilter_request_var('action');

if (isset_request_var('drp_action')) {
	do_actions();
}

if (isset_request_var('import')) {
	$action = 'import';
}

switch ($action) {
	case 'add':
		template_add();
		break;
	case 'save':
		if (isset_request_var('save_component_import')) {
			template_import();
		} elseif (isset_request_var('save') && get_nfilter_request_var('save') == 'edit') {
			template_save_edit();
		}
		break;

	case 'import':
		top_header();
		import();
		bottom_footer();

		break;
	case 'export':
		template_export();

		break;
	case 'edit':
		top_header();
		template_edit();
		bottom_footer();

		break;
	default:
		top_header();
		templates();
		bottom_footer();

		break;
}

exit;

function do_actions() {
	global $thold_template_actions;

	/* ================= input validation ================= */
	$drp_action = get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			switch ($drp_action) {
				case 1:
					top_header();

					print '<script text="text/javascript">
						function DownloadStart(url) {
							document.getElementById("download_iframe").src = url;
							setTimeout(function() {
								document.location = "thold_templates.php";
							}, 500);
						}

						$(function() {
							//debugger;
							DownloadStart(\'thold_templates.php?action=export&selected_items=' . get_nfilter_request_var('selected_items') . '\');
						});
					</script>
					<iframe id="download_iframe" style="display:none;"></iframe>';

					bottom_footer();
					exit;
				case 2:
					foreach ($selected_items as $id) {
						if ($id > 0) {
							plugin_thold_log_changes($id, 'deleted_template', array('id' => $id));

							db_execute_prepared('DELETE FROM thold_template
								WHERE id = ?
								LIMIT 1',
								array($id));

							db_execute_prepared('DELETE FROM plugin_thold_template_contact
								WHERE template_id = ?',
								array($id));

							db_execute_prepared('DELETE FROM plugin_thold_host_template
								WHERE thold_template_id = ?',
								array($id));

							db_execute_prepared("UPDATE thold_data
								SET thold_template_id = '', template_enabled = ''
								WHERE thold_template_id = ?",
								array($id));
						}
					}
					break;
				case 3:
					$message = array();
					foreach ($selected_items as $id) {
						$tholds = array_rekey(
							db_fetch_assoc_prepared('SELECT id, local_graph_id
								FROM thold_data
								WHERE thold_template_id = ?',
								array($id)),
							'id', 'local_graph_id'
						);

						if (cacti_sizeof($tholds)) {
							foreach ($tholds as $thold_id => $local_graph_id) {
								if (is_thold_allowed_graph($local_graph_id)) {
									$thold = db_fetch_row_prepared('SELECT *
										FROM thold_data
										WHERE id = ?',
										array($thold_id));

									/* check if thold templated */
									if ($thold['template_enabled'] == "on") {
										$template = db_fetch_row_prepared('SELECT *
											FROM thold_template
											WHERE id = ?',
											array($thold['thold_template_id']));
									} else {
										$template = false;
									}

									if ($thold['name_cache'] == '' || $thold['name'] == '') {
										if ($thold['name'] == '') {
											$thold['name'] = '|data_source_description| [|data_source_name|]';
										}
										$name_cache = thold_expand_string($thold, $thold['name']);
									} else {
										$name_cache = $thold['name_cache'];
									}

									plugin_thold_log_changes($thold_id, 'reapply_name', array('id' => $thold_id));

									db_execute_prepared('UPDATE thold_data
										SET name = ?, name_cache = ?
										WHERE id = ?',
										array($thold['name'], $name_cache, $thold_id));
								} else {
									$message['security'] = __('You are not authorised to modify one or more of the Thresholds selected','thold');
								}
							}
						}
					}
					if (cacti_sizeof($message)) {
						thold_raise_message(implode('<br>', $message), MESSAGE_LEVEL_ERROR);
					}
					break;
			}
			header('Location: thold_templates.php?header=false');
			exit;
		}
	}

	$tholds     = array();
	$thold_list = '';

	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_(.*)$/', $var, $matches)) {
			$id = $matches[1];
			input_validate_input_number($id);

			$template = db_fetch_row_prepared('SELECT id, name
				FROM thold_template
				WHERE id = ?',
				array($id));

			if (cacti_sizeof($template)) {
				$count = db_fetch_cell_prepared('SELECT count(id)
					FROM thold_data
					WHERE thold_template_id = ?',
					array($id));

				$tholds[$id]   = __('%s (%d Thresholds)', html_escape($template['name']), $count, 'thold');
				$tholds_list[] = $id;
			}
		}
	}

	if (cacti_sizeof($tholds)) {
		$thold_list = implode('</li><li>', $tholds);
	}

	top_header();

	form_start('thold_templates.php');

	html_start_box($thold_template_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	$message = '';

	if (cacti_sizeof($tholds)) {
		switch ($drp_action) {
			case 1:
				$message = __('Click \'Continue\' to export the following Threshold Template(s).', 'thold');
				$button = __esc('Export Template(s)', 'thold');
				break;
			case 2:
				$message = __('Click \'Continue\' to delete the following Threshold Template(s).', 'thold');
				$button = __esc('Delete Template(s)', 'thold');
				break;
			case 3:
				$message = __('Click \'Continue\' to Reapply Suggested Names to Thresholds of the following Threshold Template(s).', 'thold');
				$button = __esc('Reapply Suggested Names to Template(s)', 'thold');
				break;
			default:
				$message = __('Invalid action detected, can not proceed', 'thold');
				$button = '';
				break;
		}

		print "	<tr>
			<td colspan='2' class='textArea'>
				<p>$message</p>
				<div class='itemlist'><ul><li>$thold_list</li></ul></div>
			</td>
			</tr>\n";

		$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>";
		if (!empty($button)) {
			$save_html .= "&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue', 'thold') . "' title='$button'>";
		}
	} else {
		raise_message(40);
		header('Location: thold_templates.php?header=false');
		exit;
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . serialize($tholds_list) . "'>
			<input type='hidden' name='drp_action' value='" . $drp_action . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();

	exit;
}

function template_export() {
	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			$output = "<templates>\n";
			foreach ($selected_items as $id) {
				if ($id > 0) {
					$data = db_fetch_row_prepared('SELECT *
						FROM thold_template
						WHERE id = ?',
						array($id));

					if (cacti_sizeof($data)) {
						$data_template_hash = db_fetch_cell_prepared('SELECT hash
							FROM data_template
							WHERE id = ?',
							array($data['data_template_id']));

						$data_source_hash   = db_fetch_cell_prepared('SELECT hash
							FROM data_template_rrd
							WHERE id = ?',
							array($data['data_source_id']));

						unset($data['id']);
						$data['data_template_id'] = $data_template_hash;
						$data['data_source_id']   = $data_source_hash;
						$output .= array2xml($data);
					}
				}
			}

			$output .= "</templates>\n";
			header('Content-type: application/xml');
			header('Content-Disposition: attachment; filename=thold_template_export.xml');
			print $output;
		}
	}
}

function template_add() {
	if ((!isset_request_var('save')) || (get_nfilter_request_var('save') == '')) {
		$data_templates = array_rekey(
			db_fetch_assoc('SELECT id, name
				FROM data_template
				ORDER BY name'),
			'id', 'name'
		);

		top_header();

		form_start('thold_templates.php', 'tholdform');

		html_start_box(__('Threshold Template Creation Wizard', 'thold'), '70%', false, '3', 'center', '');

		if (!isset_request_var('data_template_id')) {
			$data_template_id = 0;
		} else {
			$data_template_id = get_filter_request_var('data_template_id');
		}

		if (empty($data_template_id)) {
			$data_template_id = 0;
		}

		if (!isset_request_var('data_source_id')) {
			$data_source_id = 0;
		} else {
			$data_source_id = get_filter_request_var('data_source_id');
		}

		if (empty($data_source_id)) {
			$data_source_id = 0;
		}

		html_end_box();

		html_start_box('', '70%', false, '3', 'center', '');

		/* display the data template dropdown */
		if ($data_template_id == 0 && sizeof($data_templates) == 1) {
			// Reset template array to ensure first element
			reset($data_templates);

			// now key() function will return first element key
			$data_template_id = key($data_templates);
		}

		?>
		<tr><td><table class='filterTable' align='center'>
			<tr>
				<td>
					<?php print __('Data Template', 'thold');?>
				</td>
				<td>
					<select id='data_template_id' name='data_template_id' onChange='applyFilter("dt")'>
						<option value=''><?php print __('None', 'thold');?></option><?php
						foreach ($data_templates as $id => $name) {
							print "<option value='" . $id . "'" . ($id == $data_template_id ? ' selected' : '') . '>' . html_escape($name) . '</option>';
						}?>
					</select>
				</td>
			</tr><?php

		if ($data_template_id != 0) {
			$data_fields = array();

			$temp = db_fetch_assoc_prepared('SELECT id, local_data_template_rrd_id,
				data_source_name, data_input_field_id
				FROM data_template_rrd
				WHERE local_data_template_rrd_id = 0
				AND data_template_id = ?',
				array($data_template_id));

			foreach ($temp as $d) {
				if ($d['data_input_field_id'] != 0) {
					$temp2 = db_fetch_assoc_prepared('SELECT name, data_name
						FROM data_input_fields
						WHERE id = ?',
						array($d['data_input_field_id']));

					$data_fields[$d['id']] = $temp2[0]['data_name'] . ' (' . $temp2[0]['name'] . ')';
				} else {
					$temp2[0]['name'] = $d['data_source_name'];
					$data_fields[$d['id']] = $temp2[0]['name'];
				}
			}

			if ($data_source_id == 0 && sizeof($data_fields) == 1) {
				// Reset field array to ensure first element
				reset($data_fields);

				// now key() function will return first element key
				$data_source_id = key($data_fields);
			}

			/* display the data source dropdown */
			?>
			<tr>
				<td>
					<?php print __('Data Source', 'thold');?>
				</td>
				<td>
					<select id='data_source_id' name='data_source_id' onChange='applyFilter("ds")'>
						<option value=''><?php print __('None', 'thold');?></option><?php
						foreach ($data_fields as $id => $name) {
							print "<option value='" . $id . "'" . ($id == $data_source_id ? ' selected' : '') . '>' . html_escape($name) . '</option>';
						}?>
					</select>
				</td>
			</tr>
			<?php
		} else {
			print "<tr><td><input type='hidden' id='data_source_id' value=''></td></tr>\n";
		}

		print '<tr><td class="center" colspan="2">&nbsp;</td></tr>';
		if ($data_template_id == 0) {
			print '<tr><td class="center" colspan="2">' . __('Please select a Data Template', 'thold') . '</td></tr>';
		} elseif ($data_source_id == 0) {
			print '<tr><td class="center" colspan="2">' . __('Please select a Data Source', 'thold') . '</td></tr>';
		} else {
			print '<tr><td class="center" colspan="2">' . __('Please press \'Create\' to create your Threshold Template', 'thold') . '</td></tr>';
		}

		if ($data_source_id != 0) {
			print "<tr><td colspan='2'><input type='hidden' name='action' value='add'><input id='save' type='hidden' name='save' value='save'><br><center><input id='go' type='button' value='" . __esc('Create', 'thold') . "'></center></td></tr>";
		} else {
			print "<tr><td colspan=2><input type=hidden name=action value='add'><br><br><br></td></tr>";
		}

		print "</table></td></tr>\n";

		html_end_box();

		form_end();

		?>
		<script type='text/javascript'>

		function applyFilter(type) {
			if (type == 'dt' && $('#data_source_id')) {
				$('#data_source_id').val('');
			}

			if ($('#save')) {
				$('#save').val('');
			}

			loadPageNoHeader('thold_templates.php?action=add&header=false&data_template_id='+
				$('#data_template_id').val()+'&data_source_id='+$('#data_source_id').val(), false, true);
		}

		$(function() {
			$('#go').button().click(function() {
				strURL = $('#tholdform').attr('action');
				json   = $('input, select').serializeObject();
				$.post(strURL, json).done(function(data) {
					$('#main').html(data);
					applySkin();
					window.scrollTo(0, 0);
				});
			});
		});

		</script>
		<?php

		bottom_footer();
	} else {
		if (!isset_request_var('data_template_id')) {
			$data_template_id = 0;
		} else {
			$data_template_id = get_filter_request_var('data_template_id');
		}

		if (!isset_request_var('data_source_id')) {
			$data_source_id = 0;
		} else {
			$data_source_id = get_filter_request_var('data_source_id');
		}

		$temp = db_fetch_row_prepared('SELECT id, hash, name
			FROM data_template
			WHERE id = ?',
			array($data_template_id));

		$save['id']   = '';
		$save['hash'] = get_hash_thold_template(0);
		$save['name'] = $temp['name'];

		$save['data_template_id']   = $data_template_id;
		$save['data_template_hash'] = $temp['hash'];
		$save['data_template_name'] = $temp['name'];
		$save['data_source_id']     = $data_source_id;

		$temp = db_fetch_row_prepared('SELECT id, local_data_template_rrd_id,
			data_source_name, data_input_field_id
			FROM data_template_rrd
			WHERE id = ?',
			array($data_source_id));

		$save['data_source_name']  = $temp['data_source_name'];
		$save['name']             .= ' [' . $temp['data_source_name'] . ']';

		if ($temp['data_input_field_id'] != 0) {
			$temp2['name'] = db_fetch_cell_prepared('SELECT name
				FROM data_input_fields
				WHERE id = ?',
				array($temp['data_input_field_id']));
		} else {
			$temp2['name'] = $temp['data_source_name'];
		}

		$save['data_source_friendly'] = $temp2['name'];
		$save['thold_enabled']        = 'on';
		$save['thold_type']           = 0;
		$save['repeat_alert']         = read_config_option('alert_repeat');

		// Allow other plugins to modify thrshold contents
		$save = api_plugin_hook_function('thold_template_edit_save_thold', $save);

		$id = sql_save($save, 'thold_template');

		if ($id) {
			plugin_thold_log_changes($id, 'modified_template', $save);

			header("Location: thold_templates.php?action=edit&id=$id&header=false");
			exit;
		} else {
			raise_message('thold_save');

			header('Location: thold_templates.php?action=add&header=false');
			exit;
		}
	}
}

function template_save_edit() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('thold_type');
	get_filter_request_var('thold_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_fail_trigger');
	get_filter_request_var('time_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_fail_trigger');
	get_filter_request_var('time_fail_length');
	get_filter_request_var('reset_ack');
	get_filter_request_var('persist_ack');
	get_filter_request_var('syslog_priority');
	get_filter_request_var('syslog_facility');
	get_filter_request_var('thold_warning_type');
	get_filter_request_var('thold_warning_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_warning_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('thold_warning_fail_trigger');
	get_filter_request_var('time_warning_hi', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_warning_low', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('time_warning_fail_trigger');
	get_filter_request_var('time_warning_fail_length');
	get_filter_request_var('bl_ref_time_range');
	get_filter_request_var('bl_pct_down', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('bl_pct_up', FILTER_VALIDATE_FLOAT);
	get_filter_request_var('bl_fail_trigger');
	get_filter_request_var('repeat_alert');
	get_filter_request_var('data_type');
	get_filter_request_var('cdef');
	get_filter_request_var('notify_warning');
	get_filter_request_var('notify_alert');
	get_filter_request_var('snmp_event_severity');
	get_filter_request_var('snmp_event_warning_severity');
	/* ==================================================== */

	/* clean up strings */
	if (isset_request_var('name')) {
		set_request_var('name', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('name'))));
	}

	if (isset_request_var('suggested_name')) {
		set_request_var('suggested_name', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('suggested_name'))));
	}

	if (isset_request_var('snmp_trap_category')) {
		set_request_var('snmp_event_category', db_qstr(trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('snmp_event_category')))));
	}

	// General Information
	$save['id']             = get_request_var('id');
	$save['hash']           = get_hash_thold_template($save['id']);
	$save['name']           = get_nfilter_request_var('name');
	$save['suggested_name'] = get_nfilter_request_var('suggested_name');
	$save['thold_type']     = get_nfilter_request_var('thold_type');
	$save['thold_enabled']  = isset_request_var('thold_enabled') ? 'on' : 'off';
	$save['exempt']         = isset_request_var('exempt')        ? 'on' : '';

	// Acknowledgment
	if (isset_request_var('acknowledgment')) {
		switch(get_nfilter_request_var('acknowledgment')) {
			case 'none':
				$save['reset_ack']   = '';
				$save['persist_ack'] = '';

				break;
			case 'reset_ack':
				$save['reset_ack']   = 'on';
				$save['persist_ack'] = '';

				break;
			case 'persist_ack':
				$save['reset_ack']   = '';
				$save['persist_ack'] = 'on';

				break;
		}
	} else {
		$save['reset_ack']   = '';
		$save['persist_ack'] = '';
	}

	$save['restored_alert'] = isset_request_var('restored_alert') ? 'on' : '';

	// High / Low
	$save['thold_hi']           = get_nfilter_request_var('thold_hi');
	$save['thold_low']          = get_nfilter_request_var('thold_low');
	$save['thold_fail_trigger'] = get_nfilter_request_var('thold_fail_trigger');

	// Time Based
	$save['time_hi']            = get_nfilter_request_var('time_hi');
	$save['time_low']           = get_nfilter_request_var('time_low');
	$save['time_fail_trigger']  = get_nfilter_request_var('time_fail_trigger');
	$save['time_fail_length']   = get_nfilter_request_var('time_fail_length');

	if (isset_request_var('thold_fail_trigger') && get_nfilter_request_var('thold_fail_trigger') != '') {
		$save['thold_fail_trigger'] = get_nfilter_request_var('thold_fail_trigger');
	} else {
		$alert_trigger = read_config_option('alert_trigger');
		if ($alert_trigger != '' && is_numeric($alert_trigger)) {
			$save['thold_fail_trigger'] = $alert_trigger;
		} else {
			$save['thold_fail_trigger'] = 5;
		}
	}

	// High / Low Warnings
	$save['thold_warning_hi']           = get_nfilter_request_var('thold_warning_hi');
	$save['thold_warning_low']          = get_nfilter_request_var('thold_warning_low');
	$save['thold_warning_fail_trigger'] = get_nfilter_request_var('thold_warning_fail_trigger');

	// Time Based Warnings
	$save['time_warning_hi']            = get_nfilter_request_var('time_warning_hi');
	$save['time_warning_low']           = get_nfilter_request_var('time_warning_low');
	$save['time_warning_fail_trigger']  = get_nfilter_request_var('time_warning_fail_trigger');
	$save['time_warning_fail_length']   = get_nfilter_request_var('time_warning_fail_length');

	if (isset_request_var('thold_warning_fail_trigger') && get_nfilter_request_var('thold_warning_fail_trigger') != '') {
		$save['thold_warning_fail_trigger'] = get_nfilter_request_var('thold_warning_fail_trigger');
	} else {
		$alert_trigger = read_config_option('alert_trigger');
		if ($alert_trigger != '' && is_numeric($alert_trigger)) {
			$save['thold_warning_fail_trigger'] = $alert_trigger;
		} else {
			$save['thold_warning_fail_trigger'] = 5;
		}
	}

	// Syslog
	$save['syslog_enabled']  = isset_request_var('syslog_enabled') ? 'on' : '';
	$save['syslog_priority'] = get_request_var('syslog_priority');
	$save['syslog_facility'] = get_request_var('syslog_facility');

	// Command execution
	$save['trigger_cmd_high'] = get_nfilter_request_var('trigger_cmd_high');
	$save['trigger_cmd_low']  = get_nfilter_request_var('trigger_cmd_low');
	$save['trigger_cmd_norm'] = get_nfilter_request_var('trigger_cmd_norm');

	// Email Body
	$save['email_body']      = get_nfilter_request_var('email_body');
	$save['email_body_warn'] = get_nfilter_request_var('email_body_warn');

	// HRULE Display
	$save['thold_hrule_warning'] = get_nfilter_request_var('thold_hrule_warning');
	$save['thold_hrule_alert']   = get_nfilter_request_var('thold_hrule_alert');

	// Baseline settings
	if (isset_request_var('bl_ref_time_range') && get_nfilter_request_var('bl_ref_time_range') != '') {
		$save['bl_ref_time_range'] = get_nfilter_request_var('bl_ref_time_range');
	} else {
		$alert_bl_timerange_def = read_config_option('alert_bl_timerange_def');
		if ($alert_bl_timerange_def != '' && is_numeric($alert_bl_timerange_def)) {
			$save['bl_ref_time_range'] = $alert_bl_timerange_def;
		} else {
			$save['bl_ref_time_range'] = 10800;
		}
	}

	$save['bl_pct_down'] = get_nfilter_request_var('bl_pct_down');
	$save['bl_pct_up']   = get_nfilter_request_var('bl_pct_up');

	if (isset_request_var('bl_fail_trigger') && get_nfilter_request_var('bl_fail_trigger') != '') {
		$save['bl_fail_trigger'] = get_nfilter_request_var('bl_fail_trigger');
	} else {
		$alert_bl_trigger = read_config_option('alert_bl_trigger');
		if ($alert_bl_trigger != '' && is_numeric($alert_bl_trigger)) {
			$save['bl_fail_trigger'] = $alert_bl_trigger;
		} else {
			$save['bl_fail_trigger'] = 3;
		}
	}

	if (isset_request_var('repeat_alert') && get_nfilter_request_var('repeat_alert') != '') {
		$save['repeat_alert'] = get_nfilter_request_var('repeat_alert');
	} else {
		$alert_repeat = read_config_option('alert_repeat');
		if ($alert_repeat != '' && is_numeric($alert_repeat)) {
			$save['repeat_alert'] = $alert_repeat;
		} else {
			$save['repeat_alert'] = 12;
		}
	}

	// SNMP Notification
	if (isset_request_var('snmp_event_category')) {
		$save['snmp_event_category'] = get_nfilter_request_var('snmp_event_category');
		$save['snmp_event_severity'] = get_nfilter_request_var('snmp_event_severity');
	}

	if (isset_request_var('snmp_event_warning_severity')) {
		if (get_nfilter_request_var('snmp_event_warning_severity') > get_nfilter_request_var('snmp_event_severity')) {
			$save['snmp_event_warning_severity'] = get_nfilter_request_var('snmp_event_severity');
		} else {
			$save['snmp_event_warning_severity'] = get_nfilter_request_var('snmp_event_warning_severity');
		}
	}

	// Email Notification
	$save['notify_extra']         = get_nfilter_request_var('notify_extra');
	$save['notify_warning_extra'] = get_nfilter_request_var('notify_warning_extra');
	$save['notify_templated']     = isset_request_var('notify_templated') ? 'on':'';
	$save['notify_warning']       = get_nfilter_request_var('notify_warning');
	$save['notify_alert']         = get_nfilter_request_var('notify_alert');

	// Data Manipulation
	$save['data_type']  = get_nfilter_request_var('data_type');
	$save['cdef']       = get_nfilter_request_var('cdef');
	$save['percent_ds'] = get_nfilter_request_var('percent_ds');
	$save['expression'] = get_nfilter_request_var('expression');

	// Other
	$save['notes'] = get_nfilter_request_var('notes');

	// Allow other plugins to modify thrshold contents
	$save = api_plugin_hook_function('thold_template_edit_save_thold', $save);

	if (!is_error_message()) {
		$id = sql_save($save, 'thold_template');

		if ($id) {
			raise_message(1);

			if (isset_request_var('notify_accounts') && is_array(get_nfilter_request_var('notify_accounts'))) {
				thold_save_template_contacts($id, get_nfilter_request_var('notify_accounts'));
			} elseif (!isset_request_var('notify_accounts')) {
				thold_save_template_contacts($id, array());
			}

			thold_template_update_thresholds($id);

			plugin_thold_log_changes($id, 'modified_template', $save);
		} else {
			raise_message(2);
		}
	}

	if (isset($_SESSION['graph_return'])) {
		$return_to = $_SESSION['graph_return'];
		unset($_SESSION['graph_return']);
		kill_session_var('graph_return');
		header('Location: ' . $return_to);
	} else {
		header('Location: thold_templates.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
	}
}

function template_edit() {
	global $config, $thold_types, $repeatarray, $timearray, $alertarray, $data_types;
	global $syslog_facil_array, $syslog_priority_array;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$id = get_request_var('id');

	$thold_data = db_fetch_row_prepared('SELECT *
		FROM thold_template
		WHERE id = ?',
		array($id));

	$temp = db_fetch_row_prepared('SELECT id, name
		FROM data_template
		WHERE id = ?',
		array($thold_data['data_template_id']));

	$data_templates[$temp['id']] = $temp['name'];

	$temp = db_fetch_row_prepared('SELECT id, data_source_name, data_input_field_id
		FROM data_template_rrd
		WHERE id = ?',
		array($thold_data['data_source_id']));

	$data_fields = array();
	if (cacti_sizeof($temp)) {
		$source_id = $temp['data_input_field_id'];

		if ($source_id != 0) {
			$temp2 = db_fetch_row_prepared('SELECT id, name
				FROM data_input_fields
				WHERE id = ?',
				array($source_id));

			$data_fields[$temp2['id']] = $temp2['name'];
			$data_source_name = $temp2['name'];
		} else {
			$data_fields[$temp['id']]  = $temp['data_source_name'];
			$data_source_name = $temp['data_source_name'];
		}
	} else {
		/* should not be reached */
		cacti_log('ERROR: Thold Template ID:' . $thold_data['id'] . ' references a deleted Data Source.');
		$data_source_name = '';
	}

	$send_notification_array = array();

	$users = db_fetch_assoc("SELECT plugin_thold_contacts.id, plugin_thold_contacts.data,
		plugin_thold_contacts.type, user_auth.full_name
		FROM plugin_thold_contacts, user_auth
		WHERE user_auth.id = plugin_thold_contacts.user_id
		AND plugin_thold_contacts.data != ''
		ORDER BY user_auth.full_name ASC, plugin_thold_contacts.type ASC");

	if (!empty($users)) {
		foreach ($users as $user) {
			$send_notification_array[$user['id']] = $user['full_name'] . ' - ' . ucfirst($user['type']);
		}
	}
	if (isset($thold_data['id'])) {
		$sql = 'SELECT contact_id as id FROM plugin_thold_template_contact WHERE template_id=' . $thold_data['id'];
	} else {
		$sql = 'SELECT contact_id as id FROM plugin_thold_template_contact WHERE template_id=0';
	}

	$step = db_fetch_cell_prepared('SELECT rrd_step
		FROM data_template_data
		WHERE data_template_id = ?',
		array($thold_data['data_template_id']));

	$rra_steps = db_fetch_assoc_prepared('SELECT dspr.steps
		FROM data_template_data AS dtd
		INNER JOIN data_source_profiles AS dsp
	    ON dsp.id=dtd.data_source_profile_id
		INNER JOIN data_source_profiles_rra AS dspr
		ON dsp.id=dspr.data_source_profile_id
	    WHERE dspr.steps > 1
		AND dtd.data_template_id = ?
	    AND dtd.local_data_template_data_id=0
		ORDER BY steps',
		array($thold_data['data_template_id']));

	$reference_types = array();
	foreach ($rra_steps as $rra_step) {
	    $seconds = $step * $rra_step['steps'];
		$reference_types[$seconds] = template_calculate_reference_avg($seconds, 'avg');
	}

	/* calculate percentage ds data sources */
	$data_fields2 = array();
	$temp = db_fetch_assoc_prepared('SELECT id, local_data_template_rrd_id, data_source_name,
		data_input_field_id
		FROM data_template_rrd
		WHERE local_data_template_rrd_id = 0
		AND data_source_name NOT IN(?)
		AND data_template_id = ?',
		array($data_source_name, $thold_data['data_template_id']));

	if (cacti_sizeof($temp)) {
		foreach ($temp as $d) {
			if ($d['data_input_field_id'] != 0) {
				$temp2 = db_fetch_row_prepared('SELECT id, name, data_name
					FROM data_input_fields
					WHERE id = ?
					ORDER BY data_name',
					array($d['data_input_field_id']));

				$data_fields2[$d['data_source_name']] = $temp2['data_name'] . ' (' . $temp2['name'] . ')';
			} else {
				$data_fields2[$d['data_source_name']] = $d['data_source_name'];
			}
		}
	}

	$replacements = db_fetch_assoc_prepared('SELECT DISTINCT field_name
		FROM data_local AS dl
		INNER JOIN (
			SELECT DISTINCT field_name, snmp_query_id
			FROM host_snmp_cache
		) AS hsc
		ON dl.snmp_query_id=hsc.snmp_query_id
		WHERE dl.data_template_id = ?',
		array($thold_data['data_template_id']));

	$nr = array();
	if (cacti_sizeof($replacements)) {
		foreach ($replacements as $r) {
			$nr[] = "<span style='color:blue;'>|query_" . $r['field_name'] . "|</span>";
		}
	}

	$vhf = explode('|', trim(VALID_HOST_FIELDS, '()'));
	if (cacti_sizeof($vhf)) {
		foreach ($vhf as $r) {
			$nr[] = "<span style='color:blue;'>|" . $r . "|</span>";
		}
	}

	$replacements = '<br>' . __('Replacement Fields: %s', implode(', ', $nr), 'thold');

	$dss = db_fetch_assoc_prepared('SELECT data_source_name
		FROM data_template_rrd
		WHERE data_template_id= ?
		AND local_data_id=0',
		array($thold_data['data_template_id']));

	if (cacti_sizeof($dss)) {
		foreach ($dss as $ds) {
			$dsname[] = "<span style='color:blue;'>|ds:" . $ds['data_source_name'] . "|</span>";
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
		'general_header' => array(
			'friendly_name' => __('General Settings', 'thold'),
			'method' => 'spacer',
		),
		'name' => array(
			'friendly_name' => __('Template Name', 'thold'),
			'method' => 'textbox',
			'max_length' => 255,
			'size' => '60',
			'default' => thold_get_default_template_name($thold_data),
			'description' => __('Provide the Threshold Template a meaningful name.', 'thold'),
			'value' => isset($thold_data['name']) ? $thold_data['name'] : ''
		),
		'suggested_name' => array(
			'friendly_name' => __('Suggested Threshold Name', 'thold'),
			'method' => 'textbox',
			'max_length' => 255,
			'size' => '60',
			'default' => thold_get_default_suggested_name($thold_data),
			'description' => __('Provide the suggested name for a Threshold created using this Template.  Standard Device (|host_*|), Data Query (|query_*|) and Input (|input_*|) substitution variables can be used as well as |graph_title| for the Graph Title.', 'thold'),
			'value' => isset($thold_data['suggested_name']) ? $thold_data['suggested_name'] : ''
		),
		'data_template_name' => array(
			'friendly_name' => __('Data Template', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Data Template that you are using. (This cannot be changed)', 'thold'),
			'value' => $thold_data['data_template_id'],
			'array' => $data_templates,
		),
		'data_field_name' => array(
			'friendly_name' => __('Data Field', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Data Field that you are using. (This cannot be changed)', 'thold'),
			'value' => $thold_data['id'],
			'array' => $data_fields,
		),
		'thold_enabled' => array(
			'friendly_name' => __('Enabled', 'thold'),
			'method' => 'checkbox',
			'default' => 'on',
			'description' => __('Whether or not this Threshold will be checked and alerted upon.', 'thold'),
			'value' => isset($thold_data['thold_enabled']) ? $thold_data['thold_enabled'] : ''
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
			'description' => __('Repeat alert after this amount of time has pasted since the last alert.', 'thold'),
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
			'description' => __('If set and Data Source value goes above this number, alert will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['thold_warning_hi']) ? $thold_data['thold_warning_hi'] : ''
		),
		'thold_warning_low' => array(
			'friendly_name' => __('Low Threshold', 'thold'),
			'method' => 'textbox',
			'max_length' => 100,
			'size' => 15,
			'description' => __('If set and Data Source value goes below this number, alert will be triggered.  NOTE: This value must be a RAW number.  The value displayed on the Graph may be modified by a cdef.', 'thold'),
			'value' => isset($thold_data['thold_warning_low']) ? $thold_data['thold_warning_low'] : ''
		),
		'thold_warning_fail_trigger' => array(
			'friendly_name' => __('Min Trigger Duration', 'thold'),
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => __('The amount of time the Data Source must be in a breach condition for an alert to be raised.', 'thold'),
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
			'friendly_name' => __('Min Trigger Duration', 'thold'),
			'method' => 'drop_array',
			'array' => $alertarray,
			'description' => __('The amount of time the Data Source must be in a breach condition for an alert to be raised.', 'thold'),
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
			'friendly_name' => __('Trigger Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 15,
			'default' => read_config_option('thold_warning_time_fail_trigger'),
			'description' => __('The number of times the Data Source must be in breach condition prior to issuing a warning.', 'thold'),
			'value' => isset($thold_data['time_warning_fail_trigger']) ? $thold_data['time_warning_fail_trigger'] : read_config_option('alert_trigger')
		),
		'time_warning_fail_length' => array(
			'friendly_name' => __('Time Period Length', 'thold'),
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => __('The amount of time in the past to check for Threshold breaches.', 'thold'),
			'value' => isset($thold_data['time_warning_fail_length']) ? $thold_data['time_warning_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1)
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
			'friendly_name' => __('Trigger Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 15,
			'description' => __('The number of times the Data Source must be in breach condition prior to issuing an alert.', 'thold'),
			'value' => isset($thold_data['time_fail_trigger']) ? $thold_data['time_fail_trigger'] : read_config_option('thold_time_fail_trigger')
		),
		'time_fail_length' => array(
			'friendly_name' => __('Time Period Length', 'thold'),
			'method' => 'drop_array',
			'array' => $timearray,
			'description' => __('The amount of time in the past to check for Threshold breaches.', 'thold'),
			'value' => isset($thold_data['time_fail_length']) ? $thold_data['time_fail_length'] : (read_config_option('thold_time_fail_length') > 0 ? read_config_option('thold_time_fail_length') : 2)
		),
		'baseline_header' => array(
			'friendly_name' => __('Baseline Monitoring', 'thold'),
			'method' => 'spacer',
		),
		'bl_ref_time_range' => array(
			'friendly_name' => __('Time reference in the past', 'thold'),
			'method' => 'drop_array',
			'array' => $reference_types,
			'description' => __('Specifies the point in the past (based on rrd resolution) that will be used as a reference', 'thold'),
			'value' => isset($thold_data['bl_ref_time_range']) ? $thold_data['bl_ref_time_range'] : read_config_option('alert_bl_timerange_def')
		),
		'bl_pct_up' => array(
			'friendly_name' => __('Baseline Deviation UP', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Specifies allowed deviation in percentage for the upper bound Threshold. If not set, upper bound Threshold will not be checked at all.', 'thold'),
			'value' => isset($thold_data['bl_pct_up']) ? $thold_data['bl_pct_up'] : read_config_option('alert_bl_percent_def')
		),
		'bl_pct_down' => array(
			'friendly_name' => __('Baseline Deviation DOWN', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Specifies allowed deviation in percentage for the lower bound Threshold. If not set, lower bound Threshold will not be checked at all.', 'thold'),
			'value' => isset($thold_data['bl_pct_down']) ? $thold_data['bl_pct_down'] : read_config_option('alert_bl_percent_def')
		),
		'bl_fail_trigger' => array(
			'friendly_name' => __('Baseline Trigger Count', 'thold'),
			'method' => 'textbox',
			'max_length' => 3,
			'size' => 15,
			'description' => __('Number of consecutive times the Data Source must be in a breached condition for an alert to be raised.<br>Leave empty to use default value (Default: %s cycles', read_config_option('alert_bl_trigger'), 'thold'),
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
			'description' => __('Special formatting for the given data.', 'thold'),
			'value' => isset($thold_data['data_type']) ? $thold_data['data_type'] : read_config_option('data_type')
		),
		'cdef' => array(
			'friendly_name' => __('Threshold CDEF', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Apply this CDEF before returning the data.', 'thold'),
			'value' => isset($thold_data['cdef']) ? $thold_data['cdef'] : 0,
			'array' => thold_cdef_select_usable_names()
		),
		'percent_ds' => array(
			'friendly_name' => __('Percent Datasource', 'thold'),
			'method' => 'drop_array',
			'default' => 'NULL',
			'description' => __('Second Datasource Item to use as total value to calculate percentage from.', 'thold'),
			'value' => isset($thold_data['percent_ds']) ? $thold_data['percent_ds'] : 0,
			'array' => $data_fields2,
		),
		'expression' => array(
			'friendly_name' => __('RPN Expression', 'thold'),
			'method' => 'textarea',
			'textarea_rows' => 5,
			'textarea_cols' => 80,
			'default' => '',
			'description' => __('An RPN Expression is an RRDtool Compatible RPN Expression.  Syntax includes all functions below in addition to both Device and Data Query replacement expressions such as <span style="color:blue;">|query_ifSpeed|</span>.  To use a Data Source in the RPN Expression, you must use the syntax: <span style="color:blue;">|ds:dsname|</span>.  For example, <span style="color:blue;">|ds:traffic_in|</span> will get the current value of the traffic_in Data Source for the RRDfile(s) associated with the Graph. Any Data Source for a Graph can be included.<br>Math Operators: <span style="color:blue;">+, -, /, *, &#37;, ^</span><br>Functions: <span style="color:blue;">SIN, COS, TAN, ATAN, SQRT, FLOOR, CEIL, DEG2RAD, RAD2DEG, ABS, EXP, LOG, ATAN, ADNAN</span><br>Flow Operators: <span style="color:blue;">UN, ISINF, IF, LT, LE, GT, GE, EQ, NE</span><br>Comparison Functions: <span style="color:blue;">MAX, MIN, INF, NEGINF, NAN, UNKN, COUNT, PREV</span>%s %s', $replacements, $datasources, 'thold'),
			'value' => isset($thold_data['expression']) ? $thold_data['expression'] : ''
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
		'notify_templated' => array(
			'friendly_name' => __('Notification List Read Only', 'thold'),
			'description' => __('If checked, Threshold Notification Lists in the Template will overwrite those of the Threshold.', 'thold'),
			'method' => 'checkbox',
			'default' => read_config_option('notify_templated'),
			'value' => isset($thold_data['notify_templated']) ? $thold_data['notify_templated'] : ''
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
				'description' => __('Severity to be used for alerts. (Low impact -> Critical impact)', 'thold'),
				'value' => isset($thold_data['snmp_event_severity']) ? $thold_data['snmp_event_severity'] : 3,
				'array' => array(1 => __('Low', 'thold'), 2 => __('Medium', 'thold'), 3 => __('High', 'thold'), 4 => __('Critical', 'thold')),
			),
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
					'array' => array(1 => __('Low', 'thold'), 2 => __('Medium', 'thold'), 3 => __('High', 'thold'), 4 => __('Critical', 'thold')),
				),
			);
		}

		$form_array += $extra;
	}

	if (read_config_option('thold_disable_legacy') != 'on') {
		$extra = array(
			'notify_accounts' => array(
				'friendly_name' => __('Notify accounts', 'thold'),
				'method' => 'drop_multi',
				'description' => __('This is a listing of accounts that will be notified when this Threshold is breached.<br><br><br><br>', 'thold'),
				'array' => $send_notification_array,
				'sql' => $sql,
			),
			'notify_extra' => array(
				'friendly_name' => __('Alert Emails', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => __('You may specify here extra Emails to receive alerts for this Data Source (comma separated)', 'thold'),
				'value' => isset($thold_data['notify_extra']) ? $thold_data['notify_extra'] : ''
			),
			'notify_warning_extra' => array(
				'friendly_name' => __('Warning Emails', 'thold'),
				'method' => 'textarea',
				'textarea_rows' => 3,
				'textarea_cols' => 50,
				'description' => __('You may specify here extra Emails to receive warnings for this Data Source (comma separated)', 'thold'),
				'value' => isset($thold_data['notify_warning_extra']) ? $thold_data['notify_warning_extra'] : ''
			)
		);

		$form_array += $extra;
	} else {
		$extra = array(
			'notify_accounts' => array(
				'method' => 'hidden',
				'value' => 'ignore',
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
			'thold_syslog_enabled' => array('method' => 'hidden', 'value' => ''),
			'thold_syslog_priority' => array('method' => 'hidden', 'value' => ''),
			'thold_syslog_facility' => array('method' => 'hidden', 'value' => '')
		);
	}

	$form_array += $extra;

	if (read_config_option('thold_enable_scripts') == 'on') {
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
		'other_settings' => array(
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
		'data_template_id' => array(
			'method' => 'hidden',
			'value' => (isset($thold_data['data_template_id']) ? $thold_data['data_template_id'] : '0')
		),
		'data_source_id' => array(
			'method' => 'hidden',
			'value' => $thold_data['data_source_id']
		),
		'save' => array(
			'method' => 'hidden',
			'value' => 'edit'
		)
	);

	// Allow plugins to hook the edit form
	$form_array = api_plugin_hook_function('thold_template_edit_form_array', $form_array);

	form_start('thold_templates.php', 'thold');

	html_start_box('', '100%', false, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_array, sizeof($thold_data) ? $thold_data : array())
		)
	);

	html_end_box();

	form_save_button('thold_templates.php?action=edit&id=' . $id, 'return', 'id');

	?>
	<script type='text/javascript'>

	function changeTholdType() {
		switch($('#thold_type').val()) {
		case '0': // Hi/Low
			thold_toggle_hilow('');
			thold_toggle_baseline('none');
			thold_toggle_time('none');

			$('#row_thold_hrule_warning').show();
			$('#row_thold_hrule_alert').show();

			break;
		case '1': // Baseline
			thold_toggle_hilow('none');
			thold_toggle_baseline('');
			thold_toggle_time('none');

			$('#row_thold_hrule_warning').hide();
			$('#row_thold_hrule_alert').hide();

			break;
		case '2': // Time Based
			thold_toggle_hilow('none');
			thold_toggle_baseline('none');
			thold_toggle_time('');

			$('#row_thold_hrule_warning').show();
			$('#row_thold_hrule_alert').show();

			break;
		}
	}

	function changeDataType() {
		switch($('#data_type').val()) {
		case '0':
			$('#row_cdef, #row_percent_ds, #row_expression').hide();

			break;
		case '1':
			$('#row_cdef').show();
			$('#row_percent_ds, #row_expression').hide();

			break;
		case '2':
			$('#row_cdef').hide();
			$('#row_percent_ds, #row_expression').show();

			break;
		case '3':
			$('#row_cdef').hide();
			$('#row_percent_ds').hide();
			$('#row_expression').show();

			break;
		}
	}

	function thold_toggle_hilow(status) {
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
			$('#row_time_header, #row_time_hi, #row_time_low').show();
			$('#row_time_fail_trigger, #row_time_fail_length, #row_time_warning_header').show();
			$('#row_time_warning_hi, #row_time_warning_low').show();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').show();
		} else {
			$('#row_time_header, #row_time_hi, #row_time_low').hide();
			$('#row_time_fail_trigger, #row_time_fail_length, #row_time_warning_header').hide();
			$('#row_time_warning_hi, #row_time_warning_low').hide();
			$('#row_time_warning_fail_trigger, #row_time_warning_fail_length').hide();
		}
	}

	$(function() {
		changeTholdType();
		changeDataType();

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
			noneSelectedText: 'Select Users(s)',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' Users Selected';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
					myReturn='All Users Selected';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __esc('All', 'thold');?>',
			uncheckAllText: '<?php print __esc('None', 'thold');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			open: function() {
				size = $('#notify_accounts option').length * 20 + 20;
				if (size > 140) {
					size = 140;
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
			label: '<?php print __esc('Search', 'thold');?>', width: '150'
		});

		<?php api_plugin_hook_function('thold_template_edit_javascript', $thold_data);?>
	});

	</script>
	<?php
}

function template_calculate_reference_avg($seconds, $suffix = 'avg') {
	$s = ($seconds % 60);
	$m = floor(($seconds % 3600) / 60);
	$h = floor(($seconds % 86400) / 3600);
	$d = floor(($seconds % 2592000) / 86400);
	$M = floor($seconds / 2592000);

	if ($M > 0) {
		if ($suffix == 'avg') {
			return __('%d Months, %d Days, %d Hours, %d Minutes, %d Seconds (Average)', $M, $d, $h, $m, $s, 'thold');
		} else {
			return __('%d Months, %d Days, %d Hours, %d Minutes, %d Seconds', $M, $d, $h, $m, $s, 'thold');
		}
	} elseif ($d > 0) {
		if ($suffix == 'avg') {
			return __('%d Days, %d Hours, %d Minutes, %d Seconds (Average)', $d, $h, $m, $s, 'thold');
		} else {
			return __('%d Days, %d Hours, %d Minutes, %d Seconds', $d, $h, $m, $s, 'thold');
		}
	} elseif ($h > 0) {
		if ($suffix == 'avg') {
			return __('%d Hours, %d Minutes, %d Seconds (Average)', $h, $m, $s, 'thold');
		} else {
			return __('%d Hours, %d Minutes, %d Seconds', $h, $m, $s, 'thold');
		}
	} elseif ($m > 0) {
		if ($suffix == 'avg') {
			return __('%d Minutes, %d Seconds (Average)', $m, $s, 'thold');
		} else {
			return __('%d Minutes, %d Seconds', $m, $s, 'thold');
		}
	} else {
		if ($suffix == 'avg') {
			return __('%d Seconds (Average)', $s, 'thold');
		} else {
			return __('%d Seconds', $s, 'thold');
		}
	}
}

function template_request_validation() {
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
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'associated' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_tt');
	/* ================= input validation ================= */
}

function templates() {
	global $config, $thold_template_actions, $item_rows, $thold_types;

	template_request_validation();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Threshold Templates', 'thold'), '100%', false, '3', 'center', 'thold_templates.php?action=add');

	?>
	<tr class='even'>
		<td>
			<form id='listthold' action='thold_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'thold');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Templates', 'thold');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input id='refresh' type='button' value='<?php print __esc('Go', 'thold');?>' onClick='applyFilter()'>
							<input id='clear' type='button' value='<?php print __esc('Clear', 'thold');?>' onClick='clearFilter()'>
							<input id='import' type='button' value='<?php print __esc('Import', 'thold');?>' onClick='importTemplate()'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'thold_templates.php?header=false&rows=' + $('#rows').val();
				strURL += '&filter=' + $('#filter').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = 'thold_templates.php?header=false&clear=1';
				loadPageNoHeader(strURL);
			}

			function importTemplate() {
				strURL = 'thold_templates.php?header=false&action=import';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#listthold').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	if (strlen(get_request_var('filter'))) {
		$sql_where .= (strlen($sql_where) ? ' AND': 'WHERE') . ' thold_template.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$total_rows = db_fetch_cell('SELECT count(*)
		FROM thold_template');

	$template_list = db_fetch_assoc("SELECT
		thold_template.*,
		(SELECT COUNT(id) FROM thold_data where thold_data.thold_template_id = thold_template.id) thresholds
		FROM thold_template
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('thold_templates.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, __('Templates', 'thold'), 'page', 'main');

	form_start('thold_templates.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, '3', 'center', '');

	$display_text = array(
		'name' => array(
			'display' => __('Name', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'id' => array(
			'display' => __('ID', 'thold'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'thresholds' => array(
			'display' => __('Thresholds', 'thold'),
			'sort' => '',
			'align' => 'right'
		),
		'data_template_name' => array(
			'display' => __('Data Template', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'thold_type' => array(
			'display' => __('Type', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'data_source_name' => array(
			'display' => __('DS Name', 'thold'),
			'sort' => 'ASC',
			'align' => 'left'
		),
		'nosort1' => array(
			'display' => __('High', 'thold'),
			'sort' => '',
			'align' => 'center',
			'tip' => __('The High Warning / Alert values.  NOTE: Baseline values are a percent, all other values are RAW values not modified by a cdef.', 'thold')
		),
		'nosort2' => array(
			'display' => __('Low', 'thold'),
			'sort' => '',
			'align' => 'center',
			'tip' => __('The Low Warning / Alert values.  NOTE: Baseline values are a percent, all other values are RAW values not modified by a cdef.', 'thold')
		),
		'nosort3' => array(
			'display' => __('Trigger', 'thold'),
			'sort' => '',
			'align' => 'left'
		),
		'nosort4' => array(
			'display' => __('Duration', 'thold'),
			'sort' => '',
			'align' => 'left'
		),
		'nosort5' => array(
			'display' => __('Repeat', 'thold'),
			'sort' => '',
			'align' => 'left'
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($template_list)) {
		foreach ($template_list as $template) {
			switch ($template['thold_type']) {
			case 0:					# hi/lo
				$value_hi               = thold_format_number($template['thold_hi'], 2, 1000);
				$value_lo               = thold_format_number($template['thold_low'], 2, 1000);
				$value_trig             = $template['thold_fail_trigger'];
				$value_duration         = '';
				$value_warning_hi       = thold_format_number($template['thold_warning_hi'], 2, 1000);
				$value_warning_lo       = thold_format_number($template['thold_warning_low'], 2, 1000);
				$value_warning_trig     = $template['thold_warning_fail_trigger'];
				$value_warning_duration = '';

				break;
			case 1:					# baseline
				$value_hi   = $template['bl_pct_up'] . (strlen($template['bl_pct_up']) ? '%':'-');
				$value_lo   = $template['bl_pct_down'] . (strlen($template['bl_pct_down']) ? '%':'-');
				$value_warning_hi = '-';
				$value_warning_lo = '-';
				$value_trig = $template['bl_fail_trigger'];

				$step = db_fetch_cell_prepared('SELECT rrd_step
					FROM data_template_data
					WHERE data_template_id = ?
					LIMIT 1',
					array($template['data_template_id']));

				$value_duration = $template['bl_ref_time_range'] / $step;;

				break;
			case 2:					#time
				$value_hi         = thold_format_number($template['time_hi'], 2, 1000);
				$value_lo         = thold_format_number($template['time_low'], 2, 1000);
				$value_warning_hi = thold_format_number($template['thold_warning_hi'], 2, 1000);
				$value_warning_lo = thold_format_number($template['thold_warning_low'], 2, 1000);
				$value_trig       = $template['time_fail_trigger'];
				$value_duration   = $template['time_fail_length'];

				break;
			}

			$name = ($template['name'] == '' ? $template['data_template_name'] . ' [' . $template['data_source_name'] . ']' : $template['name']);
			$name = filter_value($name, get_request_var('filter'));

			$suggested_name = (empty($template['suggseted_name']) ? thold_get_default_suggested_name($template) : $template['suggested_name']);

			form_alternate_row('line' . $template['id']);
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape('thold_templates.php?action=edit&id=' . $template['id']) . '">' . $name  . '</a>', $template['id']);
			form_selectable_cell($template['id'], $template['id'], '', 'right');
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape('thold.php?reset=1&thold_template_id=' . $template['id']) . '">' . $template['thresholds']  . '</a>', $template['id'], '', 'right');
			form_selectable_cell(filter_value($template['data_template_name'], get_request_var('filter')), $template['id']);
			form_selectable_cell($thold_types[$template['thold_type']], $template['id'], '', 'left');
			form_selectable_cell($template['data_source_name'], $template['id'], '', 'left');
			form_selectable_cell($value_hi . ' / ' . $value_warning_hi, $template['id'], '', 'center');
			form_selectable_cell($value_lo . ' / ' . $value_warning_lo, $template['id'], '', 'center');

			$trigger =  plugin_thold_duration_convert($template['data_template_id'], $value_trig, 'alert', 'data_template_id');
			form_selectable_cell((strlen($trigger) ? '<i>' . $trigger . '</i>':'-'), $template['id'], '', 'left');

			$duration = plugin_thold_duration_convert($template['data_template_id'], $value_duration, 'time', 'data_template_id');
			form_selectable_cell((strlen($duration) ? $duration:'-'), $template['id'], '', 'left');
			form_selectable_cell(plugin_thold_duration_convert($template['data_template_id'], $template['repeat_alert'], 'repeat', 'data_template_id'), $template['id'], '', 'left');
			form_checkbox_cell($template['data_template_name'], $template['id']);
			form_end_row();
		}
	} else {
		print "<tr><td><em>" . __('No Threshold Templates', 'thold') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($template_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($thold_template_actions);

	thold_form_end();
}

function import() {
	$form_data = array(
		'import_file' => array(
			'friendly_name' => __('Import Template from Local File', 'thold'),
			'description' => __('If the XML file containing Threshold Template data is located on your local machine, select it here.', 'thold'),
			'method' => 'file'
		),
		'import_text' => array(
			'method' => 'textarea',
			'friendly_name' => __('Import Template from Text', 'thold'),
			'description' => __('If you have the XML file containing Threshold Template data as text, you can paste it into this box to import it.', 'thold'),
			'value' => '',
			'default' => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class' => 'textAreaNotes'
		)
	);

	form_start('thold_templates.php', 'chk', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results', 'thold'), '80%', false, '3', 'center', '');

		print '<tr><td>' . __('Cacti has imported the following items:', 'thold'). '</td></tr>';
		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Threshold Templates', 'thold'), '80%', false, '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => $form_data
		));

	form_hidden_box('save_component_import','1','');

	print "	<tr><td><hr/></td></tr><tr>
			<td class='saveRow'>
				<input type='hidden' name='action' value='save'>
				<input type='submit' value='" . __esc('Import', 'thold') . "' title='" . __esc('Import Threshold Templates', 'thold') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>
			</td>
		</tr>";
	html_end_box();
}

function validate_upload() {
	/* check file tranfer if used */
	if (isset($_FILES['import_file'])) {
		/* check for errors first */
		if ($_FILES['import_file']['error'] != 0) {
			switch ($_FILES['import_file']['error']) {
				case 1:
					thold_raise_message(__('The file is too big.', 'thold'), MESSAGE_LEVEL_ERROR);
					break;
				case 2:
					thold_raise_message(__('The file is too big.', 'thold'), MESSAGE_LEVEL_ERROR);
					break;
				case 3:
					thold_raise_message(__('Incomplete file transfer.', 'thold'), MESSAGE_LEVEL_ERROR);
					break;
				case 4:
					thold_raise_message(__('No file uploaded.', 'thold'), MESSAGE_LEVEL_ERROR);
					break;
				case 6:
					thold_raise_message(__('Temporary folder missing.', 'thold'), MESSAGE_LEVEL_ERROR);
					break;
				case 7:
					thold_raise_message(__('Failed to write file to disk', 'thold'), MESSAGE_LEVEL_ERROR);
					break;
				case 8:
					thold_raise_message(__('File upload stopped by extension', 'thold'), MESSAGE_LEVEL_ERROR);
					break;
			}

			if (is_error_message()) {
				return false;
			}
		}

		/* check mine type of the uploaded file */
		if ($_FILES['import_file']['type'] != 'text/xml') {
			thold_raise_message(__('Invalid file extension.', 'thold'), MESSAGE_LEVEL_ERROR);
			return false;
		}

		return file_get_contents($_FILES['import_file']['tmp_name']);
	}

	raise_message(__('No file uploaded.', 'thold'), MESSAGE_LEVEL_ERROR);

	return false;
}

function template_import() {
	$xml_data = trim(get_nfilter_request_var('import_text'));

	// If we have text, then we were trying to import text, otherwise we are uploading a file for import
	if (empty($xml_data)) {
		$xml_data = validate_upload();
	}

	$errors = 0;

	$return_data = thold_template_import($xml_data);

	if (sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:', 'thold') . '</span> ' . $message;
			cacti_log('NOTE: Template Import Succeeded!.  Message: '. $message, false, 'THOLD');
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:', 'thold') . '</span> ' . $error;
			cacti_log('NOTE: Template Import Error!.  Message: '. $message, false, 'THOLD');
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:', 'thold') . '</span> ' . $message;
			cacti_log('NOTE: Template Import Failed!.  Message: '. $message, false, 'THOLD');
		}
	}

	if (cacti_sizeof($debug_data) > 0) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: thold_templates.php?action=import');
	exit();
}

/* form_end - draws post form end. To be combined with form_start() */
function thold_form_end($ajax = true) {
	global $form_id, $form_action;

	print "</form>\n";

	if ($ajax) { ?>
		<script type='text/javascript'>
		$(function() {
			$('#<?php print $form_id;?>').submit(function(event) {
				if ($('#drp_action').val() != '1') {
					event.preventDefault();
					strURL = '<?php print $form_action;?>';
					strURL += (strURL.indexOf('?') >= 0 ? '&':'?') + 'header=false';
					json =  $('#<?php print $form_id;?>').serializeObject();
					$.post(strURL, json).done(function(data) {
						$('#main').html(data);
						applySkin();
						window.scrollTo(0, 0);
					});
				}
			});
		});
		</script>
		<?php
	}
}

