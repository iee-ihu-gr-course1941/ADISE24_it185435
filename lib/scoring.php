<?php

require_once "lib/dbconnect.php";

function calculate_score($game_id, $move) {
    global $mysqli;

    $x = $move['x'] ?? null;
    $y = $move['y'] ?? null;
    $tile_id = $move['tile_id'] ?? null;

    if (!$x || !$y || !$tile_id) {
        return 0;
    }

    $score = 0;

    // Check horizontal row
    $horizontal_query = "SELECT COUNT(*) as count FROM board WHERE game_id = ? AND y = ? AND (x = ? OR x = ?)";
    $stmt = $mysqli->prepare($horizontal_query);
    $stmt->bind_param('iiii', $game_id, $y, $x - 1, $x + 1);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $score += $row['count'];

    // Check vertical row
    $vertical_query = "SELECT COUNT(*) as count FROM board WHERE game_id = ? AND x = ? AND (y = ? OR y = ?)";
    $stmt = $mysqli->prepare($vertical_query);
    $stmt->bind_param('iiii', $game_id, $x, $y - 1, $y + 1);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $score += $row['count'];

    // Add points for placing the tile itself
    $score += 1;

    return $score;
}

?>
