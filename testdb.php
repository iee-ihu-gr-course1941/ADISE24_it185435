<?php
require_once "lib/dbconnect.php";
if ($mysqli->connect_errno) {
    echo "Connection failed: " . $mysqli->connect_error;
} else {
    echo "Connection successful!";
}
?>
