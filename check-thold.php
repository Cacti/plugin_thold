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
exit;
global $debug, $show;
$debug = 0;
$show = false;

if (isset($_SERVER["argv"][1])) {
	$commands = $_SERVER["argv"];
	$commands[0] = "";
	if (in_array('/show', $commands))
		$show = true;
	if (in_array('/debug', $commands))
		$debug = 1;
}

$no_http_headers = true;
chdir('../../');
include(dirname(__FILE__) . "/../../include/global.php");
include_once($config["library_path"] . "/functions.php");
include_once($config["library_path"] . "/rrd.php");
include_once($config["base_path"] . "/plugins/thold/thold_functions.php");

cacti_log("Checking Thresholds", true, "THOLD");

function logger($desc, $breach_up, $threshld, $currentval, $trigger, $triggerct) {
        define_syslog_variables();
        openlog("CactiTholdLog", LOG_PID | LOG_PERROR, LOG_LOCAL0);

	$syslog_level = read_config_option('thold_syslog_level');
	if (!isset($syslog_level)) {
		$syslog_level = LOG_WARNING;
	} else if (isset($syslog_level) && ($syslog_level > 7 || $syslog_level < 0)) {
		$syslog_level = LOG_WARNING;
	}

	if(strval($breach_up) == "ok") {
		syslog($syslog_level, $desc . " restored to normal with " . $currentval . " at trigger " . $trigger . " out of " . $triggerct);
	} else {
		syslog($syslog_level, $desc . " went " . ($breach_up ? "above" : "below") . " threshold of " . $threshld . " with " . $currentval . " at trigger " . $trigger . " out of " . $triggerct);
	}
}


$db = mysql_connect("$database_hostname", "$database_username", "$database_password");
mysql_select_db("$database_default",$db);
$lookupset = mysql_fetch_array(mysql_query("SELECT * FROM settings WHERE name='path_cactilog'")) or die (mysql_error() );


$cactibasedir = $config["base_path"];

$rootdir = "$cactibasedir/thold";
$httpurl = read_config_option("alert_base_url");
$logfile = $lookupset["value"];
$newlogfile = "$cactibasedir/log/cactilog.temp";
$elementcache = "$rootdir/state";

delete_old_thresholds();

// fetch settings
$thold_cfg = mysql_fetch_array(mysql_query("SELECT * FROM tholdset"));
if (!isset($myrows["datet"])) {
	mysql_query("INSERT INTO tholdset VALUES (1, NOW())");
}
$global_alert_address = read_config_option("alert_email");
$global_notify_enabled = (read_config_option("alert_notify_default") == "on");
$global_bl_notify_enabled = (read_config_option("alert_notify_bl") == "on");
$logset = (read_config_option("alert_syslog") == "on");
$deadnotify = (read_config_option("alert_deadnotify") == "on");
$realert = read_config_option("alert_repeat");
$alert_trigger = read_config_option("alert_trigger");
$alert_bl_trigger = read_config_option("alert_bl_trigger");
$alert_exempt = read_config_option("alert_exempt");

// check for exemptions
$weekday=date("l");
if (($weekday == "Saturday" || $weekday == "Sunday") && $alert_exempt == "on") {
	exit("weekend exemption is on");
}

//timestamp for last lookup
mysql_query("UPDATE tholdset SET datet=NOW() WHERE id='1'") or die (mysql_error() );

$tholdarray = "SELECT * FROM thold";
$queryrows=db_fetch_assoc($tholdarray) or die (mysql_error() );


foreach ($queryrows as $q_row) {
	
	$graph_id = $q_row["element"];
	//$threshld = $q_row["threshold"];
	$rra = $q_row["rra"];
	$t = db_fetch_assoc("select id,name,name_cache from data_template_data where local_data_id=" . $rra . " order by id LIMIT 1");

	if (isset($t[0]["name_cache"])) {
		$desc_rra = $t[0]["name_cache"];
		unset($t);
	
		$items = db_fetch_assoc("select * from thold_data where thold_enabled='on' AND rra_id = " . $rra);

		foreach($items as $item) {
			$trigger = ($item["thold_fail_trigger"] == "" ? $alert_trigger : $item["thold_fail_trigger"]);

			$ds_item_desc = db_fetch_assoc("select id,data_source_name from data_template_rrd where id = " . $item["data_id"]);

			$currentval = get_current_value($rra, $ds_item_desc[0]["data_source_name"], $item['cdef']);

			$desc = $desc_rra;// . " [" . $ds_item_desc[0]["data_source_name"] . "]";

			$msg = "<a href=$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1>$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1</a><Br>";
			$file_array = array(0 => array('local_graph_id' => $graph_id, 'rra_id' => 0, 'file' => "$httpurl/graph_image.php?local_graph_id=$graph_id&rra_id=0&view_type=tree",'mimetype'=>'image/png','filename'=>"$graph_id"));

		
			$breach_up = ($item["thold_hi"] != "" && $currentval > $item["thold_hi"]);
			$breach_down = ($item["thold_low"] != "" && $currentval < $item["thold_low"]);
		
			$alertstat = $item["thold_alert"];
			$item["thold_alert"] = ($breach_up ? 2 : ($breach_down ? 1 : 0));

			if ($show) {
				print "Checking Threshold : \"$desc\"\n";
				print "     Data Source : " . $ds_item_desc[0]["data_source_name"];
			}

			if ( $breach_up || $breach_down) {
				$item["thold_fail_count"]++;

				// Re-Alert?
				$ra = ($item["thold_fail_count"] > $trigger && ($item["thold_fail_count"] % ($item["repeat_alert"] == "" ? $realert : $item["repeat_alert"])) == 0);
				if($item["thold_fail_count"] == $trigger || $ra) {
					if ($logset == 1) {
						logger($desc, $breach_up, ($breach_up ? $item["thold_hi"] : $item["thold_low"]), $currentval, $trigger, $item["thold_fail_count"]);
					}
					$subject = $desc . " " . ($ra ? "is still" : "went") . " " . ($breach_up ? "above" : "below") . " threshold of " . ($breach_up ? $item["thold_hi"] : $item["thold_low"]) . " with $currentval";
					if ($show)
						print " " . ($ra ? "is still" : "went") . " " . ($breach_up ? "above" : "below") . " threshold of " . ($breach_up ? $item["thold_hi"] : $item["thold_low"]) . " with $currentval\n";
					if ($global_notify_enabled || $item["notify_default"] != "off")
						thold_mail($global_alert_address, '', $subject, $msg, $file_array);
					if (trim($item["notify_extra"]) != "")
						thold_mail($item["notify_extra"], '', $subject, $msg, $file_array);
				} elseif ($show) {
						print " " . ($ra ? "is still" : "went") . " " . ($breach_up ? "above" : "below") . " threshold of " . ($breach_up ? $item["thold_hi"] : $item["thold_low"]) . " with $currentval\n";
				}
				$sql = "UPDATE thold_data SET lastread='$currentval'";
				$sql .= ", thold_alert='" . $item["thold_alert"] . "'";
				$sql .= ", thold_fail_count='" . $item["thold_fail_count"] . "'";
				$sql .= ", bl_alert='0'";
				$sql .= "WHERE rra_id='$rra' AND data_id=" . $item["data_id"];
				mysql_query($sql) or die (mysql_error() );
			
			} else {
				if ($alertstat != 0) {
					if ($logset == 1)
						logger($desc, "ok", 0, $currentval, $trigger, $item["thold_fail_count"]);
					if ($item["thold_fail_count"] >= $trigger) {
						$subject = "$desc restored to normal threshold with value $currentval";
						if ($show)
							print " restored to normal threshold with value $currentval\n";
						if ($global_notify_enabled || $item["notify_default"] != "off")
							thold_mail($global_alert_address, '', $subject, $msg, $file_array);
						if (trim($item["notify_extra"]) != "")
							thold_mail($item["notify_extra"], '', $subject, $msg, $file_array);
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
				
					$item["bl_alert"] = thold_check_baseline($rra, $ds_item_desc[0]["data_source_name"], $item["bl_ref_time"], $item["bl_ref_time_range"], $currentval, $item["bl_pct_down"], $item["bl_pct_up"]);
					//echo "bl_alert: " . $item["bl_alert"] . "\n";
					switch($item["bl_alert"]) {
						case -2:	// Exception is active
							// Future
							break;
						case -1:	// Reference value not available
							break;
					
						case 0:		// All clear
							if ($global_bl_notify_enabled && $item["bl_fail_count"] >= $bl_fail_trigger) {
								$subject = "$desc restored to normal threshold with value $currentval";
								if ($show)
									print " restored to normal threshold with value $currentval\n";
								if ($global_notify_enabled || $item["notify_default"] != "off")
									thold_mail($global_alert_address, '', $subject, $msg, $file_array);
								if (trim($item["notify_extra"]) != "")
									thold_mail($item["notify_extra"], '', $subject, $msg, $file_array);
							}
						$item["bl_fail_count"] = 0;
						break;
						
						case 1:		// Value is below calculated threshold
						case 2:		// Value is above calculated threshold
							$item["bl_fail_count"]++;
					
							// Re-Alert?
							$ra = ($item["bl_fail_count"] > $bl_fail_trigger && ($item["bl_fail_count"] % ($item["repeat_alert"] == "" ? $realert : $item["repeat_alert"])) == 0);
							if($global_bl_notify_enabled && ($item["bl_fail_count"] ==  $bl_fail_trigger || $ra)) {
								//if ($logset == 1) {
								//	logger($desc, $breach_up, ($breach_up ? $item["thold_hi"] : $item["thold_low"]), $currentval, $item["thold_fail_trigger"], $item["thold_fail_count"]);
								//}
								$subject = $desc . " " . ($ra ? "is still" : "went") . " " . ($item["bl_alert"] == 2 ? "above" : "below") . " calculated baseline threshold with $currentval";
								if ($show)
									print " " . ($ra ? "is still" : "went") . " " . ($item["bl_alert"] == 2 ? "above" : "below") . " calculated baseline threshold with $currentval\n";;
								if ($global_notify_enabled || $item["notify_default"] != "off")
									thold_mail($global_alert_address, '', $subject, $msg, $file_array);
								if (trim($item["notify_extra"]) != "")
									thold_mail($item["notify_extra"], '', $subject, $msg, $file_array);
							}
							break;
					}
					$sql .= ", bl_alert='" . $item["bl_alert"] . "'";
					$sql .=  ", bl_fail_count='" . $item["bl_fail_count"] . "'";
				}
				$sql .= " WHERE rra_id='$rra' AND data_id=" . $item["data_id"];
				mysql_query($sql) or die (mysql_error() );

			
				// debugging output
				if ($debug == 1) {
					$filename = "$cactibasedir/log/thold.log";
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
	}
}

?>