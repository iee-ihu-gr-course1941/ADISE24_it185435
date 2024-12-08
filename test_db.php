<?php
require_once "lib/dbconnect.php";

if ($mysqli->ping()) {
    echo "Database connection is successful!";
} else {
    echo "Error: " . $mysqli->connect_error;
}
?>
