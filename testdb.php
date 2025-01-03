<?php
require_once "lib/dbconnect.php";

header('Content-Type: application/json');

if ($mysqli->connect_errno) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $mysqli->connect_error]);
} else {
    echo json_encode(['status' => 'success', 'message' => 'Connection successful!']);
}
exit();
?>
