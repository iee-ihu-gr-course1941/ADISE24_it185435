<?php

function show_board() {
    global $mysqli;

    if (!isset($_GET['game_id']) || empty($_GET['game_id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'game_id is required']);
        exit;
    }

    $game_id = $_GET['game_id'];

    $sql = 'SELECT * FROM tiles WHERE game_id = ? ORDER BY tile_id';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to prepare statement']);
        exit;
    }

    $st->bind_param('i', $game_id);
    $st->execute();

    $res = $st->get_result();
    header('Content-type: application/json');
    echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

function reset_board() {
    global $mysqli;

    if (!isset($_POST['game_id']) || empty($_POST['game_id'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'game_id is required']);
        exit;
    }

    $game_id = $_POST['game_id'];

    $sql = 'CALL clean_board(?)';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to prepare statement']);
        exit;
    }

    $st->bind_param('i', $game_id);
    $st->execute();

    show_board();
}
?>

