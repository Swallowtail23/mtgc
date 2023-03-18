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
  `tier` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mtce` tinyint(1) NOT NULL,
  PRIMARY KEY (`key`),
  UNIQUE KEY `key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cards_scry`
--

DROP TABLE IF EXISTS `cards_scry`;
CREATE TABLE IF NOT EXISTS `cards_scry` (
  `id` varchar(36) COLLATE utf8mb4_general_ci NOT NULL,
  `oracle_id` varchar(36) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tcgplayer_id` mediumint DEFAULT NULL,
  `multiverse` mediumint DEFAULT NULL,
  `multiverse2` mediumint DEFAULT NULL,
  `name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `printed_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `flavor_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lang` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `set_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `setcode` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `set_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `game_types` json DEFAULT NULL,
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
  `number_import` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
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
  `f1_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_manacost` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_type` varchar(128) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_ability` mediumtext COLLATE utf8mb4_general_ci,
  `f1_colour` json DEFAULT NULL,
  `f1_artist` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_flavor` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_image_uri` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_power` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_toughness` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_loyalty` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_cmc` decimal(9,1) DEFAULT NULL,
  `f1_printed_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f1_flavor_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_manacost` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_type` varchar(128) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_ability` mediumtext COLLATE utf8mb4_general_ci,
  `f2_colour` json DEFAULT NULL,
  `f2_artist` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_flavor` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_image_uri` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_power` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_toughness` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_loyalty` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_cmc` decimal(9,1) DEFAULT NULL,
  `f2_printed_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `f2_flavor_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
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
  `scryfall_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
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
  `price_sort` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `oracle_id` (`oracle_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collectionTemplate`
--

DROP TABLE IF EXISTS `collectionTemplate`;
CREATE TABLE IF NOT EXISTS `collectionTemplate` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `normal` tinyint DEFAULT NULL,
  `foil` tinyint DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `topvalue` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `id_2` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `salt` char(21) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reg_date` date NOT NULL,
  `status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `admin` tinyint(1) DEFAULT NULL,
  `badlogins` smallint DEFAULT NULL,
  `groupid` int NOT NULL,
  `grpinout` tinyint(1) NOT NULL,
  `lastlogin_date` date DEFAULT NULL,
  PRIMARY KEY (`usernumber`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `usernumber` (`usernumber`),
  KEY `username_2` (`username`),
  KEY `email_2` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Indexes for table `sets`
--
ALTER TABLE `sets` ADD FULLTEXT KEY `code` (`code`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
