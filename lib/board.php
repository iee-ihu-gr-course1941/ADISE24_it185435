<?php
require_once "helpers.php";

function show_board() {
    global $mysqli;

    $game_id = get_game_id('GET'); 
    $sql = 'SELECT * FROM tiles WHERE game_id = ? ORDER BY tile_id';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        respond_with_error(500, 'Failed to prepare statement');
    }

    $st->bind_param('i', $game_id);
    $st->execute();

    $res = $st->get_result();
    header('Content-type: application/json');
    echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

function reset_board() {
    global $mysqli;

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['game_id']) || empty($input['game_id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'game_id is required']);
        exit;
    }

    $game_id = $input['game_id'];

    $sql = 'CALL clean_board(?)';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to prepare statement']);
        exit;
    }

    $st->bind_param('i', $game_id);
    $st->execute();

    echo json_encode(['message' => 'Board reset successfully']);
}

?>
