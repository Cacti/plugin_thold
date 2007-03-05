<?php

chdir('../../');

include_once("./include/auth.php");
include_once($config["base_path"] . "/plugins/thold/thold-functions.php");

global $config;
print "<html><head>";
print '<link type="text/css" href="../../include/main.css" rel="stylesheet">';
print "</head><body>";
$message =  "This is a test message generated from Cacti.  This message was sent to test the configuration of your Mail Settings.<br><br>";
$message .= "Your email settings are currently set as follows<br><br>";
$message .= "<b>Method</b>: ";
print "Checking Configuration...<br>";
$how = read_config_option("thold_how");
if ($how < 0 && $how > 2)
	$how = 0;
if ($how == 0) {
	$mail = "PHP's Mailer Class";
} else if ($how == 1) {
	$mail = "Sendmail<br><b>Sendmail Path</b>: ";
	$sendmail = read_config_option("thold_sendmail_path");
	$mail .= $sendmail;
} else if ($how == 2) {
	print "Method: PHP's Mailer Class<br>";
	$mail = "SMTP<br>";
	$smtp_host = read_config_option("thold_smtp_host");
	$smtp_port = read_config_option("thold_smtp_port");
	$smtp_username = read_config_option("thold_smtp_username");
	$smtp_password = read_config_option("thold_smtp_password");

	$mail .= "<b>Host</b>: $smtp_host<br>";
	$mail .= "<b>Port</b>: $smtp_port<br>";

	if ($smtp_username != '' && $smtp_password != '') {
		$mail .= "<b>Authenication</b>: true<br>";
		$mail .= "<b>Username</b>: $smtp_username<br>";
		$mail .= "<b>Password</b>: (Not Shown for Security Reasons)";
	} else {
		$mail .= "<b>Authenication</b>: false";
	}
}
$message .= $mail;
$message .= "<br>";

print "Creating Message Text...<br><br>";
print "<center><table width='95%' cellpadding=1 cellspacing=0 bgcolor=black><tr><td>";
print "<table width='100%' bgcolor=white><tr><td>$message</td><tr></table></table></center><br>";
print "Sending Message...<br><br>";
$global_alert_address = read_config_option("alert_email");
$errors = thold_mail($global_alert_address, '', "Cacti Test Message", $message, array());
if ($errors == '')
	$errors = "Success!";

print "<center><table width='95%' cellpadding=1 cellspacing=0 bgcolor=black><tr><td>";
print "<table width='100%' bgcolor=white><tr><td>$errors</td><tr></table></table></center>";

print "</body></html>";
?>