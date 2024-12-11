<?php

function handle_actions($method, $request, $input) {
    if ($method == 'POST') {
        record_action($input);
    } else if ($method == 'GET') {
        if (isset($_GET['game_id'])) {
            get_game_actions($_GET['game_id']);
        } else {
            respond_with_error(400, 'game_id is required');
        }
    } else {
        respond_with_error(405, 'Method not allowed');
    }
}
function get_game_actions($game_id) {
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

function record_action($input) {
    global $mysqli;

    $game_id = $input['game_id'] ?? null;
    $player_id = $input['player_id'] ?? null;
    $tile_id = $input['tile_id'] ?? null;
    $action_id = $input['action_id'] ?? null;
    $turn_number = $input['turn_number'] ?? null;
    $x = $input['x'] ?? null;
    $y = $input['y'] ?? null;

    if (!$game_id || !$player_id || !$tile_id || !$action_id || $x === null || $y === null) {
        respond_with_error(400, 'game_id, player_id, tile_id, action_id, x, and y are required');
    }

    if (!is_player_turn($game_id, $player_id)) {
        respond_with_error(400, 'It is not your turn');
    }

    // Έλεγχος αν η θέση είναι κατειλημμένη
    if (is_position_occupied($game_id, $x, $y)) {
        respond_with_error(400, 'Invalid tile placement: position already occupied');
    }

    // Ανάκτηση χαρακτηριστικών του πλακιδίου
    $sql = 'SELECT attribute_id FROM tiles WHERE tile_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $tile_id);
    $st->execute();
    $res = $st->get_result();
    $tile = $res->fetch_assoc();

    if (!$tile) {
        respond_with_error(400, 'Invalid tile_id');
    }

    $attribute_id = $tile['attribute_id'];

    // Έλεγχος εγκυρότητας τοποθέτησης
    if (!is_tile_placement_valid($attribute_id, $x, $y, $game_id)) {
        respond_with_error(400, 'Invalid tile placement: does not match color or shape rules');
    }

    // Εισαγωγή στο board
    $sql = 'INSERT INTO board (game_id, tile_id, attribute_id, x, y, status) VALUES (?, ?, ?, ?, ?, "placed")';
    $st = $mysqli->prepare($sql);
    $st->bind_param('iiiii', $game_id, $tile_id, $attribute_id, $x, $y);
    if (!$st->execute()) {
        respond_with_error(500, 'Failed to insert into board: ' . $mysqli->error);
    }
	$sql = 'UPDATE tiles SET row = ?, col = ?, status = "placed" WHERE tile_id = ?';
	$st = $mysqli->prepare($sql);
	$st->bind_param('iii', $x, $y, $tile_id);
	$st->execute();


    // Καταγραφή στο game_history
    $sql = 'INSERT INTO game_history (game_id, player_id, tile_id, action_id, turn_number, action_time) 
            VALUES (?, ?, ?, ?, ?, NOW())';
    $st = $mysqli->prepare($sql);
    $st->bind_param('iiiii', $game_id, $player_id, $tile_id, $action_id, $turn_number);
    $st->execute();

    move_to_next_player($game_id);
    check_for_deadlock($game_id);

    echo json_encode(['message' => 'Action recorded successfully']);
}

function move_to_next_player($game_id) {
    global $mysqli;

    $sql = 'SELECT gp.player_id, gp.turn_order 
            FROM game_players gp 
            WHERE gp.game_id = ? AND gp.is_active = 1
            ORDER BY gp.turn_order';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $game_id);
    $st->execute();
    $res = $st->get_result();

    $players = $res->fetch_all(MYSQLI_ASSOC);

    $sql = 'SELECT current_turn_player_id FROM gamestate WHERE game_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $game_id);
    $st->execute();
    $res = $st->get_result();
    $current_player = $res->fetch_assoc()['current_turn_player_id'];

    $current_index = array_search($current_player, array_column($players, 'player_id'));
    $next_index = ($current_index + 1) % count($players);
    $next_player_id = $players[$next_index]['player_id'];

    $sql = 'UPDATE gamestate SET current_turn_player_id = ? WHERE game_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('ii', $next_player_id, $game_id);
    $st->execute();
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
function has_available_moves($game_id) {
    global $mysqli;

    $sql = 'SELECT t.tile_id, t.attribute_id 
            FROM tiles t 
            WHERE t.game_id = ? AND t.status = "available"';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $game_id);
    $st->execute();
    $tiles = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    $sql = 'SELECT * FROM board WHERE game_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $game_id);
    $st->execute();
    $board_positions = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($tiles as $tile) {
        foreach ($board_positions as $position) {
            if (!is_position_occupied($game_id, $position['x'], $position['y']) && 
                is_tile_placement_valid($tile['attribute_id'], $position['x'], $position['y'], $game_id)) {
                return true;
            }
        }
    }

    return false;
}
function is_position_occupied($game_id, $x, $y) {
    global $mysqli;

    $sql = 'SELECT * FROM board WHERE game_id = ? AND x = ? AND y = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('iii', $game_id, $x, $y);
    $st->execute();
    $res = $st->get_result();

    return $res->num_rows > 0;
}

function is_tile_placement_valid($attribute_id, $x, $y, $game_id) {
    global $mysqli;

    // Έλεγχος γειτονικών θέσεων
    $sql = 'SELECT attribute_id FROM board WHERE game_id = ? AND 
            ((x = ? AND (y = ? - 1 OR y = ? + 1)) OR 
             (y = ? AND (x = ? - 1 OR x = ? + 1)))';
    $st = $mysqli->prepare($sql);
    $st->bind_param('iiiiiii', $game_id, $x, $y, $y, $x, $x, $x);
    $st->execute();
    $adjacent_tiles = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    $color_match = true;
    $shape_match = true;

    // Ανάκτηση χαρακτηριστικών του πλακιδίου που τοποθετείται
    $sql = 'SELECT color, shape FROM tileattributes WHERE attribute_id = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('i', $attribute_id);
    $st->execute();
    $current_tile_attr = $st->get_result()->fetch_assoc();
    $current_color = $current_tile_attr['color'];
    $current_shape = $current_tile_attr['shape'];

    foreach ($adjacent_tiles as $adj_tile) {
        // Ανάκτηση χαρακτηριστικών γειτονικών πλακιδίων
        $sql = 'SELECT color, shape FROM tileattributes WHERE attribute_id = ?';
        $st = $mysqli->prepare($sql);
        $st->bind_param('i', $adj_tile['attribute_id']);
        $st->execute();
        $adj_tile_attr = $st->get_result()->fetch_assoc();

        if ($adj_tile_attr['color'] !== $current_color) {
            $color_match = false;
        }
        if ($adj_tile_attr['shape'] !== $current_shape) {
            $shape_match = false;
        }
    }

    // Επιστροφή true αν ταιριάζουν είτε στο χρώμα είτε στο σχήμα
    return $color_match || $shape_match;
}

function check_for_deadlock($game_id) {
    global $mysqli;

    // Έλεγχος για διαθέσιμες κινήσεις
    if (!has_available_moves($game_id)) {
        // Ενημέρωση κατάστασης παιχνιδιού σε 'aboard'
        $sql = 'UPDATE games SET status = "aboard", end_time = NOW() WHERE game_id = ?';
        $st = $mysqli->prepare($sql);
        $st->bind_param('i', $game_id);
        $st->execute();

        echo json_encode(['message' => 'Game ended due to deadlock']);
        exit;
    }
}

?>
