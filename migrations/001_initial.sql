-- Baseline migration — installer schema.sql run કરે છે અને આ ફાઇલ ને
-- migrations ટેબલમાં 'already run' તરીકે નોંધે છે.
-- આ ફાઇલ idempotent છે (IF NOT EXISTS) — ફરી ચાલે તો પણ નુકસાન નહીં.
CREATE TABLE IF NOT EXISTS `{{prefix}}migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_file` VARCHAR(190) NOT NULL,
  `batch` INT UNSIGNED NOT NULL DEFAULT 1,
  `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migration_file` (`migration_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
