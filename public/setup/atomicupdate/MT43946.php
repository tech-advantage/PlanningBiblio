<?php
// MT43946: Add unsubscribe link to planno notifications
//TODO: dbstructure and or dbupdate
$sql[]="CREATE TABLE IF NOT EXISTS `{$dbprefix}user_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perso_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

