<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "lib/dbconnect.php";
require_once "lib/board.php";
require_once "lib/actions.php";
require_once "lib/players.php";
require_once "lib/status.php";
require_once "lib/helpers.php";

header('Content-Type: application/json');

// Επεξεργασία διαδρομής
$request_path = $_SERVER['PATH_INFO'] ?? null;

if (!$request_path) {
    $script_name = dirname($_SERVER['SCRIPT_NAME']);
    $request_path = str_replace($script_name, '', $_SERVER['REQUEST_URI']);
}

$request = explode('/', trim($request_path, '/'));
$method = $_SERVER['REQUEST_METHOD'];

// Συνδυασμός παραμέτρων από JSON και GET
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$input = array_merge($input, $_GET);

if (isset($_SERVER['HTTP_X_TOKEN'])) {
    $input['token'] = $_SERVER['HTTP_X_TOKEN'];
} else {
    $input['token'] = '';
}

$endpoint = $request[0] ?? null;
array_shift($request);

switch ($endpoint) {
    case 'board':
        switch ($b = array_shift($request)) {	
            case '':
            case null:
                handle_board($method, $input);
                break;
			case 'tile':
				handle_tile($method, $input);
				break;
            default:
                response_json(404, 'Endpoint not found');
        }
        break;
	case 'create':
		if ($method === 'POST') {
			$player_count = $input['player_count'] ?? 2;

			if ($player_count < 2 || $player_count > 4) {
				response_json(400, 'Player count must be between 2 and 4.');
				return;
			}

			$game_id = create_game($player_count);

			if (!$game_id) {
				response_json(500, 'Failed to create game.');
				return;
			}

			$players = create_players_for_game($game_id, $player_count);

			if (!$players) {
				response_json(500, 'Failed to create players.');
				return;
			}

			response_json(200, 'Game created successfully.', [
				'game_id' => $game_id,
				'players' => $players
			]);
		} else {
			response_json(405, 'Method not allowed.');
		}
		break;
	case 'join':
		if ($method === 'POST') {
			$game_id = intval($input['game_id'] ?? 0);

			if (!$game_id) {
				response_json(400, 'Game ID is required.');
				return;
			}

			// Δημιουργία ή προσθήκη παίκτη στο παιχνίδι
			$username = $input['username'] ?? 'Guest_' . uniqid();
			$email = $input['email'] ?? $username . '@example.com';
			$player_id = add_or_get_player($username, $email);

			if (!$player_id) {
				response_json(500, 'Failed to create or retrieve player.');
				return;
			}

			$result = add_player_to_game($game_id, $player_id);

			if (!$result['success']) {
				response_json(400, $result['message']);
				return;
			}

			response_json(200, 'Player joined the game successfully.', [
				'game_id' => $game_id,
				'player_id' => $player_id,
				'player_count' => $result['player_count']
			]);
		} else {
			response_json(405, 'Method not allowed.');
		}
		break;
    case 'actions':
		$action = $input['action'] ?? null;

		if (!$action) {
			response_json(400, 'Missing action parameter.');
			return;
		}
		switch ($action) {
			case 'swap':
				if (!isset($input['tiles'], $input['game_id'])) {
					response_json(400, 'Missing required parameters: game_id, tiles.');
					return;
				}
				swap_tiles($input['game_id'], $input['tiles']);
				break;

			case 'undo':
				if (!isset($input['game_id'], $input['player_id'])) {
					response_json(400, 'Missing required parameters: game_id, player_id.');
					return;
				}
				undo_action($input['game_id'], $input['player_id']);
				break;

			case 'end_turn':
				if (!isset($input['game_id'], $input['player_id'])) {
					response_json(400, 'Missing required parameters: game_id, player_id.');
					return;
				}
				end_turn($input['game_id'], $input['player_id']);
				break;

			case 'leave_game':
				if (!isset($input['game_id'], $input['player_id'])) {
					response_json(400, 'Missing required parameters: game_id, player_id.');
					return;
				}
				leave_game($input['game_id'], $input['player_id']);
				break;

			default:
				response_json(400, "Invalid action: $action.");
				return;
		}
		break;
    case 'players':
		switch ($b = array_shift($request)) {
			case '':
			case null:
				handle_players($method, $input);
				break;

			case 'stats':
				if ($method === 'GET') {
					$player_id = intval($input['player_id'] ?? 0);
					show_player_stats($player_id);
				} else {
					response_json(405, 'Method not allowed.');
				}
				break;

			default:
				response_json(400, "Invalid endpoint for players: $b.");
				break;
		}
		break;
    default:
        header("HTTP/1.1 404 Not Found");
        exit;
}
function handle_board($method, $input) {
    $game_id = $input['game_id'] ?? null;

    if (!$game_id) {
        response_json(400, 'Missing game_id parameter');
        return;
    }

    switch ($method) {
        case 'GET':
            show_board($game_id);
            break;

        case 'POST':
            reset_board($game_id);
            break;

        default:
            response_json(405, 'Method not allowed');
    }
}
function handle_tile($method, $input) {
    if ($method !== 'POST') {
        response_json(405, 'Method not allowed');
        return;
    }

    $game_id = $input['game_id'] ?? null;
    $row = $input['row'] ?? null;
    $col = $input['col'] ?? null;
    $attribute_id = $input['attribute_id'] ?? null;

    if (!$game_id || !$row || !$col || !$attribute_id) {
        response_json(400, 'Missing parameters: game_id, row, col, or attribute_id');
        return;
    }

    place_tile($game_id, $attribute_id, $row, $col);
}
function get_tiles($game_id, $player_id) {
    global $mysqli;

    // Ανάκτηση 6 τυχαίων πλακιδίων από το sack
    $sql = "
        SELECT tile_id, attribute_id 
        FROM tiles 
        WHERE game_id = ? AND status = 'available' 
        ORDER BY RAND() 
        LIMIT 6";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $game_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tiles = [];
    while ($row = $result->fetch_assoc()) {
        $tiles[] = $row;

        // Ενημέρωση των πλακιδίων ως ανατεθειμένα στον παίκτη
        $sql_assign_tile = "
            UPDATE tiles 
            SET status = 'assigned', row = NULL, col = NULL 
            WHERE tile_id = ?";
        $stmt_assign_tile = $mysqli->prepare($sql_assign_tile);
        $stmt_assign_tile->bind_param('i', $row['tile_id']);
        $stmt_assign_tile->execute();
    }

    if (count($tiles) < 6) {
        response_json(400, 'Not enough tiles available in the sack.');
        return;
    }

    response_json(200, 'Tiles assigned successfully.', ['tiles' => $tiles]);
}

function assign_initial_tiles($game_id, $player_ids) {
    foreach ($player_ids as $player_id) {
        get_tiles($game_id, $player_id);
    }
}

function handle_players($method, $input) {
    switch ($method) {
        case 'GET':
            $player_id = intval($input['player_id'] ?? 0);
            show_player($player_id);
            break;

        default:
            response_json(405, 'Method not allowed.');
    }
}
?>