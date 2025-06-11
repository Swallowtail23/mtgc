-- phpMyAdmin SQL Dump
-- version 5.0.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 03, 2022 at 02:21 PM
-- Server version: 8.0.26
-- PHP Version: 7.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mtg_new`
--
CREATE DATABASE IF NOT EXISTS `mtg_new` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mtg_new`;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `key` int NOT NULL AUTO_INCREMENT,
  `usemin` tinyint(1) NOT NULL,
  `mtce` tinyint(1) NOT NULL,
  PRIMARY KEY (`key`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fx`
--

DROP TABLE IF EXISTS `fx`;
CREATE TABLE `fx` (
  `currencies` varchar(12) COLLATE utf8mb4_general_ci NOT NULL,
  `updatetime` int NOT NULL,
  `rate` decimal(6,2) NOT NULL,
  UNIQUE KEY `currencies` (`currencies`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `cards_scry`
--

DROP TABLE IF EXISTS `cards_scry`;
CREATE TABLE `cards_scry` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `oracle_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tcgplayer_id` mediumint DEFAULT NULL,
  `multiverse` mediumint DEFAULT NULL,
  `multiverse2` mediumint DEFAULT NULL,
  `name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `printed_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `flavor_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lang` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `primary_card` tinyint DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `set_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `setcode` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `set_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `game_types` json DEFAULT NULL,
  `finishes` json DEFAULT NULL,
  `promo_types` json DEFAULT NULL,
  `type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `power` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `toughness` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `loyalty` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `manacost` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cmc` decimal(9,1) DEFAULT NULL,
  `artist` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `flavor` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `color` json DEFAULT NULL,
  `color_identity` json DEFAULT NULL,
  `generatedmana` json DEFAULT NULL,
  `number` mediumint DEFAULT NULL,
  `number_import` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `layout` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rarity` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ability` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `keywords` json DEFAULT NULL,
  `backid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `maxpower` int DEFAULT NULL,
  `minpower` int DEFAULT NULL,
  `maxtoughness` int DEFAULT NULL,
  `mintoughness` int DEFAULT NULL,
  `maxloyalty` int DEFAULT NULL,
  `minloyalty` int DEFAULT NULL,
  `f1_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_manacost` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_ability` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `f1_colour` json DEFAULT NULL,
  `f1_artist` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_flavor` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_image_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_power` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_toughness` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_loyalty` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_cmc` decimal(9,1) DEFAULT NULL,
  `f1_printed_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_flavor_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_manacost` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_ability` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `f2_colour` json DEFAULT NULL,
  `f2_artist` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_flavor` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_image_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_power` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_toughness` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_loyalty` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_cmc` decimal(9,1) DEFAULT NULL,
  `f2_printed_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_flavor_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reserved` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `foil` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nonfoil` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oversized` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `promo` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gatherer_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `api_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `scryfall_uri` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `legalityblock` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitystandard` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityextended` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitymodern` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitylegacy` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityvintage` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityhighlander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityfrenchcommander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitytinyleaderscommander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitymodernduelcommander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitycommander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitypeasant` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitypauper` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitypioneer` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityalchemy` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityhistoric` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updatetime` bigint NOT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `price_foil` decimal(8,2) DEFAULT NULL,
  `price_etched` decimal(8,2) DEFAULT NULL,
  `price_sort` decimal(8,2) DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  `is_paper` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"paper"')) VIRTUAL,
  `is_mtgo` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"mtgo"')) VIRTUAL,
  `is_arena` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"arena"')) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `oracle_id` (`oracle_id`),
  KEY `release_date` (`release_date`),
  KEY `setcode` (`setcode`),
  KEY `primary_card` (`setcode`,`primary_card`) USING BTREE,
  KEY `set_name` (`set_name`),
  KEY `lang` (`lang`),
  KEY `type_2` (`type`),
  KEY `number` (`number`),
  KEY `primary_card_2` (`primary_card`),
  KEY `number_import` (`number_import`),
  KEY `idx_is_paper` (`is_paper`),
  KEY `idx_is_mtgo` (`is_mtgo`),
  KEY `idx_is_arena` (`is_arena`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collectionTemplate`
--

DROP TABLE IF EXISTS `collectionTemplate`;
CREATE TABLE IF NOT EXISTS `collectionTemplate` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `normal` tinyint DEFAULT NULL,
  `foil` tinyint DEFAULT NULL,
  `etched` tinyint DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `topvalue` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deckcards`
--

DROP TABLE IF EXISTS `deckcards`;
CREATE TABLE IF NOT EXISTS `deckcards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `decknumber` smallint NOT NULL,
  `cardnumber` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cardqty` tinyint DEFAULT NULL,
  `sideqty` tinyint DEFAULT NULL,
  `commander` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `DeckCardCombo` (`decknumber`,`cardnumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `performed_at` date NOT NULL,
  `object` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `migration_strategy` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `old_scryfall_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `new_scryfall_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_lang` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_set_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_oracle_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_collector_number` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `db_match` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `decks`
--

DROP TABLE IF EXISTS `decks`;
CREATE TABLE IF NOT EXISTS `decks` (
  `decknumber` int NOT NULL AUTO_INCREMENT,
  `owner` smallint NOT NULL,
  `deckname` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `sidenotes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`decknumber`),
  UNIQUE KEY `decknumber` (`decknumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `decktypes`
--

DROP TABLE IF EXISTS `decktypes`;
CREATE TABLE IF NOT EXISTS `decktypes` (
  `typenumber` smallint NOT NULL AUTO_INCREMENT,
  `type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cardcount` int NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`typenumber`),
  UNIQUE KEY `typenumber` (`typenumber`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
CREATE TABLE IF NOT EXISTS `groups` (
  `groupnumber` int NOT NULL AUTO_INCREMENT,
  `groupname` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner` int NOT NULL,
  PRIMARY KEY (`groupnumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rulings_scry`
--

DROP TABLE IF EXISTS `rulings_scry`;
CREATE TABLE IF NOT EXISTS `rulings_scry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `oracle_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `published_at` date NOT NULL,
  `comment` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `oracle_id` (`oracle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scryfalljson`
--

DROP TABLE IF EXISTS `scryfalljson`;
CREATE TABLE IF NOT EXISTS `scryfalljson` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jsonupdatetime` int NOT NULL,
  `tcg_buy_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sets`
--

DROP TABLE IF EXISTS `sets`;
CREATE TABLE IF NOT EXISTS `sets` (
  `id` varchar(36) COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `api_uri` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `scryfall_uri` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `search_uri` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `set_type` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `card_count` int DEFAULT NULL,
  `parent_set_code` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nonfoil_only` tinyint(1) DEFAULT NULL,
  `foil_only` tinyint(1) DEFAULT NULL,
  `icon_svg_uri` varchar(256) COLLATE utf8mb4_general_ci NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `updatenotices`
--

DROP TABLE IF EXISTS `updatenotices`;
CREATE TABLE IF NOT EXISTS `updatenotices` (
  `number` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `update` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `author` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`number`),
  UNIQUE KEY `number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `usernumber` smallint NOT NULL AUTO_INCREMENT,
  `username` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reg_date` date NOT NULL,
  `status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `badlogins` smallint DEFAULT NULL,
  `groupid` int NOT NULL DEFAULT '0',
  `grpinout` tinyint(1) NOT NULL DEFAULT '0',
  `lastlogin_date` date DEFAULT NULL,
  `collection_view` tinyint NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `weeklyexport` tinyint NOT NULL DEFAULT '0',
  `tfa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `tfa_method` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tfa_backup_codes` text COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`usernumber`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `usernumber` (`usernumber`),
  KEY `username_2` (`username`),
  KEY `email_2` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cards_scry`
--
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
-- Indexes for table `sets`
--
ALTER TABLE `sets` ADD FULLTEXT KEY `code` (`code`);

-- --------------------------------------------------------

--
-- Table structure for table `trusted_devices`
--

DROP TABLE IF EXISTS `trusted_devices`;
CREATE TABLE IF NOT EXISTS `trusted_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_used` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token_hash` (`token_hash`),
  KEY `expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tfa_codes`
--

DROP TABLE IF EXISTS `tfa_codes`;
CREATE TABLE IF NOT EXISTS `tfa_codes` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `expiry` int NOT NULL,
  `attempts` int NOT NULL DEFAULT 0,
  UNIQUE KEY(user_id),
  KEY `idx_tfa_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.0.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 03, 2022 at 02:21 PM
-- Server version: 8.0.26
-- PHP Version: 7.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mtg_new`
--
CREATE DATABASE IF NOT EXISTS `mtg_new` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mtg_new`;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `key` int NOT NULL AUTO_INCREMENT,
  `usemin` tinyint(1) NOT NULL,
  `mtce` tinyint(1) NOT NULL,
  PRIMARY KEY (`key`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fx`
--

DROP TABLE IF EXISTS `fx`;
CREATE TABLE `fx` (
  `currencies` varchar(12) COLLATE utf8mb4_general_ci NOT NULL,
  `updatetime` int NOT NULL,
  `rate` decimal(6,2) NOT NULL,
  UNIQUE KEY `currencies` (`currencies`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `cards_scry`
--

DROP TABLE IF EXISTS `cards_scry`;
CREATE TABLE `cards_scry` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `oracle_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tcgplayer_id` mediumint DEFAULT NULL,
  `multiverse` mediumint DEFAULT NULL,
  `multiverse2` mediumint DEFAULT NULL,
  `name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `printed_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `flavor_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lang` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `primary_card` tinyint DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `set_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `setcode` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `set_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `game_types` json DEFAULT NULL,
  `finishes` json DEFAULT NULL,
  `promo_types` json DEFAULT NULL,
  `type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `power` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `toughness` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `loyalty` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `manacost` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cmc` decimal(9,1) DEFAULT NULL,
  `artist` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `flavor` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `color` json DEFAULT NULL,
  `color_identity` json DEFAULT NULL,
  `generatedmana` json DEFAULT NULL,
  `number` mediumint DEFAULT NULL,
  `number_import` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `layout` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rarity` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ability` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `keywords` json DEFAULT NULL,
  `backid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `maxpower` int DEFAULT NULL,
  `minpower` int DEFAULT NULL,
  `maxtoughness` int DEFAULT NULL,
  `mintoughness` int DEFAULT NULL,
  `maxloyalty` int DEFAULT NULL,
  `minloyalty` int DEFAULT NULL,
  `f1_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_manacost` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_ability` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `f1_colour` json DEFAULT NULL,
  `f1_artist` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_flavor` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_image_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_power` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_toughness` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_loyalty` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_cmc` decimal(9,1) DEFAULT NULL,
  `f1_printed_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_flavor_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_manacost` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_type` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_ability` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `f2_colour` json DEFAULT NULL,
  `f2_artist` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_flavor` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_image_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_power` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_toughness` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_loyalty` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_cmc` decimal(9,1) DEFAULT NULL,
  `f2_printed_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_flavor_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p1_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p2_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p3_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p4_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p5_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p6_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_component` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_type_line` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `p7_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reserved` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `foil` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nonfoil` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oversized` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `promo` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gatherer_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `api_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `scryfall_uri` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `legalityblock` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitystandard` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityextended` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitymodern` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitylegacy` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityvintage` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityhighlander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityfrenchcommander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitytinyleaderscommander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitymodernduelcommander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitycommander` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitypeasant` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitypauper` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalitypioneer` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityalchemy` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legalityhistoric` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updatetime` bigint NOT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `price_foil` decimal(8,2) DEFAULT NULL,
  `price_etched` decimal(8,2) DEFAULT NULL,
  `price_sort` decimal(8,2) DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  `is_paper` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"paper"')) VIRTUAL,
  `is_mtgo` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"mtgo"')) VIRTUAL,
  `is_arena` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"arena"')) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `oracle_id` (`oracle_id`),
  KEY `release_date` (`release_date`),
  KEY `setcode` (`setcode`),
  KEY `primary_card` (`setcode`,`primary_card`) USING BTREE,
  KEY `set_name` (`set_name`),
  KEY `lang` (`lang`),
  KEY `type_2` (`type`),
  KEY `number` (`number`),
  KEY `primary_card_2` (`primary_card`),
  KEY `number_import` (`number_import`),
  KEY `idx_is_paper` (`is_paper`),
  KEY `idx_is_mtgo` (`is_mtgo`),
  KEY `idx_is_arena` (`is_arena`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collectionTemplate`
--

DROP TABLE IF EXISTS `collectionTemplate`;
CREATE TABLE IF NOT EXISTS `collectionTemplate` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `normal` tinyint DEFAULT NULL,
  `foil` tinyint DEFAULT NULL,
  `etched` tinyint DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `topvalue` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deckcards`
--

DROP TABLE IF EXISTS `deckcards`;
CREATE TABLE IF NOT EXISTS `deckcards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `decknumber` smallint NOT NULL,
  `cardnumber` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cardqty` tinyint DEFAULT NULL,
  `sideqty` tinyint DEFAULT NULL,
  `commander` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `DeckCardCombo` (`decknumber`,`cardnumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `performed_at` date NOT NULL,
  `object` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `migration_strategy` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `old_scryfall_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `new_scryfall_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `note` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_lang` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_set_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_oracle_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata_collector_number` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `db_match` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `decks`
--

DROP TABLE IF EXISTS `decks`;
CREATE TABLE IF NOT EXISTS `decks` (
  `decknumber` int NOT NULL AUTO_INCREMENT,
  `owner` smallint NOT NULL,
  `deckname` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `sidenotes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`decknumber`),
  UNIQUE KEY `decknumber` (`decknumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `decktypes`
--

DROP TABLE IF EXISTS `decktypes`;
CREATE TABLE IF NOT EXISTS `decktypes` (
  `typenumber` smallint NOT NULL AUTO_INCREMENT,
  `type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cardcount` int NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`typenumber`),
  UNIQUE KEY `typenumber` (`typenumber`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
CREATE TABLE IF NOT EXISTS `groups` (
  `groupnumber` int NOT NULL AUTO_INCREMENT,
  `groupname` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner` int NOT NULL,
  PRIMARY KEY (`groupnumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rulings_scry`
--

DROP TABLE IF EXISTS `rulings_scry`;
CREATE TABLE IF NOT EXISTS `rulings_scry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `oracle_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `published_at` date NOT NULL,
  `comment` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `oracle_id` (`oracle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scryfalljson`
--

DROP TABLE IF EXISTS `scryfalljson`;
CREATE TABLE IF NOT EXISTS `scryfalljson` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jsonupdatetime` int NOT NULL,
  `tcg_buy_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sets`
--

DROP TABLE IF EXISTS `sets`;
CREATE TABLE IF NOT EXISTS `sets` (
  `id` varchar(36) COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `api_uri` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `scryfall_uri` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `search_uri` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `set_type` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `card_count` int DEFAULT NULL,
  `parent_set_code` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nonfoil_only` tinyint(1) DEFAULT NULL,
  `foil_only` tinyint(1) DEFAULT NULL,
  `icon_svg_uri` varchar(256) COLLATE utf8mb4_general_ci NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `updatenotices`
--

DROP TABLE IF EXISTS `updatenotices`;
CREATE TABLE IF NOT EXISTS `updatenotices` (
  `number` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `update` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `author` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`number`),
  UNIQUE KEY `number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `usernumber` smallint NOT NULL AUTO_INCREMENT,
  `username` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reg_date` date NOT NULL,
  `status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `badlogins` smallint DEFAULT NULL,
  `groupid` int NOT NULL DEFAULT '0',
  `grpinout` tinyint(1) NOT NULL DEFAULT '0',
  `lastlogin_date` date DEFAULT NULL,
  `collection_view` tinyint NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `weeklyexport` tinyint NOT NULL DEFAULT '0',
  `tfa_enabled` tinyint NOT NULL DEFAULT '0',
  `tfa_method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tfa_backup_codes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tfa_app_secret` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`usernumber`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `usernumber` (`usernumber`),
  KEY `username_2` (`username`),
  KEY `email_2` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cards_scry`
--
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
-- Indexes for table `sets`
--
ALTER TABLE `sets` ADD FULLTEXT KEY `code` (`code`);

-- --------------------------------------------------------

--
-- Table structure for table `trusted_devices`
--

DROP TABLE IF EXISTS `trusted_devices`;
CREATE TABLE IF NOT EXISTS `trusted_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_used` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token_hash` (`token_hash`),
  KEY `expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tfa_codes`
--

DROP TABLE IF EXISTS `tfa_codes`;
CREATE TABLE IF NOT EXISTS `tfa_codes` (
  `usernumber` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `expiry` int NOT NULL,
  `attempts` int NOT NULL DEFAULT 0,
  UNIQUE KEY(user_id),
  KEY `idx_tfa_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Set up initial admin row
INSERT INTO `admin` (`key`, `usemin`, `mtce`) VALUES
(1, 0, 0);

-- Initial group (for future use)
INSERT INTO `groups` (`groupnumber`, `groupname`, `owner`) VALUES
(1, 'Masters', 1);
