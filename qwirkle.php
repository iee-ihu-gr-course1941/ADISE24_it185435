<?php

ini_set("log_errors", 1);
ini_set("error_log", "logs/php-error.log");

require_once "lib/dbconnect.php";
require_once "lib/board.php";
require_once "game.php";
require_once "lib/actions.php";

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$input = json_decode(file_get_contents('php://input'), true);

switch ($r = array_shift($request)) {
    case 'board':
        handle_board($method, $request, $input);
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

?>
