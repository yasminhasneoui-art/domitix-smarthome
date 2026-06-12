--  ESP32 SecurePanel — Base de données complète
--  ➡ Importer dans phpMyAdmin → onglet "Importer"

CREATE DATABASE IF NOT EXISTS DOMITIX
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE DOMITIX;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  role ENUM('admin','viewer') DEFAULT 'viewer',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  type ENUM('email','sms') DEFAULT 'email',
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS access_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(100) NOT NULL,
  code_used VARCHAR(20) DEFAULT NULL,
  status ENUM('success','fail','lock') NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS sensor_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  temperature DECIMAL(5,2) DEFAULT NULL,
  humidity DECIMAL(5,2) DEFAULT NULL,
  voltage DECIMAL(5,3) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS system_state (
  id INT AUTO_INCREMENT PRIMARY KEY,
  is_locked TINYINT(1) DEFAULT 0,
  lock_until DATETIME DEFAULT NULL,
  door_open TINYINT(1) DEFAULT 0,
  failed_attempts INT DEFAULT 0,
  alarm_active TINYINT(1) DEFAULT 0,
  led_blue_level ENUM('off','low','medium','high') DEFAULT 'off',
  led_green TINYINT(1) DEFAULT 0,
  led_red TINYINT(1) DEFAULT 0,
  door_code VARCHAR(10) DEFAULT '1234',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO system_state (id,is_locked,door_open,failed_attempts,alarm_active,led_blue_level,door_code)
VALUES (1,0,0,0,0,'off','1234');

-- Login: admin / admin123 (mot de passe en clair)
INSERT IGNORE INTO users (username,password,role) VALUES ('admin','admin123','admin');
