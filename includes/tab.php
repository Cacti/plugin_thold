<?php

function thold_show_tab () {
	global $config;
	if (api_user_realm_auth('thold_graph.php')) {
		$cp = false;
		if (basename($_SERVER['PHP_SELF']) == 'thold_graph.php' || basename($_SERVER['PHP_SELF']) == 'thold_view_failures.php' || basename($_SERVER['PHP_SELF']) == 'thold_view_normal.php')
			$cp = true;

		print '<a href="' . $config['url_path'] . 'plugins/thold/thold_graph.php"><img src="' . $config['url_path'] . 'plugins/thold/images/tab_thold' . ($cp ? '_down': '') . '.gif" alt="thold" align="absmiddle" border="0"></a>';
	}
}