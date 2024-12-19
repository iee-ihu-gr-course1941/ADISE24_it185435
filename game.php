<?php

ini_set("log_errors", 1);
ini_set("error_log", "logs/php-error.log");

require_once "lib/dbconnect.php";
require_once "lib/game_logic.php";
require_once "lib/scoring.php";

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$input = json_decode(file_get_contents('php://input'), true);

switch ($r = array_shift($request)) {
    case 'create':
        handle_create_game($method, $input);
        break;

    case 'start':
        handle_start_game($method, $input);
        break;

    case 'validate':
        handle_validate_move($method, $input);
        break;

    case 'score':
        handle_calculate_score($method, $input);
        break;

    case 'turn':
        handle_turn_order($method, $input);
        break;

    default:
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

function handle_create_game($method, $input) {
    global $mysqli;

    if ($method === 'POST') {
        $player_count = $input['player_count'] ?? null;

        if (!$player_count || $player_count < 2 || $player_count > 4) {
            respond_with_error(400, "Player count must be between 2 and 4");
        }

        $query = "INSERT INTO games (status, player_count) VALUES ('initialized', ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $player_count);
        $stmt->execute();

        $game_id = $stmt->insert_id;

        echo json_encode(["status" => "success", "game_id" => $game_id, "message" => "Game created successfully"]);
    } else {
        respond_with_error(405, "Method not allowed");
    }
}


function handle_start_game($method, $input) {
    global $mysqli;

    if ($method == 'POST') {
        $game_id = $input['game_id'] ?? null;

        if ($game_id) {
            $query = "UPDATE games SET status = 'active' WHERE game_id = ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('i', $game_id);
            $stmt->execute();

            echo json_encode(["status" => "success", "message" => "Game started successfully"]);
        } else {
            echo json_encode(["error" => "Game ID is required"]);
        }
    } else {
        header("HTTP/1.1 405 Method Not Allowed");
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handle_validate_move($method, $input) {
    if ($method == 'POST') {
        $game_id = $input['game_id'] ?? null;
        $player_id = $input['player_id'] ?? null;
        $move = $input['move'] ?? null; // Example: ['x' => 2, 'y' => 3, 'tile_id' => 5]

        if ($game_id && $player_id && $move) {
            $is_valid = validate_move($game_id, $move);

            if ($is_valid) {
                echo json_encode(["status" => "success", "message" => "Move is valid"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid move"]);
            }
        } else {
            echo json_encode(["error" => "Game ID, Player ID, and Move are required"]);
        }
    } else {
        header("HTTP/1.1 405 Method Not Allowed");
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handle_calculate_score($method, $input) {
    if ($method == 'POST') {
        $game_id = $input['game_id'] ?? null;
        $player_id = $input['player_id'] ?? null;
        $move = $input['move'] ?? null;

        if ($game_id && $player_id && $move) {
            $score = calculate_score($game_id, $move);
            echo json_encode(["status" => "success", "score" => $score]);
        } else {
            echo json_encode(["error" => "Game ID, Player ID, and Move are required"]);
        }
    } else {
        header("HTTP/1.1 405 Method Not Allowed");
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handle_turn_order($method, $input) {
    if ($method == 'POST') {
        $game_id = $input['game_id'] ?? null;
        $player_id = $input['player_id'] ?? null;

        if ($game_id && $player_id) {
            $next_player = get_next_turn($game_id, $player_id);
            echo json_encode(["status" => "success", "next_player" => $next_player]);
        } else {
            echo json_encode(["error" => "Game ID and Player ID are required"]);
        }
    } else {
        header("HTTP/1.1 405 Method Not Allowed");
        echo json_encode(['error' => 'Method not allowed']);
    }
}
function handle_status($method) {
    global $mysqli;

    if ($method == 'GET') {
        $game_id = $_GET['game_id'] ?? null;

        if ($game_id) {
            $query = "SELECT player_id, score FROM scores WHERE game_id = ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('i', $game_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $scores = [];
            while ($row = $result->fetch_assoc()) {
                $scores[$row['player_id']] = $row['score'];
            }

            echo json_encode(["status" => "success", "scores" => $scores]);
        } else {
            echo json_encode(["error" => "Game ID is required"]);
        }
    } else {
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['error' => 'Method not allowed']);
    }
}
?>
