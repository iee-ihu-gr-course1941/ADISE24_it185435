<?php
require_once "lib/dbconnect.php";
require_once "lib/board.php";
require_once "lib/actions.php";
require_once "lib/players.php";
require_once "lib/status.php";

$request_path = $_SERVER['PATH_INFO'] ?? null;

if (!$request_path) {
    $script_name = dirname($_SERVER['SCRIPT_NAME']);
    $request_path = str_replace($script_name, '', $_SERVER['REQUEST_URI']);
}

$request = explode('/', trim($request_path, '/'));
$method = $_SERVER['REQUEST_METHOD'];

// Συνένωση παραμέτρων από JSON και $_GET
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$input = array_merge($input, $_GET);

if (isset($_SERVER['HTTP_X_TOKEN'])) {
    $input['token'] = $_SERVER['HTTP_X_TOKEN'];
} else {
    $input['token'] = '';
}

// Ασφαλής λήψη του endpoint
$endpoint = $request[0] ?? null;
array_shift($request); // Αφαιρεί το πρώτο στοιχείο από το $request


switch ($endpoint) {
    case 'board':
        switch ($b = array_shift($request)) {
            case '':
            case null:
                handle_board($method, $input);
                break;
            case 'tile':
                handle_tile($method, $request[0] ?? null, $request[1] ?? null, $input);
                break;
            default:
                header("HTTP/1.1 404 Not Found");
                exit;
        }
        break;
    case 'actions':
		$action = $input['action'] ?? null;

		if (!$action) {
			header('HTTP/1.1 400 Bad Request');
			print json_encode(['errormesg' => 'Missing action parameter.']);
			exit;
		}
		switch ($action) {
			case 'swap':
				if (!isset($input['tiles'], $input['game_id'], $input['player_id'])) {
					header('HTTP/1.1 400 Bad Request');
					print json_encode(['errormesg' => 'Missing required parameters: game_id, player_id, tiles.']);
					exit;
				}
				swap_tiles($input['game_id'], $input['player_id'], $input['tiles']);
				break;

			case 'undo':
				if (!isset($input['game_id'], $input['player_id'])) {
					header('HTTP/1.1 400 Bad Request');
					print json_encode(['errormesg' => 'Missing required parameters: game_id, player_id.']);
					exit;
				}
				undo_action($input['game_id'], $input['player_id']);
				break;

			case 'end_turn':
				if (!isset($input['game_id'], $input['player_id'])) {
					header('HTTP/1.1 400 Bad Request');
					print json_encode(['errormesg' => 'Missing required parameters: game_id, player_id.']);
					exit;
				}
				end_turn($input['game_id'], $input['player_id']);
				break;

			case 'leave_game':
				if (!isset($input['game_id'], $input['player_id'])) {
					header('HTTP/1.1 400 Bad Request');
					print json_encode(['errormesg' => 'Missing required parameters: game_id, player_id.']);
					exit;
				}
				leave_game($input['game_id'], $input['player_id']);
				break;

			default:
				header('HTTP/1.1 400 Bad Request');
				print json_encode(['errormesg' => "Invalid action: $action."]);
				exit;
		}
		break;
    case 'players':
        switch ($b = array_shift($request)) {
            case '':
            case null:
                handle_players($method, $input);
                break;
            default:
                handle_player($method, $b, $input);
                break;
        }
        break;
    case 'status':
        if (empty($request)) {
            handle_status($method);
        } else {
            header("HTTP/1.1 404 Not Found");
            exit;
        }
        break;

    default:
        header("HTTP/1.1 404 Not Found");
        exit;
}

function handle_board($method, $input) {
    if (!isset($input['game_id'])) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Missing game_id parameter.']);
        exit;
    }

    $gameId = intval($input['game_id']);

    switch ($method) {
        case 'GET':
            show_board($gameId); // Περνά το $gameId
            break;
        case 'POST':
            reset_board($gameId);
            show_board($gameId);
            break;
        default:
            header('HTTP/1.1 405 Method Not Allowed');
            print json_encode(['errormesg' => "Method $method not allowed for resource board."]);
            exit;
    }
}
function handle_tile($method, $x, $y, $input) {
    if ($x === null || $y === null) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Coordinates x and y are required.']);
        exit;
    }

    $x = intval($x);
    $y = intval($y);

    switch ($method) {
        case 'GET':
            show_tile($x, $y);
            break;
        case 'PUT':
            if (!isset($input['tile']) || !isset($input['token'])) {
                header('HTTP/1.1 400 Bad Request');
                print json_encode(['errormesg' => 'Tile and token are required.']);
                exit;
            }
            place_tile($x, $y, $input['tile'], $input['token']);
            break;
        default:
            header('HTTP/1.1 405 Method Not Allowed');
            print json_encode(['errormesg' => "Method $method not allowed for resource tile."]);
            exit;
    }
}
function handle_players($method, $input) {
    if ($method == 'GET') {
        show_players();
    } else {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg' => "Method $method not allowed for players."]);
    }
}
/*function handle_player($method, $player, $input) {
    if ($player === null) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg' => "Missing player identifier."]);
        exit;
    }
    switch ($method) {
        case 'GET':
            show_player($player);
            break;
        case 'PUT':
            update_player($player, $input);
            break;
        default:
            header("HTTP/1.1 405 Method Not Allowed");
            print json_encode(['errormesg' => "Method $method not allowed for player."]);
            exit;
    }
}*/
function handle_status($method) {
    if ($method == 'GET') {
        show_status();
    } else {
        header('HTTP/1.1 405 Method Not Allowed');
        print json_encode(['errormesg' => "Method $method not allowed for status."]);
    }
}
?>