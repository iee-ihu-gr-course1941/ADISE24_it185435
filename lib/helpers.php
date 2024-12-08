<?php
function get_game_id($method) {
    $game_id = ($method === 'GET') ? $_GET['game_id'] ?? null : $_POST['game_id'] ?? null;

    if (!$game_id) {
        respond_with_error(400, 'game_id is required');
    }

    return $game_id;
}

function respond_with_error($http_status, $error_message) {
    header("HTTP/1.1 $http_status");
    echo json_encode(['error' => $error_message]);
    error_log("[$http_status] $error_message", 3, "logs/errors.log");
    exit;
}
?>
