<?php

function response_json($status, $message, $data = null) {
    http_response_code($status);
    $response = [
        'status' => $status === 200 ? 'success' : 'error',
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}
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
    error_log("[$http_status] $error_message", 3, "logs/php-error.log");
    exit;
}

function validate_game_id($game_id) {
    global $mysqli;

    $query = "SELECT game_id FROM games WHERE game_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $game_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        respond_with_error(404, "Game not found");
    }
}
?>
