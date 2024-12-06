<?php

function show_status() {
    global $mysqli;

    if (!isset($_GET['game_id']) || empty($_GET['game_id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'game_id is required']);
        exit;
    }

    $game_id = $_GET['game_id'];

    $sql = 'SELECT g.game_id, g.status, g.start_time, g.end_time, gs.current_turn_player_id 
            FROM games g
            LEFT JOIN gamestate gs ON g.game_id = gs.game_id
            WHERE g.game_id = ?';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to prepare statement']);
        exit;
    }

    $st->bind_param('i', $game_id);

    try {
        $st->execute();
        $res = $st->get_result();

        if ($res->num_rows > 0) {
            header('Content-type: application/json');
            echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Game not found']);
        }
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to execute query']);
    }
}
?>
