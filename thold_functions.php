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


// Update automatically 'alert_base_url' if not set and if we are called from the browser
// so that check-thold can pick it up
if (isset($_SERVER["HTTP_HOST"]) && isset($_SERVER["PHP_SELF"]) && read_config_option("alert_base_url") == "") {
	$dir = dirname($_SERVER["PHP_SELF"]);
	if (strpos($dir, '/plugins/') !== false)
		$dir = substr($dir, 0, strpos($dir, '/plugins/'));
	db_execute("replace into settings (name,value) values ('alert_base_url', '" . ("http://" . $_SERVER["HTTP_HOST"] . $dir . "/") . "')");
	
	/* reset local settings cache so the user sees the new settings */
	kill_session_var("sess_config_array");
}

function thold_check_threshold ($rra_id, $data_id, $name, $currentval, $cdef) {
	global $config;
	include_once($config["base_path"] . "/plugins/thold/thold_functions.php");

	// Maybe set an option for these?
	$show = true;
	$debug = false;

	$alert_exempt = read_config_option("alert_exempt");
	/* check for exemptions */
	$weekday=date("l");
	if (($weekday == "Saturday" || $weekday == "Sunday") && $alert_exempt == "on") {
		return;
	}

	/* Get the graph ID - If not found, something is wrong so exit */
	$grapharr = db_fetch_assoc("SELECT DISTINCT local_graph_id FROM graph_templates_item WHERE task_item_id=" . $data_id);
	if (isset($grapharr[0]['local_graph_id']) && $grapharr[0]['local_graph_id'] > 0) {
		$graph_id = $grapharr[0]['local_graph_id'];
	} else {
		//cacti_log("THOLD ERROR: No graph? - HOST: " . $item['host_id'] . " - SQL: SELECT DISTINCT local_graph_id FROM graph_templates_item WHERE task_item_id=$data_id", TRUE);
		return;
	}

	/* Get all the info about the item from the database */
	$item = db_fetch_assoc("select * from thold_data where thold_enabled='on' AND data_id = " . $data_id);

	/* Return if the item doesn't exist, which means its disabled */	
	if (!isset($item[0]))
		return;
	$item = $item[0];

	/* Pull the cached name, if not present, it means that the graph hasn't polled yet */
	$t = db_fetch_assoc("select id,name,name_cache from data_template_data where local_data_id=" . $rra_id . " order by id LIMIT 1");
	if (isset($t[0]["name_cache"]))
		$desc = $t[0]["name_cache"];
	else
		return;

	/* Pull a few default settings */
	$global_alert_address = read_config_option("alert_email");
	$global_notify_enabled = (read_config_option("alert_notify_default") == "on");
	$global_bl_notify_enabled = (read_config_option("alert_notify_bl") == "on");
	$logset = (read_config_option("alert_syslog") == "on");
	$deadnotify = (read_config_option("alert_deadnotify") == "on");
	$realert = read_config_option("alert_repeat");
	$alert_trigger = read_config_option("alert_trigger");
	$alert_bl_trigger = read_config_option("alert_bl_trigger");
	$httpurl = read_config_option("alert_base_url");
	$thold_show_datasource = read_config_option("thold_show_datasource");
	$thold_send_text_only = read_config_option("thold_send_text_only");
	$thold_alert_text = read_config_option('thold_alert_text');

	// Remove this after adding an option for it
	$thold_show_datasource = true;

	if ($cdef != 0)
		$currentval = thold_build_cdef($cdef, $currentval, $rra_id, $data_id);
	$currentval = round($currentval, 4);

	$trigger = ($item["thold_fail_trigger"] == "" ? $alert_trigger : $item["thold_fail_trigger"]);

	if ($show) {
		print "Checking Threshold : \"$desc\"\n";
		print "     Data Source : " . $name;
	}


	$breach_up = ($item["thold_hi"] != "" && $currentval > $item["thold_hi"]);
	$breach_down = ($item["thold_low"] != "" && $currentval < $item["thold_low"]);
		
	$alertstat = $item["thold_alert"];
	$item["thold_alert"] = ($breach_up ? 2 : ($breach_down ? 1 : 0));

	// Make sure the alert text has been set
	if (!isset($thold_alert_text) || $thold_alert_text == '') {
		$thold_alert_text = "<html><body>An alert has been issued that requires your attention.<br><br><strong>Host</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH></body></html>";
	}

	$hostname = db_fetch_assoc('SELECT description, hostname from host WHERE id = ' . $item['host_id']);
	$hostname = $hostname[0];


	// Do some replacement of variables
	$thold_alert_text = str_replace('<DESCRIPTION>', $hostname['description'], $thold_alert_text);
	$thold_alert_text = str_replace('<HOSTNAME>', $hostname['hostname'], $thold_alert_text);
	$thold_alert_text = str_replace('<TIME>', time(), $thold_alert_text);
	$thold_alert_text = str_replace('<GRAPHID>', $graph_id, $thold_alert_text);
	$thold_alert_text = str_replace('<URL>', "<a href='$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1'>$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1</a>", $thold_alert_text);
	$thold_alert_text = str_replace('<CURRENTVALUE>', $currentval, $thold_alert_text);
	$thold_alert_text = str_replace('<THRESHOLDNAME>', $desc, $thold_alert_text);
	$thold_alert_text = str_replace('<DSNAME>', $name, $thold_alert_text);

	$msg = $thold_alert_text;

	if ($thold_send_text_only == 'on') {
		$file_array = '';
	} else {
		$file_array = array(0 => array('local_graph_id' => $graph_id, 'rra_id' => 0, 'file' => "$httpurl/graph_image.php?local_graph_id=$graph_id&rra_id=0&view_type=tree",'mimetype'=>'image/png','filename'=>"$graph_id"));
	}

	$rows = db_fetch_assoc('SELECT plugin_thold_contacts.data FROM plugin_thold_contacts, plugin_thold_threshold_contact WHERE plugin_thold_contacts.id = plugin_thold_threshold_contact.contact_id AND plugin_thold_threshold_contact.thold_id = ' . $item['id']);
	$alert_emails = array();
	if (count($rows)) {
		foreach ($rows as $row) {
			$alert_emails[] = $row['data'];
		}
	}
	$alert_emails = implode(',', $alert_emails);
	$alert_emails .= ',' . $item["notify_extra"];

	db_execute("REPLACE INTO settings (name, value) VALUES ('thold_last_poll', NOW())");

	if ( $breach_up || $breach_down) {
		$item["thold_fail_count"]++;

		// Re-Alert?
		$ra = ($item['thold_fail_count'] > $trigger && $item['repeat_alert'] != 0 && ($item['thold_fail_count'] % ($item['repeat_alert'] == '' ? $realert : $item['repeat_alert'])) == 0);
		if($item["thold_fail_count"] == $trigger || $ra) {
			if ($logset == 1) {
				plugin_thold_syslog_alert_status($desc, $breach_up, ($breach_up ? $item["thold_hi"] : $item["thold_low"]), $currentval, $trigger, $item["thold_fail_count"]);
			}
			$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . " " . ($ra ? "is still" : "went") . " " . ($breach_up ? "above" : "below") . " threshold of " . ($breach_up ? $item["thold_hi"] : $item["thold_low"]) . " with $currentval";
			if ($show)
				print " " . ($ra ? "is still" : "went") . " " . ($breach_up ? "above" : "below") . " threshold of " . ($breach_up ? $item["thold_hi"] : $item["thold_low"]) . " with $currentval\n";
			if (trim($alert_emails) != "")
				thold_mail($alert_emails, '', $subject, $msg, $file_array);
		} elseif ($show) {
				print " " . ($ra ? "is still" : "went") . " " . ($breach_up ? "above" : "below") . " threshold of " . ($breach_up ? $item["thold_hi"] : $item["thold_low"]) . " with $currentval\n";
		}
		$sql = "UPDATE thold_data SET lastread='$currentval'";
		$sql .= ", thold_alert='" . $item["thold_alert"] . "'";
		$sql .= ", thold_fail_count='" . $item["thold_fail_count"] . "'";
		$sql .= ", bl_alert='0'";
		$sql .= "WHERE rra_id='$rra_id' AND data_id=" . $item["data_id"];
		db_execute($sql);
	} else {
		if ($alertstat != 0) {
			if ($logset == 1)
				plugin_thold_syslog_alert_status($desc, "ok", 0, $currentval, $trigger, $item["thold_fail_count"]);
			if ($item["thold_fail_count"] >= $trigger) {
				$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . " restored to normal threshold with value $currentval";
				if ($show)
					print " restored to normal threshold with value $currentval\n";
				if (trim($alert_emails) != "")
					thold_mail($alert_emails, '', $subject, $msg, $file_array);
			} elseif ($show) {
				print "\n";
			}
		} elseif ($show) {
			print " is normal with $currentval\n";
		}
		$sql = "UPDATE thold_data SET lastread='$currentval'";
		$sql .= ", thold_alert='0'";
		$sql .= ", thold_fail_count='0'";
		if ($item["bl_enabled"] == "on") {
			$bl_alert_prev = $item["bl_alert"];
			$bl_count_prev = $item["bl_fail_count"];
			$bl_fail_trigger = ($item["bl_fail_trigger"] == "" ? $alert_bl_trigger : $item["bl_fail_trigger"]);

			$item["bl_alert"] = thold_check_baseline($rra_id, $name, $item["bl_ref_time"], $item["bl_ref_time_range"], $currentval, $item["bl_pct_down"], $item["bl_pct_up"]);
			//echo "bl_alert: " . $item["bl_alert"] . "\n";
			switch($item["bl_alert"]) {
				case -2:	// Exception is active
					// Future
					break;
				case -1:	// Reference value not available
					break;
			
				case 0:		// All clear
					if ($global_bl_notify_enabled && $item["bl_fail_count"] >= $bl_fail_trigger) {
						$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . " restored to normal threshold with value $currentval";
						if ($show)
							print " restored to normal threshold with value $currentval\n";
						if (trim($alert_emails) != "")
							thold_mail($alert_emails, '', $subject, $msg, $file_array);
					}
					$item["bl_fail_count"] = 0;
					break;

				case 1:		// Value is below calculated threshold
				case 2:		// Value is above calculated threshold
					$item["bl_fail_count"]++;

					// Re-Alert?
					$ra = ($item["bl_fail_count"] > $bl_fail_trigger && ($item["bl_fail_count"] % ($item["repeat_alert"] == "" ? $realert : $item["repeat_alert"])) == 0);
					if($global_bl_notify_enabled && ($item["bl_fail_count"] ==  $bl_fail_trigger || $ra)) {
						if ($logset == 1) {
							plugin_thold_syslog_alert_status($desc, $breach_up, ($breach_up ? $item["thold_hi"] : $item["thold_low"]), $currentval, $item["thold_fail_trigger"], $item["thold_fail_count"]);
						}
						$subject = $desc . ($thold_show_datasource ? " [$name]" : '') . " " . ($ra ? "is still" : "went") . " " . ($item["bl_alert"] == 2 ? "above" : "below") . " calculated baseline threshold with $currentval";
						if ($show)
							print " " . ($ra ? "is still" : "went") . " " . ($item["bl_alert"] == 2 ? "above" : "below") . " calculated baseline threshold with $currentval\n";;
						if (trim($alert_emails) != "")
							thold_mail($alert_emails, '', $subject, $msg, $file_array);
					}
					break;
			}
			$sql .= ", bl_alert='" . $item["bl_alert"] . "'";
			$sql .=  ", bl_fail_count='" . $item["bl_fail_count"] . "'";
		}
		$sql .= " WHERE rra_id='$rra_id' AND data_id=" . $item["data_id"];
		db_execute($sql);

		// debugging output
		if ($debug == 1) {
			$filename = $config['base_path'] . '/log/thold.log';
			if (is_writable($filename)) {
				if (!$handle = fopen($filename, 'a')) {
					echo "Cannot open file ($filename)";
					continue;
				}
			} else {
				echo "The file $filename is not writable";
				continue;
			}
			$logdate = date("m-d-y.H:i:s");
			$logout = "$logdate element: $desc alertstat: $alertstat graph_id: $graph_id thold_low: " . $item["thold_low"] . " thold_hi: " . $item["thold_hi"] . " rra: $rra trigger: " . $trigger . " triggerct: " . $item["thold_fail_count"] . " current: $currentval logset: $logset\n";
			fwrite($handle, $logout);
			fclose($handle);
		}
	}
}

function plugin_thold_syslog_alert_status ($desc, $breach_up, $threshld, $currentval, $trigger, $triggerct) {
	define_syslog_variables();
	openlog("CactiTholdLog", LOG_PID | LOG_PERROR, LOG_LOCAL0);

	$syslog_level = read_config_option('thold_syslog_level');
	if (!isset($syslog_level)) {
		$syslog_level = LOG_WARNING;
	} else if (isset($syslog_level) && ($syslog_level > 7 || $syslog_level < 0)) {
		$syslog_level = LOG_WARNING;
	}

	if (strval($breach_up) == "ok") {
		syslog($syslog_level, $desc . " restored to normal with " . $currentval . " at trigger " . $trigger . " out of " . $triggerct);
	} else {
		syslog($syslog_level, $desc . " went " . ($breach_up ? "above" : "below") . " threshold of " . $threshld . " with " . $currentval . " at trigger " . $trigger . " out of " . $triggerct);
	}
}

function plugin_thold_syslog_host_status ($message) {
	define_syslog_variables();
	openlog('CactiTholdLog', LOG_PID | LOG_PERROR, LOG_LOCAL0);

	$syslog_level = read_config_option('thold_syslog_level');
	if (!isset($syslog_level)) {
		$syslog_level = LOG_WARNING;
	} else if (isset($syslog_level) && ($syslog_level > 7 || $syslog_level < 0)) {
		$syslog_level = LOG_WARNING;
	}
	syslog($syslog_level, $message);
}

function thold_cdef_get_usable () {
	$cdef_items = db_fetch_assoc("select * from cdef_items where value = 'CURRENT_DATA_SOURCE' order by cdef_id");
	$cdef_usable = array();
	if (sizeof($cdef_items) > 0) {
		foreach ($cdef_items as $cdef_item) {
			  	$cdef_usable[] =  $cdef_item['cdef_id'];
		}
	}

	return $cdef_usable;
}

function thold_cdef_select_usable_names () {
	$ids = thold_cdef_get_usable();
	$cdefs = db_fetch_assoc("select id, name from cdef");
	$cdef_names[0] = '';
	if (sizeof($cdefs) > 0) {
		foreach ($cdefs as $cdef) {
			if (in_array($cdef['id'], $ids)) {

			  	$cdef_names[$cdef['id']] =  $cdef['name'];
			}
		}
	}
	return $cdef_names;
}

function thold_build_cdef ($id, $value, $rra, $ds) {
	$oldvalue = $value;

	$cdefs = db_fetch_assoc("select * from cdef_items where cdef_id = $id order by sequence");
	if (sizeof($cdefs) > 0) {
		foreach ($cdefs as $cdef) {
		     	if ($cdef['type'] == 4) {
				$cdef['type'] = 6;
				switch ($cdef['value']) {
				case 'CURRENT_DATA_SOURCE':
					$cdef['value'] = $oldvalue; // get_current_value($rra, $ds, 0);
					break;
				case 'CURRENT_GRAPH_MAXIMUM_VALUE':
					$cdef['value'] = get_current_value($rra, 'upper_limit', 0);
					break;
				case 'CURRENT_GRAPH_MINIMUM_VALUE':
					$cdef['value'] = get_current_value($rra, 'lower_limit', 0);
					break;
				case 'CURRENT_DS_MINIMUM_VALUE':
					$cdef['value'] = get_current_value($rra, 'rrd_minimum', 0);
					break;
				case 'CURRENT_DS_MAXIMUM_VALUE':
					$cdef['value'] = get_current_value($rra, 'rrd_maximum', 0);
					break;
				case 'VALUE_OF_HDD_TOTAL':
					$cdef['value'] = get_current_value($rra, 'hdd_total', 0);
					break;
				case 'ALL_DATA_SOURCES_NODUPS': // you can't have DUPs in a single data source, really...
				case 'ALL_DATA_SOURCES_DUPS':
					$cdef['value'] = 0;
					$all_dsns = array();
					$all_dsns = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE local_data_id = $rra");
					if(is_array($all_dsns)) {
						foreach ($all_dsns as $dsn) {
							$cdef['value'] += get_current_value($rra, $dsn['data_source_name'], 0);
						}
					}
					break;
				default:
					print "CDEF property not implemented yet: " . $cdef['value'];
					return $oldvalue;
					break;
				}
			}
			$cdef_array[] = $cdef;
		}
	}
	$x = count($cdef_array);

	if ($x == 0) return $oldvalue;

	$stack = array(); // operation stack for RPN
	array_push($stack, $cdef_array[0]); // first one always goes on
	$cursor = 1; // current pointer through RPN operations list

	while($cursor < $x) {
		$type = $cdef_array[$cursor]['type'];
		switch($type) {
			case 6:
				array_push($stack, $cdef_array[$cursor]);
				break;
			case 2:
				// this is a binary operation. pop two values, and then use them.
				$v1 = array_pop($stack);
				$v2 = array_pop($stack);
				$result = thold_rpn($v2['value'], $v1['value'], $cdef_array[$cursor]['value']);
				// put the result back on the stack.
				array_push($stack, array('type'=>6,'value'=>$result));
				break;
			default:
				print "Unknown RPN type: ";
				print $cdef_array[$cursor]['type'];
				return $oldvalue;
				break;
		}
		$cursor++;
	}
	return $result;
}

function thold_rpn ($x, $y, $z) {
	switch ($z) {
		case 1:
			return $x + $y;
			break;
		case 2:
			return $x - $y;
			break;
		case 3:
			return $x * $y;
			break;
		case 4:
			return $x / $y;
			break;
		case 5:
			return $x % $y;
			break;
	}
	return '';
}

function delete_old_thresholds () {
	$result = db_fetch_assoc('SELECT id, data_id, rra_id FROM thold_data');
	foreach ($result as $row) {
		$ds_item_desc = db_fetch_assoc("select id, data_source_name from data_template_rrd where id = " . $row["data_id"]);
		if (!isset($ds_item_desc[0]["data_source_name"])) {
			db_execute('DELETE FROM thold_data WHERE id=' . $row['id']);
			db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $row['id']);
		}
	}
}

function thold_rrd_last($rra, $cf) {
	global $config;
	$last_time_entry = rrdtool_execute("last " . trim(get_data_source_path($rra, true)) . " " . trim($cf), false, RRDTOOL_OUTPUT_STDOUT);
	return trim($last_time_entry);
}

function get_current_value($rra, $ds, $cdef = 0) {
	global $config;
	$last_time_entry = thold_rrd_last($rra, "AVERAGE");

	// This should fix and "did you really mean month 899 errors", this is because your RRD has not polled yet
	if ($last_time_entry == -1)
		$last_time_entry = time();

	$data_template_data = db_fetch_row("SELECT * FROM data_template_data WHERE local_data_id = $rra");

	$step = $data_template_data['rrd_step'];

	// Round down to the nearest 100
	$last_time_entry = (intval($last_time_entry /100) * 100) - $step;
	$last_needed = $last_time_entry + $step;

	$result = rrdtool_function_fetch($rra, trim($last_time_entry), trim($last_needed));

	// Return Blank if the data source is not found (Newly created?)
	if (!isset( $result["data_source_names"])) return "";

	$idx = array_search($ds, $result["data_source_names"]);

	// Return Blank if the value was not found (Cache Cleared?)
	if (!isset($result["values"][$idx][0]))
			return "";

	$value = $result["values"][$idx][0];
	if ($cdef != 0)
		$value = thold_build_cdef($cdef, $value, $rra, $ds);
	return round($value, 4);
}

function thold_get_ref_value($rra_id, $ds, $ref_time, $time_range) {
	global $config;

	$real_ref_time = time() - $ref_time;

	$result = rrdtool_function_fetch($rra_id, $real_ref_time - ($time_range / 2), $real_ref_time + ($time_range / 2));

	//print_r($result);
	//echo "\n";
	$idx = array_search($ds, $result["data_source_names"]);
	if(count($result["values"][$idx]) == 0) {
		return false;
	}

	return $result["values"][$idx];
}

/* thold_check_exception_periods
 @to-do: This function should check "globally" declared exceptions, like
 holidays etc., as well as exceptions bound to the speciffic $rra_id. $rra_id 
 should inherit exceptions that are assigned on the higher level (i.e. device).
 
*/
function thold_check_exception_periods($rra_id, $ref_time, $ref_range) {
	// TO-DO
	// Check if the reference time falls into global exceptions
	// Check if the current time falls into global exceptions
	// Check if $rra_id + $ds have an exception (again both reference time and current time)
	// Check if there are inheritances
	
	// More on the exception concept:
	// -Exceptions can be one time and recurring
	// -Exceptions can be global and assigned to:
	// 	-templates
	//	-devices
	//	-data sources
	//

	return false;
}

/* thold_check_baseline - 
 Should be called after hard limits have been checked and only when they are OK
 
 The function "goes back in time" $ref_time seconds and retrieves the data 
 for $ref_range seconds. Then it finds minimum and maximum values and calculates
 allowed deviations from those values.
 
 @arg $rra_id - the data source to check the data
 @arg $ds - Index of the data_source in the RRD
 @arg $ref_time - Integer value representing reference offset in seconds
 @arg $ref_range - Integer value indicating reference time range in seconds
 @arg $current_value - Current "value" of the data source
 @arg $pct_down - Allowed baseline deviation in % - if set to false will not be considered
 @arg $pct_up - Allowed baseline deviation in % - if set to false will not be considered
 
 @returns (integer) - integer value that indicates status
   -2 if the exception is active
   -1 if the reference value is not available
   0 if the current value is within the boundaries
   1 if the current value is below the calculated threshold
   2 if the current value is above the calculated threshold
 */
function &thold_check_baseline($rra_id, $ds, $ref_time, $ref_range, $current_value, $pct_down, $pct_up) {
	global $debug;
	
	// First let's check if either current time or reference time falls within either
	// globally set exceptions or rra itself has some exceptios
	
	if(thold_check_exception_periods($rra_id, $ref_time, $ref_range)) {
		return -2;	// An exception period is blocking us out...
	}
	$ref_values = thold_get_ref_value($rra_id, $ds, $ref_time, $ref_range);
	
	if(!$ref_values) {
		// if($debug) echo "Baseline reference value not yet established!\n";
		return -1; // Baseline reference value not yet established
	}
	$current_value = get_current_value($rra_id,$ds);
	$ref_value_max = round(max($ref_values));
	$ref_value_min = round(min($ref_values));
	
	$blt_low = false;
	$blt_high = false;
	
	if($pct_down != "") {
		$blt_low = round($ref_value_min - ($ref_value_min * $pct_down / 100));
	}
	
	if($pct_up != "") {
		$blt_high = round($ref_value_max + ($ref_value_max * $pct_up / 100));
	}
	
	$failed = 0;
	
	// Check low boundary
	if($blt_low && $current_value < $blt_low) {
		$failed = 1;
	}
	
	// Check up boundary
	if($failed == 0 && $blt_high && $current_value > $blt_high) {
		$failed = 2;
	}
	
	if($debug) {
		echo "RRA: $rra_id : $ds\n";
		echo "Ref. values count: ". count($ref_values) . "\n";
		echo "Ref. value (min): $ref_value_min\n";
		echo "Ref. value (max): $ref_value_max\n";
		echo "Cur. value: $current_value\n";
		echo "Low bl thresh: $blt_low\n";
		echo "High bl thresh: $blt_high\n";
		echo "Check against baseline: ";
		switch($failed) {
			case 0:
			echo "OK";
			break;
			
			case 1:
			echo "FAIL: Below baseline threshold!";
			break;
			
			case 2:
			echo "FAIL: Above baseline threshold!";
			break;
		}
		echo "\n";
		echo "------------------\n";
	}
	
	return $failed;
}

function save_thold() {
	global $rra, $banner, $hostid;

	$template_enabled = isset($_POST["template_enabled"]) && $_POST["template_enabled"] == 'on' ? $_POST["template_enabled"] : "off";
	if ($template_enabled == 'on') {
		input_validate_input_number($_POST['rra']);
		input_validate_input_number($_POST['data_template_rrd_id']);

		$rra_id = $_POST["rra"];
		$data_id = $_POST["data_template_rrd_id"];
		$data = db_fetch_row("SELECT id, template FROM thold_data WHERE rra_id = $rra_id AND data_id = $data_id");
		thold_template_update_threshold ($data['id'], $data['template']);
		$banner = "<font color=green><strong>Record Updated</strong></font>";
		return true;
	}

	// Make sure this is defined
	$_POST["bl_enabled"] = isset($_POST["bl_enabled"]) ? 'on' : "off";
	$_POST["thold_enabled"] = isset($_POST["thold_enabled"]) ? 'on' : "off";
	$_POST["template_enabled"] = isset($_POST["template_enabled"]) ? 'on' : "off";


	$banner = "<font color=red><strong>";
	if ((!isset($_POST['thold_hi']) || trim($_POST['thold_hi']) == "") && (!isset($_POST['thold_low']) || trim($_POST['thold_low']) == "") && (!isset($_POST['bl_ref_time']) || trim($_POST['bl_ref_time'])  == "")) {
		$banner .= "You must specify either &quot;High Threshold&quot; or &quot;Low Threshold&quot; or both!<br>RECORD NOT UPDATED!</strong></font>";
		return;
	}

	if (isset($_POST['thold_hi']) && isset($_POST['thold_low']) && trim($_POST['thold_hi']) != "" && trim($_POST['thold_low']) != "" && round($_POST['thold_low'],4) >= round($_POST['thold_hi'],4)) {
		$banner .= "Impossible thresholds: &quot;High Threshold&quot; smaller than or equal to &quot;Low Threshold&quot;<br>RECORD NOT UPDATED!</strong></font>";
		return;
	}

	if($_POST["bl_enabled"] == "on") {
		$banner .= "With baseline thresholds enabled ";
		if(!thold_mandatory_field_ok("bl_ref_time", "Reference in the past")) {
			return;
		}
		if((!isset($_POST['bl_pct_down']) || trim($_POST['bl_pct_down']) == "") && (!isset($_POST['bl_pct_up']) || trim($_POST['bl_pct_up']) == "")) {
			$banner .= "You must specify either &quot;Baseline deviation UP&quot; or &quot;Baseline deviation DWON&quot; or both!<br>RECORD NOT UPDATED!</strong></font>";
			return;
		}
	}

	$existing = db_fetch_assoc("SELECT id FROM thold_data WHERE rra_id = " . $rra . " AND data_id = " . $_POST["data_template_rrd_id"]);
	$save = array();
	if (count($existing)) {
		$save['id'] = $existing[0]['id'];
	} else {
		$save['id'] = 0;
		$save['template'] = '';
	}


	input_validate_input_number(get_request_var('thold_hi'));
	input_validate_input_number(get_request_var('thold_low'));
	input_validate_input_number(get_request_var('thold_fail_trigger'));
	input_validate_input_number(get_request_var('repeat_alert'));
	input_validate_input_number(get_request_var('cdef'));
	input_validate_input_number($_POST['rra']);
	input_validate_input_number($_POST['data_template_rrd_id']);

	$save['host_id'] = $hostid;
	$save['data_id'] = $_POST["data_template_rrd_id"];
	$save['rra_id'] = $_POST["rra"];
	$save['thold_hi'] = (trim($_POST['thold_hi'])) == '' ? '' : round($_POST['thold_hi'],4);
	$save['thold_low'] = (trim($_POST['thold_low'])) == '' ? '' : round($_POST['thold_low'],4);
	$save['thold_fail_trigger'] = (trim($_POST['thold_fail_trigger'])) == '' ? '' : $_POST['thold_fail_trigger'];
	$save['thold_enabled'] = isset($_POST['thold_enabled']) ? $_POST['thold_enabled'] : '';
	$save['bl_enabled'] = isset($_POST['bl_enabled']) ? $_POST['bl_enabled'] : '';
	$save['repeat_alert'] = (trim($_POST['repeat_alert'])) == '' ? '' : $_POST['repeat_alert'];
	$save['notify_extra'] = (trim($_POST['notify_extra'])) == '' ? '' : $_POST['notify_extra'];
	$save['cdef'] = (trim($_POST['cdef'])) == '' ? '' : $_POST['cdef'];
	$save['template_enabled'] = $_POST['template_enabled'];

	if($_POST["bl_enabled"] == "on") {
		input_validate_input_number(get_request_var('bl_ref_time'));
		input_validate_input_number(get_request_var('bl_ref_time_range'));
		input_validate_input_number(get_request_var('bl_pct_down'));
		input_validate_input_number(get_request_var('bl_pct_up'));
		input_validate_input_number(get_request_var('bl_fail_trigger'));
		$save['bl_ref_time'] = (trim($_POST['bl_ref_time'])) == '' ? '' : $_POST['bl_ref_time'];
		$save['bl_ref_time_range'] = (trim($_POST['bl_ref_time_range'])) == '' ? '' : $_POST['bl_ref_time_range'];
		$save['bl_pct_down'] = (trim($_POST['bl_pct_down'])) == '' ? '' : $_POST['bl_pct_down'];
		$save['bl_pct_up'] = (trim($_POST['bl_pct_up'])) == '' ? '' : $_POST['bl_pct_up'];
		$save['bl_fail_trigger'] = (trim($_POST['bl_fail_trigger'])) == '' ? '' : $_POST['bl_fail_trigger'];
	}

	$id = sql_save($save , 'thold_data');

	if (isset($_POST['notify_accounts'])) {
		thold_save_threshold_contacts ($id, $_POST['notify_accounts']);
	}

	$banner="<font color=green><strong>Record Updated</strong></font>";
}

function thold_save_template_contacts ($id, $contacts) {
	db_execute('DELETE FROM plugin_thold_template_contact WHERE template_id = ' . $id);
	// ADD SOME SECURITY!!
	if (!empty($contacts)) {
		foreach ($contacts as $contact) {
			db_execute("INSERT INTO plugin_thold_template_contact (template_id, contact_id) VALUES ($id, $contact)");
		}
	}
}

function thold_save_threshold_contacts ($id, $contacts) {
	db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id = ' . $id);
	// ADD SOME SECURITY!!
	foreach ($contacts as $contact) {
		db_execute("INSERT INTO plugin_thold_threshold_contact (thold_id, contact_id) VALUES ($id, $contact)");
	}
}

function thold_mandatory_field_ok($name, $friendly_name) {
	global $banner;
	if(!isset($_POST[$name]) || (isset($_POST[$name]) && (trim($_POST[$name]) == "" || $_POST[$name] <= 0))) {
		$banner .= "&quot;" . $friendly_name . "&quot; must be set to positive integer value!<br>RECORD NOT UPDATED!</strong></font>";
		return false;
	}
	return true;
}

// Create tholds for all possible data elements for a host
function autocreate($hostid) {
	$c = 0;
	$message = "";

	$rralist = db_fetch_assoc("SELECT id, data_template_id FROM data_local where host_id='$hostid'");

	if (!count($rralist)) {
		$_SESSION['thold_message'] = "<font size=-2>No thresholds were created.</font>";
		return 0;
	}

	foreach ($rralist as $row) {
		$local_data_id = $row["id"];
		$data_template_id = $row['data_template_id'];
		$existing = db_fetch_assoc("SELECT id FROM thold_data WHERE rra_id = " . $local_data_id . " AND data_id = " . $data_template_id);
		$template = db_fetch_assoc("SELECT * FROM thold_template WHERE data_template_id = " . $data_template_id);
		if (count($existing) == 0 && count($template)) {
			$rrdlookup = db_fetch_cell("SELECT id FROM data_template_rrd WHERE local_data_id=$local_data_id order by id LIMIT 1");

			$grapharr = db_fetch_row("SELECT local_graph_id FROM graph_templates_item WHERE task_item_id=$rrdlookup and local_graph_id <> '' LIMIT 1");
			$graph = (isset($grapharr["local_graph_id"]) ? $grapharr["local_graph_id"] : '');

			if ($graph) {
				for ($y = 0; $y < count($template); $y++) {
					$data_source_name = $template[$y]['data_source_name'];
					$insert = array();

					$insert['host_id'] = $hostid;
					$insert['rra_id'] = $local_data_id;

					$insert['thold_hi'] = $template[$y]['thold_hi'];
					$insert['thold_low'] = $template[$y]['thold_low'];
					$insert['thold_fail_trigger'] = $template[$y]['thold_fail_trigger'];
					$insert['thold_enabled'] = $template[$y]['thold_enabled'];
					$insert['bl_enabled'] = $template[$y]['bl_enabled'];
					$insert['bl_ref_time'] = $template[$y]['bl_ref_time'];
					$insert['bl_ref_time_range'] = $template[$y]['bl_ref_time_range'];
					$insert['bl_pct_down'] = $template[$y]['bl_pct_down'];
					$insert['bl_pct_up'] = $template[$y]['bl_pct_up'];
					$insert['bl_fail_trigger'] = $template[$y]['bl_fail_trigger'];
					$insert['bl_alert'] = $template[$y]['bl_alert'];
					$insert['repeat_alert'] = $template[$y]['repeat_alert'];
					$insert['notify_extra'] = $template[$y]['notify_extra'];
					$insert['cdef'] = $template[$y]['cdef'];
					$insert['template'] = $template[$y]['id'];
					$insert['template_enabled'] = 'on';


					$rrdlist = db_fetch_assoc("SELECT id, data_input_field_id FROM data_template_rrd where local_data_id='$local_data_id' and data_source_name = '$data_source_name'") or die(mysql_error());

					$int = array('id', 'data_template_id', 'data_source_id', 'thold_fail_trigger', 'bl_ref_time', 'bl_ref_time_range', 'bl_pct_down', 'bl_pct_up', 'bl_fail_trigger', 'bl_alert', 'repeat_alert', 'cdef');
					foreach ($rrdlist as $rrdrow) {
						$data_rrd_id=$rrdrow["id"];
						$insert['data_id'] = $data_rrd_id;
						$existing = db_fetch_assoc("SELECT id FROM thold_data WHERE rra_id='$local_data_id' AND data_id='$data_rrd_id'");
						if (count($existing) == 0) {
							$insert['id'] = 0;
							$id = sql_save($insert, 'thold_data');
							thold_template_update_threshold ($id, $insert['template']);

							$l = db_fetch_assoc("SELECT name FROM data_template where id=$data_template_id") or die(mysql_error());
							$tname = $l[0]['name'];

							$name = $data_source_name;
							if ($rrdrow['data_input_field_id'] != 0) {
								$l = db_fetch_assoc("SELECT name FROM data_input_fields where id=" . $rrdrow['data_input_field_id']) or die(mysql_error());
								$name = $l[0]['name'];
							}
							$message .= "Created threshold for the Graph '<i>$tname</i>' using the Data Source '<i>$name</i>'<Br>";
							$c++;
						}
					}
				}
			}
		}
	}
	$_SESSION['thold_message'] = "<font size=-2>$message</font>";
	return $c;
}

/* Sends a group of graphs to a user */
function thold_mail($to, $from, $subject, $message, $filename, $headers = '') {
	global $config;
	include_once($config["base_path"] . "/plugins/settings/include/mailer.php");

	$subject = trim($subject);

	$message = str_replace('<SUBJECT>', $subject, $message);

	$how = read_config_option("settings_how");
	if ($how < 0 && $how > 2)
		$how = 0;
	if ($how == 0) {
		$Mailer = new Mailer(array(
			'Type' => 'PHP'));
	} else if ($how == 1) {
		$sendmail = read_config_option('settings_sendmail_path');
		$Mailer = new Mailer(array(
			'Type' => 'DirectInject',
			'DirectInject_Path' => $sendmail));
	} else if ($how == 2) {
		$smtp_host = read_config_option("settings_smtp_host");
		$smtp_port = read_config_option("settings_smtp_port");
		$smtp_username = read_config_option("settings_smtp_username");
		$smtp_password = read_config_option("settings_smtp_password");

		$Mailer = new Mailer(array(
			'Type' => 'SMTP',
			'SMTP_Host' => $smtp_host,
			'SMTP_Port' => $smtp_port,
			'SMTP_Username' => $smtp_username,
			'SMTP_Password' => $smtp_password));
	}

	if ($from == '') {
		$from = read_config_option('thold_from_email');
		$fromname = read_config_option('thold_from_name');
		if ($from == "") {
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'Cacti@cactiusers.org';
			}
		}
		if ($fromname == "")
			$fromname = "Cacti";

		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	} else {
		$from = $Mailer->email_format('Cacti', $from);
		if ($Mailer->header_set('From', $from) === false) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	if ($to == '')
		return "Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.";
	$to = explode(',', $to);

	foreach($to as $t) {
		if (trim($t) != '' && !$Mailer->header_set("To", $t)) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	$wordwrap = read_config_option("settings_wordwrap");
	if ($wordwrap == '')
		$wordwrap = 76;
	if ($wordwrap > 9999)
		$wordwrap = 9999;
	if ($wordwrap < 0)
		$wordwrap = 76;

	$Mailer->Config["Mail"]["WordWrap"] = $wordwrap;

	if (! $Mailer->header_set("Subject", $subject)) {
		print "ERROR: " . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	if (is_array($filename) && !empty($filename) && strstr($message, '<GRAPH>') !==0) {
		foreach($filename as $val) {
			$graph_data_array = array("output_flag"=> RRDTOOL_OUTPUT_STDOUT);
  			$data = rrdtool_function_graph($val['local_graph_id'], $val['rra_id'], $graph_data_array);
			if ($data != "") {
				$cid = $Mailer->content_id();
				if ($Mailer->attach($data, $val['filename'].'.png', "image/png", "inline", $cid) == false) {
					print "ERROR: " . $Mailer->error() . "\n";
					return $Mailer->error();
				}
				$message = str_replace('<GRAPH>', "<br><br><img src='cid:$cid'>", $message);
			} else {
				$message = str_replace('<GRAPH>', "<br><img src='" . $val['file'] . "'><br>Could not open!<br>" . $val['file'], $message);
			}
		}
	}
	$text = array('text' => '', 'html' => '');
	if ($filename == '') {
		$message = str_replace('<br>',  "\n", $message);
		$message = str_replace('<BR>',  "\n", $message);
		$message = str_replace('</BR>', "\n", $message);
		$text['text'] = strip_tags($message);
	} else {
		$text['html'] = $message . '<br>';
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	}

	$v = thold_version();
	$Mailer->header_set("X-Mailer", 'Cacti-Thold-v' . $v['version']);
	$Mailer->header_set("User-Agent", 'Cacti-Thold-v' . $v['version']);

	if ($Mailer->send($text) == false) {
		print "ERROR: " . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	return '';
}

function thold_template_update_threshold ($id, $template) {
	db_execute("UPDATE thold_data, thold_template
		SET thold_data.thold_hi = thold_template.thold_hi,
		thold_data.template_enabled = 'on',
		thold_data.thold_low = thold_template.thold_low,
		thold_data.thold_fail_trigger = thold_template.thold_fail_trigger,
		thold_data.thold_enabled = thold_template.thold_enabled,
		thold_data.bl_enabled = thold_template.bl_enabled,
		thold_data.bl_ref_time = thold_template.bl_ref_time,
		thold_data.bl_ref_time_range = thold_template.bl_ref_time_range,
		thold_data.bl_pct_down = thold_template.bl_pct_down,
		thold_data.bl_fail_trigger = thold_template.bl_fail_trigger,
		thold_data.bl_alert = thold_template.bl_alert,
		thold_data.repeat_alert = thold_template.repeat_alert,
		thold_data.notify_extra = thold_template.notify_extra,
		thold_data.cdef = thold_template.cdef
		WHERE thold_data.id=$id AND thold_template.id=$template");
	db_execute('DELETE FROM plugin_thold_threshold_contact where thold_id = ' . $id);
	db_execute("INSERT INTO plugin_thold_threshold_contact (thold_id, contact_id) SELECT $id, contact_id FROM plugin_thold_template_contact WHERE template_id = $template");
}

function thold_template_update_thresholds ($id) {
	db_execute("UPDATE thold_data, thold_template
		SET thold_data.thold_hi = thold_template.thold_hi,
		thold_data.thold_low = thold_template.thold_low,
		thold_data.thold_fail_trigger = thold_template.thold_fail_trigger,
		thold_data.thold_enabled = thold_template.thold_enabled,
		thold_data.bl_enabled = thold_template.bl_enabled,
		thold_data.bl_ref_time = thold_template.bl_ref_time,
		thold_data.bl_ref_time_range = thold_template.bl_ref_time_range,
		thold_data.bl_pct_down = thold_template.bl_pct_down,
		thold_data.bl_fail_trigger = thold_template.bl_fail_trigger,
		thold_data.bl_alert = thold_template.bl_alert,
		thold_data.repeat_alert = thold_template.repeat_alert,
		thold_data.notify_extra = thold_template.notify_extra,
		thold_data.cdef = thold_template.cdef
		WHERE thold_data.template=$id AND thold_data.template_enabled='on' AND thold_template.id=$id");
	$rows = db_fetch_assoc("SELECT id, template FROM thold_data WHERE thold_data.template=$id AND thold_data.template_enabled='on'");

	foreach ($rows as $row) {
		db_execute('DELETE FROM plugin_thold_threshold_contact where thold_id = ' . $row['id']);
		db_execute('INSERT INTO plugin_thold_threshold_contact (thold_id, contact_id) SELECT ' . $row['id'] . ', contact_id FROM plugin_thold_template_contact WHERE template_id = ' . $row['template']);
	}
}

