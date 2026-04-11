<?php

$source = file_get_contents(dirname(__DIR__, 2) . '/notify_lists.php');

if ($source === false) {
	fwrite(STDERR, "Unable to read notify_lists.php\n");
	exit(1);
}

$needles = array(
	"html_escape(get_request_var('drp_action'))",
	"html_escape(get_request_var('id'))",
	"'td.data_template_id = ' . (int)get_request_var('template')",
	"' h.site_id=' . (int)get_request_var('site_id')",
	"'td.name_cache RLIKE ' . db_qstr(get_request_var('rfilter'))",
	"'(td.notify_warning=' . (int)get_request_var('id') . ' OR td.notify_alert=' . (int)get_request_var('id') . ')'",
);

foreach ($needles as $needle) {
	if (strpos($source, $needle) === false) {
		fwrite(STDERR, "Missing expected notify list guard\n");
		exit(1);
	}
}

echo "OK\n";
