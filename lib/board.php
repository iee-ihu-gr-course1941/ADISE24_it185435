<?php

function show_board($game_id) {
    global $mysqli; 

    if (!$game_id) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Missing game_id parameter.']);
        exit;
    }

    try {
        $stmt = $mysqli->prepare("SELECT * FROM board WHERE game_id = ?");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $board = $result->fetch_all(MYSQLI_ASSOC);

        if (!$board) {
            header('HTTP/1.1 404 Not Found');
            print json_encode(['errormesg' => 'Board not found.']);
            exit;
        }

        header('Content-Type: application/json');
        print json_encode($board);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}


function reset_board() {
    global $mysqli;


    if (!isset($_POST['game_id'])) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Missing game_id parameter.']);
        exit;
    }

    $game_id = $mysqli->real_escape_string($_POST['game_id']);

    $query = "DELETE FROM board WHERE game_id = '$game_id'";

    if (!$mysqli->query($query)) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => 'Failed to reset board.']);
        exit;
    }

    header('Content-Type: application/json');
    print json_encode(['message' => 'Board has been reset.']);
}
function show_tile($x, $y) {
    global $mysqli; 

    try {
        $stmt = $mysqli->prepare("
            SELECT 
                t.tile_id, 
                t.game_id, 
                t.row AS x, 
                t.col AS y, 
                t.status, 
                ta.color, 
                ta.shape
            FROM tiles t
            LEFT JOIN tileattributes ta ON t.attribute_id = ta.attribute_id
            WHERE t.row = ? AND t.col = ?
        ");
        $stmt->bind_param('ii', $x, $y);
        $stmt->execute();
        $result = $stmt->get_result();
        $tile = $result->fetch_assoc();

        if (!$tile) {
            header('HTTP/1.1 404 Not Found');
            print json_encode(['errormesg' => 'Tile not found at the given coordinates.']);
            exit;
        }

        header('Content-Type: application/json');
        print json_encode($tile);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}


?>