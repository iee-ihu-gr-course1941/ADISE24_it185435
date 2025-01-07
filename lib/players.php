<?php
require_once "lib/dbconnect.php";

function show_player($player_id) {
    global $mysqli;

    if (!$player_id) {
        response_json(400, 'Missing player_id parameter.');
        return;
    }

    $sql = "SELECT player_id, username, email FROM players WHERE player_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $player_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $player = $result->fetch_assoc();

    if (!$player) {
        response_json(404, 'Player not found.');
        return;
    }

    response_json(200, 'Player retrieved successfully.', $player);
}
function show_player_stats($player_id = null, $game_id = null) {
    global $mysqli;

	$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : null;
	$player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : null;


	show_player_stats($player_id, $game_id);

    // Έλεγχος αν λείπουν και οι δύο παράμετροι
    if (!$player_id && !$game_id) {
        response_json(400, 'Missing player_id or game_id parameter.');
        return;
    }

    if ($player_id) {
        // Ανάκτηση στατιστικών για συγκεκριμένο παίκτη
        $sql = "
            SELECT 
                p.username, 
                COUNT(gp.game_id) AS games_played, 
                SUM(gp.score) AS total_score 
            FROM players p
            LEFT JOIN game_players gp ON p.player_id = gp.player_id
            WHERE p.player_id = ?
            GROUP BY p.player_id";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $player_id);
    } else if ($game_id) {
        // Ανάκτηση στατιστικών για όλους τους παίκτες σε ένα παιχνίδι
        $sql = "
            SELECT 
                p.username, 
                gp.score AS total_score 
            FROM players p
            JOIN game_players gp ON p.player_id = gp.player_id
            WHERE gp.game_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $game_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_all(MYSQLI_ASSOC);

    if (!$stats) {
        response_json(404, 'No stats found.');
        return;
    }

    response_json(200, 'Player stats retrieved successfully.', $stats);
}


?>

