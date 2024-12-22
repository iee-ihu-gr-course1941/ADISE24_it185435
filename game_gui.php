<?php
// Φόρτωσε το board μέσω API
$game_id = $_GET['game_id'] ?? 1;
$api_url = "http://localhost/MyProject/qwirkle.php/board?game_id=$game_id";

$board = json_decode(file_get_contents($api_url), true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qwirkle Game</title>
    <style>
        table {
            border-collapse: collapse;
            margin: 20px 0;
            width: 50%;
        }
        td {
            border: 1px solid black;
            width: 30px;
            height: 30px;
            text-align: center;
        }
        textarea {
            width: 100%;
            height: 50px;
        }
        #submitMove {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>Qwirkle Game (Game ID: <?= $game_id ?>)</h1>
    
    <h2>Board</h2>
    <table>
        <?php
        if (!empty($board['board'])) {
            $max_x = max(array_column($board['board'], 'x'));
            $max_y = max(array_column($board['board'], 'y'));

            for ($y = 0; $y <= $max_y; $y++) {
                echo "<tr>";
                for ($x = 0; $x <= $max_x; $x++) {
                    $cell = array_filter($board['board'], fn($item) => $item['x'] == $x && $item['y'] == $y);
                    $tile_id = $cell ? array_values($cell)[0]['tile_id'] : '';
                    echo "<td>$tile_id</td>";
                }
                echo "</tr>";
            }
        } else {
            echo "<p>No board data available</p>";
        }
        ?>
    </table>

    <h2>Make a Move</h2>
    <form id="moveForm">
        <input type="text" id="player_id" placeholder="Enter player_id" /><br><br>
        <textarea id="moveInput" placeholder="Enter move as: tile_id,x,y"></textarea><br>
        <button type="button" id="submitMove">Submit Move</button>
    </form>

    <div id="response"></div>

    <script>
        document.getElementById('submitMove').addEventListener('click', async () => {
            const moveInput = document.getElementById('moveInput').value.trim();
            const playerId = document.getElementById('player_id').value.trim();
            const [tile_id, x, y] = moveInput.split(',');

            if (!playerId || !tile_id || x === undefined || y === undefined) {
                document.getElementById('response').innerText = "Invalid input format. Use: player_id and move as tile_id,x,y";
                return;
            }

            const moveData = {
                game_id: <?= $game_id ?>,
                player_id: parseInt(playerId, 10),
                move: {
                    tile_id: parseInt(tile_id, 10),
                    x: parseInt(x, 10),
                    y: parseInt(y, 10)
                }
            };

            try {
                const response = await fetch("http://localhost/MyProject/qwirkle.php/actions", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(moveData)
                });
                const result = await response.json();
                document.getElementById('response').innerText = JSON.stringify(result, null, 2);

                if (response.ok) {
                    // Reload board after successful move
                    window.location.reload();
                }
            } catch (error) {
                document.getElementById('response').innerText = `Error: ${error}`;
            }
        });
    </script>
</body>
</html>
