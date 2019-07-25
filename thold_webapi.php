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

function thold_add_graphs_action_execute() {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$host_id           = get_filter_request_var('host_id');
	$local_graph_id    = get_filter_request_var('local_graph_id');
	$thold_template_id = get_filter_request_var('thold_template_id');

	$message = '';

	$template = db_fetch_row_prepared('SELECT *
		FROM thold_template
		WHERE id = ?',
		array($thold_template_id));

	$temp = db_fetch_row_prepared('SELECT dtr.*
		FROM data_template_rrd AS dtr
		LEFT JOIN graph_templates_item AS gti
		ON gti.task_item_id=dtr.id
		LEFT JOIN graph_local AS gl
		ON gl.id=gti.local_graph_id
		WHERE gl.id = ?
		LIMIT 1' ,
		array($local_graph_id));

	$data_template_id = $temp['data_template_id'];
	$local_data_id    = $temp['local_data_id'];

	$data_source = db_fetch_row_prepared('SELECT *
		FROM data_local
		WHERE id = ?',
		array($local_data_id));

	$data_template_id = $data_source['data_template_id'];

	/* allow duplicate thresholds, but only from differing templates */
	$existing = db_fetch_assoc_prepared('SELECT id
		FROM thold_data
		WHERE local_data_id = ?
		AND data_template_rrd_id = ?
		AND thold_template_id = ?
		AND template_enabled = "on"',
		array($local_data_id, $data_template_id, $template['id']));

	if (!cacti_sizeof($existing) && cacti_sizeof($template)) {
		if ($local_graph_id) {
			$rrd_id = db_fetch_cell_prepared('SELECT id
				FROM data_template_rrd
				WHERE local_data_id = ?
				ORDER BY id LIMIT 1',
				array($local_data_id));

			$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
				FROM graph_templates_item
				WHERE task_item_id = ?
				AND local_graph_id = ?',
				array($rrd_id, $local_graph_id));

			$data_source_name = $template['data_source_name'];

			$save = array();

			$save['host_id']            = $data_source['host_id'];
			$save['local_data_id']      = $local_data_id;
			$save['local_graph_id']     = $local_graph_id;
			$save['data_template_id']   = $data_template_id;
			$save['graph_template_id']  = $graph_template_id;

			$save = thold_create_thold_save_from_template($save, $template);

			$save['name_cache'] = thold_expand_string($save, $save['name']);

			$rrdlist = db_fetch_assoc_prepared('SELECT id, data_input_field_id
				FROM data_template_rrd
				WHERE local_data_id = ?
				AND data_source_name = ?',
				array($local_data_id, $data_source_name));

			if (cacti_sizeof($rrdlist)) {
				foreach ($rrdlist as $rrdrow) {
					$data_rrd_id = $rrdrow['id'];
					$save['data_template_rrd_id'] = $data_rrd_id;

					$existing = db_fetch_assoc_prepared("SELECT id
						FROM thold_data
						WHERE local_data_id = ?
						AND data_template_rrd_id = ?
						AND thold_template_id = ?
						AND template_enabled='on'",
						array($local_data_id, $data_rrd_id, $template['id']));

					if (count($existing) == 0) {
						$save['id'] = 0;
						$id = sql_save($save, 'thold_data');

						if ($id) {
							thold_template_update_threshold($id, $save['thold_template_id']);

							$tname = db_fetch_cell_prepared('SELECT name
								FROM data_template
								WHERE id = ?',
								array($data_template_id));

							$name = $data_source_name;
							if ($rrdrow['data_input_field_id'] != 0) {
								$name = db_fetch_cell_prepared('SELECT name
									FROM data_input_fields
									WHERE id = ?',
									array($rrdrow['data_input_field_id']));
							}

							plugin_thold_log_changes($id, 'created', " $tname [$name]");

							$message .= "Created Threshold for the Graph '$tname' using the Data Source '$name'<br>";
						}
					}
				}
			}
		}
	}

	if (strlen($message)) {
		thold_raise_message($message, MESSAGE_LEVEL_INFO);
	} else {
		thold_raise_message(__('Threshold(s) Already Exists - No Thresholds Created', 'thold'), MESSAGE_LEVEL_INFO);
	}

	if (isset($_SESSION['graph_return'])) {
		$return_to = $_SESSION['graph_return'];

		unset($_SESSION['graph_return']);

		kill_session_var('graph_return');

		header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&':'?') . 'header=false');
	} else {
		header('Location:' . $config['url_path'] . 'plugins/thold/thold.php?header=false');
	}
}

function thold_add_graphs_action_prepare() {
	global $config;

	top_header();

	thold_wizard();

	bottom_footer();
}

function thold_add_graphs_action_array($action) {
	$action['plugin_thold_create'] = __('Create Threshold from Template', 'thold');

	return $action;
}

function thold_wizard() {
	global $config;

	include_once($config['base_path'] . '/lib/html_graph.php');

	if (isset_request_var('usetemplate') && isset_request_var('local_graph_id')) {
		$local_graph_id = get_filter_request_var('local_graph_id');

		$graph_local = db_fetch_row_prepared('SELECT *
			FROM graph_local
			WHERE id = ?',
			array($local_graph_id));

		if (cacti_sizeof($graph_local)) {
			if ($graph_local['snmp_query_id'] > 0) {
				$graph_template_id = $graph_local['snmp_query_graph_id'];
			} else {
				$graph_template_id = $graph_local['graph_template_id'];
			}

			$host_id       = $graph_local['host_id'];
			$data_query_id = $graph_local['snmp_query_id'];
			$snmp_index    = $graph_local['snmp_index'];

			$data_source_info  = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT dl.data_template_id) AS ids
				FROM data_local AS dl
				INNER JOIN data_template_rrd AS dtr
				ON dtr.local_data_id = dl.id
				INNER JOIN graph_templates_item AS gti
				ON dtr.id = gti.task_item_id
				LEFT JOIN thold_data AS td
				ON td.local_graph_id = gti.local_graph_id
				AND td.local_data_id = dl.id
				AND td.data_source_name = dtr.data_source_name
				AND td.data_template_rrd_id = dtr.id
				INNER JOIN thold_template AS tt
				ON tt.id = td.thold_template_id
				WHERE gti.local_graph_id = ?
				AND td.id IS NULL',
				array($local_graph_id));

			if ($data_source_info != '') {
				$templates = db_fetch_assoc('SELECT id, name
					FROM thold_template
					WHERE data_template_id IN(' . $data_source_info . ')
					AND thold_enabled = "on"');

				if (cacti_sizeof($templates)) {
					$thold_template_id = $templates[0]['id'];
					$type_id = 'template';
				} else {
					$thold_template_id = '';
					$type_id = 'thold';
				}

				$parts = explode(',', $data_source_info);
				$data_template_id = $parts[0];
			} else {
				$thold_template_id = '';
				$data_template_id  = '';
				$type_id           = 'thold';
				$templates         = array();
			}

			$data_template_rrd_id = '';

			set_request_var('type_id', $type_id);
			set_request_var('thold_template_id', $thold_template_id);
			set_request_var('my_host_id', $host_id);
			set_request_var('local_graph_id', $local_graph_id);
			set_request_var('data_template_rrd_id', $data_template_rrd_id);
			set_request_var('data_template_id', $data_template_id);
			set_request_var('data_query_id', $data_query_id);
			set_request_var('snmp_index', $snmp_index);
		} else {
			return false;
		}
	} else {
		$type_id              = get_nfilter_request_var('type_id');
		$thold_template_id    = get_filter_request_var('thold_template_id');
		$graph_template_id    = get_filter_request_var('graph_template_id');
		$host_id              = get_filter_request_var('my_host_id');
		$local_graph_id       = get_filter_request_var('local_graph_id');
		$data_template_rrd_id = get_filter_request_var('data_template_rrd_id');
		$data_template_id     = get_filter_request_var('data_template_id');
		$data_query_id        = get_filter_request_var('data_query_id');
		$snmp_index           = get_nfilter_request_var('snmp_index');

		$templates = db_fetch_assoc('SELECT id, name
			FROM thold_template
			WHERE thold_enabled="on"
			ORDER BY name');
	}

	$hosts = get_allowed_devices();

	$show_go    = false;
	$form_array = array();

	if ($type_id == '') {
		$message = __('Threshold Creation Wizard [ Select a Threshold Type ]', 'thold');
	} elseif ($type_id == 'template') {
		if ($thold_template_id != '' && $graph_template_id != '' && $host_id != '' && $snmp_index != '') {
			$show_go = true;
			$message = '<tr><td class="center">' . __('Threshold Creation Wizard [ Enter Custom Data and press \'Create\' to Create your Threshold and Graph ]', 'thold');
		} elseif ($thold_template_id != '' && $graph_template_id != '' && $host_id != '') {
			$message = __('Threshold Creation Wizard [ Select Available Data Query Rows ]', 'thold');
		} elseif ($thold_template_id != '' && $graph_template_id != '') {
			$message = __('Threshold Creation Wizard [ Select a Device ]', 'thold');
		} elseif ($thold_template_id != '') {
			$message = __('Threshold Creation Wizard [ Select a Graph Template ]', 'thold');
		} else {
			$message = __('Threshold Creation Wizard [ Select a Threshold Template ]', 'thold');
		}
	} else {
		if ($host_id != '' && $local_graph_id != '' && $data_template_rrd_id != '') {
			$show_go = true;
			$message = __('Threshold Creation Wizard [ Press \'Create\' to Create your Threshold ]', 'thold') . '</td></tr>';
		} elseif ($host_id != '' && $local_graph_id != '') {
			$message = __('Threshold Creation Wizard [ Select a Data Source ]', 'thold') . '</td></tr>';
		} elseif ($host_id != '') {
			$message = __('Threshold Creation Wizard [ Select a Graph ]', 'thold') . '</td></tr>';
		} else {
			$message = __('Threshold Creation Wizard [ Select a Device ]', 'thold') . '</td></tr>';
		}
	}

	/* display the type dropdown */
	$form_array['spacer']  = array(
		'method' => 'spacer',
		'friendly_name' => __('Threshold Creation Criteria', 'thold'),
	);

	$form_array['type_id'] = array(
		'method' => 'drop_array',
		'friendly_name' => __('Create Type', 'thold'),
		'description' => __('Select a Threshold Type to use for creating this Threshold.', 'thold'),
		'on_change' => 'applyTholdFilter()',
		'value' => $type_id,
		'array' => array(
			'none'     => __('Select a Threshold Type', 'thold'),
			'thold'    => __('Non Templated', 'thold'),
		)
	);

	if (cacti_sizeof($templates)) {
		$form_array['type_id']['array']['template'] = __('Threshold Template', 'thold');
	}

	if ($type_id == 'template') {
		$form_array['thold_template_id'] = array(
			'method' => 'drop_sql',
			'friendly_name' => __('Threshold Template', 'thold'),
			'description' => __('Select a Threshold Template that the Graph and Threshold will be based upon.', 'thold'),
			'on_change' => 'applyTholdFilter()',
			'value' => $thold_template_id,
			'sql' => 'SELECT id, name FROM thold_template WHERE thold_enabled="on" ORDER BY name',
			'none_value' => __('Select a Threshold Template', 'thold')
		);

		$host_ids = array();
		$in_sql = array();

		if ($thold_template_id != '') {
			/* display the host dropdown */
			$graph_templates = array_rekey(
				db_fetch_assoc_prepared('SELECT DISTINCT gt.id, gt.name
					FROM graph_templates AS gt
					INNER JOIN graph_templates_item AS gti
					ON gt.id=gti.graph_template_id
					AND local_graph_id=0
					INNER JOIN data_template_rrd AS dtr
					ON dtr.id=task_item_id
					INNER JOIN thold_template AS tt
					ON tt.data_template_id=dtr.data_template_id
					AND tt.id = ?',
					array($thold_template_id)),
				'id', 'name'
			);

			// Limit ths hosts to only hosts that either have a graph template
			// Listed as multiple, or do not have a threshold created
			// Using the Graph Template listed
			// If the Graph Template is associated with a Data Query
			// make sure that your get all the Data Query based Graph Templates
			if (cacti_sizeof($graph_templates)) {
				$new_templates = array();
				$hql = '';

				$data_query_id = db_fetch_cell('SELECT snmp_query_id
					FROM snmp_query_graph
					WHERE graph_template_id IN (' . implode(', ', array_keys($graph_templates)) . ')');

				if ($data_query_id) {
					$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
						FROM thold_template
						WHERE id = ?',
						array($thold_template_id));

					$templates = db_fetch_assoc_prepared('SELECT DISTINCT sqg.id, sqg.name
						FROM snmp_query_graph AS sqg
						INNER JOIN graph_templates_item AS gti
						ON sqg.graph_template_id = gti.graph_template_id
						AND gti.local_graph_id = 0
						INNER JOIN data_template_rrd AS dtr
						ON gti.task_item_id=dtr.id
						AND dtr.local_data_id = 0
						WHERE snmp_query_id IN(
							SELECT snmp_query_id
							FROM snmp_query_graph
							WHERE graph_template_id IN (' . implode(', ', array_keys($graph_templates)) . ')
						)
						AND dtr.data_template_id = ?',
						array($data_template_id));

					if (cacti_sizeof($templates)) {
						$new_templates = $templates;

						foreach ($templates as $t) {
							$in_sql[$t['id']] = $t['id'];
						}
					}

					$host_templates = array_rekey(
						db_fetch_assoc_prepared('SELECT host_template_id AS id
							FROM host_template_snmp_query
							WHERE snmp_query_id = ?',
							array($data_query_id)),
						'id', 'id');

					if (cacti_sizeof($host_templates)) {
						$hql = 'h.host_template_id IN(' . implode(', ', $host_templates) . ')';
					} else {
						$hosts_ids = array();
					}
				} else {
					$host_template_ids = array_rekey(
						db_fetch_assoc_prepared('SELECT host_template_id
							FROM host_template_graph
							WHERE graph_template_id = ?',
							array($graph_template_id)),
						'host_template_id', 'host_template_id');

					if (cacti_sizeof($host_template_ids)) {
						$hiql = ' WHERE h.host_template_id IN (' . implode(', ', $host_template_ids) . ')';
					} else {
						$hiql = ' WHERE 0 = 1';
					}

					foreach ($graph_templates as $key => $name) {
						$new_templates[] = array('id' => $key, 'name' => $name);

						$in_sql[$key] = $key;
					}

					$host_ids = array_rekey(db_fetch_assoc('SELECT DISTINCT rs.id
						FROM (
							SELECT h.id, gt.id AS gti, gt.multiple, h.host_template_id
							FROM host AS h, graph_templates AS gt
							' . $hiql . '
						) AS rs
						LEFT JOIN graph_local AS gl
						ON gl.graph_template_id=rs.gti
						AND gl.host_id=rs.id
						WHERE (gti IN(' . implode(', ', $in_sql) . ')
						AND host_id IS NULL)
						OR rs.multiple = "on"'), 'id', 'id');
				}
			} else {
				$host_ids = array('0');
			}

			if (!cacti_sizeof($in_sql)) {
				$in_sql[] = 'NULL';
			}

			if (!$data_query_id) {
				$gr_sql = 'SELECT gt.id, gt.name
					FROM graph_templates AS gt
					WHERE id IN (' . implode(', ', $in_sql) . ')
					ORDER BY name';
			} else {
				$gr_sql = 'SELECT id, name
					FROM snmp_query_graph AS gt
					WHERE id IN (' . implode(', ', $in_sql) . ')
					ORDER BY name';
			}

			$form_array['graph_template_id'] = array(
				'method' => 'drop_sql',
				'friendly_name' => __('Graph Template', 'thold'),
				'description' => __('Select a Graph Template to use for the Graph to be created.', 'thold'),
				'on_change' => 'applyTholdFilter()',
				'value' => $graph_template_id,
				'sql' => $gr_sql,
				'none_value' => __('Select a Graph Template', 'thold')
			);
		}

		if ($graph_template_id > 0) {
			if (isset($hql) && $hql != '') {
				$sql = "SELECT id, description AS name FROM host AS h WHERE $hql ORDER BY description";
			} elseif (cacti_sizeof($host_ids)) {
				$sql = 'SELECT id, description AS name FROM host AS h WHERE h.id IN (' . implode(', ', $host_ids) . ') ORDER BY description';
			} else {
				$sql = "SELECT id, description AS name FROM host AS h ORDER BY description";
			}

			$form_array['my_host_id'] = array(
				'method' => 'drop_sql',
				'friendly_name' => __('Device', 'thold'),
				'description' => __('Select a Device to use for the Threshold and Graph to be created.', 'thold'),
				'on_change' => 'applyTholdFilter()',
				'value' => $host_id,
				'sql' => $sql,
				'none_value' => __('Select a Device', 'thold')
			);
		}

		if ($host_id > 0 && $data_query_id > 0) {
			$available_items = array();
			$sort_field = db_fetch_cell_prepared('SELECT sort_field
				FROM host_snmp_query
				WHERE host_id = ?
				AND snmp_query_id = ?',
				array($host_id, $data_query_id));

			if ($sort_field != '') {
				$available_items = array('noneselected' => __('Select and Available Item', 'thold'));
				$available_items += array_rekey(
					db_fetch_assoc_prepared('SELECT hsc.snmp_index AS id, hsc.field_value AS name
						FROM host_snmp_cache AS hsc
						LEFT JOIN (
							SELECT *
							FROM graph_local AS gl
							WHERE snmp_query_id = ?
							AND host_id = ?
							AND snmp_query_graph_id = ?
						) AS gl
						ON hsc.host_id = gl.host_id
						AND hsc.snmp_query_id = gl.snmp_query_id
						AND hsc.snmp_index = gl.snmp_index
						WHERE gl.snmp_index IS NULL
						AND hsc.host_id = ?
						AND hsc.snmp_query_id = ?
						AND field_name = ?',
						array(
							$data_query_id,
							$host_id,
							$graph_template_id,
							$host_id,
							$data_query_id,
							$sort_field
						)
					),
					'id', 'name'
				);
			}

			$form_array['snmp_index'] = array(
				'method' => 'drop_array',
				'friendly_name' => __('Data Query Item', 'thold'),
				'description' => __('Select the applicable row from the Data Query for the Graph and Threshold.', 'thold'),
				'on_change' => 'applyTholdFilter()',
				'value' => $snmp_index,
				'array' => $available_items,
				'default' => ''
			);
		} else {
			$local_graph_id       = 0;
			$data_template_rrd_id = 0;
		}

		if ($data_query_id) {
			$form_array['data_query_id'] = array(
				'method' => 'hidden',
				'value' => $data_query_id
			);

			$form_array['data_template_id'] = array(
				'method' => 'hidden',
				'value' => $data_template_id
			);
		} else {
			$form_array['data_query_id'] = array(
				'method' => 'hidden',
				'value' => '0'
			);

			$form_array['data_template_id'] = array(
				'method' => 'hidden',
				'value' => '0'
			);
		}

		top_header();

		form_start('thold.php?action=save', 'chk');

		html_start_box($message, '100%', false, '3', 'center', '');

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);

		html_end_box(false);

		html_start_box(__('Creation Notes', 'thold'), '100%', false, '3', 'center', '');
		print '<tr><td><p style="padding:0px 5px"><b><font color=\'red\'>' . __('Important Note:', 'thold') .'&nbsp;&nbsp;</font></b>';
		print __('This Threshold will be Templated.  When using the Threshold Template option, you will be prompted for a Threshold Template, Graph Template, Device and possibly Data Query Item information before receiving the \'Create\' prompt at which time, if any overridable Graph or Data Source information is allowed at the Graph and Data Source Template level, you will be prompted for it.  Then, by pressing the \'Create\' button, both the Graph and Threshold will be created simultaneously.', 'thold') . '</p></td></tr>';
		html_end_box(false);

		if ($data_query_id > 0) {
			if ($snmp_index != 'noneselected' && $snmp_index != '' && $host_id > 0 && $thold_template_id > 0 && $graph_template_id > 0) {
				$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
					FROM host
					WHERE id = ?',
					array($host_id));

				$selected_graphs['sg'][$data_query_id][$graph_template_id][encode_data_query_index($snmp_index)] = true;

				thold_graph_new_graphs('thold.php', $host_id, $host_template_id, $selected_graphs);

				form_end();
			}
		} elseif ($host_id > 0 && $thold_template_id > 0 && $graph_template_id > 0) {
			$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
				FROM host
				WHERE id = ?',
				array($host_id));

			$selected_graphs['cg'][$graph_template_id][$graph_template_id] = true;

			thold_graph_new_graphs('thold.php', $host_id, $host_template_id, $selected_graphs);

			form_end();
		}

	} elseif ($type_id == 'thold') {
		$host_template_ids = array_rekey(
			db_fetch_assoc_prepared('SELECT host_template_id
				FROM host_template_graph
				WHERE graph_template_id = ?',
				array($graph_template_id)),
			'host_template_id', 'host_template_id');

		if (cacti_sizeof($host_template_ids)) {
			$hiql = ' AND host_template_id IN (' . implode(', ', $host_template_ids) . ')';
		} else {
			$hiql = ' AND 0 = 1';
		}

		$form_array['my_host_id'] = array(
			'method' => 'drop_callback',
			'friendly_name' => __('Device', 'thold'),
			'description' => __('Select a Device to use for the Threshold and Graph to be created.', 'thold'),
			'on_change' => 'applyTholdFilter()',
			'action' => 'ajax_hosts',
			'id' => $host_id,
			'sql' => 'SELECT id, description AS name FROM host WHERE disabled!="" AND deleted!=""' . $hiql,
			'value' => db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($host_id)),
			'none_value' => __('Select a Device', 'thold')
		);

		if ($host_id > 0) {
			$graphs = get_allowed_graphs('gl.host_id=' . $host_id);

			$ng = array();
			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $g) {
					$ng[$g['local_graph_id']] = $g['title_cache'];
				}
			}

			$form_array['local_graph_id'] = array(
				'method' => 'drop_array',
				'friendly_name' => __('Graph', 'thold'),
				'description' => __('Select the Graph for the Threshold.', 'thold'),
				'on_change' => 'applyTholdFilter()',
				'value' => $local_graph_id,
				'array' => $ng,
				'none_value' => __('Select a Graph', 'thold')
			);
		}

		if ($local_graph_id != '' && $host_id > 0) {
			$dt_sql = 'SELECT DISTINCT dtr.local_data_id
				FROM data_template_rrd AS dtr
				LEFT JOIN graph_templates_item AS gti
				ON gti.task_item_id=dtr.id
				LEFT JOIN graph_local AS gl
				ON gl.id=gti.local_graph_id
				WHERE gl.id = ' . $local_graph_id;

			$local_data_id = db_fetch_cell($dt_sql);

			$dss = array_rekey(
				db_fetch_assoc('SELECT DISTINCT id, data_source_name AS name
					FROM data_template_rrd
					WHERE local_data_id IN (' . $dt_sql . ')
					ORDER BY data_source_name'),
				'id', 'name');

			$form_array['data_template_rrd_id'] = array(
				'method' => 'drop_array',
				'friendly_name' => __('Data Source', 'thold'),
				'description' => __('Select a Data Source for the Threshold.', 'thold'),
				'on_change' => 'applyTholdFilter()',
				'value' => $data_template_rrd_id,
				'array' => $dss,
				'none_value' => __('Select a Data Source', 'thold')
			);

			$form_array['local_data_id'] = array(
				'method' => 'hidden',
				'value' => $local_data_id
			);
		} else {
			$local_graph_id       = 0;
			$data_template_rrd_id = 0;
		}

		top_header();

		form_start('thold.php?action=save', 'chk');

		html_start_box($message, '100%', false, '3', 'center', '');

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);

		if ($data_template_rrd_id > 0) {
			print "<tr class='odd'>
				<td colspan='2' class='saveRow'>
					<input type='submit' value='" . __esc('Create', 'thold') . "'>
				</td>
			</tr>\n";
		}

		html_end_box(false);

		html_start_box(__('Creation Notes', 'thold'), '100%', false, '3', 'center', '');
		print '<tr><td><p style="padding:0px 5px"><b><font color=\'red\'>' . __('Important Note:', 'thold') .'&nbsp;&nbsp;</font></b>';
		print __('This Threshold will <b>NOT</b> be Templated and will only work on existing Graphs.  If you wish to both Create the Graph and the Threshold simultaneously, select Threshold Template from the drop down and continue until the \'Create\' button appears.', 'thold') . '</p></td></tr>';
		html_end_box(false);
	} else {
		top_header();

		form_start('thold.php?action=save', 'chk');

		html_start_box($message, '100%', false, '3', 'center', '');

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);

		html_end_box(false);

		html_start_box(__('Creation Notes', 'thold'), '100%', false, '3', 'center', '');
		print '<tr><td><p style="padding:0px 5px"><b><font color=\'red\'>' . __('Important Note:', 'thold') .'&nbsp;&nbsp;</font></b>';
		print __('This Threshold will <b>NOT</b> be Templated.  You can select either By Graph where you will then select an existing Device, Graph and Data Source before creating your Threshold, or you can select Threshold Template which will allow you to create a Non Templated Threshold and corresponding Graph simultaneously', 'thold') . '</p></td></tr>';
		html_end_box(false);
	}

	if ($local_graph_id > 0) {
		html_start_box(__('Selected Graph', 'thold'), '100%', '', '3', 'center', '');

		print "<tr><td class='center'><p><img class='center' id='graphi' style='max-width:700px;' src='../../graph_image.php?local_graph_id=$local_graph_id&rra_id=0'></p></td></tr>";

		html_end_box();
	}

	form_end();

	?>
	<script type='text/javascript'>

	function applyTholdFilter() {
		strURL  = 'thold.php?action=add&header=false';
		strURL += '&type_id=' + $('#type_id').val();

		if ($('#type_id').val() == 'thold') {
			if ($('#my_host_id').length && $('#my_host_id').val() > 0) {
				strURL += '&my_host_id=' + $('#my_host_id').val();
			}

			if ($('#local_graph_id').length && $('#local_graph_id').val() > 0) {
				strURL += '&local_graph_id=' + $('#local_graph_id').val();
			}

			if ($('#data_template_rrd_id').length && $('#data_template_rrd_id').val() > 0) {
				strURL += '&data_template_rrd_id=' + $('#data_template_rrd_id').val();
			}
		} else {
			if ($('#thold_template_id').length && $('#thold_template_id').val() > 0) {
				strURL += '&thold_template_id=' + $('#thold_template_id').val();
			}

			if ($('#graph_template_id').length && $('#graph_template_id').val() > 0) {
				strURL += '&graph_template_id=' + $('#graph_template_id').val();
			}

			if ($('#data_query_id').length && $('#data_query_id').val() > 0) {
				strURL += '&data_query_id=' + $('#data_query_id').val();
			}

			if ($('#data_template_id').length && $('#data_template_id').val() > 0) {
				strURL += '&data_template_id=' + $('#data_template_id').val();
			}

			if ($('#my_host_id').length && $('#my_host_id').val() != 0) {
				strURL += '&my_host_id=' + $('#my_host_id').val();
			}

			if ($('#snmp_index').length && $('#snmp_index').val() != '') {
				strURL += '&snmp_index=' + $('#snmp_index').val();
			}
		}

		loadPageNoHeader(strURL, false, true);
	}

	$(function() {
		if ($('#type_id').val() == 'template') {
			$('#submit').prev().hide();
			$('#submit').off().click(function(event) {
				event.preventDefault();

				json = $('input, select').serializeObject();
				$.post('thold.php', json).done(function(data) {
					$('#main').html(data);
					applySkin();
					window.scrollTo(0, 0);
				});
			});
		}
	});

	</script>
	<?php

	bottom_footer();
}

function thold_new_graphs_save($host_id) {
	$return_array = false;

	$selected_graphs_array = unserialize(stripslashes(get_nfilter_request_var('selected_graphs_array')));

	$values = array();

	/* form an array that contains all of the data on the previous form */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^g_(\d+)_(\d+)_(\w+)/', $var, $matches)) {
			/* 1: snmp_query_id, 2: graph_template_id, 3: field_name */

			/* this is a new graph from template field */
			if (empty($matches[1])) {
				$values['cg'][$matches[2]]['graph_template'][$matches[3]] = $val;
			} else { /* this is a data query field */
				$values['sg'][$matches[1]][$matches[2]]['graph_template'][$matches[3]] = $val;
			}
		} elseif (preg_match('/^gi_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) {
			/* 1: snmp_query_id, 2: graph_template_id, 3: graph_template_input_id, 4:field_name */

			/* ================= input validation ================= */
			input_validate_input_number($matches[3]);
			/* ==================================================== */

			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc_prepared('SELECT
				graph_template_item_id
				FROM graph_template_input_defs
				WHERE graph_template_input_id = ?',
				array($matches[3]));

			/* loop through each item affected and update column data */
			if (cacti_sizeof($item_list)) {
				foreach ($item_list as $item) {
					/* this is a new graph from template field */
					if (empty($matches[1])) {
						$values['cg'][$matches[2]]['graph_template_item'][$item['graph_template_item_id']][$matches[4]] = $val;
					} else {
						/* this is a data query field */
						$values['sg'][$matches[1]][$matches[2]]['graph_template_item'][$item['graph_template_item_id']][$matches[4]] = $val;
					}
				}
			}
		} elseif (preg_match('/^d_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) {
			/* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:field_name */

			/* this is a new graph from template field */
			if (empty($matches[1])) {
				$values['cg'][$matches[2]]['data_template'][$matches[3]][$matches[4]] = $val;
			} else {
				/* this is a data query field */
				$values['sg'][$matches[1]][$matches[2]]['data_template'][$matches[3]][$matches[4]] = $val;
			}
		} elseif (preg_match('/^c_(\d+)_(\d+)_(\d+)_(\d+)/', $var, $matches)) {
			/* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:data_input_field_id */

			/* this is a new graph from template field */
			if (empty($matches[1])) {
				$values['cg'][$matches[2]]['custom_data'][$matches[3]][$matches[4]] = $val;
			} else { /* this is a data query field */
				$values['sg'][$matches[1]][$matches[2]]['custom_data'][$matches[3]][$matches[4]] = $val;
			}
		} elseif (preg_match('/^di_(\d+)_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:local_data_template_rrd_id, 5:field_name */
			/* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:local_data_template_rrd_id, 5:field_name */

			/* this is a new graph from template field */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values['cg'][$matches[2]]['data_template_item'][$matches[4]][$matches[5]] = $val;
			} else { /* this is a data query field */
				$values['sg'][$matches[1]][$matches[2]]['data_template_item'][$matches[4]][$matches[5]] = $val;
			}
		}
	}

	debug_log_clear('new_graphs');

	foreach ($selected_graphs_array as $form_type => $form_array) {
		$current_form_type = $form_type;

		foreach ($form_array as $form_id1 => $form_array2) {
			/* enumerate information from the arrays stored in post variables */
			if ($form_type == 'cg') {
				$graph_template_id = $form_id1;
			} elseif ($form_type == 'sg') {
				foreach ($form_array2 as $form_id2 => $form_array3) {
					$snmp_index_array = $form_array3;

					$snmp_query_array['snmp_query_id'] = $form_id1;
					$snmp_query_array['snmp_index_on'] = get_best_data_query_index_type($host_id, $form_id1);
					$snmp_query_array['snmp_query_graph_id'] = $form_id2;
				}

				$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
					FROM snmp_query_graph
					WHERE id = ?',
					array($snmp_query_array['snmp_query_graph_id']));
			}

			if ($current_form_type == 'cg') {
				$return_array = create_complete_graph_from_template($graph_template_id, $host_id, '', $values['cg']);

				if (cacti_sizeof($return_array)) {
					thold_raise_message(__('Created graph: %s', html_escape(get_graph_title($return_array['local_graph_id'])), 'thold'), MESSAGE_LEVEL_INFO);
					/* lastly push host-specific information to our data sources */
					foreach ($return_array['local_data_id'] as $item) {
						push_out_host($host_id, $item);
					}
				}
			} elseif ($current_form_type == 'sg') {
				foreach($snmp_index_array as $snmp_index => $true) {
					$snmp_query_array['snmp_index'] = decode_data_query_index($snmp_index, $snmp_query_array['snmp_query_id'], $host_id);

					$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $values['sg']{$snmp_query_array['snmp_query_id']});

					if (cacti_sizeof($return_array)) {
						thold_raise_message(__('Created graph: %s', html_escape(get_graph_title($return_array['local_graph_id'])), 'thold'), MESSAGE_LEVEL_INFO);
						/* lastly push host-specific information to our data sources */
						foreach ($return_array['local_data_id'] as $item) {
							push_out_host($host_id, $item);
						}
					}
				}
			}
		}
	}

	return $return_array;
}

function thold_graph_new_graphs($page, $host_id, $host_template_id, $selected_graphs_array) {
	/* we use object buffering on this page to allow redirection to another page if no
	fields are actually drawn */
	ob_start();

	top_header();

	form_start($page);

	$snmp_query_id = 0;
	$num_output_fields = array();

	foreach ($selected_graphs_array as $form_type => $form_array) {
		foreach ($form_array as $form_id1 => $form_array2) {
			$num_output_fields += html_graph_custom_data($host_id, $host_template_id, $snmp_query_id, $form_type, $form_id1, $form_array2);
		}
	}

	/* flush the current output buffer to the browser */
	ob_end_flush();

	form_hidden_box('host_template_id', $host_template_id, '0');
	form_hidden_box('host_id', $host_id, '0');
	form_hidden_box('save_component_new_graphs', '1', '');
	form_hidden_box('save_autocreate', '0', '');
	form_hidden_box('selected_graphs_array', serialize($selected_graphs_array), '');

	if (isset($_SERVER['HTTP_REFERER']) && !substr_count($_SERVER['HTTP_REFERER'], 'graphs_new')) {
		set_request_var('returnto', basename(sanitize_uri($_SERVER['HTTP_REFERER'])));
	}
	load_current_session_value('returnto', 'sess_grn_returnto', '');

	form_save_button(get_nfilter_request_var('returnto'));
}
