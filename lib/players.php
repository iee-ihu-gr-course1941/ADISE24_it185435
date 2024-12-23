<?php
require_once "lib/dbconnect.php";

function show_player($player_id) {
    global $mysqli;

    if (!$player_id) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Missing player_id parameter.']);
        exit;
    }

    try {
        $stmt = $mysqli->prepare("SELECT * FROM players WHERE player_id = ?");
        $stmt->bind_param('i', $player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $player = $result->fetch_assoc();

        if (!$player) {
            header('HTTP/1.1 404 Not Found');
            print json_encode(['errormesg' => 'Player not found.']);
            exit;
        }

        header('Content-Type: application/json');
        print json_encode($player);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}
?>
/*
// Ενημέρωση δεδομένων παίκτη
function update_player($player_id, $input) {
    global $mysqli;

    if (!$player_id) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Missing player_id parameter.']);
        exit;
    }

    if (empty($input)) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'No data provided for update.']);
        exit;
    }

    $columns = [];
    $values = [];

    foreach ($input as $key => $value) {
        $columns[] = "$key = ?";
        $values[] = $value;
    }

    $values[] = $player_id;
    $set_clause = implode(", ", $columns);

    try {
        $stmt = $mysqli->prepare("UPDATE players SET $set_clause WHERE player_id = ?");

        // Δημιουργία τύπων παραμέτρων για bind_param
        $types = str_repeat('s', count($values) - 1) . 'i';
        $stmt->bind_param($types, ...$values);

        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            header('HTTP/1.1 404 Not Found');
            print json_encode(['errormesg' => 'Player not found or no changes made.']);
            exit;
        }

        header('Content-Type: application/json');
        print json_encode(['status' => 'success', 'message' => 'Player updated successfully.']);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}/*

