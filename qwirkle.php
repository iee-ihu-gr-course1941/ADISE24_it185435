<?php

ini_set("log_errors", 1);
ini_set("error_log", "logs/php-error.log");

require_once "lib/dbconnect.php";
require_once "lib/board.php";
require_once "lib/game.php";
require_once "lib/actions.php";
// require_once "lib/auth.php"; 

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$input = json_decode(file_get_contents('php://input'), true);

// $user = validate_token(); 

switch ($r = array_shift($request)) {
    case 'board':
        switch ($b = array_shift($request)) {
            case '':
            case null:
                handle_board($method); // Αφαιρείται η χρήση του $user
                break;
            default:
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'Endpoint not found']);
                break;
        }
        break;

    case 'status':
        handle_status($method); 
        break;

    case 'players':
        handle_player($method, $request, $input); 
        break;

    case 'actions':
        handle_actions($method, $request, $input); 
        break;

    default:
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['error' => 'Endpoint not found']);
        exit;
}

function handle_board($method) {
    if ($method == 'GET') {
        show_board(); // Αφαιρείται το $user
    } else if ($method == 'POST') {
        reset_board(); // Αφαιρείται το $user
    } else {
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handle_status($method) { 
    if ($method == 'GET') {
        show_status($method); // Αφαιρείται το $user
    } else {
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['error' => 'Method not allowed']);
    }
}

function handle_player($method, $p, $input) { 
    if ($method == 'POST') {
        echo json_encode(['message' => 'Player creation is managed externally.']);
    } else {
        header('HTTP/1.1 405 Method Not Allowed');
        echo json_encode(['error' => 'Method not allowed']);
    }
}
?>
