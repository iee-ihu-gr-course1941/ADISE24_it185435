<?php

function show_board($game_id) {
    global $mysqli;

    $query = "
        SELECT 
            b.x, b.y, b.attribute_id, ta.color, ta.shape, b.status 
        FROM board b
        LEFT JOIN tileattributes ta ON b.attribute_id = ta.attribute_id
        WHERE b.game_id = ?
    ";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $board = [];
    while ($row = $result->fetch_assoc()) {
        $board[$row['x']][$row['y']] = [
            "attribute_id" => $row['attribute_id'],
            "color" => $row['color'],
            "shape" => $row['shape'],
            "status" => $row['status']
        ];
    }

    response_json(200, 'Board retrieved successfully', ["board" => $board]);
}

function reset_board($game_id) {
    global $mysqli;

    // Ελέγξτε την κατάσταση του παιχνιδιού
    $game_status_query = "SELECT status FROM games WHERE game_id = ?";
    $stmt = $mysqli->prepare($game_status_query);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        response_json(404, 'Game not found');
        return;
    }

    $game_status = $result->fetch_assoc()['status'];

    if ($game_status !== 'active') {
        response_json(400, 'Cannot reset board for a game that is not active');
        return;
    }

    // Διαγραφή του πίνακα board και των πίνακα tiles για το συγκεκριμένο game_id
    $delete_board_query = "DELETE FROM board WHERE game_id = ?";
    $delete_tiles_query = "DELETE FROM tiles WHERE game_id = ?";

    $stmt = $mysqli->prepare($delete_board_query);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();

    $stmt = $mysqli->prepare($delete_tiles_query);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();

    // Εισαγωγή κενού board 5x5
    $insert_board_query = "INSERT INTO board (game_id, x, y, status) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($insert_board_query);
    $status = 'pending';

    for ($x = 1; $x <= 5; $x++) {
        for ($y = 1; $y <= 5; $y++) {
            $stmt->bind_param("iiis", $game_id, $x, $y, $status);
            $stmt->execute();
        }
    }

    // Εισαγωγή διαθέσιμων πλακιδίων στον πίνακα tiles
    $tile_attributes_query = "SELECT attribute_id FROM tileattributes";
    $result = $mysqli->query($tile_attributes_query);

    $attributes = [];
    while ($row = $result->fetch_assoc()) {
        $attributes[] = $row['attribute_id'];
    }

    if (count($attributes) < 25) {
        response_json(400, 'Not enough tiles in the tileattributes table');
        return;
    }

    $insert_tiles_query = "INSERT INTO tiles (attribute_id, game_id, status) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($insert_tiles_query);
    $tile_status = 'available';

    for ($i = 0; $i < 25; $i++) {
        $attribute_id = $attributes[$i];
        $stmt->bind_param("iis", $attribute_id, $game_id, $tile_status);
        $stmt->execute();
    }

    response_json(200, 'Board and tiles have been reset successfully');
}

function place_tile($game_id, $attribute_id, $row, $col) {
    global $mysqli;

    // Επικύρωση αν το πλακίδιο είναι διαθέσιμο
    $sql_validate_tile = "
        SELECT tile_id, status 
        FROM tiles 
        WHERE attribute_id = ? AND game_id = ? AND status = 'available'";
    $stmt_validate_tile = $mysqli->prepare($sql_validate_tile);
    $stmt_validate_tile->bind_param('ii', $attribute_id, $game_id);
    $stmt_validate_tile->execute();
    $result_validate_tile = $stmt_validate_tile->get_result();

    if ($result_validate_tile->num_rows === 0) {
        response_json(400, 'Tile is not available, already placed, or does not belong to this game.');
        return;
    }

    $tile = $result_validate_tile->fetch_assoc();

    // Επικύρωση αν η θέση είναι ήδη κατειλημμένη
    $sql_check_position = "
        SELECT status 
        FROM board 
        WHERE game_id = ? AND x = ? AND y = ?";
    $stmt_check_position = $mysqli->prepare($sql_check_position);
    $stmt_check_position->bind_param('iii', $game_id, $row, $col);
    $stmt_check_position->execute();
    $result_check_position = $stmt_check_position->get_result();

    if ($result_check_position->num_rows > 0) {
        $position_status = $result_check_position->fetch_assoc()['status'];
        if ($position_status === 'placed') {
            response_json(400, 'The position is already occupied.');
            return;
        }
    }

    // Ενημέρωση του πίνακα board
    $sql_update_board = "
        UPDATE board 
        SET attribute_id = ?, status = 'placed' 
        WHERE game_id = ? AND x = ? AND y = ?";
    $stmt_update_board = $mysqli->prepare($sql_update_board);
    $stmt_update_board->bind_param('iiii', $attribute_id, $game_id, $row, $col);

    if (!$stmt_update_board->execute()) {
        response_json(500, 'Failed to update the board: ' . $stmt_update_board->error);
        return;
    }

    // Ενημέρωση του πίνακα tiles
    $sql_update_tile = "
        UPDATE tiles 
        SET row = ?, col = ?, status = 'placed' 
        WHERE tile_id = ?";
    $stmt_update_tile = $mysqli->prepare($sql_update_tile);
    $stmt_update_tile->bind_param('iii', $row, $col, $tile['tile_id']);

    if (!$stmt_update_tile->execute()) {
        response_json(500, 'Failed to update tile status: ' . $stmt_update_tile->error);
        return;
    }

    // Επιτυχής τοποθέτηση
    response_json(200, 'Tile placed successfully at position (' . $row . ', ' . $col . ').');
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