<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include_once('./include/auth.php');

require_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include_once($config['base_path'] . '/plugins/thold/setup.php');
include_once($config['base_path'] . '/plugins/thold/includes/database.php');
include($config['base_path'] . '/plugins/thold/includes/arrays.php');

$actions = array(
	1 => __('Delete')
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'action':
		form_actions();

		break;
	case 'suspend':
		set_config_option('thold_notification_suspended', 1);
		raise_message('notify_suspend', __('Notification has been Suspended.  Press the Resume button to resume it', 'thold'), MESSAGE_LEVEL_INFO);
		header('Location: notify_queue.php');
		exit();

		break;
	case 'resume':
		set_config_option('thold_notification_suspended', 0);
		raise_message('notify_suspend', __('Notification has been Resumed.  Press the Suspend button to suspend it', 'thold'), MESSAGE_LEVEL_INFO);
		header('Location: notify_queue.php');
		exit();

		break;
	case 'purge':
		db_execute('DELETE FROM notification_queue WHERE event_processed = 0');
		raise_message('notify_purge', __('Pending Notifications have been removed from the database.  Previously sent notification not purged will remain until they age out.', 'thold'), MESSAGE_LEVEL_INFO);
		header('Location: notify_queue.php');
		exit();

		break;
	default:
		top_header();

		notify_queue();

		bottom_footer();
		break;
}

function form_actions() {
	global $actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM notification_queue WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: notify_queue.php?header=false');
		exit;
	}

	/* setup some variables */
	$notify_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$notify_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT type FROM notification_queue WHERE id = ?', array($matches[1]))) . '</li>';
			$notify_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('notify_queue.php');

	html_start_box($actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($notify_array) && cacti_sizeof($notify_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following Notification.', 'Click \'Continue\' to delete all following Notifications.', cacti_sizeof($notify_array)) . "</p>
					<div class='itemlist'><ul>$notify_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete Notification', 'Delete Notifications', cacti_sizeof($notify_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: notify_queue.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($notify_array) ? serialize($notify_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function notify_queue() {
	global $actions, $item_rows;

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
		'has_graphs' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => read_config_option('default_has') == 'on' ? 'true':'false'
			)
	);

	validate_store_request_vars($filters, 'sess_notify_queue');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$state = read_config_option('thold_notification_suspended', true);

	html_start_box(__('Event Notifications'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form_notify' action='notify_queue.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Notifications');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<?php if ($state == 0) {?><input type='button' class='ui-button ui-corner-all ui-widget' id='suspend' value='<?php print __esc('Suspend');?>' title='<?php print __esc('Suspend Notification Processing');?>'><?php } else {?>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='resume' value='<?php print __esc('Resume');?>' title='<?php print __esc('Resume Notification Processing');?>'><?php }?>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc('Purge');?>' title='<?php print __esc('Purge Notification Queue');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'notify_queue.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'notify_queue.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#suspend').click(function() {
					strURL = 'notify_queue.php?action=suspend';
					loadPage(strURL);
				});

				$('#resume').click(function() {
					strURL = 'notify_queue.php?action=resume';
					loadPage(strURL);
				});

				$('#purge').click(function() {
					strURL = 'notify_queue.php?action=purge';
					loadPage(strURL);
				});

				$('#form_notify').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (type LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM notification_queue
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$notify_list = db_fetch_assoc("SELECT *
		FROM notification_queue
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('notify_queue.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Notifications'), 'page', 'main');

	form_start('notify_queue.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'type' => array(
			'display' => __('Type'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The supported notification type.')
		),
		'object_id' => array(
			'display' => __('Object ID'),
			'align'   => 'right',
			'tip'     => __('The Object ID defined by the caller.  Generally its unique \'id\'.')
		),
		'nosort1' => array(
			'display' => __('Object Name'),
			'align'   => 'right',
			'tip'     => __('The name of the object as defined by the caller.')
		),
		'event_time' => array(
			'display' => __('Event Time'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The time of the event as defined by the caller.')
		),
		'event_processed' => array(
			'display' => __('Processed'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('Has the Notification Event been processed.')
		),
		'error_code' => array(
			'display' => __('Errors'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('Did this notification result in an error.  Hover on the error column for details.')
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($notify_list)) {
		foreach ($notify_list as $n) {
			$data = json_decode($n['event_data'], true);

			if (isset($data['object']['name'])) {
				$name = $data['object']['name'];
			} elseif (isset($data['object']['description'])) {
				$name = $data['object']['description'];
			}

			form_alternate_row('line' . $n['id'], false, $disabled);

			form_selectable_cell(filter_value($n['type'], get_request_var('filter')), $n['id']);
			form_selectable_cell($n['id'], $n['id'], '', 'right');
			form_selectable_cell($name, $n['id'], '', 'right');
			form_selectable_cell($n['event_time'] == 0, $n['id'], '', 'right');
			form_selectable_cell($n['event_processed'] == 0 ? __('No'):__('Yes'), $n['id'], '', 'right');
			form_selectable_cell($n['error_code'] > 0 ? __('Errored', 'thold'):__('Success', 'thold'), $n['id'], '', 'right');
			form_checkbox_cell($name, $n['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Notifications') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($notify_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}

