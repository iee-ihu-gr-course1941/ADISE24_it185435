-- --------------------------------------------------------
-- Διακομιστής:                  127.0.0.1
-- Έκδοση διακομιστή:            10.4.32-MariaDB - mariadb.org binary distribution
-- Λειτ. σύστημα διακομιστή:     Win64
-- HeidiSQL Έκδοση:              12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for mydbqwirkle
CREATE DATABASE IF NOT EXISTS `mydbqwirkle` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `mydbqwirkle`;

-- Dumping structure for πίνακας mydbqwirkle.actions
CREATE TABLE IF NOT EXISTS `actions` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `action_name` enum('place','swap','undo','leave') NOT NULL,
  PRIMARY KEY (`action_id`),
  UNIQUE KEY `action_name` (`action_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.actions: ~4 rows (approximately)
DELETE FROM `actions`;
INSERT INTO `actions` (`action_id`, `action_name`) VALUES
	(1, 'place'),
	(2, 'swap'),
	(3, 'undo'),
	(4, 'leave');

-- Dumping structure for πίνακας mydbqwirkle.board
CREATE TABLE IF NOT EXISTS `board` (
  `board_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `tile_id` int(11) NOT NULL,
  `attribute_id` int(11) DEFAULT NULL,
  `x` int(11) NOT NULL,
  `y` int(11) NOT NULL,
  `status` enum('placed','pending') NOT NULL DEFAULT 'placed',
  PRIMARY KEY (`board_id`),
  UNIQUE KEY `game_id` (`game_id`,`x`,`y`),
  KEY `tile_id` (`tile_id`),
  CONSTRAINT `board_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `board_ibfk_2` FOREIGN KEY (`tile_id`) REFERENCES `tiles` (`tile_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.board: ~2 rows (approximately)
DELETE FROM `board`;
INSERT INTO `board` (`board_id`, `game_id`, `tile_id`, `attribute_id`, `x`, `y`, `status`) VALUES
	(11, 1, 1, 1, 0, 0, 'placed'),
	(12, 1, 2, 1, 1, 0, 'placed');

-- Dumping structure for procedure mydbqwirkle.clean_board
DELIMITER //
CREATE PROCEDURE `clean_board`(IN p_game_id INT)
BEGIN
    DELETE FROM tiles WHERE game_id = p_game_id;
END//
DELIMITER ;

-- Dumping structure for πίνακας mydbqwirkle.games
CREATE TABLE IF NOT EXISTS `games` (
  `game_id` int(11) NOT NULL AUTO_INCREMENT,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `status` enum('initialized','active','completed','aboard') NOT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `player_count` int(11) NOT NULL,
  PRIMARY KEY (`game_id`),
  KEY `winner_id` (`winner_id`),
  CONSTRAINT `games_ibfk_1` FOREIGN KEY (`winner_id`) REFERENCES `players` (`player_id`),
  CONSTRAINT `check_player_count` CHECK (`player_count` between 2 and 4)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.games: ~0 rows (approximately)
DELETE FROM `games`;
INSERT INTO `games` (`game_id`, `start_time`, `end_time`, `status`, `winner_id`, `player_count`) VALUES
	(1, '2024-12-08 09:06:39', '2024-12-11 00:15:18', 'aboard', NULL, 2),
	(2, '2024-12-10 21:53:18', NULL, 'active', NULL, 2);

-- Dumping structure for πίνακας mydbqwirkle.gamestate
CREATE TABLE IF NOT EXISTS `gamestate` (
  `game_id` int(11) NOT NULL,
  `current_turn_player_id` int(11) DEFAULT NULL,
  `current_turn_attribute_id` int(11) DEFAULT NULL,
  `current_action_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`game_id`),
  KEY `current_turn_player_id` (`current_turn_player_id`),
  KEY `current_turn_attribute_id` (`current_turn_attribute_id`),
  KEY `current_action_id` (`current_action_id`),
  CONSTRAINT `gamestate_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`),
  CONSTRAINT `gamestate_ibfk_2` FOREIGN KEY (`current_turn_player_id`) REFERENCES `players` (`player_id`),
  CONSTRAINT `gamestate_ibfk_3` FOREIGN KEY (`current_turn_attribute_id`) REFERENCES `tileattributes` (`attribute_id`),
  CONSTRAINT `gamestate_ibfk_4` FOREIGN KEY (`current_action_id`) REFERENCES `actions` (`action_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.gamestate: ~1 rows (approximately)
DELETE FROM `gamestate`;
INSERT INTO `gamestate` (`game_id`, `current_turn_player_id`, `current_turn_attribute_id`, `current_action_id`) VALUES
	(1, 1, NULL, NULL);

-- Dumping structure for πίνακας mydbqwirkle.game_history
CREATE TABLE IF NOT EXISTS `game_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `turn_number` int(11) DEFAULT NULL,
  `action_id` int(11) DEFAULT NULL,
  `tile_id` int(11) DEFAULT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `game_id` (`game_id`),
  KEY `player_id` (`player_id`),
  KEY `tile_id` (`tile_id`),
  KEY `action_id` (`action_id`),
  CONSTRAINT `game_history_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`),
  CONSTRAINT `game_history_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`player_id`),
  CONSTRAINT `game_history_ibfk_3` FOREIGN KEY (`tile_id`) REFERENCES `tiles` (`tile_id`),
  CONSTRAINT `game_history_ibfk_4` FOREIGN KEY (`action_id`) REFERENCES `actions` (`action_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.game_history: ~2 rows (approximately)
DELETE FROM `game_history`;
INSERT INTO `game_history` (`history_id`, `game_id`, `player_id`, `turn_number`, `action_id`, `tile_id`, `action_time`) VALUES
	(14, 1, 1, 1, 1, 1, '2024-12-11 00:15:09'),
	(15, 1, 2, 2, 1, 2, '2024-12-11 00:15:18');

-- Dumping structure for πίνακας mydbqwirkle.game_players
CREATE TABLE IF NOT EXISTS `game_players` (
  `game_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `turn_order` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_action_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`game_id`,`player_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `game_players_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`),
  CONSTRAINT `game_players_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.game_players: ~2 rows (approximately)
DELETE FROM `game_players`;
INSERT INTO `game_players` (`game_id`, `player_id`, `score`, `turn_order`, `is_active`, `last_action_time`) VALUES
	(1, 1, 0, 1, 1, '2024-12-10 21:22:07'),
	(1, 2, 0, 2, 1, '2024-12-10 21:22:07');

-- Dumping structure for πίνακας mydbqwirkle.players
CREATE TABLE IF NOT EXISTS `players` (
  `player_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `access_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`player_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.players: ~3 rows (approximately)
DELETE FROM `players`;
INSERT INTO `players` (`player_id`, `username`, `email`, `created_at`, `access_token`) VALUES
	(1, 'test_player', 'test_player@example.com', '2024-12-08 10:20:37', NULL),
	(2, 'player1', 'player1@example.com', '2024-12-10 21:18:50', NULL),
	(3, 'player2', 'player2@example.com', '2024-12-10 21:18:50', NULL);

-- Dumping structure for πίνακας mydbqwirkle.tileattributes
CREATE TABLE IF NOT EXISTS `tileattributes` (
  `attribute_id` int(11) NOT NULL AUTO_INCREMENT,
  `color` enum('red','blue','green','yellow','purple','orange') NOT NULL,
  `shape` enum('circle','square','triangle','star','hexagon') NOT NULL,
  PRIMARY KEY (`attribute_id`),
  UNIQUE KEY `color` (`color`,`shape`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.tileattributes: ~30 rows (approximately)
DELETE FROM `tileattributes`;
INSERT INTO `tileattributes` (`attribute_id`, `color`, `shape`) VALUES
	(1, 'red', 'circle'),
	(2, 'red', 'square'),
	(3, 'red', 'triangle'),
	(4, 'red', 'star'),
	(5, 'red', 'hexagon'),
	(6, 'blue', 'circle'),
	(7, 'blue', 'square'),
	(8, 'blue', 'triangle'),
	(9, 'blue', 'star'),
	(10, 'blue', 'hexagon'),
	(11, 'green', 'circle'),
	(12, 'green', 'square'),
	(13, 'green', 'triangle'),
	(14, 'green', 'star'),
	(15, 'green', 'hexagon'),
	(16, 'yellow', 'circle'),
	(17, 'yellow', 'square'),
	(18, 'yellow', 'triangle'),
	(19, 'yellow', 'star'),
	(20, 'yellow', 'hexagon'),
	(21, 'purple', 'circle'),
	(22, 'purple', 'square'),
	(23, 'purple', 'triangle'),
	(24, 'purple', 'star'),
	(25, 'purple', 'hexagon'),
	(26, 'orange', 'circle'),
	(27, 'orange', 'square'),
	(28, 'orange', 'triangle'),
	(29, 'orange', 'star'),
	(30, 'orange', 'hexagon');

-- Dumping structure for πίνακας mydbqwirkle.tiles
CREATE TABLE IF NOT EXISTS `tiles` (
  `tile_id` int(11) NOT NULL AUTO_INCREMENT,
  `attribute_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `row` int(11) DEFAULT NULL,
  `col` int(11) DEFAULT NULL,
  `status` enum('available','placed') DEFAULT 'available',
  PRIMARY KEY (`tile_id`),
  KEY `attribute_id` (`attribute_id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `tiles_ibfk_1` FOREIGN KEY (`attribute_id`) REFERENCES `tileattributes` (`attribute_id`),
  CONSTRAINT `tiles_ibfk_2` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mydbqwirkle.tiles: ~4 rows (approximately)
DELETE FROM `tiles`;
INSERT INTO `tiles` (`tile_id`, `attribute_id`, `game_id`, `row`, `col`, `status`) VALUES
	(1, 1, 1, 0, 0, 'placed'),
	(2, 1, 1, 1, 0, 'placed'),
	(3, 2, 1, NULL, NULL, 'available'),
	(4, 3, 1, NULL, NULL, 'available');

-- Dumping structure for trigger mydbqwirkle.update_end_time
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER update_end_time
AFTER UPDATE ON Games
FOR EACH ROW
BEGIN
    -- Ελέγχουμε αν η κατάσταση άλλαξε σε 'completed'
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Αν ναι, ενημερώνουμε το end_time με την τρέχουσα ημερομηνία και ώρα
        UPDATE Games
        SET end_time = CURRENT_TIMESTAMP
        WHERE game_id = NEW.game_id;
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
