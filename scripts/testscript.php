<?php

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

		$file = fopen("/var/www/html/log/cacti.log", 'a+');
//		fwrite($file, "Breached = $value\n");
		fwrite($file, "Breached = test\n");
		fclose($file);
exit;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
	case "--id":
		$file = fopen("/var/www/html/log/cacti.log", 'a+');
		fwrite($file, "Breached = $value\n");
		fclose($file);
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		exit;
	}
}