-- =========================================================
-- STUDY TRACKER DATABASE (MySQL / MariaDB)
-- 8 core entities, stats-first design
-- Each account owns its study data.
-- Run in phpMyAdmin SQL tab or: mysql -u root -p study_tracker < this_file.sql
-- =========================================================

-- Select your database in phpMyAdmin before importing this file.
-- Shared hosting accounts usually cannot create databases or change databases
-- from an imported SQL file.

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 1. SUBJECTS
-- What you're studying. Needed so stats can be sliced per subject.
-- =========================================================
CREATE TABLE IF NOT EXISTS subjects (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  name          VARCHAR(100) NOT NULL,
  color_hex     VARCHAR(7) DEFAULT '#E8A33D',
  is_archived   TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_subjects_user (user_id)
) ENGINE=InnoDB;

-- =========================================================
-- 2. MATERIALS
-- What you're learning within a subject (a chapter, a topic, a resource).
-- =========================================================
CREATE TABLE IF NOT EXISTS materials (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  subject_id    INT NOT NULL,
  title         VARCHAR(200) NOT NULL,
  status        ENUM('not_started','in_progress','learned','mastered') NOT NULL DEFAULT 'not_started',
  difficulty    TINYINT DEFAULT 3,                 -- 1 (easy) to 5 (hard)
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_materials_subject (subject_id),
  INDEX idx_materials_status (status)
) ENGINE=InnoDB;

-- =========================================================
-- 3. CYCLES  (the heart of the app)
-- 1 cycle = 90 min study + 30 min revision/brainstorm.
-- Every stats table below is derived from this one.
-- =========================================================
CREATE TABLE IF NOT EXISTS cycles (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  user_id                 INT NOT NULL,
  subject_id              INT NOT NULL,
  material_id             INT NULL,
  cycle_date              DATE NOT NULL,           -- the day this cycle counts toward
  study_start             DATETIME NULL,
  study_end               DATETIME NULL,
  study_minutes           INT NOT NULL DEFAULT 0,  -- actual, not planned
  revision_start          DATETIME NULL,
  revision_end            DATETIME NULL,
  revision_minutes        INT NOT NULL DEFAULT 0,
  planned_study_minutes   INT NOT NULL DEFAULT 90,
  planned_revision_minutes INT NOT NULL DEFAULT 30,
  status                  ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cycles_date (cycle_date),
  INDEX idx_cycles_subject (subject_id),
  INDEX idx_cycles_status (status)
) ENGINE=InnoDB;

-- =========================================================
-- 4. GOALS  (daily/weekly targets — lets stats show progress vs. target)
-- =========================================================
CREATE TABLE IF NOT EXISTS goals (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  goal_type         ENUM('daily','weekly') NOT NULL,
  target_cycles     INT NOT NULL DEFAULT 4,
  target_minutes    INT NULL,
  start_date        DATE NOT NULL,
  end_date          DATE NULL,
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_goals_user (user_id)
) ENGINE=InnoDB;

-- =========================================================
-- 5. APP_SETTINGS  (singleton — theme, defaults, timezone)
-- =========================================================
CREATE TABLE IF NOT EXISTS app_settings (
  user_id                   INT PRIMARY KEY,
  theme                     ENUM('dark','light') NOT NULL DEFAULT 'light',
  default_study_minutes     INT NOT NULL DEFAULT 90,
  default_revision_minutes  INT NOT NULL DEFAULT 30,
  timezone                  VARCHAR(50) NOT NULL DEFAULT 'Asia/Dhaka',
  updated_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Statistics are calculated directly from cycles by the PHP application.
-- Triggers, cache tables, and views are intentionally omitted because shared
-- hosting accounts commonly do not have TRIGGER or CREATE VIEW privileges.
