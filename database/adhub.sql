-- ============================================================
--  adhub.sql
--  Full schema + seed data for AdHub V2
--  Matches: users, campaigns, milestones, assets,
--           asset_versions, approvals, time_logs,
--           retainers, notifications, campaign_notes,
--           user_preferences
--  To run: mysql -u root -p < adhub.sql
-- ============================================================
 
DROP DATABASE IF EXISTS adhub;
CREATE DATABASE adhub;
USE adhub;
 
-- ============================================================
--  TABLES
-- ============================================================
 
-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    user_id          INT           AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100),
    email            VARCHAR(100)  UNIQUE,
    password         VARCHAR(255),
    role             ENUM('staff','client'),
    hourly_rate      DECIMAL(10,2),
    profile_img_data LONGBLOB      NULL,
    profile_img_type VARCHAR(50)   NULL
);
 
-- ============================================================
-- CAMPAIGNS
-- ============================================================
CREATE TABLE campaigns (
    campaign_id       INT          AUTO_INCREMENT PRIMARY KEY,
    campaign_name     VARCHAR(100),
    description       TEXT,
    budget            DECIMAL(10,2),
    client_id         INT,
    assigned_staff_id INT,
    start_date        DATE,
    deadline          DATE,
    status            VARCHAR(50),
    FOREIGN KEY (client_id)         REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_staff_id) REFERENCES users(user_id) ON DELETE SET NULL
);
 
-- ============================================================
-- MILESTONES
-- ============================================================
CREATE TABLE milestones (
    milestone_id INT          AUTO_INCREMENT PRIMARY KEY,
    campaign_id  INT,
    title        VARCHAR(100),
    status       ENUM('pending','approved','revision') DEFAULT 'pending',
    deadline     DATE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE
);
 
-- ============================================================
-- ASSETS
-- ============================================================
CREATE TABLE assets (
    asset_id     INT          AUTO_INCREMENT PRIMARY KEY,
    milestone_id INT,
    file_path    VARCHAR(255),
    file_data    LONGBLOB     NULL,
    file_type    VARCHAR(50)  NULL,
    uploaded_by  INT,
    uploaded_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (milestone_id) REFERENCES milestones(milestone_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by)  REFERENCES users(user_id)           ON DELETE SET NULL
);
 
-- ============================================================
-- ASSET VERSIONS
-- ============================================================
CREATE TABLE asset_versions (
    version_id     INT          AUTO_INCREMENT PRIMARY KEY,
    asset_id       INT,
    version_number INT,
    file_path      VARCHAR(255),
    uploaded_by    INT,
    uploaded_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id)    REFERENCES assets(asset_id)  ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)    ON DELETE SET NULL
);
 
-- ============================================================
-- APPROVALS
-- ============================================================
CREATE TABLE approvals (
    approval_id  INT          AUTO_INCREMENT PRIMARY KEY,
    milestone_id INT,
    client_id    INT,
    status       ENUM('approved','revision'),
    feedback     TEXT,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    notified     TINYINT(1)   DEFAULT 0,
    FOREIGN KEY (milestone_id) REFERENCES milestones(milestone_id) ON DELETE CASCADE,
    FOREIGN KEY (client_id)    REFERENCES users(user_id)           ON DELETE CASCADE
);
 
-- ============================================================
-- TIME LOGS
-- ============================================================
CREATE TABLE time_logs (
    log_id      INT           AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT,
    staff_id    INT,
    log_date    DATE,
    hours       DECIMAL(5,2),
    hourly_rate DECIMAL(10,2),
    cost        DECIMAL(10,2),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id)    REFERENCES users(user_id)         ON DELETE CASCADE
);
 
-- ============================================================
-- RETAINERS
-- ============================================================
CREATE TABLE retainers (
    retainer_id      INT           AUTO_INCREMENT PRIMARY KEY,
    client_id        INT,
    total_amount     DECIMAL(10,2),
    used_amount      DECIMAL(10,2) DEFAULT 0,
    remaining_amount DECIMAL(10,2),
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE
);
 
-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    notification_id INT          AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NULL,
    title           VARCHAR(255),
    message         TEXT,
    is_read         TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    deleted_at      DATETIME     NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
 
-- ============================================================
-- CAMPAIGN NOTES
-- ============================================================
CREATE TABLE campaign_notes (
    note_id     INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT          NOT NULL,
    staff_id    INT          NOT NULL,
    body        TEXT         NOT NULL,
    is_pinned   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NULL     ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id)    REFERENCES users(user_id)         ON DELETE CASCADE
);
 
-- ============================================================
-- USER PREFERENCES
-- (used by topbar toggle — e.g. email_notif)
-- ============================================================
CREATE TABLE user_preferences (
    user_id    INT          NOT NULL,
    pref_key   VARCHAR(50)  NOT NULL,
    pref_value TINYINT(1)   DEFAULT 1,
    PRIMARY KEY (user_id, pref_key),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);