<?php

$source = file_get_contents(dirname(__DIR__, 2) . '/notify_lists.php');

if ($source === false) {
	fwrite(STDERR, "Unable to read notify_lists.php\n");
	exit(1);
}

$checks = array(
	"id=<?php print (int)get_filter_request_var('id'); ?>",
	"'notify_lists.php?action=edit&id=' . (int)get_request_var('id')",
	"<input type='hidden' name='id' value='\" . html_escape(get_request_var('id')) . \"'>",
	"<input type='hidden' name='drp_action' value='\" . html_escape(get_request_var('drp_action')) . \"'>",
);

foreach ($checks as $needle) {
	if (strpos($source, $needle) === false) {
		fwrite(STDERR, "Missing expected notify list wiring\n");
		exit(1);
	}
}

echo "OK\n";
