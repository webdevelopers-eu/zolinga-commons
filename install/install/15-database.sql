CREATE TABLE `uploads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(39) NOT NULL,
  `mime` varchar(255) DEFAULT NULL,
  `stamp` int(10) unsigned NOT NULL,
  `name` varchar(1024) NOT NULL,
  `data` mediumblob NOT NULL,
  `hash` binary(20) NOT NULL,
  `size` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash_UNIQUE` (`hash`)
);
