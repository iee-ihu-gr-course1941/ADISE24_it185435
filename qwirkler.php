<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
	case 'create':
		if ($method === 'POST') {
			require_once "lib/actions.php";
			$player_count = $input['player_count'] ?? 2;
			if ($player_count < 2 || $player_count > 4) {
				echo json_encode(['success' => false, 'message' => 'Player count must be between 2 and 4.']);
				exit;
			}
			$game_id = create_game($player_count);
			if ($game_id) {
				echo json_encode(['success' => true, 'game_id' => $game_id]);
			} else {
				echo json_encode(['success' => false, 'message' => 'Failed to create game.']);
			}
		}
		break;
	case 'join':
		if ($method === 'POST') {
			$game_id = intval($input['game_id'] ?? 0);

			// Έλεγχος αν το Game ID δόθηκε
			if (!$game_id) {
				echo json_encode(['success' => false, 'message' => 'Game ID is required.']);
				exit;
			}

			// Προσπάθεια προσθήκης παίκτη στο παιχνίδι
			$result = add_player_to_game($game_id);

			// Έλεγχος αν η συνάρτηση επέστρεψε επιτυχία
			if (!$result['success']) {
				echo json_encode(['success' => false, 'message' => $result['message']]);
				exit;
			}

			// Ανάκτηση δεδομένων από το αποτέλεσμα
			$player_id = $result['player_id'] ?? null;
			$player_count = $result['player_count'] ?? null;

			// Έλεγχος για έγκυρα δεδομένα παίκτη
			if ($player_id === null || $player_count === null) {
				echo json_encode(['success' => false, 'message' => 'Failed to retrieve player information.']);
				exit;
			}

			// Επιστροφή επιτυχούς απάντησης
			echo json_encode([
				'success' => true,
				'game_id' => $game_id,
				'player_id' => $player_id,
				'player_count' => $player_count
			]);
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
			case 'get_tiles':
				if ($method === 'GET') {
					$player_id = intval($input['player_id'] ?? 0);
					if ($player_id === 0) {
						echo json_encode(['success' => false, 'message' => 'Player ID is required.']);
						exit;
					}
					echo get_tiles($player_id);
				} else {
					echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
				}
				break;
			case 'place_tile':
				header('Content-Type: application/json');
				try {
					if ($method === 'POST') {
						error_log("Input: " . json_encode($input));
						$game_id = intval($input['game_id'] ?? 0);
						$tile_id = intval($input['tile'] ?? 0);
						$position = $input['position'] ?? null;

						if ($game_id === 0 || $tile_id === 0 || !$position || !isset($position['x']) || !isset($position['y'])) {
							throw new Exception('Invalid input parameters.');
						}

						echo place_tile($game_id, $tile_id, $position);
					} else {
						throw new Exception('Invalid request method.');
					}
				} catch (Exception $e) {
					error_log("Error in place_tile: " . $e->getMessage());
					echo json_encode(['success' => false, 'message' => $e->getMessage()]);
				}
				exit;
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
            default:
                handle_players($method, $input);
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
function get_tiles($player_id) {
    global $mysqli;

    $sql = "SELECT ta.attribute_id, ta.color, ta.shape FROM tileattributes ta
            WHERE ta.attribute_id NOT IN (
                SELECT attribute_id FROM tiles WHERE status = 'placed'
            )
            ORDER BY RAND() LIMIT 6";
    $result = $mysqli->query($sql);

    if (!$result) {
        error_log("Failed to fetch tiles: " . $mysqli->error);
        return json_encode(['success' => false, 'message' => 'Failed to fetch tiles.']);
    }

    $tiles = [];
    while ($row = $result->fetch_assoc()) {
        $tiles[] = $row;
        $assign_sql = "INSERT INTO tiles (attribute_id, game_id, status) VALUES (?, NULL, 'available')";
        $stmt = $mysqli->prepare($assign_sql);
        $stmt->bind_param('i', $row['attribute_id']);
        $stmt->execute();
    }

    return json_encode(['success' => true, 'tiles' => $tiles]);
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
function handle_status($method) {
    if ($method == 'GET') {
        if (!isset($_GET['game_id'])) {
            header('HTTP/1.1 400 Bad Request');
            print json_encode(['errormesg' => 'Missing game_id parameter.']);
            exit;
        }

        $gameId = intval($_GET['game_id']);
        show_status($gameId);
    } else {
        header('HTTP/1.1 405 Method Not Allowed');
        print json_encode(['errormesg' => "Method $method not allowed for status."]);
    }
}
?>