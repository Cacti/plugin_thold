<?php

$source = file_get_contents(dirname(__DIR__, 2) . '/notify_lists.php');

if ($source === false) {
	fwrite(STDERR, "Unable to read notify_lists.php\n");
	exit(1);
}

$legacy = array(
	"<input type='hidden' name='drp_action' value='\" . get_request_var('drp_action') . \"'>",
	"<input type='hidden' name='id' value='\" . get_request_var('id') . \"'>",
	"td.name_cache RLIKE '\" . get_request_var('rfilter') . \"'",
	"'td.data_template_id = ' . get_request_var('template')",
	"' h.site_id=' . get_request_var('site_id')",
	"'(td.notify_warning=' . get_request_var('id') . ' OR td.notify_alert=' . get_request_var('id') . ')'",
);

foreach ($legacy as $needle) {
	if (strpos($source, $needle) !== false) {
		fwrite(STDERR, "Found legacy insecure notify list pattern\n");
		exit(1);
	}
}

echo "OK\n";
