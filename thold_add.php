<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2016 The Cacti Group                                 |
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

$host = $graph = $ds = $dt = '';

if (isset_request_var('hostid') && get_filter_request_var('hostid') != '') {
	$host = get_request_var('hostid');
} else {
	$host = 0;
}

if (isset($_SERVER['HTTP_REFERER']) && (substr_count($_SERVER['HTTP_REFERER'], 'graph_view.php') || substr_count($_SERVER['HTTP_REFERER'], 'graph.php'))) {
	$_SESSION['graph_return'] = $_SERVER['HTTP_REFERER'];
}

if (isset_request_var('graphid') && get_filter_request_var('graphid') != '') {
	$graph = get_request_var('graphid');

	if ($host == 0) {
		$host = db_fetch_cell('SELECT host_id FROM graph_local WHERE id = ' . $graph);
	}
} else {
	$graph = 0;
}

if (isset_request_var('doaction') && get_request_var('doaction') != '') {
	if (get_request_var('doaction') == 1) {
		header('Location:' . $config['url_path'] . "plugins/thold/thold_add.php?graphid=$graph");
	} else {
		$temp = db_fetch_row("SELECT dtr.*
			 FROM data_template_rrd AS dtr
			 LEFT JOIN graph_templates_item AS gti
			 ON gti.task_item_id=dtr.id
			 LEFT JOIN graph_local AS gl
			 ON gl.id=gti.local_graph_id
			 WHERE gl.id=$graph");

		$dt = $temp['data_template_id'];

		header('Location:' . $config['url_path'] . "plugins/thold/thold_templates.php?action=add&data_template_id=$dt");
	}

	exit;
}

if (isset_request_var('dsid') && get_filter_request_var('dsid') != '') {
	$ds = get_request_var('dsid');
}

if (isset_request_var('dt') && get_filter_request_var('dt') != '') {
	if ($ds) {
		$dt = db_fetch_cell("SELECT local_data_id FROM data_template_rrd WHERE id = $ds");
	} else {
		$dt = get_request_var('dt');
	}
}

if (isset_request_var('save') && get_nfilter_request_var('save') == 'save') {
	header("Location: thold.php?header=false&rra=$dt&view_rrd=$ds");
	exit;
}

if (isset_request_var('usetemplate') && get_nfilter_request_var('usetemplate') != '') {
	if (isset_request_var('thold_template_id') && get_filter_request_var('thold_template_id') != '') {
		if (get_request_var('thold_template_id') == '0') {
			thold_add_select_host();
		} else {
			thold_add_graphs_action_execute();
		}
	} else {
		thold_add_graphs_action_prepare($graph);
	}
} else {
	thold_add_select_host();
}

function thold_add_graphs_action_execute() {
	global $config, $host, $graph;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$message = '';
	get_filter_request_var('thold_template_id');

	$template = db_fetch_row('SELECT * FROM thold_template WHERE id=' . get_request_var('thold_template_id'));

	$temp = db_fetch_row("SELECT dtr.*
		 FROM data_template_rrd AS dtr
		 LEFT JOIN graph_templates_item AS gti
		 ON gti.task_item_id=dtr.id
		 LEFT JOIN graph_local AS gl
		 ON gl.id=gti.local_graph_id
		 WHERE gl.id=$graph");

	$data_template_id = $temp['data_template_id'];
	$local_data_id = $temp['local_data_id'];

	$data_source      = db_fetch_row('SELECT * FROM data_local WHERE id=' . $local_data_id);
	$data_template_id = $data_source['data_template_id'];

	/* allow duplicate thresholds, but only from differing templates */
	$existing = db_fetch_assoc('SELECT id
		FROM thold_data
		WHERE rra_id=' . $local_data_id . '
		AND data_id=' . $data_template_id . '
		AND template=' . $template['id'] . " AND template_enabled='on'");

	if (count($existing) == 0 && count($template)) {
		if ($graph) {
			$rrdlookup = db_fetch_cell("SELECT id FROM data_template_rrd WHERE local_data_id=$local_data_id order by id LIMIT 1");
			$grapharr  = db_fetch_row("SELECT graph_template_id FROM graph_templates_item WHERE task_item_id=$rrdlookup and local_graph_id = $graph");

			$desc = db_fetch_cell('SELECT name_cache FROM data_template_data WHERE local_data_id=' . $local_data_id . ' LIMIT 1');

			$data_source_name = $template['data_source_name'];
			$insert = array();

			$name = thold_format_name($template, $graph, $local_data_id, $data_source_name);

			$insert['name']               = $name;
			$insert['host_id']            = $data_source['host_id'];
			$insert['rra_id']             = $local_data_id;
			$insert['graph_id']           = $graph;
			$insert['data_template']	  = $data_template_id;
			$insert['graph_template']	  = $grapharr['graph_template_id'];
			$insert['thold_hi']           = $template['thold_hi'];
			$insert['thold_low']          = $template['thold_low'];
			$insert['thold_fail_trigger'] = $template['thold_fail_trigger'];
			$insert['thold_enabled']      = $template['thold_enabled'];
			$insert['thold_warning_hi']           = $template['thold_warning_hi'];
			$insert['thold_warning_low']          = $template['thold_warning_low'];
			$insert['thold_warning_fail_trigger'] = $template['thold_warning_fail_trigger'];
			$insert['bl_ref_time_range']  = $template['bl_ref_time_range'];
			$insert['bl_pct_down']        = $template['bl_pct_down'];
			$insert['bl_pct_up']          = $template['bl_pct_up'];
			$insert['bl_fail_trigger']    = $template['bl_fail_trigger'];
			$insert['bl_alert']           = $template['bl_alert'];
			$insert['repeat_alert']       = $template['repeat_alert'];
			$insert['notify_extra']       = $template['notify_extra'];
			$insert['cdef']               = $template['cdef'];
			$insert['template']           = $template['id'];
			$insert['template_enabled']   = 'on';

			$rrdlist = db_fetch_assoc("SELECT id, data_input_field_id
				FROM data_template_rrd
				WHERE local_data_id='$local_data_id'
				AND data_source_name='$data_source_name'");

			$int = array('id', 'data_template_id', 'data_source_id', 'thold_fail_trigger', 'bl_ref_time_range', 'bl_pct_down', 'bl_pct_up', 'bl_fail_trigger', 'bl_alert', 'repeat_alert', 'cdef');

			foreach ($rrdlist as $rrdrow) {
				$data_rrd_id = $rrdrow['id'];
				$insert['data_id'] = $data_rrd_id;

				$existing = db_fetch_assoc("SELECT id
					FROM thold_data
					WHERE rra_id='$local_data_id'
					AND data_id='$data_rrd_id'
					AND template='" . $template['id'] . "' AND template_enabled='on'");

				if (count($existing) == 0) {
					$insert['id'] = 0;
					$id = sql_save($insert, 'thold_data');
					if ($id) {
						thold_template_update_threshold ($id, $insert['template']);

						$l = db_fetch_assoc("SELECT name FROM data_template where id=$data_template_id");
						$tname = $l[0]['name'];

						$name = $data_source_name;
						if ($rrdrow['data_input_field_id'] != 0) {
							$l = db_fetch_assoc('SELECT name FROM data_input_fields where id=' . $rrdrow['data_input_field_id']);
							$name = $l[0]['name'];
						}
						plugin_thold_log_changes($id, 'created', " $tname [$name]");
						$message .= "Created threshold for the Graph '<i>$tname</i>' using the Data Source '<i>$name</i>'<br>";
					}
				}
			}
		}
	}

	if (strlen($message)) {
		$_SESSION['thold_message'] = "<font size=-2>$message</font>";
	}else{
		$_SESSION['thold_message'] = "<font size=-2>Threshold(s) Already Exists - No Thresholds Created</font>";
	}

	raise_message('thold_created');

	if (isset($_SESSION['graph_return'])) {
		$return_to = $_SESSION['graph_return'];

		unset($_SESSION['graph_return']);

		kill_session_var('graph_return');

		header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');
	}else{
		header('Location:' . $config['url_path'] . 'plugins/thold/listthold.php?header=false');
	}
}

function thold_add_graphs_action_prepare($graph) {
	global $config;

	top_header();

	html_start_box('Create Threshold from Template', '60%', '', '3', 'center', '');

	form_start('thold_add.php');

	/* get the valid thold templates
	 * remove those hosts that do not have any valid templates
	 */
	$templates  = '';
	$found_list = '';
	$not_found  = '';

	$data_template_id = db_fetch_cell("SELECT dtr.data_template_id
		 FROM data_template_rrd AS dtr
		 LEFT JOIN graph_templates_item AS gti
		 ON gti.task_item_id=dtr.id
		 LEFT JOIN graph_local AS gl
		 ON gl.id=gti.local_graph_id
		 WHERE gl.id=$graph");
	if ($data_template_id != '') {
		if (sizeof(db_fetch_assoc("SELECT id FROM thold_template WHERE data_template_id=$data_template_id"))) {
			$found_list .= '<li>' . get_graph_title($graph) . '</li>';
			if (strlen($templates)) {
				$templates .= ", $data_template_id";
			}else{
				$templates  = "$data_template_id";
			}
		}else{
			$not_found .= '<li>' . get_graph_title($graph) . '</li>';
		}
	}else{
		$not_found .= '<li>' . get_graph_title($graph) . '</li>';
	}

	if (strlen($templates)) {
		$sql = 'SELECT id, name FROM thold_template WHERE data_template_id IN (' . $templates . ') ORDER BY name';
	}else{
		$sql = 'SELECT id, name FROM thold_template ORDER BY name';
	}

	print "<tr><td colspan='2' class='textArea'>\n";

	if (strlen($found_list)) {
		if (strlen($not_found)) {
			print '<p>The following Graph has no Threshold Templates associated with them</p>';
			print '<ul>' . $not_found . '</ul>';
		}

		print '<p>Are you sure you wish to create Thresholds for this Graph?
				<ul>' . $found_list . "</ul>
				</td>
			</tr>\n";

		if (isset_request_var('tree_id')) {
			get_filter_request_var('tree_id');
		}else{
			set_request_var('tree_id', '');
		}

		if (isset_request_var('leaf_id')) {
			get_filter_request_var('leaf_id');
		}else{
			set_request_var('leaf_id', '');
		}

		$form_array = array(
			'general_header' => array(
				'friendly_name' => 'Available Threshold Templates',
				'method' => 'spacer',
			),
			'thold_template_id' => array(
				'method' => 'drop_sql',
				'friendly_name' => 'Select a Threshold Template',
				'description' => '',
				'none_value' => 'None',
				'value' => 'None',
				'sql' => $sql
			),
			'tree_id' => array(
				'method' => 'hidden',
				'value' => get_request_var('tree_id')
			),
			'action2' => array(
				'method' => 'hidden',
				'value' => get_nfilter_request_var('action2')
			),
			'leaf_id' => array(
				'method' => 'hidden',
				'value' => get_request_var('leaf_id')
			),
			'usetemplate' => array(
				'method' => 'hidden',
				'value' => 1
			),
			'graphid' => array(
				'method' => 'hidden',
				'value' => $graph
			)
		);

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
				)
			);
	}else{
		if (strlen($not_found)) {
			print '<p>There are no Threshold Templates associated with the following Graph</p>';
			print '<ul>' . $not_found . '</ul>';
		}

		if (isset_request_var('tree_id')) {
			get_filter_request_var('tree_id');
		}else{
			set_request_var('tree_id', '');
		}

		if (isset_request_var('leaf_id')) {
			get_filter_request_var('leaf_id');
		}else{
			set_request_var('leaf_id', '');
		}

		$form_array = array(
			'general_header' => array(
				'friendly_name' => 'Please select an action',
				'method' => 'spacer',
			),
			'doaction' => array(
				'method' => 'drop_array',
				'friendly_name' => 'Threshold Action',
				'description' => 'You may either create a new Threshold Template, or an non-templated Threshold from this screen.',
				'value' => 'None',
				'array' => array(1 => 'Create a new Threshold', 2 => 'Create a Threshold Template')
			),
			'tree_id' => array(
				'method' => 'hidden',
				'value' => get_request_var('tree_id')
			),
			'action2' => array(
				'method' => 'hidden',
				'value' => get_nfilter_request_var('action2')
			),
			'leaf_id' => array(
				'method' => 'hidden',
				'value' => get_request_var('leaf_id')
			),
			'usetemplate' => array(
				'method' => 'hidden',
				'value' => 1
			),
			'graphid' => array(
				'method' => 'hidden',
				'value' => $graph
			)
		);

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);
	}

	if (!strlen($not_found)) {
		$save_html = "<input type='submit' value='Continue'>";

		print "<tr>
			<td colspan='2' class='saveRow'>
				<input type='hidden' id='action' value='actions'>
				<input type='button' onClick='cactiReturnTo()' value='Cancel' title='Cancel'>
				$save_html
			</td>
		</tr>\n";
	} else {
		$save_html = "<input type='submit' value='Continue'>";

		print "<tr>
			<td colspan='2' class='saveRow'>
				<input type='button' onClick='cactiReturnTo()' value='Cancel' title='Cancel'>
				$save_html
			</td>
		</tr>\n";
	}

	html_end_box();

	bottom_footer();
}

function thold_add_graphs_action_array($action) {
	$action['plugin_thold_create'] = 'Create Threshold from Template';

	return $action;
}

function thold_add_select_host() {
	global $config, $host, $graph, $ds;

	/* get policy information for the sql where clause */
	$current_user = db_fetch_row("SELECT * FROM user_auth WHERE id=" . $_SESSION["sess_user_id"]);

	$hosts = get_allowed_devices();

	top_header();

	form_start('thold_add.php', 'tholdform');

	html_start_box('Threshold Creation Wizard', '50%', '', '3', 'center', '');

	if ($host == '') {
		print '<tr><td class="center">Please select a Device</td></tr>';
	} else if ($graph == '') {
		print '<tr><td class="center">Please select a Graph</td></tr>';
	} else if ($ds == '') {
		print '<tr><td class="center">Please select a Data Source</td></tr>';
	} else {
		print '<tr><td class="center">Please press "Create" to activate your Threshold</td></tr>';
	}

	html_end_box();

	html_start_box('', '50%', '', '3', 'center', '');

	/* display the host dropdown */
	?>
	<tr><td><table class='filterTable' align='center'>
		<tr>
			<td>
				Device
			</td>
			<td>
				<select id='hostid' onChange='applyTholdFilterChange("host")'>
					<option value=''></option><?php
					foreach ($hosts as $row) {
						echo "<option value='" . $row['id'] . "'" . ($row['id'] == $host ? ' selected' : '') . '>' . $row['description'] . ' [' . $row['hostname'] . ']</option>';
					}?>
				</select>
			</td>
		</tr><?php

	if ($host != '') {
		$graphs = db_fetch_assoc('SELECT
			graph_templates_graph.id,
			graph_templates_graph.local_graph_id,
			graph_templates_graph.title_cache
			FROM (graph_templates_graph,graph_local)
			LEFT JOIN host ON (host.id=graph_local.host_id)
			LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
			LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . '))
			WHERE graph_templates_graph.local_graph_id=graph_local.id
			AND graph_templates.id IS NOT NULL
			' . (empty($sql_where) ? '' : "AND $sql_where") . "
			AND host.id = $host
			ORDER BY title_cache");

		/* display the graphs dropdown */
		?>
		<tr>
			<td>
				Graph
			</td>
			<td>
				<select id='graphid' name='graphid' onChange='applyTholdFilterChange("graph")'>
					<option value=''></option><?php
					foreach ($graphs as $row) {
						echo "<option value='" . $row['local_graph_id'] . "'" . ($row['local_graph_id'] == $graph ? ' selected' : '') . '>' . $row['title_cache'] . '</option>';
					}?>
				</select>
			</td>
		</tr><?php
	} else {
		?>
		<tr>
			<td>
				<input type='hidden' id='graphid' name='graphid' value=''>
			</td>
		</tr><?php
	}

	if ($graph != '') {
		$dt_sql = 'SELECT DISTINCT dtr.local_data_id
			FROM data_template_rrd AS dtr
			LEFT JOIN graph_templates_item AS gti
			ON gti.task_item_id=dtr.id
			LEFT JOIN graph_local AS gl
			ON gl.id=gti.local_graph_id
			WHERE gl.id = ' . $graph;

		$dt = db_fetch_cell($dt_sql);

		$dss = db_fetch_assoc('SELECT DISTINCT id, data_source_name
			FROM data_template_rrd
			WHERE local_data_id IN (' . $dt_sql . ') ORDER BY data_source_name');

		/* show the data source options */
		?>
		<tr>
			<td>
				Data Source
			</td>
			<td>
				<input type='hidden' id='dt' name='dt' value='<?php print $dt;?>'>
				<select id='dsid' name='dsid' onChange='applyTholdFilterChange("ds")'>
					<option value=''></option><?php
					foreach ($dss as $row) {
						echo "<option value='" . $row['id'] . "'" . ($row['id'] == $ds ? ' selected' : '') . '>' . $row['data_source_name'] . '</option>';
					}?>
				</select>
			</td>
		</tr></table></td></tr><?php
	} else {
		?>
		<tr>
			<td>
				<input type='hidden' id='dsid' name='dsid' value=''>
			</td>
		</tr></table></td></tr><?php
	}

	if ($ds != '') {
		echo "<tr><td class='center' colspan='2'><input type='hidden' name='save' id='save' value='save'><input id='go' type='button' value='Create' title='Create Threshold'></td></tr>";
	} else {
		echo "<tr><td class='center' colspan='2'></td></tr>";
	}

	html_end_box();

	form_end();

	html_start_box('', '50%', '', '3', 'center', '');

	if ($graph != '') {
		print "<tr><td style='text-align:center'><img id='graphi' src='../../graph_image.php?local_graph_id=$graph&rra_id=0'></td></tr>";
	}

	html_end_box();

	?>
	<script type='text/javascript'>

	function applyTholdFilterChange(target) {
		strURL = 'thold_add.php?header=false&hostid=' + $('#hostid').val();
		if (target != 'host') {
			strURL += '&graphid=' + $('#graphid').val();
		}
		if (target == 'ds') {
			strURL += '&dsid=' + $('#dsid').val();
		}
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#go').click(function() {
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
}
