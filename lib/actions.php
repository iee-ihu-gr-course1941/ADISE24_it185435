<?php

function handle_actions($method, $request, $input) {
    if ($method == 'POST') {
        record_action($input);
    } else if ($method == 'GET') {
        if (isset($_GET['game_id'])) {
            fetch_game_actions($_GET['game_id']);
        } else {
            respond_with_error(400, 'game_id is required');
        }
    } else {
        respond_with_error(405, 'Method not allowed');
    }
}
function fetch_game_actions($game_id) {
    global $mysqli;

    $sql = 'SELECT * FROM game_history WHERE game_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $game_id);
    $st->execute();
    $res = $st->get_result();

    header('Content-Type: application/json');
    echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
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

    if (!is_player_turn($game_id, $player_id)) {
        respond_with_error(400, 'It is not your turn');
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
	
        move_to_next_player($game_id);

        echo json_encode(['message' => 'Action recorded successfully']);
    } catch (Exception $e) {
        respond_with_error(500, 'Failed to record action: ' . $e->getMessage());
    }
}
function move_to_next_player($game_id) {
    global $mysqli;

    // Βρες την τρέχουσα σειρά
    $sql = 'SELECT gp.player_id, gp.turn_order 
            FROM game_players gp 
            JOIN gamestate gs ON gp.game_id = gs.game_id
            WHERE gp.game_id = ? 
            ORDER BY gp.turn_order';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $game_id);
    $st->execute();
    $res = $st->get_result();

    $players = $res->fetch_all(MYSQLI_ASSOC);

    // Βρες τον επόμενο παίκτη
    $current_player = null;
    foreach ($players as $index => $player) {
        if ($player['player_id'] == $players[0]['current_turn_player_id']) {
            $current_player = $index;
            break;
        }
    }

    $next_player = $players[($current_player + 1) % count($players)]['player_id'];

    // Ενημέρωσε το gamestate
    $sql = 'UPDATE gamestate SET current_turn_player_id = ? WHERE game_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('ii', $next_player, $game_id);
    $st->execute();
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
function validate_tile_placement($game_id, $tile_id) {
    global $mysqli;

    $sql = 'SELECT * FROM tiles WHERE tile_id = ? AND game_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('ii', $tile_id, $game_id);
    $st->execute();
    $res = $st->get_result();

    if ($res->num_rows === 0) {
        return false; // Το πλακίδιο δεν υπάρχει
    }

    $tile = $res->fetch_assoc();

    $sql = 'SELECT * FROM tiles WHERE game_id = ? AND (row = ? OR col = ?) AND status = "placed"';
    $st = $mysqli->prepare($sql);
    $st->bind_param('iii', $game_id, $tile['row'], $tile['col']);
    $st->execute();
    $res = $st->get_result();
    $adjacent_tiles = $res->fetch_all(MYSQLI_ASSOC);

    // Έλεγχος κανόνων
    return check_tile_rules($tile, $adjacent_tiles);
}

function check_tile_rules($tile, $adjacent_tiles) {
    $color_match = true;
    $shape_match = true;

    foreach ($adjacent_tiles as $adj_tile) {
        if ($tile['color'] !== $adj_tile['color']) {
            $color_match = false;
        }
        if ($tile['shape'] !== $adj_tile['shape']) {
            $shape_match = false;
        }
    }

    // Επιστροφή true αν τα πλακίδια ταιριάζουν με χρώμα ή σχήμα
    return $color_match || $shape_match;
}
function is_player_turn($game_id, $player_id) {
    global $mysqli;

    $sql = 'SELECT current_turn_player_id FROM gamestate WHERE game_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $game_id);
    $st->execute();
    $res = $st->get_result();

    if ($res->num_rows === 0) {
        respond_with_error(404, 'Game not found');
    }

    $game = $res->fetch_assoc();

    // Επιστροφή true αν ο παίκτης είναι ο current_turn_player_id
    return $game['current_turn_player_id'] == $player_id;
}

?>
