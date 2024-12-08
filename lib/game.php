<?php
require_once "helpers.php";

function show_status() {
    global $mysqli;

    $game_id = get_game_id('GET'); 
    $sql = 'SELECT g.game_id, g.status, g.start_time, g.end_time, gs.current_turn_player_id 
            FROM games g
            LEFT JOIN gamestate gs ON g.game_id = gs.game_id
            WHERE g.game_id = ?';
    $st = $mysqli->prepare($sql);

    if (!$st) {
        respond_with_error(500, 'Failed to prepare statement');
    }

    try {
        $st->bind_param('i', $game_id);
        $st->execute();
        $res = $st->get_result();

        if ($res->num_rows > 0) {
            header('Content-type: application/json');
            echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
        } else {
            respond_with_error(404, 'Game not found');
        }
    } catch (Exception $e) {
        respond_with_error(500, 'Failed to execute query: ' . $e->getMessage());
    }
}
?>
