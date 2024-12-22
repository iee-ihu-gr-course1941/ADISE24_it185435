<?php
require_once "lib/dbconnect.php";
require_once "lib/board.php";
require_once "lib/actions.php";
require_once "lib/players.php";
require_once "lib/status.php";

$method = $_SERVER['REQUEST_METHOD'];
$resource = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : null;
$queryParams = $_GET;
$request = $resource ? explode('/', $resource) : [];
$input = json_decode(file_get_contents('php://input'), true);

if ($input === null) {
    $input = [];
}
if (isset($_SERVER['HTTP_X_TOKEN'])) {
    $input['token'] = $_SERVER['HTTP_X_TOKEN'];
} else {
    $input['token'] = '';
}

switch ($resource) {
    case 'board':
        switch ($b = array_shift($request)) {
            case '':
            case null:
                handle_board($method, $queryParams, $input);
                break;
            case 'tile':
                handle_tile($method, $request[0], $request[1], $input);
                break;
            default:
                header("HTTP/1.1 404 Not Found");
                exit;
        }
        break;
    case 'actions':
        handle_actions($method, $request, $input);
        break;
    case 'players':
        handle_players($method, $request, $input);
        break;
    case 'status':
        if (sizeof($request) == 0) {
            handle_status($method);
        } else {
            header("HTTP/1.1 404 Not Found");
        }
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        exit;
}

function handle_board($method, $queryParams, $input) {
    if (!isset($queryParams['game_id'])) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Missing game_id parameter.']);
        exit;
    }

    $gameId = intval($queryParams['game_id']);

    switch ($method) {
        case 'GET':
            show_board($gameId);
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
    if (empty($x) || empty($y)) {
        header('HTTP/1.1 400 Bad Request');
        print json_encode(['errormesg' => 'Coordinates are required.']);
        exit;
    }

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

function handle_actions($method, $request, $input) {
    switch ($action = array_shift($request)) {
        case 'swap':
            if ($method == 'POST') {
                swap_tiles($input);
            } else {
                header('HTTP/1.1 405 Method Not Allowed');
                print json_encode(['errormesg' => "Method $method not allowed for action swap."]);
            }
            break;
        case 'undo':
            if ($method == 'POST') {
                undo_action($input);
            } else {
                header('HTTP/1.1 405 Method Not Allowed');
                print json_encode(['errormesg' => "Method $method not allowed for action undo."]);
            }
            break;
        case 'end_turn':
            if ($method == 'POST') {
                end_turn($input);
            } else {
                header('HTTP/1.1 405 Method Not Allowed');
                print json_encode(['errormesg' => "Method $method not allowed for action end_turn."]);
            }
            break;
		case 'leave_game':
            if ($method == 'POST') {
                leave_game($input);
            } else {
                header('HTTP/1.1 405 Method Not Allowed');
                print json_encode(['errormesg' => "Method $method not allowed for action leave_game."]);
            }
            break;
        default:
            header("HTTP/1.1 404 Not Found");
            print json_encode(['errormesg' => "Action $action not found."]);
            exit;
    }
}

function handle_players($method, $request, $input) {
    switch ($player = array_shift($request)) {
        case '':
        case null:
            if ($method == 'GET') {
                show_players();
            } else {
                header("HTTP/1.1 400 Bad Request");
                print json_encode(['errormesg' => "Method $method not allowed for players."]);
            }
            break;
        default:
            handle_player($method, $player, $input);
            break;
    }
}

function handle_status($method) {
    if ($method == 'GET') {
        show_status();
    } else {
        header('HTTP/1.1 405 Method Not Allowed');
        print json_encode(['errormesg' => "Method $method not allowed for status."]);
    }
}
?>
