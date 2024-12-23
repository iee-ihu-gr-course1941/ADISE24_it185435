<?php

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
