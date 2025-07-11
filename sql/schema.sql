CREATE TABLE `anchor_profile` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `uid` BIGINT UNIQUE,
  `nick` VARCHAR(100),
  `avatar` TEXT,
  `level` INT,
  `is_quality` BOOLEAN,
  `promotion_rate` INT,
  `promotion_status` INT,
  `total_reward` DECIMAL(10,2),
  `last_active_time` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `anchor_daily_earning` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `uid` BIGINT,
  `nick` VARCHAR(100),
  `avatar` TEXT,
  `reward` DECIMAL(10,2),
  `is_quality` BOOLEAN,
  `stat_date` DATE,
  UNIQUE KEY `unique_uid_date` (`uid`, `stat_date`)
);
