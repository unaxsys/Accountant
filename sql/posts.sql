CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(191) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `meta_description` VARCHAR(160) NOT NULL DEFAULT '',
  `excerpt` TEXT NOT NULL,
  `content_html` LONGTEXT NOT NULL,
  `cover_image` VARCHAR(255) NULL,
  `tags` VARCHAR(255) NULL,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_posts_slug` (`slug`),
  KEY `idx_posts_status_published_at` (`status`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
