<?php

function show_board($input) {
    global $mysqli;


    if (!$game_id) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Missing game_id parameter.']);
        exit;
    }

    // Debugging: Εκτύπωσε το game_id
    error_log("DEBUG: game_id = $game_id");

    // SQL Query
    $query = "SELECT x, y, tile_id, attribute_id, status FROM board WHERE game_id = '$game_id'";
    $result = $mysqli->query($query);

    // Debugging: Έλεγξε αν η SQL εκτελείται
    if (!$result) {
        error_log("DEBUG: SQL Error - " . $mysqli->error);
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => 'Database query failed.']);
        exit;
    }

    $board = [];
    while ($row = $result->fetch_assoc()) {
        $board[] = [
            'x' => (int)$row['x'],
            'y' => (int)$row['y'],
            'tile_id' => (int)$row['tile_id'],
            'attribute_id' => isset($row['attribute_id']) ? (int)$row['attribute_id'] : null,
            'status' => $row['status']
        ];
    }

    // Debugging: Επιστροφή δεδομένων
    error_log("DEBUG: Board Data = " . json_encode($board));

    header('Content-Type: application/json');
    print json_encode(['board' => $board]);
}



function reset_board() {
    global $mysqli;


    if (!isset($_POST['game_id'])) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Missing game_id parameter.']);
        exit;
    }

    $game_id = $mysqli->real_escape_string($_POST['game_id']);

    // SQL για την επαναφορά του ταμπλό
    $query = "DELETE FROM board WHERE game_id = '$game_id'";

    if (!$mysqli->query($query)) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => 'Failed to reset board.']);
        exit;
    }

    header('Content-Type: application/json');
    print json_encode(['message' => 'Board has been reset.']);
}
?>