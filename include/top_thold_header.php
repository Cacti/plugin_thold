<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
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

global $colors, $config, $thold_menu;

$show_console_tab = true;

if (read_config_option("global_auth") == "on") {
	global $colors, $config, $thold_menu;

	/* at this point this user is good to go... so get some setting about this
	user and put them into variables to save excess SQL in the future */
	$current_user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);

	/* find out if we are logged in as a 'guest user' or not */
	if (db_fetch_cell("select id from user_auth where username='" . read_config_option("guest_user") . "'") == $_SESSION["sess_user_id"]) {
		$using_guest_account = true;
	}

	/* find out if we should show the "console" tab or not, based on this user's permissions */
	if (sizeof(db_fetch_assoc("select realm_id from user_auth_realm where realm_id=8 and user_id=" . $_SESSION["sess_user_id"])) == 0) {
		$show_console_tab = false;
	}
}
?>
<html>
<head>
	<link href="<?php echo $config['url_path']; ?>include/main.css" rel="stylesheet">
	<link href="images/favicon.ico" rel="shortcut icon"/>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/layout.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/calendar.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/lang/calendar-en.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/calendar-setup.js"></script>
	<?php if (isset($refresh)) {
	print "<meta http-equiv=refresh content='" . $refresh["seconds"] . "'; url='" . $refresh["page"] . "'>";
	}?>
</style>
</head>

<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" background="<?php echo $config['url_path']; ?>images/left_border.gif">

<table width="100%" cellspacing="0" cellpadding="0">
	<tr height="37" bgcolor="#a9a9a9" class="noprint">
		<td valign="bottom" colspan="3" nowrap>
			<table width="100%" cellspacing="0" cellpadding="0">
				<tr>
					<td nowrap>
						&nbsp;<?php if ($show_console_tab == true) {?><a href="<?php echo $config['url_path']; ?>index.php"><img src="<?php echo $config['url_path']; ?>images/tab_console.gif" alt="Console" align="absmiddle" border="0"></a><?php }?><a href="<?php echo $config['url_path']; ?>graph_view.php"><img src="<?php echo $config['url_path']; ?>images/tab_graphs<?php if ((substr(basename($_SERVER["PHP_SELF"]),0,10) == "graph_view") || (basename($_SERVER["PHP_SELF"]) == "graph_settings.php")) { print "_down"; } print ".gif";?>" alt="Graphs" align="absmiddle" border="0"></a><?php
						do_hook("top_graph_header_tabs");
					?>&nbsp;					</td>
					</td>
					<td>
					</td>
					<td align="right" nowrap>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr height="2" bgcolor="#183c8f" class="noprint">
		<td colspan="3">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" width="170" height="2" border="0"><br>
		</td>
	</tr>
	<tr height="5" bgcolor="#e9e9e9" class="noprint">
		<td colspan="3">
			<table width="100%">
				<tr>
					<td>
						<?php draw_navigation_text();?>
					</td>
					<td align="right">
						<?php if (read_config_option("global_auth") == "on") { ?>
						Logged in as <strong><?php print db_fetch_cell("select username from user_auth where id=" . $_SESSION["sess_user_id"]);?></strong> (<a href="<?php echo $config['url_path']; ?>logout.php">Logout</a>)&nbsp;
						<?php } ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr class="noprint">
		<td bgcolor="#f5f5f5" colspan="1" height="8" width="135" style="background-image: url(<?php echo $config['url_path']; ?>images/shadow_gray.gif); background-repeat: repeat-x; border-right: #aaaaaa 1px solid;">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" width="135" height="2" border="0"><br>
		</td>
		<td colspan="2" height="8" style="background-image: url(<?php echo $config['url_path']; ?>images/shadow.gif); background-repeat: repeat-x;" bgcolor="#ffffff">
		</td>
	</tr>
	<tr height="5" class="noprint">
		<td valign="top" rowspan="2" width="135" style="padding: 5px; border-right: #aaaaaa 1px solid;" bgcolor='#f5f5f5'>
			<table bgcolor="#f5f5f5" width="100%" cellpadding="1" cellspacing="0" border="0">
				<?php draw_menu($thold_menu);?>
			</table>

			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" width="135" height="5" border="0"><br>
		</td>
		<td></td>
	</tr>
	<tr>
		<td width="135" height="500"></td>
		<td width="100%" valign="top"><?php display_output_messages();?>

