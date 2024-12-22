<?php
require_once "dbconnect.php";

// Handle swapping of tiles
function swap_tiles($input) {
    global $db;
    if (!isset($input['game_id']) || !isset($input['player_id']) || !isset($input['tiles'])) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'game_id, player_id, and tiles are required.']);
        exit;
    }

    $gameId = intval($input['game_id']);
    $playerId = intval($input['player_id']);
    $tiles = $input['tiles']; // Expecting an array of tile IDs

    try {
        $db->beginTransaction();

        // Remove the specified tiles from the player
        $stmt = $db->prepare("DELETE FROM tiles WHERE tile_id = ? AND game_id = ? AND status = 'available'");
        foreach ($tiles as $tile) {
            $stmt->execute([$tile, $gameId]);
        }

        // Add the swap action to game_history
        $stmt = $db->prepare("INSERT INTO game_history (game_id, player_id, action_id, turn_number) VALUES (?, ?, (SELECT action_id FROM actions WHERE action_name = 'swap'), (SELECT MAX(turn_number) + 1 FROM game_history WHERE game_id = ?))");
        $stmt->execute([$gameId, $playerId, $gameId]);

        $db->commit();
        print json_encode(['status' => 'success', 'message' => 'Tiles swapped.']);
    } catch (Exception $e) {
        $db->rollBack();
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}

// Handle undoing an action
function undo_action($input) {
    global $db;
    if (!isset($input['game_id']) || !isset($input['player_id'])) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'game_id and player_id are required.']);
        exit;
    }

    $gameId = intval($input['game_id']);
    $playerId = intval($input['player_id']);

    try {
        $db->beginTransaction();

        // Find the last action by this player
        $stmt = $db->prepare("SELECT history_id, action_id, tile_id FROM game_history WHERE game_id = ? AND player_id = ? ORDER BY turn_number DESC LIMIT 1");
        $stmt->execute([$gameId, $playerId]);
        $lastAction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lastAction) {
            throw new Exception('No actions found to undo.');
        }

        // Fetch the action ID for 'place'
        $stmt = $db->prepare("SELECT action_id FROM actions WHERE action_name = 'place'");
        $stmt->execute();
        $placeAction = $stmt->fetch(PDO::FETCH_ASSOC);

        // Revert the tile placement if applicable
        if ($lastAction['action_id'] == $placeAction['action_id']) {
            $stmt = $db->prepare("UPDATE tiles SET status = 'available', row = NULL, col = NULL WHERE tile_id = ?");
            $stmt->execute([$lastAction['tile_id']]);
        }

        // Remove the last action from game_history
        $stmt = $db->prepare("DELETE FROM game_history WHERE history_id = ?");
        $stmt->execute([$lastAction['history_id']]);

        $db->commit();
        print json_encode(['status' => 'success', 'message' => 'Last action undone.']);
    } catch (Exception $e) {
        $db->rollBack();
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}

// Handle ending a turn
function end_turn($input) {
    global $db;
    if (!isset($input['game_id']) || !isset($input['player_id'])) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'game_id and player_id are required.']);
        exit;
    }

    $gameId = intval($input['game_id']);
    $playerId = intval($input['player_id']);

    try {
        $db->beginTransaction();

        // Update the current turn player
        $stmt = $db->prepare("UPDATE gamestate SET current_turn_player_id = (SELECT player_id FROM game_players WHERE game_id = ? AND turn_order = ((SELECT turn_order FROM game_players WHERE game_id = ? AND player_id = ?) % (SELECT COUNT(*) FROM game_players WHERE game_id = ?)) + 1) WHERE game_id = ?");
        $stmt->execute([$gameId, $gameId, $playerId, $gameId, $gameId]);

        // Add the end_turn action to game_history
        $stmt = $db->prepare("INSERT INTO game_history (game_id, player_id, action_id, turn_number) VALUES (?, ?, (SELECT action_id FROM actions WHERE action_name = 'end_turn'), (SELECT MAX(turn_number) + 1 FROM game_history WHERE game_id = ?))");
        $stmt->execute([$gameId, $playerId, $gameId]);

        $db->commit();
        print json_encode(['status' => 'success', 'message' => 'Turn ended.']);
    } catch (Exception $e) {
        $db->rollBack();
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}

// Handle leaving the game
function leave_game($input) {
    global $db;
    if (!isset($input['game_id']) || !isset($input['player_id'])) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'game_id and player_id are required.']);
        exit;
    }

    $gameId = intval($input['game_id']);
    $playerId = intval($input['player_id']);

    try {
        $db->beginTransaction();

        // Mark the player as inactive in the game
        $stmt = $db->prepare("UPDATE game_players SET is_active = 0 WHERE game_id = ? AND player_id = ?");
        $stmt->execute([$gameId, $playerId]);

        // Add the leave action to game_history
        $stmt = $db->prepare("INSERT INTO game_history (game_id, player_id, action_id, turn_number) VALUES (?, ?, (SELECT action_id FROM actions WHERE action_name = 'leave'), (SELECT MAX(turn_number) + 1 FROM game_history WHERE game_id = ?))");
        $stmt->execute([$gameId, $playerId, $gameId]);

        $db->commit();
        print json_encode(['status' => 'success', 'message' => 'Player left the game.']);
    } catch (Exception $e) {
        $db->rollBack();
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}
?>
