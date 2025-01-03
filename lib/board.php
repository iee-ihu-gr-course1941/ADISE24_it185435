<?php

function show_board($game_id) {
    global $mysqli;

    $sql_select = "
        SELECT b.x, b.y, ta.color, ta.shape, b.status
        FROM board b
        JOIN tileattributes ta ON b.attribute_id = ta.attribute_id
        WHERE b.game_id = ?
    ";
    $stmt = $mysqli->prepare($sql_select);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $board = [];
    while ($row = $result->fetch_assoc()) {
        $board[$row['x']][$row['y']] = [
            "color" => $row['color'],
            "shape" => $row['shape'],
            "status" => $row['status']
        ];
    }
    echo json_encode(["board" => $board]);
}
function reset_board($game_id) {
    global $mysqli;

    $sql_delete = "DELETE FROM board WHERE game_id = ?";
    $stmt = $mysqli->prepare($sql_delete);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();

    $sql_select_attributes = "SELECT attribute_id FROM tileattributes LIMIT 25";
    $result = $mysqli->query($sql_select_attributes);

    $attributes = [];
    while ($row = $result->fetch_assoc()) {
        $attributes[] = $row['attribute_id'];
    }

    if (count($attributes) < 25) {
        echo json_encode(["error" => "Not enough tiles in the tileattributes table."]);
        exit;
    }

    $sql_insert = "INSERT INTO board (game_id, tile_id, attribute_id, x, y, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql_insert);

    $status = 'placed';
    $index = 0;

    for ($x = 1; $x <= 5; $x++) {
        for ($y = 1; $y <= 5; $y++) {
            $attribute_id = intval($attributes[$index]);
            $tile_id = $attribute_id; // Αν το tile_id βασίζεται στο attribute_id
            $stmt->bind_param("iiiis", $game_id, $tile_id, $attribute_id, $x, $y, $status);
            $stmt->execute();
            $index++;
        }
    }

    echo json_encode(["message" => "Board has been reset."]);
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