<?php
function create_game($player_count) {
    global $mysqli;

    $sql = "INSERT INTO games (status, player_count) VALUES ('initialized', ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $player_count);

    if ($stmt->execute()) {
        return $stmt->insert_id;
    }

    return false;
}
function create_players_for_game($game_id, $player_count) {
    global $mysqli;

    $players = [];
    for ($i = 1; $i <= $player_count; $i++) {
        $username = "Player_" . uniqid();
        $email = $username . "@example.com";

        // Δημιουργία παίκτη
        $sql_insert_player = "INSERT INTO players (username, email) VALUES (?, ?)";
        $stmt_insert_player = $mysqli->prepare($sql_insert_player);
        $stmt_insert_player->bind_param('ss', $username, $email);

        if (!$stmt_insert_player->execute()) {
            return false;
        }

        $player_id = $stmt_insert_player->insert_id;

        // Προσθήκη παίκτη στον πίνακα game_players
        $turn_order = $i;
        $sql_insert_game_player = "
            INSERT INTO game_players (game_id, player_id, turn_order) 
            VALUES (?, ?, ?)";
        $stmt_insert_game_player = $mysqli->prepare($sql_insert_game_player);
        $stmt_insert_game_player->bind_param('iii', $game_id, $player_id, $turn_order);

        if (!$stmt_insert_game_player->execute()) {
            return false;
        }

        $players[] = [
            'player_id' => $player_id,
            'username' => $username,
            'turn_order' => $turn_order
        ];
    }

    return $players;
}

function join_game($game_id) {
    global $mysqli;

    $sql_check = "SELECT * FROM games WHERE game_id = ? AND status = 'initialized'";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param('i', $game_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $sql_update = "UPDATE games SET status = 'active' WHERE game_id = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param('i', $game_id);

        if ($stmt_update->execute()) {
            return [
                'success' => true,
                'message' => 'Game joined successfully.',
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update game status.',
            ];
        }
    }

    return [
        'success' => false,
        'message' => 'Game not found or already active.',
    ];
}
function add_player_to_game($game_id, $player_id) {
    global $mysqli;

    // Έλεγχος αν το παιχνίδι υπάρχει και είναι έγκυρο
    $sql_check_game = "SELECT player_count, status FROM games WHERE game_id = ?";
    $stmt_check_game = $mysqli->prepare($sql_check_game);
    $stmt_check_game->bind_param("i", $game_id);
    $stmt_check_game->execute();
    $result_check_game = $stmt_check_game->get_result();

    if ($result_check_game->num_rows === 0) {
        return ['success' => false, 'message' => 'Game not found.'];
    }

    $game_data = $result_check_game->fetch_assoc();

    // Έλεγχος αν το παιχνίδι έχει ήδη το μέγιστο αριθμό παικτών
    if ($game_data['player_count'] >= 4) {
        return ['success' => false, 'message' => 'Game has already reached the maximum number of players.'];
    }

    // Ενημέρωση του πίνακα `game_players`
    $turn_order = $game_data['player_count'] + 1;
    $sql_add_game_player = "INSERT INTO game_players (game_id, player_id, turn_order) VALUES (?, ?, ?)";
    $stmt_add_game_player = $mysqli->prepare($sql_add_game_player);
    $stmt_add_game_player->bind_param("iii", $game_id, $player_id, $turn_order);

    if (!$stmt_add_game_player->execute()) {
        return ['success' => false, 'message' => 'Failed to add player to game.'];
    }

    // Ενημέρωση του αριθμού παικτών
    $new_player_count = $game_data['player_count'] + 1;
    $sql_update_count = "UPDATE games SET player_count = ? WHERE game_id = ?";
    $stmt_update_count = $mysqli->prepare($sql_update_count);
    $stmt_update_count->bind_param("ii", $new_player_count, $game_id);

    if (!$stmt_update_count->execute()) {
        return ['success' => false, 'message' => 'Failed to update player count.'];
    }

    // Ενημέρωση του `status` σε `active` αν οι παίκτες είναι 2 ή περισσότεροι
    if ($new_player_count >= 2 && $game_data['status'] === 'initialized') {
        $sql_update_status = "UPDATE games SET status = 'active' WHERE game_id = ?";
        $stmt_update_status = $mysqli->prepare($sql_update_status);
        $stmt_update_status->bind_param("i", $game_id);

        if (!$stmt_update_status->execute()) {
            return ['success' => false, 'message' => 'Failed to update game status to active.'];
        }
    }

    return [
        'success' => true,
        'player_count' => $new_player_count
    ];
}

function add_or_get_player($username, $email) {
    global $mysqli;

    // Έλεγχος αν υπάρχει ήδη ο παίκτης
    $sql_check = "SELECT player_id FROM players WHERE username = ? OR email = ?";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param('ss', $username, $email);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['player_id']; // Επιστροφή του player_id
    }

    // Δημιουργία νέου παίκτη
    $sql_insert = "INSERT INTO players (username, email) VALUES (?, ?)";
    $stmt_insert = $mysqli->prepare($sql_insert);
    $stmt_insert->bind_param('ss', $username, $email);

    if (!$stmt_insert->execute()) {
        return false;
    }

    return $stmt_insert->insert_id; // Επιστροφή του νέου player_id
}


function generate_board($game_id) {
    $board = [];
    for ($i = 0; $i < 5; $i++) {
        $row = [];
        for ($j = 0; $j < 5; $j++) {
            $row[] = '';
        }
        $board[] = $row;
    }
    return $board;
}

function swap_tiles($game_id, $tiles) {
    global $mysqli;

    // Επιστροφή των πλακιδίων στον "σάκο"
    $sql_return_tiles = "
        UPDATE tiles 
        SET status = 'available', row = NULL, col = NULL 
        WHERE game_id = ? AND tile_id IN (" . implode(',', $tiles) . ")";
    $stmt_return_tiles = $mysqli->prepare($sql_return_tiles);
    $stmt_return_tiles->bind_param("i", $game_id);

    if (!$stmt_return_tiles->execute()) {
        response_json(500, 'Failed to return tiles: ' . $stmt_return_tiles->error);
        return;
    }

    // Ανάκτηση νέων πλακιδίων από τον "σάκο"
    $sql_get_new_tiles = "
        SELECT tile_id, attribute_id 
        FROM tiles 
        WHERE game_id = ? AND status = 'available' 
        LIMIT ?";
    $stmt_get_new_tiles = $mysqli->prepare($sql_get_new_tiles);
    $limit = count($tiles); // Αριθμός πλακιδίων που ζητήθηκαν
    $stmt_get_new_tiles->bind_param("ii", $game_id, $limit);
    $stmt_get_new_tiles->execute();
    $result_new_tiles = $stmt_get_new_tiles->get_result();

    $new_tiles = [];
    while ($row = $result_new_tiles->fetch_assoc()) {
        $new_tiles[] = $row;
    }

    // Έλεγχος αν υπάρχουν αρκετά διαθέσιμα πλακίδια
    if (count($new_tiles) < $limit) {
        response_json(400, 'Not enough available tiles in the sack.');
        return;
    }

    // Ενημέρωση των νέων πλακιδίων ως ανατεθειμένα
    foreach ($new_tiles as $tile) {
        $sql_assign_tile = "
            UPDATE tiles 
            SET status = 'assigned' 
            WHERE tile_id = ?";
        $stmt_assign_tile = $mysqli->prepare($sql_assign_tile);
        $stmt_assign_tile->bind_param("i", $tile['tile_id']);

        if (!$stmt_assign_tile->execute()) {
            response_json(500, 'Failed to assign new tile: ' . $stmt_assign_tile->error);
            return;
        }
    }

    response_json(200, 'Tiles swapped successfully.', ['new_tiles' => $new_tiles]);
}

function undo_action($game_id, $player_id) {
    global $mysqli;

    // Αναίρεση των ενεργειών από τον πίνακα board
    $sql_undo = "DELETE FROM board WHERE game_id = ? AND player_id = ? AND status = 'placed'";
    $stmt = $mysqli->prepare($sql_undo);
    $stmt->bind_param("ii", $game_id, $player_id);

    if (!$stmt->execute()) {
        response_json(500, 'Failed to undo action: ' . $stmt->error);
        return;
    }

    response_json(200, 'Last action undone successfully.');
}

function end_turn($game_id, $player_id) {
    global $mysqli;

    // Ενημέρωση της σειράς γύρων
    $sql_end_turn = "
        UPDATE game_players 
        SET is_active = 0 
        WHERE game_id = ? AND player_id = ?";
    $stmt = $mysqli->prepare($sql_end_turn);
    $stmt->bind_param("ii", $game_id, $player_id);

    if (!$stmt->execute()) {
        response_json(500, 'Failed to end turn: ' . $stmt->error);
        return;
    }

    // Ενεργοποίηση του επόμενου παίκτη
    $sql_next_player = "
        UPDATE game_players 
        SET is_active = 1 
        WHERE game_id = ? AND turn_order = (
            SELECT MIN(turn_order) 
            FROM game_players 
            WHERE game_id = ? AND is_active = 0
        )";
    $stmt_next = $mysqli->prepare($sql_next_player);
    $stmt_next->bind_param("ii", $game_id, $game_id);

    if (!$stmt_next->execute()) {
        response_json(500, 'Failed to set next player: ' . $stmt_next->error);
        return;
    }

    response_json(200, 'Turn ended successfully.');
}

function leave_game($game_id, $player_id) {
    global $mysqli;

    // Διαγραφή του παίκτη από το παιχνίδι
    $sql_leave = "DELETE FROM game_players WHERE game_id = ? AND player_id = ?";
    $stmt = $mysqli->prepare($sql_leave);
    $stmt->bind_param("ii", $game_id, $player_id);

    if (!$stmt->execute()) {
        response_json(500, 'Failed to leave game: ' . $stmt->error);
        return;
    }

    // Ενημέρωση του αριθμού παικτών
    $sql_update_count = "UPDATE games SET player_count = player_count - 1 WHERE game_id = ?";
    $stmt_update = $mysqli->prepare($sql_update_count);
    $stmt_update->bind_param("i", $game_id);

    if (!$stmt_update->execute()) {
        response_json(500, 'Failed to update player count: ' . $stmt_update->error);
        return;
    }

    response_json(200, 'Player left the game successfully.');
}


function update_game_status($game_id) {
    global $mysqli;

    // Ανάκτηση παικτών από τη στήλη `players`
    $sql = "SELECT players FROM games WHERE game_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $players = json_decode($row['players'], true) ?? [];

    // Ενημέρωση σε active αν υπάρχουν τουλάχιστον 2 παίκτες
    if (count($players) >= 2) {
        $sql_update = "UPDATE games SET status = 'active' WHERE game_id = ? AND status = 'initialized'";
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param('i', $game_id);
        $stmt_update->execute();
    }
}

function get_players_for_game($game_id) {
    global $mysqli;

    $sql = "SELECT p.player_id, p.username, gp.turn_order, gp.score
            FROM players p
            JOIN game_players gp ON p.player_id = gp.player_id
            WHERE gp.game_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $game_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }

    return $players;
}
?>
