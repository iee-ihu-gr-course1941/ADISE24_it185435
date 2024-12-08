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

    $game_id = get_game_id('POST'); 
    $sql = 'CALL clean_board(?)';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        respond_with_error(500, 'Failed to prepare statement');
    }

    $st->bind_param('i', $game_id);
    $st->execute();

    show_board();
}
?>
