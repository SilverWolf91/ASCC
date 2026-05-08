<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

putenv("GMAIL_USER=lopeztorresjosesamuel@gmail.com");
putenv("GMAIL_PASSWORD=wsbektkkksiompyz");
putenv("GMAIL_NAME=ASCC Colombia");

echo "Test getenv: " . getenv('GMAIL_USER') . "\n";
require_once __DIR__ . '/config/email_config.php';
// Let's modify config/email_config.php to dump the username/password before sending
