-- phpMyAdmin SQL Dump
-- version 5.2.2-1.el9
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 11, 2025 at 11:15 PM
-- Server version: 8.0.41
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mtg`
--
CREATE DATABASE IF NOT EXISTS `mtg` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mtg`;

-- --------------------------------------------------------

--
-- Table structure for table `1collection`
--

DROP TABLE IF EXISTS `1collection`;
CREATE TABLE `1collection` (
  `id` varchar(36) NOT NULL,
  `normal` tinyint DEFAULT NULL,
  `foil` tinyint DEFAULT NULL,
  `etched` tinyint DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `topvalue` decimal(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `key` int NOT NULL,
  `usemin` tinyint(1) NOT NULL,
  `mtce` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Initial data for table `admin`
--

INSERT INTO `admin` (`key`, `usemin`, `mtce`) VALUES
(1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `cards_scry`
--

DROP TABLE IF EXISTS `cards_scry`;
CREATE TABLE `cards_scry` (
  `id` varchar(36) NOT NULL,
  `oracle_id` varchar(36) DEFAULT NULL,
  `tcgplayer_id` mediumint DEFAULT NULL,
  `multiverse` mediumint DEFAULT NULL,
  `multiverse2` mediumint DEFAULT NULL,
  `name` varchar(256) NOT NULL,
  `printed_name` varchar(256) DEFAULT NULL,
  `flavor_name` varchar(256) DEFAULT NULL,
  `lang` varchar(3) DEFAULT NULL,
  `primary_card` tinyint DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `set_name` varchar(64) DEFAULT NULL,
  `setcode` varchar(8) DEFAULT NULL,
  `set_id` varchar(64) DEFAULT NULL,
  `game_types` json DEFAULT NULL,
  `finishes` json DEFAULT NULL,
  `promo_types` json DEFAULT NULL,
  `type` varchar(128) DEFAULT NULL,
  `power` varchar(8) DEFAULT NULL,
  `toughness` varchar(8) DEFAULT NULL,
  `loyalty` varchar(8) DEFAULT NULL,
  `manacost` varchar(64) DEFAULT NULL,
  `cmc` decimal(9,1) DEFAULT NULL,
  `artist` varchar(64) DEFAULT NULL,
  `flavor` varchar(1024) DEFAULT NULL,
  `color` json DEFAULT NULL,
  `color_identity` json DEFAULT NULL,
  `generatedmana` json DEFAULT NULL,
  `number` mediumint DEFAULT NULL,
  `number_import` varchar(16) DEFAULT NULL,
  `layout` varchar(32) DEFAULT NULL,
  `rarity` varchar(16) DEFAULT NULL,
  `ability` varchar(2048) DEFAULT NULL,
  `keywords` json DEFAULT NULL,
  `backid` varchar(36) DEFAULT NULL,
  `maxpower` int DEFAULT NULL,
  `minpower` int DEFAULT NULL,
  `maxtoughness` int DEFAULT NULL,
  `mintoughness` int DEFAULT NULL,
  `maxloyalty` int DEFAULT NULL,
  `minloyalty` int DEFAULT NULL,
  `f1_name` varchar(256) DEFAULT NULL,
  `f1_manacost` varchar(64) DEFAULT NULL,
  `f1_type` varchar(128) DEFAULT NULL,
  `f1_ability` mediumtext DEFAULT NULL,
  `f1_colour` json DEFAULT NULL,
  `f1_artist` varchar(64) DEFAULT NULL,
  `f1_flavor` varchar(1024) DEFAULT NULL,
  `f1_image_uri` varchar(256) DEFAULT NULL,
  `f1_power` varchar(8) DEFAULT NULL,
  `f1_toughness` varchar(8) DEFAULT NULL,
  `f1_loyalty` varchar(8) DEFAULT NULL,
  `f1_cmc` decimal(9,1) DEFAULT NULL,
  `f1_printed_name` varchar(256) DEFAULT NULL,
  `f1_flavor_name` varchar(256) DEFAULT NULL,
  `f2_name` varchar(256) DEFAULT NULL,
  `f2_manacost` varchar(64) DEFAULT NULL,
  `f2_type` varchar(128) DEFAULT NULL,
  `f2_ability` mediumtext DEFAULT NULL,
  `f2_colour` json DEFAULT NULL,
  `f2_artist` varchar(64) DEFAULT NULL,
  `f2_flavor` varchar(1024) DEFAULT NULL,
  `f2_image_uri` varchar(256) DEFAULT NULL,
  `f2_power` varchar(8) DEFAULT NULL,
  `f2_toughness` varchar(8) DEFAULT NULL,
  `f2_loyalty` varchar(8) DEFAULT NULL,
  `f2_cmc` decimal(9,1) DEFAULT NULL,
  `f2_printed_name` varchar(256) DEFAULT NULL,
  `f2_flavor_name` varchar(256) DEFAULT NULL,
  `p1_id` varchar(36) DEFAULT NULL,
  `p1_component` varchar(64) DEFAULT NULL,
  `p1_name` varchar(256) DEFAULT NULL,
  `p1_type_line` varchar(128) DEFAULT NULL,
  `p1_uri` varchar(256) DEFAULT NULL,
  `p2_id` varchar(36) DEFAULT NULL,
  `p2_component` varchar(64) DEFAULT NULL,
  `p2_name` varchar(256) DEFAULT NULL,
  `p2_type_line` varchar(128) DEFAULT NULL,
  `p2_uri` varchar(256) DEFAULT NULL,
  `p3_id` varchar(36) DEFAULT NULL,
  `p3_component` varchar(64) DEFAULT NULL,
  `p3_name` varchar(256) DEFAULT NULL,
  `p3_type_line` varchar(128) DEFAULT NULL,
  `p3_uri` varchar(256) DEFAULT NULL,
  `p4_id` varchar(36) DEFAULT NULL,
  `p4_component` varchar(64) DEFAULT NULL,
  `p4_name` varchar(256) DEFAULT NULL,
  `p4_type_line` varchar(128) DEFAULT NULL,
  `p4_uri` varchar(256) DEFAULT NULL,
  `p5_id` varchar(36) DEFAULT NULL,
  `p5_component` varchar(64) DEFAULT NULL,
  `p5_name` varchar(256) DEFAULT NULL,
  `p5_type_line` varchar(128) DEFAULT NULL,
  `p5_uri` varchar(256) DEFAULT NULL,
  `p6_id` varchar(36) DEFAULT NULL,
  `p6_component` varchar(64) DEFAULT NULL,
  `p6_name` varchar(256) DEFAULT NULL,
  `p6_type_line` varchar(128) DEFAULT NULL,
  `p6_uri` varchar(256) DEFAULT NULL,
  `p7_id` varchar(36) DEFAULT NULL,
  `p7_component` varchar(64) DEFAULT NULL,
  `p7_name` varchar(256) DEFAULT NULL,
  `p7_type_line` varchar(128) DEFAULT NULL,
  `p7_uri` varchar(256) DEFAULT NULL,
  `reserved` varchar(2) DEFAULT NULL,
  `foil` varchar(2) DEFAULT NULL,
  `nonfoil` varchar(2) DEFAULT NULL,
  `oversized` varchar(2) DEFAULT NULL,
  `promo` varchar(2) DEFAULT NULL,
  `gatherer_uri` varchar(256) DEFAULT NULL,
  `image_uri` varchar(256) DEFAULT NULL,
  `api_uri` varchar(256) DEFAULT NULL,
  `scryfall_uri` varchar(512) NOT NULL,
  `legalityblock` varchar(16) DEFAULT NULL,
  `legalitystandard` varchar(16) DEFAULT NULL,
  `legalityextended` varchar(16) DEFAULT NULL,
  `legalitymodern` varchar(16) DEFAULT NULL,
  `legalitylegacy` varchar(16) DEFAULT NULL,
  `legalityvintage` varchar(16) DEFAULT NULL,
  `legalityhighlander` varchar(16) DEFAULT NULL,
  `legalityfrenchcommander` varchar(16) DEFAULT NULL,
  `legalitytinyleaderscommander` varchar(16) DEFAULT NULL,
  `legalitymodernduelcommander` varchar(16) DEFAULT NULL,
  `legalitycommander` varchar(16) DEFAULT NULL,
  `legalitypeasant` varchar(16) DEFAULT NULL,
  `legalitypauper` varchar(16) DEFAULT NULL,
  `legalitypioneer` varchar(16) DEFAULT NULL,
  `legalityalchemy` varchar(16) DEFAULT NULL,
  `legalityhistoric` varchar(16) DEFAULT NULL,
  `updatetime` bigint NOT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `price_foil` decimal(8,2) DEFAULT NULL,
  `price_etched` decimal(8,2) DEFAULT NULL,
  `price_sort` decimal(8,2) DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  `is_paper` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"paper"')) VIRTUAL,
  `is_mtgo` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"mtgo"')) VIRTUAL,
  `is_arena` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"arena"')) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collectionTemplate`
--

DROP TABLE IF EXISTS `collectionTemplate`;
CREATE TABLE `collectionTemplate` (
  `id` varchar(36) NOT NULL,
  `normal` tinyint DEFAULT NULL,
  `foil` tinyint DEFAULT NULL,
  `etched` tinyint DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `topvalue` decimal(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `deckcards`
--

DROP TABLE IF EXISTS `deckcards`;
CREATE TABLE `deckcards` (
  `id` int NOT NULL,
  `decknumber` smallint NOT NULL,
  `cardnumber` varchar(36) NOT NULL,
  `cardqty` tinyint DEFAULT NULL,
  `sideqty` tinyint DEFAULT NULL,
  `commander` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `decks`
--

DROP TABLE IF EXISTS `decks`;
CREATE TABLE `decks` (
  `decknumber` int NOT NULL,
  `owner` smallint NOT NULL,
  `deckname` text NOT NULL,
  `notes` text DEFAULT NULL,
  `sidenotes` text DEFAULT NULL,
  `type` varchar(16) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `decktypes`
--

DROP TABLE IF EXISTS `decktypes`;
CREATE TABLE `decktypes` (
  `typenumber` smallint NOT NULL,
  `type` varchar(64) NOT NULL,
  `cardcount` int NOT NULL,
  `name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `fx`
--

DROP TABLE IF EXISTS `fx`;
CREATE TABLE `fx` (
  `currencies` varchar(12) NOT NULL,
  `updatetime` int NOT NULL,
  `rate` decimal(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
CREATE TABLE `groups` (
  `groupnumber` int NOT NULL,
  `groupname` varchar(32) NOT NULL,
  `owner` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` varchar(36) NOT NULL,
  `performed_at` date NOT NULL,
  `object` varchar(20) NOT NULL,
  `migration_strategy` varchar(20) NOT NULL,
  `uri` varchar(256) NOT NULL,
  `old_scryfall_id` varchar(36) NOT NULL,
  `new_scryfall_id` varchar(36) DEFAULT NULL,
  `note` varchar(4096) DEFAULT NULL,
  `metadata_id` varchar(36) DEFAULT NULL,
  `metadata_lang` varchar(6) DEFAULT NULL,
  `metadata_name` varchar(256) DEFAULT NULL,
  `metadata_set_code` varchar(8) DEFAULT NULL,
  `metadata_oracle_id` varchar(36) DEFAULT NULL,
  `metadata_collector_number` varchar(16) DEFAULT NULL,
  `db_match` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `rulings_scry`
--

DROP TABLE IF EXISTS `rulings_scry`;
CREATE TABLE `rulings_scry` (
  `id` int NOT NULL,
  `oracle_id` varchar(64) NOT NULL,
  `source` varchar(32) NOT NULL,
  `published_at` date NOT NULL,
  `comment` varchar(2048) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `scryfalljson`
--

DROP TABLE IF EXISTS `scryfalljson`;
CREATE TABLE `scryfalljson` (
  `id` varchar(36) NOT NULL,
  `jsonupdatetime` int NOT NULL,
  `tcg_buy_uri` varchar(256) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `sets`
--

DROP TABLE IF EXISTS `sets`;
CREATE TABLE `sets` (
  `id` varchar(36) NOT NULL,
  `code` varchar(8) DEFAULT NULL,
  `name` varchar(64) DEFAULT NULL,
  `api_uri` varchar(256) DEFAULT NULL,
  `scryfall_uri` varchar(256) DEFAULT NULL,
  `search_uri` varchar(256) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `set_type` varchar(64) DEFAULT NULL,
  `card_count` int DEFAULT NULL,
  `parent_set_code` varchar(8) DEFAULT NULL,
  `nonfoil_only` tinyint(1) DEFAULT NULL,
  `foil_only` tinyint(1) DEFAULT NULL,
  `icon_svg_uri` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `tfa_codes`
--

DROP TABLE IF EXISTS `tfa_codes`;
CREATE TABLE `tfa_codes` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `code` varchar(10) NOT NULL,
  `expiry` int NOT NULL,
  `attempts` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trusted_devices`
--

DROP TABLE IF EXISTS `trusted_devices`;
CREATE TABLE `trusted_devices` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_used` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `updatenotices`
--

DROP TABLE IF EXISTS `updatenotices`;
CREATE TABLE `updatenotices` (
  `number` int NOT NULL,
  `date` date NOT NULL,
  `update` varchar(1024) NOT NULL,
  `author` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `usernumber` smallint NOT NULL,
  `username` varchar(16) NOT NULL,
  `password` varchar(64) NOT NULL,
  `email` varchar(64) NOT NULL,
  `reg_date` date NOT NULL,
  `status` varchar(10) NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `badlogins` smallint DEFAULT NULL,
  `groupid` int NOT NULL,
  `grpinout` tinyint(1) NOT NULL DEFAULT '0',
  `lastlogin_date` date DEFAULT NULL,
  `collection_view` tinyint NOT NULL DEFAULT '0',
  `currency` varchar(3) DEFAULT NULL,
  `weeklyexport` tinyint NOT NULL DEFAULT '0',
  `tfa_enabled` tinyint NOT NULL DEFAULT '0',
  `tfa_method` varchar(20) DEFAULT NULL,
  `tfa_backup_codes` text DEFAULT NULL,
  `tfa_app_secret` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `1collection`
--
ALTER TABLE `1collection`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cards_scry`
--
ALTER TABLE `cards_scry`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oracle_id` (`oracle_id`),
  ADD KEY `release_date` (`release_date`),
  ADD KEY `setcode` (`setcode`),
  ADD KEY `primary_card` (`setcode`,`primary_card`) USING BTREE,
  ADD KEY `set_name` (`set_name`),
  ADD KEY `lang` (`lang`),
  ADD KEY `type_2` (`type`),
  ADD KEY `number` (`number`),
  ADD KEY `primary_card_2` (`primary_card`),
  ADD KEY `number_import` (`number_import`),
  ADD KEY `idx_is_paper` (`is_paper`),
  ADD KEY `idx_is_mtgo` (`is_mtgo`),
  ADD KEY `idx_is_arena` (`is_arena`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `ability` (`ability`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `name` (`name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `type` (`type`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f1_name` (`f1_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f1_type` (`f1_type`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f1_ability` (`f1_ability`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f2_name` (`f2_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f2_type` (`f2_type`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f2_ability` (`f2_ability`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `printed_name` (`printed_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `flavor_name` (`flavor_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f1_printed_name` (`f1_printed_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f1_flavor_name` (`f1_flavor_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f2_printed_name` (`f2_printed_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `f2_flavor_name` (`f2_flavor_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `combined_name_index` (`name`,`f1_name`,`f2_name`,`printed_name`,`f1_printed_name`,`f2_printed_name`,`flavor_name`,`f1_flavor_name`,`f2_flavor_name`);
ALTER TABLE `cards_scry` ADD FULLTEXT KEY `combined_ability_index` (`ability`,`f1_ability`,`f2_ability`);

--
-- Indexes for table `collectionTemplate`
--
ALTER TABLE `collectionTemplate`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deckcards`
--
ALTER TABLE `deckcards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `DeckCardCombo` (`decknumber`,`cardnumber`);

--
-- Indexes for table `decks`
--
ALTER TABLE `decks`
  ADD PRIMARY KEY (`decknumber`);

--
-- Indexes for table `decktypes`
--
ALTER TABLE `decktypes`
  ADD PRIMARY KEY (`typenumber`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `fx`
--
ALTER TABLE `fx`
  ADD PRIMARY KEY (`currencies`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`groupnumber`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rulings_scry`
--
ALTER TABLE `rulings_scry`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oracle_id` (`oracle_id`);

--
-- Indexes for table `scryfalljson`
--
ALTER TABLE `scryfalljson`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sets`
--
ALTER TABLE `sets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_3` (`name`,`release_date`,`parent_set_code`) USING BTREE,
  ADD KEY `name_2` (`name`),
  ADD KEY `code_2` (`code`),
  ADD KEY `release_date` (`release_date`);
ALTER TABLE `sets` ADD FULLTEXT KEY `code` (`code`);
ALTER TABLE `sets` ADD FULLTEXT KEY `name` (`name`);

--
-- Indexes for table `tfa_codes`
--
ALTER TABLE `tfa_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `trusted_devices`
--
ALTER TABLE `trusted_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token_hash` (`token_hash`),
  ADD KEY `expires` (`expires`);

--
-- Indexes for table `updatenotices`
--
ALTER TABLE `updatenotices`
  ADD PRIMARY KEY (`number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`usernumber`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `key` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `deckcards`
--
ALTER TABLE `deckcards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `decks`
--
ALTER TABLE `decks`
  MODIFY `decknumber` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `decktypes`
--
ALTER TABLE `decktypes`
  MODIFY `typenumber` smallint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `groupnumber` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rulings_scry`
--
ALTER TABLE `rulings_scry`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tfa_codes`
--
ALTER TABLE `tfa_codes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trusted_devices`
--
ALTER TABLE `trusted_devices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `updatenotices`
--
ALTER TABLE `updatenotices`
  MODIFY `number` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `usernumber` smallint NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
