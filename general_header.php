<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2008 The Cacti Group                                 |
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

$using_guest_account = false;
$show_console_tab = true;

$oper_mode = api_plugin_hook_function('top_header', OPER_MODE_NATIVE);
if ($oper_mode == OPER_MODE_RESKIN) {
	return;
}

if (read_config_option("auth_method") != 0) {
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

$page_title = api_plugin_hook_function('page_title', 'Cacti');

?>
<html>
<head>
	<title><?php echo $page_title; ?></title>
	<?php
	if (isset($_SESSION["custom"]) && ($_SESSION["custom"])) {
		print "<meta http-equiv=refresh content='99999'>\r\n";
	}else{
		$refresh = api_plugin_hook_function('top_graph_refresh', read_graph_config_option('page_refresh'));
		print "<meta http-equiv=refresh content='" . $refresh . "'>\r\n";
	}
	?>
	<link href="<?php echo $config['url_path']; ?>include/main.css" rel="stylesheet">
	<link href="<?php echo $config['url_path']; ?>images/favicon.ico" rel="shortcut icon"/>
	<?php api_plugin_hook('page_head'); ?>
</head>
<?php if ($oper_mode == OPER_MODE_NATIVE) {?>
<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" <?php print api_plugin_hook_function("body_style", "");?>>
<a name='page_top'></a>
<?php }else{?>
<body leftmargin="15" topmargin="15" marginwidth="15" marginheight="15" <?php print api_plugin_hook_function("body_style", "");?>>
<?php }?>

<table width="100%" height="100%" cellspacing="0" cellpadding="0">
<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<tr height="37" bgcolor="#a9a9a9" class="noprint">
		<td colspan="2" valign="bottom" nowrap>
			<table width="100%" cellspacing="0" cellpadding="0">
				<tr>
					<td nowrap>
						&nbsp;<?php if ($show_console_tab == true) {?><a href="<?php echo $config['url_path']; ?>index.php"><img src="<?php echo $config['url_path']; ?>images/tab_console.gif" alt="Console" align="absmiddle" border="0"></a><?php }?><a href="<?php echo $config['url_path']; ?>graph_view.php"><img src="<?php echo $config['url_path']; ?>images/tab_graphs<?php if ((substr(basename($_SERVER["PHP_SELF"]),0,5) == "graph") || (basename($_SERVER["PHP_SELF"]) == "graph_settings.php")) { print "_down"; } print ".gif";?>" alt="Graphs" align="absmiddle" border="0"></a><?php
						api_plugin_hook('top_graph_header_tabs');
					?>&nbsp;
					</td>
					<td>
						<img src="<?php echo $config['url_path']; ?>images/cacti_backdrop2.gif" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr height="2" colspan="2" bgcolor="#183c8f" class="noprint">
		<td colspan="2">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" width="170" height="2" border="0"><br>
		</td>
	</tr>
	<tr height="5" bgcolor="#e9e9e9" class="noprint">
		<td colspan="2">
			<table width="100%">
				<tr>
					<td>
						<?php echo draw_navigation_text();?>
					</td>
					<td align="right">
						<?php if ((isset($_SESSION["sess_user_id"])) && ($using_guest_account == false)) { ?>
						Logged in as <strong><?php print db_fetch_cell("select username from user_auth where id=" . $_SESSION["sess_user_id"]);?></strong> (<a href="<?php echo $config['url_path']; ?>logout.php">Logout</a>)&nbsp;
						<?php } ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr class="noprint">
		<td bgcolor="#efefef" colspan="1" height="8" style="background-image: url(<?php echo $config['url_path']; ?>images/shadow_gray.gif); background-repeat: repeat-x; border-right: #aaaaaa 1px solid;">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" width="<?php print read_graph_config_option("default_dual_pane_width");?>" height="2" border="0"><br>
		</td>
		<td bgcolor="#ffffff" colspan="1" height="8" style="background-image: url(<?php echo $config['url_path']; ?>images/shadow.gif); background-repeat: repeat-x;">

		</td>
	</tr>
<?php } ?>
	<tr>
		<td valign="top" style="padding: 5px; border-right: #aaaaaa 1px solid;">
