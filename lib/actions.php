<?php

function create_game() {
    global $mysqli;
    $sql = "INSERT INTO games (status) VALUES ('initialized')";
    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        error_log("Prepare failed: " . $mysqli->error);
        return false;
    }
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }

    return $stmt->insert_id; 
}
function join_game($game_id) {
    global $mysqli;

    $sql_check = "SELECT * FROM games WHERE game_id = ? AND status = 'initialized'";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param('i', $game_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $sql_update = "UPDATE games SET status = 'active' WHERE game_id = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param('i', $game_id);

        if ($stmt_update->execute()) {
            return [
                'success' => true,
                'message' => 'Game joined successfully.',
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update game status.',
            ];
        }
    }

    return [
        'success' => false,
        'message' => 'Game not found or already active.',
    ];
}
function add_player_to_game($game_id) {
    global $mysqli;

    $sql_check_game = "SELECT player_count, status FROM games WHERE game_id = ?";
    $stmt_check_game = $mysqli->prepare($sql_check_game);
    $stmt_check_game->bind_param("i", $game_id);
    $stmt_check_game->execute();
    $result_check_game = $stmt_check_game->get_result();

    if ($result_check_game->num_rows === 0) {
        return ['success' => false, 'message' => 'Game not found.'];
    }

    $game_data = $result_check_game->fetch_assoc();

    // Έλεγχος αν το παιχνίδι έχει ήδη το μέγιστο αριθμό παικτών
    if ($game_data['player_count'] >= 4) {
        return ['success' => false, 'message' => 'Game has already reached the maximum number of players.'];
    }

    // Ενημέρωση του player_count
    $new_player_count = $game_data['player_count'] + 1;
    $sql_update_count = "UPDATE games SET player_count = ? WHERE game_id = ?";
    $stmt_update_count = $mysqli->prepare($sql_update_count);
    $stmt_update_count->bind_param("ii", $new_player_count, $game_id);
    $stmt_update_count->execute();

    // Ενημέρωση του status σε 'active' αν οι παίκτες είναι >= 2
    if ($new_player_count >= 2 && $game_data['status'] === 'initialized') {
        $sql_update_status = "UPDATE games SET status = 'active' WHERE game_id = ?";
        $stmt_update_status = $mysqli->prepare($sql_update_status);
        $stmt_update_status->bind_param("i", $game_id);
        $stmt_update_status->execute();
    }

    return [
        'success' => true,
        'player_id' => $mysqli->insert_id, // Αν υπάρχει πίνακας players και γίνεται εισαγωγή
        'player_count' => $new_player_count
    ];
}
function generate_board($game_id) {
    $board = [];
    for ($i = 0; $i < 5; $i++) {
        $row = [];
        for ($j = 0; $j < 5; $j++) {
            $row[] = '';
        }
        $board[] = $row;
    }
    return $board;
}
function place_tile($game_id, $tile_id, $position) {
    global $mysqli;

    try {
        // Επικύρωση αν το πλακίδιο ανήκει στο παιχνίδι και είναι διαθέσιμο
        $sql_validate_tile = "
            SELECT *
            FROM tiles
            WHERE tile_id = ? AND game_id = ? AND status = 'available'";
        $stmt_validate_tile = $mysqli->prepare($sql_validate_tile);
        $stmt_validate_tile->bind_param('ii', $tile_id, $game_id);
        $stmt_validate_tile->execute();
        $result_validate_tile = $stmt_validate_tile->get_result();

        if ($result_validate_tile->num_rows === 0) {
            throw new Exception('Tile is not available or does not belong to this game.');
        }

        // Επικύρωση αν η θέση είναι ήδη κατειλημμένη
        $sql_check_position = "
            SELECT *
            FROM board
            WHERE x = ? AND y = ? AND game_id = ?";
        $stmt_check_position = $mysqli->prepare($sql_check_position);
        $stmt_check_position->bind_param('iii', $position['x'], $position['y'], $game_id);
        $stmt_check_position->execute();
        $result_check_position = $stmt_check_position->get_result();

        if ($result_check_position->num_rows > 0) {
            throw new Exception('Position is already occupied.');
        }

        // Τοποθέτηση πλακιδίου στον πίνακα
        $sql_place_tile = "
            INSERT INTO board (x, y, tile_id, game_id, status)
            VALUES (?, ?, ?, ?, 'placed')";
        $stmt_place_tile = $mysqli->prepare($sql_place_tile);
        $stmt_place_tile->bind_param('iiii', $position['x'], $position['y'], $tile_id, $game_id);

        if (!$stmt_place_tile->execute()) {
            throw new Exception('Failed to place the tile on the board.');
        }

        // Ενημέρωση της κατάστασης του πλακιδίου στον πίνακα tiles
        $sql_update_tile_status = "
            UPDATE tiles
            SET status = 'placed'
            WHERE tile_id = ?";
        $stmt_update_tile_status = $mysqli->prepare($sql_update_tile_status);
        $stmt_update_tile_status->bind_param('i', $tile_id);

        if (!$stmt_update_tile_status->execute()) {
            throw new Exception('Failed to update tile status.');
        }

        // Επιτυχής τοποθέτηση
        return json_encode(['success' => true, 'message' => 'Tile placed successfully.']);
    } catch (Exception $e) {
        // Διαχείριση σφαλμάτων
        return json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function swap_tiles($game_id, $player_id, $tiles) {
    global $mysqli;

    if (is_string($tiles)) {
        $tiles = json_decode($tiles, true);
        if (!is_array($tiles)) {
            header('HTTP/1.1 400 Bad Request');
            print json_encode(['errormesg' => 'Invalid tiles parameter. Must be an array.']);
            exit;
        }
    }
    try {
        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("DELETE FROM tiles WHERE tile_id = ? AND game_id = ? AND status = 'available'");
        foreach ($tiles as $tile) {
            $stmt->bind_param('ii', $tile, $game_id);
            $stmt->execute();
        }

        $stmt = $mysqli->prepare("
            INSERT INTO game_history (game_id, player_id, action_id, turn_number) 
            VALUES (?, ?, 
                (SELECT action_id FROM actions WHERE action_name = 'swap'), 
                (SELECT COALESCE(MAX(turn_number), 0) + 1 FROM (SELECT * FROM game_history WHERE game_id = ?) AS subquery)
            )
        ");
        $stmt->bind_param('iii', $game_id, $player_id, $game_id);
        $stmt->execute();

        $mysqli->commit();
        header('Content-Type: application/json');
        print json_encode(['status' => 'success', 'message' => 'Tiles swapped.']);
    } catch (Exception $e) {
        $mysqli->rollback();
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}
function undo_action($game_id, $player_id) {
    global $mysqli;

    try {
        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("SELECT history_id, action_id, tile_id FROM game_history WHERE game_id = ? AND player_id = ? ORDER BY turn_number DESC LIMIT 1");
        $stmt->bind_param('ii', $game_id, $player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lastAction = $result->fetch_assoc();

        if (!$lastAction) {
            throw new Exception('No actions found to undo.');
        }

        $stmt = $mysqli->prepare("SELECT action_id FROM actions WHERE action_name = 'place'");
        $stmt->execute();
        $result = $stmt->get_result();
        $placeAction = $result->fetch_assoc();

        if ($lastAction['action_id'] == $placeAction['action_id']) {
            $stmt = $mysqli->prepare("UPDATE tiles SET status = 'available', row = NULL, col = NULL WHERE tile_id = ?");
            $stmt->bind_param('i', $lastAction['tile_id']);
            $stmt->execute();
        }

        $stmt = $mysqli->prepare("DELETE FROM game_history WHERE history_id = ?");
        $stmt->bind_param('i', $lastAction['history_id']);
        $stmt->execute();

        $mysqli->commit();
        header('Content-Type: application/json');
        print json_encode(['status' => 'success', 'message' => 'Last action undone.']);
    } catch (Exception $e) {
        $mysqli->rollback();
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}
function end_turn($game_id, $player_id) {
    global $mysqli;

    try {
        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("
            UPDATE gamestate 
            SET current_turn_player_id = (
                SELECT player_id 
                FROM game_players 
                WHERE game_id = ? 
                AND turn_order = (
                    (SELECT turn_order FROM game_players WHERE game_id = ? AND player_id = ?) % 
                    (SELECT COUNT(*) FROM game_players WHERE game_id = ?)
                ) + 1
            ) 
            WHERE game_id = ?
        ");
        $stmt->bind_param('iiiii', $game_id, $game_id, $player_id, $game_id, $game_id);
        $stmt->execute();

        $stmt = $mysqli->prepare("
            INSERT INTO game_history (game_id, player_id, action_id, turn_number) 
            VALUES (?, ?, 
                (SELECT action_id FROM actions WHERE action_name = 'end_turn'), 
                (SELECT COALESCE(MAX(turn_number), 0) + 1 FROM (SELECT * FROM game_history WHERE game_id = ?) AS subquery)
            )
        ");
        $stmt->bind_param('iii', $game_id, $player_id, $game_id);
        $stmt->execute();

        $mysqli->commit();
        header('Content-Type: application/json');
        print json_encode(['status' => 'success', 'message' => 'Turn ended.']);
    } catch (Exception $e) {
        $mysqli->rollback();
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}
function leave_game($game_id, $player_id) {
    global $mysqli;

    try {
        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("UPDATE game_players SET is_active = 0 WHERE game_id = ? AND player_id = ?");
        $stmt->bind_param('ii', $game_id, $player_id);
        $stmt->execute();

        $stmt = $mysqli->prepare("
            INSERT INTO game_history (game_id, player_id, action_id, turn_number) 
            VALUES (?, ?, 
                (SELECT action_id FROM actions WHERE action_name = 'leave'), 
                (SELECT COALESCE(MAX(turn_number), 0) + 1 FROM (SELECT * FROM game_history WHERE game_id = ?) AS subquery)
            )
        ");
        $stmt->bind_param('iii', $game_id, $player_id, $game_id);
        $stmt->execute();

        $mysqli->commit();
        header('Content-Type: application/json');
        print json_encode(['status' => 'success', 'message' => 'Player left the game.']);
    } catch (Exception $e) {
        $mysqli->rollback();
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}
?>
