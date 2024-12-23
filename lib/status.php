<?php
function show_status($gameId) {
    global $db;

    try {
        $stmt = $db->prepare("SELECT g.status, g.start_time, g.end_time, gs.current_turn_player_id FROM games g INNER JOIN gamestate gs ON g.game_id = gs.game_id WHERE g.game_id = ?");
        $stmt->execute([$gameId]);

        $status = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$status) {
            header('HTTP/1.1 404 Not Found');
            print json_encode(['errormesg' => "No status found for game_id $gameId"]);
            exit;
        }

        header('Content-Type: application/json');
        print json_encode($status);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => $e->getMessage()]);
    }
}
?>