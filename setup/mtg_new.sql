SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS `mtg_new` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mtg_new`;

CREATE TABLE `admin` (
  `key` int NOT NULL,
  `usemin` tinyint(1) NOT NULL,
  `mtce` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `is_arena` tinyint(1) GENERATED ALWAYS AS (json_contains(`game_types`,_utf8mb4'"arena"')) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `collectionTemplate` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `normal` tinyint DEFAULT NULL,
  `foil` tinyint DEFAULT NULL,
  `etched` tinyint DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `topvalue` decimal(8,2) DEFAULT NULL,
  `qty_total` smallint UNSIGNED GENERATED ALWAYS AS (((ifnull(`normal`,0) + ifnull(`foil`,0)) + ifnull(`etched`,0))) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `deckcards` (
  `id` int NOT NULL,
  `decknumber` smallint NOT NULL,
  `cardnumber` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cardqty` tinyint DEFAULT NULL,
  `sideqty` tinyint DEFAULT NULL,
  `commander` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `decks` (
  `decknumber` int NOT NULL,
  `owner` smallint NOT NULL,
  `deckname` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `sidenotes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `decktypes` (
  `typenumber` smallint NOT NULL,
  `type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `cardcount` int NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `fx` (
  `currencies` varchar(12) COLLATE utf8mb4_general_ci NOT NULL,
  `updatetime` int NOT NULL,
  `rate` decimal(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `groups` (
  `groupnumber` int NOT NULL,
  `groupname` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `migrations` (
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
  `db_match` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rulings_scry` (
  `id` int NOT NULL,
  `oracle_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `published_at` date NOT NULL,
  `comment` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `scryfalljson` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jsonupdatetime` int NOT NULL,
  `tcg_buy_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sets` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `api_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `scryfall_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `search_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `set_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `card_count` int DEFAULT NULL,
  `parent_set_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nonfoil_only` tinyint(1) DEFAULT NULL,
  `foil_only` tinyint(1) DEFAULT NULL,
  `icon_svg_uri` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tfa_codes` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expiry` int NOT NULL,
  `attempts` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `trusted_devices` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `device_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_used` datetime DEFAULT NULL,
  `created` datetime NOT NULL,
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `updatenotices` (
  `number` int NOT NULL,
  `date` date NOT NULL,
  `update` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `author` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `usernumber` smallint NOT NULL,
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
  `tfa_backup_codes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tfa_app_secret` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `collection_values` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `usernumber` smallint NOT NULL,
  `collected_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `value_usd` decimal(15,2) NOT NULL DEFAULT 0,
  `value_local` decimal(15,2) DEFAULT NULL,
  `local_currency` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rate_used` decimal(12,6) DEFAULT NULL,
  `card_count` int UNSIGNED NOT NULL DEFAULT 0,
  `mr_count` int UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_collected` (`usernumber`,`collected_at`),
  CONSTRAINT `fk_collection_values_user`
    FOREIGN KEY (`usernumber`) REFERENCES `users` (`usernumber`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `admin`
  ADD PRIMARY KEY (`key`),
  ADD UNIQUE KEY `key` (`key`);

ALTER TABLE `cards_scry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `oracle_id` (`oracle_id`),
  ADD KEY `setcode` (`setcode`),
  ADD KEY `primary_card` (`setcode`,`primary_card`) USING BTREE,
  ADD KEY `lang` (`lang`),
  ADD KEY `type_2` (`type`),
  ADD KEY `set_name` (`set_name`),
  ADD KEY `release_date` (`release_date`),
  ADD KEY `number` (`number`),
  ADD KEY `primary_card_2` (`primary_card`),
  ADD KEY `number_import` (`number_import`),
  ADD KEY `idx_price_fields` (`id`,`price`,`price_foil`,`price_etched`,`foil`),
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

ALTER TABLE `collectionTemplate`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qty_total` (`qty_total`),
  ADD KEY `idx_topvalue` (`topvalue`);

ALTER TABLE `deckcards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `DeckCardCombo` (`decknumber`,`cardnumber`);

ALTER TABLE `decks`
  ADD PRIMARY KEY (`decknumber`),
  ADD UNIQUE KEY `decknumber` (`decknumber`);

ALTER TABLE `decktypes`
  ADD PRIMARY KEY (`typenumber`),
  ADD UNIQUE KEY `typenumber` (`typenumber`),
  ADD KEY `name` (`name`);

ALTER TABLE `fx`
  ADD UNIQUE KEY `currencies` (`currencies`);

ALTER TABLE `groups`
  ADD PRIMARY KEY (`groupnumber`);

ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`email`);

ALTER TABLE `rulings_scry`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oracle_id` (`oracle_id`);

ALTER TABLE `scryfalljson`
  ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `sets`
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `code_2` (`code`) USING BTREE,
  ADD UNIQUE KEY `name_3` (`name`,`release_date`,`parent_set_code`) USING BTREE,
  ADD KEY `release_date` (`release_date`),
  ADD KEY `name` (`name`);
ALTER TABLE `sets` ADD FULLTEXT KEY `code` (`code`);
ALTER TABLE `sets` ADD FULLTEXT KEY `name_2` (`name`);

ALTER TABLE `tfa_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_tfa_user_id` (`user_id`);

ALTER TABLE `trusted_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token_hash` (`token_hash`),
  ADD KEY `expires` (`expires`);

ALTER TABLE `updatenotices`
  ADD PRIMARY KEY (`number`),
  ADD UNIQUE KEY `number` (`number`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`usernumber`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `usernumber` (`usernumber`),
  ADD KEY `username_2` (`username`),
  ADD KEY `email_2` (`email`);


ALTER TABLE `admin`
  MODIFY `key` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `deckcards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `decks`
  MODIFY `decknumber` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `decktypes`
  MODIFY `typenumber` smallint NOT NULL AUTO_INCREMENT;

ALTER TABLE `groups`
  MODIFY `groupnumber` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `rulings_scry`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `tfa_codes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `trusted_devices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `updatenotices`
  MODIFY `number` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `usernumber` smallint NOT NULL AUTO_INCREMENT;

COMMIT;
