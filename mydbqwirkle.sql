-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Εξυπηρετητής: 127.0.0.1
-- Χρόνος δημιουργίας: 10 Νοε 2024 στις 15:28:34
-- Έκδοση διακομιστή: 10.4.32-MariaDB
-- Έκδοση PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Βάση δεδομένων: `mydbqwirkle`
--

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `actions`
--

CREATE TABLE `actions` (
  `action_id` int(11) NOT NULL,
  `action_name` enum('swap','undo','end turn','leave') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `games`
--

CREATE TABLE `games` (
  `game_id` int(11) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `status` enum('initialized','active','completed','aboard') NOT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `player_count` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Δείκτες `games`
--
DELIMITER $$
CREATE TRIGGER `update_end_time` AFTER UPDATE ON `games` FOR EACH ROW BEGIN
    -- Ελέγχουμε αν η κατάσταση άλλαξε σε 'completed'
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Αν ναι, ενημερώνουμε το end_time με την τρέχουσα ημερομηνία και ώρα
        UPDATE Games
        SET end_time = CURRENT_TIMESTAMP
        WHERE game_id = NEW.game_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `gamestate`
--

CREATE TABLE `gamestate` (
  `game_id` int(11) NOT NULL,
  `current_turn_player_id` int(11) DEFAULT NULL,
  `current_turn_attribute_id` int(11) DEFAULT NULL,
  `current_action_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `game_history`
--

CREATE TABLE `game_history` (
  `history_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `turn_number` int(11) DEFAULT NULL,
  `action_id` int(11) DEFAULT NULL,
  `tile_id` int(11) DEFAULT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `game_players`
--

CREATE TABLE `game_players` (
  `game_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `turn_order` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `players`
--

CREATE TABLE `players` (
  `player_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `tileattributes`
--

CREATE TABLE `tileattributes` (
  `attribute_id` int(11) NOT NULL,
  `color` enum('red','blue','green','yellow','purple','orange') NOT NULL,
  `shape` enum('circle','square','triangle','star','hexagon') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `tiles`
--

CREATE TABLE `tiles` (
  `tile_id` int(11) NOT NULL,
  `attribute_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `is_used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Ευρετήρια για άχρηστους πίνακες
--

--
-- Ευρετήρια για πίνακα `actions`
--
ALTER TABLE `actions`
  ADD PRIMARY KEY (`action_id`);

--
-- Ευρετήρια για πίνακα `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `winner_id` (`winner_id`);

--
-- Ευρετήρια για πίνακα `gamestate`
--
ALTER TABLE `gamestate`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `current_turn_player_id` (`current_turn_player_id`),
  ADD KEY `current_turn_attribute_id` (`current_turn_attribute_id`),
  ADD KEY `current_action_id` (`current_action_id`);

--
-- Ευρετήρια για πίνακα `game_history`
--
ALTER TABLE `game_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `tile_id` (`tile_id`),
  ADD KEY `action_id` (`action_id`);

--
-- Ευρετήρια για πίνακα `game_players`
--
ALTER TABLE `game_players`
  ADD PRIMARY KEY (`game_id`,`player_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Ευρετήρια για πίνακα `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`player_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Ευρετήρια για πίνακα `tileattributes`
--
ALTER TABLE `tileattributes`
  ADD PRIMARY KEY (`attribute_id`),
  ADD UNIQUE KEY `color` (`color`,`shape`);

--
-- Ευρετήρια για πίνακα `tiles`
--
ALTER TABLE `tiles`
  ADD PRIMARY KEY (`tile_id`),
  ADD KEY `attribute_id` (`attribute_id`),
  ADD KEY `game_id` (`game_id`);

--
-- AUTO_INCREMENT για άχρηστους πίνακες
--

--
-- AUTO_INCREMENT για πίνακα `actions`
--
ALTER TABLE `actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `games`
--
ALTER TABLE `games`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `game_history`
--
ALTER TABLE `game_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `players`
--
ALTER TABLE `players`
  MODIFY `player_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `tileattributes`
--
ALTER TABLE `tileattributes`
  MODIFY `attribute_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT για πίνακα `tiles`
--
ALTER TABLE `tiles`
  MODIFY `tile_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Περιορισμοί για άχρηστους πίνακες
--

--
-- Περιορισμοί για πίνακα `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`winner_id`) REFERENCES `players` (`player_id`);

--
-- Περιορισμοί για πίνακα `gamestate`
--
ALTER TABLE `gamestate`
  ADD CONSTRAINT `gamestate_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`),
  ADD CONSTRAINT `gamestate_ibfk_2` FOREIGN KEY (`current_turn_player_id`) REFERENCES `players` (`player_id`),
  ADD CONSTRAINT `gamestate_ibfk_3` FOREIGN KEY (`current_turn_attribute_id`) REFERENCES `tileattributes` (`attribute_id`),
  ADD CONSTRAINT `gamestate_ibfk_4` FOREIGN KEY (`current_action_id`) REFERENCES `actions` (`action_id`);

--
-- Περιορισμοί για πίνακα `game_history`
--
ALTER TABLE `game_history`
  ADD CONSTRAINT `game_history_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`),
  ADD CONSTRAINT `game_history_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`player_id`),
  ADD CONSTRAINT `game_history_ibfk_3` FOREIGN KEY (`tile_id`) REFERENCES `tiles` (`tile_id`),
  ADD CONSTRAINT `game_history_ibfk_4` FOREIGN KEY (`action_id`) REFERENCES `actions` (`action_id`);

--
-- Περιορισμοί για πίνακα `game_players`
--
ALTER TABLE `game_players`
  ADD CONSTRAINT `game_players_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`),
  ADD CONSTRAINT `game_players_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`player_id`);

--
-- Περιορισμοί για πίνακα `tiles`
--
ALTER TABLE `tiles`
  ADD CONSTRAINT `tiles_ibfk_1` FOREIGN KEY (`attribute_id`) REFERENCES `tileattributes` (`attribute_id`),
  ADD CONSTRAINT `tiles_ibfk_2` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
