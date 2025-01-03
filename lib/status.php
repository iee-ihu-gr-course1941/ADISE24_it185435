<?php
function show_status($gameId) {
    global $mysqli; 

    try {
        $stmt = $mysqli->prepare("
            SELECT 
                g.status, 
                g.start_time, 
                g.end_time, 
                gs.current_turn_player_id 
            FROM 
                games g 
            LEFT JOIN 
                gamestate gs 
            ON 
                g.game_id = gs.game_id 
            WHERE 
                g.game_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $mysqli->error);
        }

        $stmt->bind_param('i', $gameId);
        $stmt->execute();

        $result = $stmt->get_result();
        $status = $result->fetch_assoc();

        if (!$status) {
            header('HTTP/1.1 404 Not Found');
            print json_encode(['errormesg' => "No status found for game_id $gameId"]);
            exit;
        }

        header('Content-Type: application/json');
        print json_encode($status);

        $stmt->close();
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        print json_encode(['errormesg' => 'Unexpected error: ' . $e->getMessage()]);
    }
}
?>
