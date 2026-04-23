-- ============================================================
-- Stakeholder Map v1.2 – Database Schema
-- MariaDB 11.8 / MySQL 8.0
-- ============================================================

-- Hinweis: Datenbank existiert bereits bei Strato (dbs15576584)
-- Einfach in phpMyAdmin importieren – die Tabellen werden angelegt.

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(255) NOT NULL UNIQUE,
  name        VARCHAR(255) DEFAULT NULL,
  wohnort     VARCHAR(255) DEFAULT NULL,
  arbeitsort  VARCHAR(255) DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login  TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- ── Magic Links ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS magic_links (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token       VARCHAR(64) NOT NULL UNIQUE,
  expires_at  TIMESTAMP NOT NULL,
  used        TINYINT(1) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token),
  INDEX idx_user_expires (user_id, expires_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Sessions ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token       VARCHAR(64) NOT NULL UNIQUE,
  expires_at  TIMESTAMP NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_session_token (token),
  INDEX idx_session_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Stakeholders ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS stakeholders (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED NOT NULL,
  uuid                VARCHAR(36) NOT NULL,
  name                VARCHAR(255) NOT NULL,
  firma               VARCHAR(255) DEFAULT NULL,
  rolle               VARCHAR(255) DEFAULT NULL,
  email               VARCHAR(255) DEFAULT NULL,
  telefon             VARCHAR(100) DEFAULT NULL,
  linkedin            VARCHAR(500) DEFAULT NULL,
  tags                JSON DEFAULT NULL,
  beziehungsstaerke   TINYINT UNSIGNED DEFAULT 3,
  prioritaet          ENUM('hoch','mittel','niedrig') DEFAULT 'mittel',
  wohnort             VARCHAR(255) DEFAULT NULL,
  wohnort_lat         DECIMAL(10,7) DEFAULT NULL,
  wohnort_lng         DECIMAL(10,7) DEFAULT NULL,
  arbeitsort          VARCHAR(255) DEFAULT NULL,
  arbeitsort_lat      DECIMAL(10,7) DEFAULT NULL,
  arbeitsort_lng      DECIMAL(10,7) DEFAULT NULL,
  notizen             TEXT DEFAULT NULL,
  geburtstag          VARCHAR(10) DEFAULT NULL,
  letzter_kontakt     DATE DEFAULT NULL,
  naechster_schritt   TEXT DEFAULT NULL,
  verbindungen        JSON DEFAULT NULL,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_uuid (user_id, uuid),
  INDEX idx_user_id (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Calendar Entries ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS calendar_entries (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  uuid            VARCHAR(36) NOT NULL,
  title           VARCHAR(500) NOT NULL,
  description     TEXT DEFAULT NULL,
  entry_date      DATE NOT NULL,
  end_date        DATE DEFAULT NULL,
  entry_time      VARCHAR(5) DEFAULT NULL,
  entry_type      ENUM('treffen','notiz','event','erinnerung') DEFAULT 'notiz',
  ort             VARCHAR(255) DEFAULT NULL,
  kontakte        JSON DEFAULT NULL,
  tags            JSON DEFAULT NULL,
  erledigt        TINYINT(1) DEFAULT 0,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_cal_uuid (user_id, uuid),
  INDEX idx_cal_user_date (user_id, entry_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Activity Log ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  month_key   VARCHAR(7) NOT NULL,   -- "2026-04"
  added       INT UNSIGNED DEFAULT 0,
  gepflegt    INT UNSIGNED DEFAULT 0,
  INDEX idx_user_month (user_id, month_key),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- v1.2 Migration (auf bestehender v1.1 Datenbank ausfuehren)
-- ============================================================
-- ALTER TABLE stakeholders ADD COLUMN geburtstag VARCHAR(10) DEFAULT NULL AFTER notizen;
-- Dann calendar_entries Tabelle oben anlegen (CREATE TABLE IF NOT EXISTS ...).
