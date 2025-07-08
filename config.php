<?php

$servername = "localhost";
// $username = "bcuxufxdwb";
// $password = "dnfcbr9hqC";
// $dbname = "bcuxufxdwb";
$username = "root";
$password = "";
$dbname = "bettercsi";
$conn = new mysqli($servername, $username, $password, $dbname);

define("CLIENT_ID", "d07624a8-b954-4fce-88e3-04792757d1b5");
define("CLIENT_SECRET", "42023db5-6726-4359-9d74-80044c35ca1b");
// $CFG->wwwroot   = 'http://phpstack-273256-5237083.cloudwaysapps.com';


// SFTP configuration
define("SFTP_HOST", "cdcsftp.meevo.com");
define("SFTP_USERNAME", "TheGentsPlace");
define("SFTP_PRIVATE_KEY_PATH", "./sftp_key.pem");
define("SFTP_REMOTE_PATH", "/Tenant200515/Location201561");
define("SFTP_PASSPHRASE", "Meevo2021");