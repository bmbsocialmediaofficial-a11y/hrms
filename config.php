<?php
// config.php

// Database configuration
$servername = "metro.proxy.rlwy.net";
$username = "root";
$password = "MhJRDhatBtkMwGCgOxizGHkVednZSUBj";
$dbname = "railway";

// Define the secure session encryption key (must be 32 bytes after base64 decode)
define('SESSION_ENCRYPTION_KEY', base64_decode('v6K7f8Q9pL2sJ5hG1mN3cX4wZ8bV0tY7rE6uI9oP1aSdFgHjKlMnBvCxZqWeRtY'));

// Include secure session handler
require_once 'secure.php';
?>
