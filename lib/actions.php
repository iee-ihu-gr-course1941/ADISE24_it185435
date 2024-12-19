<?php
require_once "helpers.php";
require_once "lib/game_logic.php";
require_once "lib/scoring.php";

function handle_player($method, $request, $input) {
    global $mysqli;

    if ($method == 'POST') {
        $player_name = $input['player_name'] ?? null;
        $game_id = $input['game_id'] ?? null;

        if ($player_name && $game_id) {
            $query = "INSERT INTO players (game_id, player_name) VALUES (?, ?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('is', $game_id, $player_name);
            $stmt->execute();

            echo json_encode(["status" => "success", "message" => "Player added successfully"]);
        } else {
            echo json_encode(["error" => "Player name and Game ID are required"]);
        }
    } else {
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['error' => 'Method not allowed']);
    }
}
function handle_actions($method, $request, $input) {
    global $mysqli;

    if ($method == 'POST') {
        $game_id = $input['game_id'] ?? null;
        $player_id = $input['player_id'] ?? null;
        $move = $input['move'] ?? null;

        if (!$game_id || !$player_id || !$move) {
            respond_with_error(400, "game_id, player_id, and move are required");
        }

        validate_game_id($game_id);

        // Validate the move
        if (!validate_move($game_id, $move)) {
            respond_with_error(400, "Invalid move");
        }

        // Record the move and calculate score
        $score = calculate_score($game_id, $move);
        record_action($game_id, $player_id, $move, $score);

        // Move to next player
        move_to_next_player($game_id);

        echo json_encode(["status" => "success", "score" => $score]);
    } elseif ($method == 'GET') {
        $game_id = $_GET['game_id'] ?? null;

        if (!$game_id) {
            respond_with_error(400, "game_id is required");
        }

        validate_game_id($game_id);
        get_game_actions($game_id);
    } else {
        respond_with_error(405, "Method not allowed");
    }
}

function record_action($game_id, $player_id, $move, $score) {
    global $mysqli;

    $x = $move['x'];
    $y = $move['y'];
    $tile_id = $move['tile_id'];

    // Insert into board
    $query = "INSERT INTO board (game_id, tile_id, x, y, status) VALUES (?, ?, ?, ?, 'placed')";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('iiii', $game_id, $tile_id, $x, $y);

    if (!$stmt->execute()) {
        respond_with_error(500, "Failed to update board: " . $mysqli->error);
    }

    // Insert into game_history
    $query = "INSERT INTO game_history (game_id, player_id, tile_id, action_time, score) VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('iiii', $game_id, $player_id, $tile_id, $score);

    if (!$stmt->execute()) {
        respond_with_error(500, "Failed to record action: " . $mysqli->error);
    }
}

function get_game_actions($game_id) {
    global $mysqli;

    $query = "SELECT * FROM game_history WHERE game_id = ? ORDER BY action_time ASC";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $game_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $actions = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["status" => "success", "actions" => $actions]);
}

?>