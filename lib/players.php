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

