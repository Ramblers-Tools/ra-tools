-- 4.2.0: tables for signing in to the Ramblers Tools app
CREATE TABLE IF NOT EXISTS `#__ra_app_codes` (
  `code_hash` CHAR(64) NOT NULL,
  `user_id` INT NOT NULL,
  `code_challenge` VARCHAR(128) NOT NULL,
  `redirect_uri` VARCHAR(255) NOT NULL,
  `client_id` VARCHAR(64) NOT NULL,
  `created` DATETIME NOT NULL,
  `expires` DATETIME NOT NULL,
  PRIMARY KEY (`code_hash`),
  KEY `idx_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__ra_app_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_hash` CHAR(64) NOT NULL,
  `user_id` INT NOT NULL,
  `client_id` VARCHAR(64) NOT NULL,
  `user_agent` VARCHAR(255) NULL,
  `created` DATETIME NOT NULL,
  `last_used` DATETIME NULL,
  `revoked` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__ra_app_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_hash` CHAR(64) NOT NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_created` (`ip_hash`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
