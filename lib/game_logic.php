<?php

require_once "lib/dbconnect.php";

function validate_move($game_id, $move) {
    global $mysqli;

    $x = $move['x'] ?? null;
    $y = $move['y'] ?? null;
    $tile_id = $move['tile_id'] ?? null;

    if (!$x || !$y || !$tile_id) {
        return false;
    }

    // Check if the position is already occupied
    $query = "SELECT COUNT(*) as count FROM board WHERE game_id = ? AND x = ? AND y = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('iii', $game_id, $x, $y);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        return false; // Position is already occupied
    }

    // Additional logic to validate adjacency and game rules
    // Example: Ensure the tile connects to at least one other tile
    $adjacency_query = "SELECT COUNT(*) as adjacent FROM board WHERE game_id = ? AND ((x = ? AND y = ?) OR (x = ? AND y = ?) OR (x = ? AND y = ?) OR (x = ? AND y = ?))";
    $stmt = $mysqli->prepare($adjacency_query);
    $stmt->bind_param('iiiiiiii', $game_id, $x - 1, $y, $x + 1, $y, $x, $y - 1, $x, $y + 1);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['adjacent'] == 0) {
        return false; // No adjacent tiles
    }

    return true;
}

?>
