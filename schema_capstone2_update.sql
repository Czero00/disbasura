-- ============================================================
--  DisBasura — Full Database Schema (Capstone 2)
--  Run this ONE file in phpMyAdmin > Import. That's it.
--
--  This is the complete, final schema — every table, column and
--  relationship your Capstone 1 manuscript's ERD / Data Dictionary
--  (Chapter 3) documents, built directly rather than patched in
--  through migration steps.
-- ============================================================

CREATE DATABASE IF NOT EXISTS disbasura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE disbasura;

-- ────────────────────────────────────────────────────────────
--  GEOGRAPHIC HIERARCHY  (Tables 6–9)
--  Cities → Barangays → Sitios → Sites
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS cities (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS barangays (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    city_id    INT NOT NULL,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_barangay (city_id, name),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sitios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL UNIQUE,
    barangay_id INT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sites (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sitio_id   INT NOT NULL,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sitio_id) REFERENCES sitios(id) ON DELETE CASCADE
);

INSERT IGNORE INTO cities (id, name) VALUES (1, 'Cebu City');
INSERT IGNORE INTO barangays (id, city_id, name) VALUES (1, 1, 'Unassigned');

-- ────────────────────────────────────────────────────────────
--  ADMINISTRATOR  (Table 20)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS administrators (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) UNIQUE,
    password      VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(500) DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ────────────────────────────────────────────────────────────
--  USERS  (Table 13 — residents & sitio leaders)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(255) NOT NULL,
    username    VARCHAR(100) NOT NULL UNIQUE,
    email       VARCHAR(255) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('resident','leader') NOT NULL DEFAULT 'resident',
    sitio       VARCHAR(255),
    sitio_id    INT NULL,
    phone       VARCHAR(50),
    sms_number  VARCHAR(20),
    profile_photo VARCHAR(500) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sitio_id) REFERENCES sitios(id) ON DELETE SET NULL
);

-- ────────────────────────────────────────────────────────────
--  COLLECTORS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS collectors (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    full_name        VARCHAR(255) NOT NULL,
    sitio            VARCHAR(255) NOT NULL,
    sitio_id         INT NULL,
    phone            VARCHAR(50),
    status           ENUM('available','sick','unavailable') NOT NULL DEFAULT 'available',
    username         VARCHAR(100) UNIQUE,
    password         VARCHAR(255),
    truck_lat        DECIMAL(10,7),
    truck_lng        DECIMAL(10,7),
    truck_updated_at DATETIME,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sitio_id) REFERENCES sitios(id) ON DELETE SET NULL
);

-- ────────────────────────────────────────────────────────────
--  SCHEDULES  (Table 8 — actual collection instances)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS schedules (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    sitio                 VARCHAR(255) NOT NULL,
    scheduled_at          DATETIME NOT NULL,
    waste_type            VARCHAR(100) NOT NULL,
    collector_id          INT,
    status                ENUM('scheduled','assigned','completed','cancelled','disputed','unverified') NOT NULL DEFAULT 'scheduled',
    proof_photo           VARCHAR(500),
    resident_proof_photo  VARCHAR(500),
    completed_at          DATETIME,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (collector_id) REFERENCES collectors(id) ON DELETE SET NULL
);

-- ────────────────────────────────────────────────────────────
--  WEEKLY SCHEDULE  (recurring template)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS weekly_schedule (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    day_name        VARCHAR(20) NOT NULL,
    sitio           VARCHAR(255) NOT NULL,
    status          ENUM('pending','received','missed','inactive') NOT NULL DEFAULT 'pending',
    collection_time TIME NOT NULL DEFAULT '07:00:00',
    waste_type      VARCHAR(100) NOT NULL DEFAULT 'Mixed',
    collector_id    INT,
    notes           TEXT,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_day_sitio (day_name, sitio),
    FOREIGN KEY (collector_id) REFERENCES collectors(id) ON DELETE SET NULL
);

-- ────────────────────────────────────────────────────────────
--  REQUESTS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS requests (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    resident_id          INT NOT NULL,
    submitted_by         INT,
    sitio                VARCHAR(255) NOT NULL,
    sitio_id             INT NULL,
    location             VARCHAR(500),
    waste_type           VARCHAR(100) NOT NULL,
    preferred_date       DATETIME NOT NULL,
    note                 TEXT,
    status               ENUM('pending','approved','rejected','completed','assigned') NOT NULL DEFAULT 'pending',
    collector_id         INT,
    ai_suggested_id      INT NULL COMMENT 'Which collector the AI Smart Dispatch recommended',
    proof_photo          VARCHAR(500),
    resident_proof_photo VARCHAR(500),
    completed_at         DATETIME,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (collector_id) REFERENCES collectors(id) ON DELETE SET NULL,
    FOREIGN KEY (ai_suggested_id) REFERENCES collectors(id) ON DELETE SET NULL,
    FOREIGN KEY (sitio_id) REFERENCES sitios(id) ON DELETE SET NULL
);

-- ────────────────────────────────────────────────────────────
--  DISPUTES  (Table 19)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS disputes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id  INT NOT NULL,
    user_id      INT NOT NULL,
    admin_id     INT NULL,
    proof_photo  VARCHAR(500),
    description  TEXT,
    status       ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending',
    resolution   TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES administrators(id) ON DELETE SET NULL
);

-- ────────────────────────────────────────────────────────────
--  NOTIFICATIONS  (Table 15)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(100) NOT NULL DEFAULT 'Notification',
    message    TEXT NOT NULL,
    status     ENUM('sent','pending') NOT NULL DEFAULT 'sent',
    is_read    TINYINT NOT NULL DEFAULT 0 COMMENT 'Drives the unread-badge UI',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ────────────────────────────────────────────────────────────
--  PASSWORD RESET CODES
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS reset_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    code       VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    used       TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ────────────────────────────────────────────────────────────
--  FEEDBACK  (Table 17)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS feedback (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id  INT NOT NULL,
    collector_id INT NULL,
    user_id      INT NOT NULL,
    rating       TINYINT NOT NULL DEFAULT 5 COMMENT '1-5 stars',
    comment      TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_feedback (schedule_id, user_id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (collector_id) REFERENCES collectors(id) ON DELETE SET NULL
);

-- ────────────────────────────────────────────────────────────
--  ANNOUNCEMENTS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS announcements (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    message    TEXT NOT NULL,
    sitio      VARCHAR(255) DEFAULT NULL COMMENT 'NULL = all sitios',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES administrators(id) ON DELETE CASCADE
);

-- ────────────────────────────────────────────────────────────
--  ACTIVITY LOG  (Table 21)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS activity_log (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    actor_id            INT NOT NULL,
    actor_type          VARCHAR(20) NOT NULL DEFAULT 'admin' COMMENT 'admin | collector | resident | leader',
    action_type         VARCHAR(255) NOT NULL,
    affected_table      VARCHAR(100) NULL,
    affected_record_id  INT NULL,
    ip_address          VARCHAR(45) NULL,
    details             TEXT,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ────────────────────────────────────────────────────────────
--  SMS LOG
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS sms_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    phone      VARCHAR(20) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ────────────────────────────────────────────────────────────
--  USER PREFERENCES
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id    INT PRIMARY KEY,
    dark_mode  TINYINT NOT NULL DEFAULT 0,
    language   ENUM('en','fil') NOT NULL DEFAULT 'en',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ────────────────────────────────────────────────────────────
--  PROOF PHOTOS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS proof_photos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    request_id  INT NOT NULL,
    uploaded_by INT NOT NULL,
    photo_path  VARCHAR(500) NOT NULL,
    photo_type  ENUM('before','after') NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ────────────────────────────────────────────────────────────
--  TRACKING HISTORY  (Table 14)
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS tracking_history (
    tracking_id  INT AUTO_INCREMENT PRIMARY KEY,
    collector_id INT NOT NULL,
    latitude     DECIMAL(10,8) NOT NULL,
    longitude    DECIMAL(11,8) NOT NULL,
    `timestamp`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (collector_id) REFERENCES collectors(id) ON DELETE CASCADE
);

-- ────────────────────────────────────────────────────────────
--  RATINGS
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS ratings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    rated_by   INT NOT NULL,
    stars      TINYINT NOT NULL,
    comment    VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_by) REFERENCES users(id) ON DELETE CASCADE
);
