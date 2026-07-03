-- =============================================
-- FastSaved Bot - Kerakli jadvallar sxemasi
-- Bu faylni bazangizga BIR MARTA import qiling
-- (phpMyAdmin -> Import, yoki: mysql -u user -p dbname < schema.sql)
-- =============================================

CREATE TABLE IF NOT EXISTS `users` (
  `users_id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(50) NOT NULL,
  `holat` VARCHAR(10) DEFAULT '✅',
  `vaqt` VARCHAR(50) DEFAULT NULL,
  `step` VARCHAR(100) DEFAULT 'none'
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(50) NOT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `kanallar` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(20) DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `channelID` VARCHAR(50) DEFAULT NULL,
  `requestchannel` VARCHAR(10) DEFAULT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `movieChannel` VARCHAR(255) DEFAULT NULL,
  `sub_price` INT(20) DEFAULT 0,
  `status` VARCHAR(10) DEFAULT '✅'
) DEFAULT CHARSET=utf8mb4;

-- Bot to'g'ri ishlashi uchun settings jadvalida id=1 qatori bo'lishi shart
INSERT INTO `settings` (`id`, `status`)
SELECT 1, '✅' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `id` = 1);

CREATE TABLE IF NOT EXISTS `send` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `time1` VARCHAR(10) DEFAULT NULL,
  `time2` VARCHAR(10) DEFAULT NULL,
  `start_id` INT(20) DEFAULT 0,
  `stop_id` VARCHAR(50) DEFAULT NULL,
  `admin_id` VARCHAR(50) DEFAULT NULL,
  `message_id` VARCHAR(50) DEFAULT NULL,
  `reply_markup` TEXT DEFAULT NULL,
  `step` VARCHAR(50) DEFAULT NULL,
  `edit_mess_id` VARCHAR(50) DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT NULL,
  `statistics` INT(20) DEFAULT 0,
  `sends_count` INT(20) DEFAULT 0,
  `receive_count` INT(20) DEFAULT 0
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `groups` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `group_id` VARCHAR(250)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `requests` (
  `id` VARCHAR(50) DEFAULT NULL,
  `chat_id` VARCHAR(50) DEFAULT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `left_users` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(50) NOT NULL,
  `date` VARCHAR(50) DEFAULT NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `music` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100),
  `file_id` VARCHAR(100),
  `artist` VARCHAR(100),
  `music_id` VARCHAR(100)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `top_songs` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) DEFAULT NULL,
  `artist` VARCHAR(255) DEFAULT NULL,
  `music_url` VARCHAR(500) DEFAULT NULL,
  `downloads` INT(20) DEFAULT 0
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `video_downloads` (
  `id` INT(20) AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(50) DEFAULT NULL,
  `platform` VARCHAR(50) DEFAULT NULL,
  `downloaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;
