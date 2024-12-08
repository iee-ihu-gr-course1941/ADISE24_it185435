<?php

function handle_actions($method, $request, $input) {
    if ($method == 'POST') {
        record_action($input);
    } else if ($method == 'GET') {
        $game_id = $request[0] ?? null;
        get_actions($game_id);
    } else {
        respond_with_error(405, 'Method not allowed');
    }
}

function record_action($input) {
    global $mysqli;

    $game_id = $input['game_id'] ?? null;
    $player_id = $input['player_id'] ?? null;
    $tile_id = $input['tile_id'] ?? null;
    $action_id = $input['action_id'] ?? null;
    $turn_number = $input['turn_number'] ?? null;

    if (!$game_id || !$player_id || !$tile_id || !$action_id) {
        respond_with_error(400, 'game_id, player_id, tile_id, and action_id are required');
    }

    $sql = 'INSERT INTO game_history (game_id, player_id, tile_id, action_id, turn_number, action_time) 
            VALUES (?, ?, ?, ?, ?, NOW())';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        respond_with_error(500, 'Failed to prepare statement');
    }

    $st->bind_param('iiiii', $game_id, $player_id, $tile_id, $action_id, $turn_number);

    try {
        $st->execute();
        echo json_encode(['message' => 'Action recorded successfully']);
    } catch (Exception $e) {
        respond_with_error(500, 'Failed to record action: ' . $e->getMessage());
    }
}

function get_actions($game_id) {
    global $mysqli;

    if (!$game_id) {
        respond_with_error(400, 'game_id is required');
    }

    $sql = 'SELECT * FROM game_history WHERE game_id = ?';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        respond_with_error(500, 'Failed to prepare statement');
    }

    $st->bind_param('i', $game_id);

    try {
        $st->execute();
        $res = $st->get_result();
        header('Content-Type: application/json');
        echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        respond_with_error(500, 'Failed to fetch actions: ' . $e->getMessage());
    }
}
?>
