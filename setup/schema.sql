-- ============================================================
--  説明会予約ツール — DBスキーマ
--  対応DB: MySQL 5.7+ / MariaDB 10.3+
--  文字セット: utf8mb4 / 照合順序: utf8mb4_unicode_ci
-- ============================================================

-- ── 管理者ログイン ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(100) NOT NULL,
    role          ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    line_user_id  VARCHAR(100) NULL UNIQUE,
    last_login_at DATETIME NULL,
    created_at    DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(100) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    attempted_at DATETIME     NOT NULL DEFAULT NOW(),
    INDEX idx_ala_user (username, attempted_at),
    INDEX idx_ala_ip   (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── システム設定（KVS） ───────────────────────────────────
CREATE TABLE IF NOT EXISTS system_config (
    config_key   VARCHAR(100) PRIMARY KEY,
    config_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 選択肢マスタ（学年・学部。管理画面から編集） ──────────
CREATE TABLE IF NOT EXISTS grades (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faculties (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 登録者（予約前のユーザー登録） ────────────────────────
CREATE TABLE IF NOT EXISTS registrants (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    line_user_id     VARCHAR(100) NOT NULL UNIQUE,
    display_name     VARCHAR(100) NOT NULL,
    grade_id         INT NULL,
    faculty_id       INT NULL,
    created_at       DATETIME DEFAULT NOW(),
    INDEX idx_reg_grade   (grade_id),
    INDEX idx_reg_faculty (faculty_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 予約枠・予約 ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS event_slots (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    event_date  DATE NOT NULL,
    start_time  VARCHAR(10) NULL,
    end_time    VARCHAR(10) NULL,
    location    VARCHAR(200) NULL,
    capacity    INT NOT NULL DEFAULT 0 COMMENT '0=無制限',
    description TEXT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME DEFAULT NOW(),
    INDEX idx_es_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    slot_id       INT NOT NULL,
    registrant_id INT NOT NULL,
    reserved_at   DATETIME DEFAULT NOW(),
    UNIQUE KEY uk_slot_registrant (slot_id, registrant_id),
    INDEX idx_res_slot       (slot_id),
    INDEX idx_res_registrant (registrant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── メッセージ送信ログ ────────────────────────────────────
CREATE TABLE IF NOT EXISTS message_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    line_user_id VARCHAR(100) NOT NULL,
    kind         ENUM('confirm','reminder') NOT NULL,
    slot_id      INT NULL,
    sent_at      DATETIME DEFAULT NOW(),
    success      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  初期マスターデータ投入
-- ============================================================

INSERT IGNORE INTO grades (name, sort_order) VALUES
    ('1年生', 1), ('2年生', 2), ('3年生', 3), ('4年生', 4),
    ('M1',    5), ('M2',    6), ('その他', 7);

INSERT IGNORE INTO system_config (config_key, config_value) VALUES
    ('circle_name',               ''),
    ('liff_id',                   ''),
    ('line_channel_id',           ''),
    ('line_channel_secret',       ''),
    ('line_channel_access_token', ''),
    ('line_login_channel_id',     ''),
    ('line_login_channel_secret', ''),
    ('sync_secret_token',         ''),
    ('auto_notify_reminder',      '1'),
    ('liff_token_verify',         '0');
