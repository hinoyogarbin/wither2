-- ============================================================
-- Wither Micro-Climate Monitoring System - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS wither_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wither_db;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(60)  NOT NULL UNIQUE,
    email       VARCHAR(120) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,           -- bcrypt hash
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed default admin (password: admin123)
INSERT INTO users (username, email, password, role) VALUES
  ('admin', 'admin@wither.local',
   '$2y$12$PLACEHOLDER_HASH_REPLACE_WITH_REAL',   -- replace via setup script
   'admin');

-- ============================================================
-- MARKERS (sensor locations) TABLE
-- ============================================================
CREATE TABLE markers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    latitude    DECIMAL(10,8) NOT NULL,
    longitude   DECIMAL(11,8) NOT NULL,
    description TEXT,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT UNSIGNED,                     -- FK → users.id
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_markers_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Seed from pin.json
INSERT INTO markers (name, latitude, longitude, created_by) VALUES
  ('NBSC Canteen',       8.35940476, 124.86847807, 1),
  ('NBSC Spot 3',        8.36079200, 124.86919500, 1),
  ('NBSC Spot 2',        8.35952068, 124.86766785, 1),
  ('NBSC Spot 1',        8.35940869, 124.86880759, 1),
  ('NBSC Covered Court', 8.35996846, 124.86889845, 1),
  ('Lab 1',              8.35917491, 124.86905432, 1),
  ('SWDC',               8.36024500, 124.86747400, 1);

-- ============================================================
-- SENSOR READINGS TABLE
-- ============================================================
CREATE TABLE sensor_readings (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    marker_id    INT UNSIGNED NOT NULL,
    temperature  DECIMAL(5,2) NOT NULL,           -- °C
    humidity     DECIMAL(5,2) NOT NULL,            -- %
    recorded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sr_marker   (marker_id),
    INDEX idx_sr_recorded (recorded_at),
    CONSTRAINT fk_sr_marker FOREIGN KEY (marker_id) REFERENCES markers(id) ON DELETE CASCADE
);

-- ============================================================
-- USER ACTIVITY LOGS TABLE
-- ============================================================
CREATE TABLE user_activity_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED,
    action     VARCHAR(80)  NOT NULL,              -- 'login','logout','view_dashboard', etc.
    detail     VARCHAR(255),
    ip_address VARCHAR(45),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ual_user    (user_id),
    INDEX idx_ual_created (created_at),
    CONSTRAINT fk_ual_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);