<?php

require_once "helpers.php";

function handle_board($method, $request, $input) {
    global $mysqli;

    if ($method === 'GET') {
        $game_id = $_GET['game_id'] ?? null;

        if (!$game_id) {
            respond_with_error(400, "Game ID is required");
        }

        show_board($game_id);
    } elseif ($method === 'POST') {
        reset_board($input);
    } else {
        respond_with_error(405, "Method not allowed");
    }
}

function show_board($game_id) {
    global $mysqli;

    $query = "SELECT * FROM board WHERE game_id = ? ORDER BY x, y";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $game_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $board = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["status" => "success", "board" => $board]);
}

function reset_board($input) {
    global $mysqli;

    $game_id = $input['game_id'] ?? null;

    if (!$game_id) {
        respond_with_error(400, "Game ID is required");
    }

    validate_game_id($game_id);

    // Clear the board
    $query = "DELETE FROM board WHERE game_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $game_id);
    $stmt->execute();

    // Reset tiles
    $query = "UPDATE tiles SET status = 'available' WHERE game_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $game_id);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Board reset successfully"]);
}
?>
