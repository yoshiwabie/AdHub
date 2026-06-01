-- ============================================================
--  adhub.sql
--  Full schema + seed data for AdHub V2
--  Matches: users, campaigns, milestones, assets,
--           asset_versions, approvals, time_logs,
--           retainers, notifications
--  To run: mysql -u root -p < adhub.sql
-- ============================================================

DROP DATABASE IF EXISTS adhub;
CREATE DATABASE adhub;
USE adhub;

-- ============================================================
--  TABLES
-- ============================================================

CREATE TABLE users (
    user_id      INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100),
    email        VARCHAR(100) UNIQUE,
    password     VARCHAR(255),               -- bcrypt hashed
    role         ENUM('staff','client'),
    is_admin     BOOLEAN DEFAULT FALSE,
    hourly_rate  DECIMAL(10,2),
    profile_img  VARCHAR(255)
);

CREATE TABLE campaigns (
    campaign_id       INT AUTO_INCREMENT PRIMARY KEY,
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

CREATE TABLE milestones (
    milestone_id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id  INT,
    title        VARCHAR(100),
    status       ENUM('pending','approved','revision') DEFAULT 'pending',
    deadline     DATE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE
);

CREATE TABLE assets (
    asset_id    INT AUTO_INCREMENT PRIMARY KEY,
    milestone_id INT,
    file_path   VARCHAR(255),
    file_data   LONGBLOB NULL,
    file_type   VARCHAR(50) NULL,
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (milestone_id) REFERENCES milestones(milestone_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by)  REFERENCES users(user_id)           ON DELETE SET NULL
);

CREATE TABLE asset_versions (
    version_id     INT AUTO_INCREMENT PRIMARY KEY,
    asset_id       INT,
    version_number INT,
    file_path      VARCHAR(255),
    uploaded_by    INT,
    uploaded_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id)    REFERENCES assets(asset_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)  ON DELETE SET NULL
);

CREATE TABLE approvals (
    approval_id  INT AUTO_INCREMENT PRIMARY KEY,
    milestone_id INT,
    client_id    INT,
    status       ENUM('approved','revision'),
    feedback     TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Notified     TINYINT(1) DEFAULT 0,
    FOREIGN KEY (milestone_id) REFERENCES milestones(milestone_id) ON DELETE CASCADE,
    FOREIGN KEY (client_id)    REFERENCES users(user_id)           ON DELETE CASCADE
);

CREATE TABLE time_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT,
    staff_id    INT,
    log_date    DATE,
    hours       DECIMAL(5,2),
    hourly_rate DECIMAL(10,2),
    cost        DECIMAL(10,2),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id)    REFERENCES users(user_id)         ON DELETE CASCADE
);

CREATE TABLE retainers (
    retainer_id      INT AUTO_INCREMENT PRIMARY KEY,
    client_id        INT,
    total_amount     DECIMAL(10,2),
    used_amount      DECIMAL(10,2) DEFAULT 0,
    remaining_amount DECIMAL(10,2),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL,
    title           VARCHAR(255),
    message         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ============================================================
--  SEED DATA
--  Passwords are bcrypt hashes of the plaintext shown below.
--  All test accounts use the same password: Password@123
--  Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================

-- ------------------------------------------------------------
--  USERS
--  user_id 1   → admin / staff
--  user_id 2-4 → staff
--  user_id 5-8 → clients
-- ------------------------------------------------------------
INSERT INTO users (name, email, password, role, is_admin, hourly_rate, profile_img) VALUES

-- Admin
('Admin User',      'admin@adhub.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',  TRUE,  NULL,  NULL),

-- Staff
('Juan dela Cruz',  'juan@adhub.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',  FALSE, 350.00, NULL),
('Maria Santos',    'maria@adhub.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',  FALSE, 400.00, NULL),
('Carlo Reyes',     'carlo@adhub.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',  FALSE, 375.00, NULL),

-- Clients
('Bright Co.',      'bright@client.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', FALSE, NULL,   NULL),
('Nexus Brand',     'nexus@client.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', FALSE, NULL,   NULL),
('Verde Foods',     'verde@client.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', FALSE, NULL,   NULL),
('Skyline Realty',  'skyline@client.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', FALSE, NULL,   NULL);

-- ------------------------------------------------------------
--  CAMPAIGNS
--  Covers all 4 kanban statuses:
--    planning  → no milestones / assets
--    active    → has milestones, not fully approved
--    review    → all milestones approved + has assets
--    completed → status = 'completed'
-- ------------------------------------------------------------
INSERT INTO campaigns (campaign_name, description, budget, client_id, assigned_staff_id, start_date, deadline, status) VALUES

-- Bright Co. campaigns
('Bright Summer Launch',    'Social media campaign for summer product line.',        85000.00,  5, 2, '2026-05-01', '2026-07-31', 'active'),
('Bright Brand Refresh',    'Full rebranding of digital assets and collaterals.',    120000.00, 5, 3, '2026-06-01', '2026-09-30', 'planning'),

-- Nexus Brand campaigns
('Nexus Q3 Push',           'Paid ads and content strategy for Q3.',                 95000.00,  6, 2, '2026-04-15', '2026-06-30', 'review'),
('Nexus Year-End Promo',    'Holiday season promotional campaign.',                  60000.00,  6, 4, '2026-11-01', '2026-12-31', 'planning'),

-- Verde Foods campaigns
('Verde Product Showcase',  'Video and photo production for new product line.',      75000.00,  7, 3, '2026-03-01', '2026-05-31', 'completed'),
('Verde Social Series',     'Monthly social content for Instagram and Facebook.',    40000.00,  7, 4, '2026-06-01', '2026-08-31', 'active'),

-- Skyline Realty campaigns
('Skyline Grand Opening',   'Launch event coverage and digital promotions.',         110000.00, 8, 2, '2026-05-15', '2026-07-15', 'active'),
('Skyline Q4 Listings',     'Digital ads for Q4 property listings.',                 55000.00,  8, 4, '2026-09-01', '2026-11-30', 'planning');

-- ------------------------------------------------------------
--  MILESTONES
-- ------------------------------------------------------------
INSERT INTO milestones (campaign_id, title, status, deadline) VALUES

-- Campaign 1 — Bright Summer Launch (active: milestones exist, not all approved)
(1, 'Creative Brief Sign-off',    'approved',  '2026-05-10'),
(1, 'Initial Concepts Delivery',  'approved',  '2026-05-25'),
(1, 'Final Artwork Submission',   'pending',   '2026-06-15'),
(1, 'Campaign Go-Live',           'pending',   '2026-07-01'),

-- Campaign 3 — Nexus Q3 Push (review: all milestones approved + has assets)
(3, 'Strategy Deck Presentation', 'approved',  '2026-04-22'),
(3, 'Ad Creatives Batch 1',       'approved',  '2026-05-05'),
(3, 'Ad Creatives Batch 2',       'approved',  '2026-05-20'),
(3, 'Final Review & Approval',    'approved',  '2026-06-10'),

-- Campaign 5 — Verde Product Showcase (completed)
(5, 'Pre-Production Planning',    'approved',  '2026-03-10'),
(5, 'Photo Shoot Day 1',          'approved',  '2026-03-20'),
(5, 'Photo Shoot Day 2',          'approved',  '2026-04-05'),
(5, 'Post-Production Delivery',   'approved',  '2026-05-15'),

-- Campaign 6 — Verde Social Series (active)
(6, 'Content Calendar',           'approved',  '2026-06-10'),
(6, 'June Content Batch',         'revision',  '2026-06-25'),
(6, 'July Content Batch',         'pending',   '2026-07-25'),

-- Campaign 7 — Skyline Grand Opening (active)
(7, 'Event Brief & Moodboard',    'approved',  '2026-05-22'),
(7, 'Promotional Video Draft',    'pending',   '2026-06-15'),
(7, 'Digital Ad Set',             'pending',   '2026-07-01');

-- ------------------------------------------------------------
--  ASSETS
--  Attached to milestones so kanban status logic works.
-- ------------------------------------------------------------
INSERT INTO assets (milestone_id, file_path, file_type, uploaded_by, uploaded_at) VALUES

-- Campaign 1 milestones
(1, '/uploads/bright/brief_signoff_v1.pdf',       'application/pdf', 2, '2026-05-10 10:00:00'),
(2, '/uploads/bright/concepts_v1.jpg',            'image/jpeg',       2, '2026-05-25 14:30:00'),
(2, '/uploads/bright/concepts_v2.jpg',            'image/jpeg',       2, '2026-05-26 09:00:00'),

-- Campaign 3 milestones (all approved — triggers 'review' status)
(5, '/uploads/nexus/strategy_deck.pdf',           'application/pdf', 2, '2026-04-22 11:00:00'),
(6, '/uploads/nexus/ad_batch1_v1.jpg',            'image/jpeg',       2, '2026-05-05 15:00:00'),
(7, '/uploads/nexus/ad_batch2_v1.jpg',            'image/jpeg',       2, '2026-05-20 16:00:00'),
(8, '/uploads/nexus/final_review.pdf',            'application/pdf', 2, '2026-06-10 10:00:00'),

-- Campaign 5 milestones (completed)
(9,  '/uploads/verde/preproduction_plan.pdf',     'application/pdf', 3, '2026-03-10 09:00:00'),
(10, '/uploads/verde/shoot_day1_batch.jpg',       'image/jpeg',       3, '2026-03-20 17:00:00'),
(11, '/uploads/verde/shoot_day2_batch.jpg',       'image/jpeg',       3, '2026-04-05 17:30:00'),
(12, '/uploads/verde/final_delivery.zip',         'application/zip',  3, '2026-05-15 12:00:00'),

-- Campaign 6 milestone
(13, '/uploads/verde/content_calendar_june.pdf',  'application/pdf', 3, '2026-06-10 10:00:00'),
(14, '/uploads/verde/june_batch_v1.jpg',          'image/jpeg',       3, '2026-06-20 14:00:00'),

-- Campaign 7 milestone
(16, '/uploads/skyline/moodboard_v1.pdf',         'application/pdf', 2, '2026-05-22 11:00:00');

-- ------------------------------------------------------------
--  ASSET VERSIONS
-- ------------------------------------------------------------
INSERT INTO asset_versions (asset_id, version_number, file_path, uploaded_by, uploaded_at) VALUES

-- Nexus ad batch 1 went through a revision
(5, 1, '/uploads/nexus/ad_batch1_v1.jpg', 2, '2026-05-05 15:00:00'),
(5, 2, '/uploads/nexus/ad_batch1_v2.jpg', 2, '2026-05-08 10:00:00'),

-- Verde june batch revision
(13, 1, '/uploads/verde/june_batch_v1.jpg', 3, '2026-06-20 14:00:00'),
(13, 2, '/uploads/verde/june_batch_v2.jpg', 3, '2026-06-23 09:00:00');

-- ------------------------------------------------------------
--  APPROVALS
-- ------------------------------------------------------------
INSERT INTO approvals (milestone_id, client_id, status, feedback, created_at, Notified) VALUES

-- Campaign 1
(1, 5, 'approved', 'Looks great, proceeding.',                           '2026-05-10 13:00:00', 1),
(2, 5, 'revision', 'Please revise the color palette to match brand guidelines.', '2026-05-26 10:00:00', 1),
(2, 5, 'approved', 'Revised version approved.',                          '2026-05-27 09:00:00', 1),

-- Campaign 3 (all approved)
(5, 6, 'approved', 'Strategy looks solid.',                              '2026-04-23 10:00:00', 1),
(6, 6, 'revision', 'Adjust CTA copy on banner ads.',                     '2026-05-06 11:00:00', 1),
(6, 6, 'approved', 'Updated version approved.',                          '2026-05-09 09:00:00', 1),
(7, 6, 'approved', 'Good work, approved.',                               '2026-05-21 15:00:00', 1),
(8, 6, 'approved', 'Final review approved. Ready to launch.',            '2026-06-11 10:00:00', 1),

-- Campaign 5 (completed)
(9,  7, 'approved', 'Pre-production plan approved.',                     '2026-03-11 09:00:00', 1),
(10, 7, 'approved', 'Great shots from day 1.',                           '2026-03-21 10:00:00', 1),
(11, 7, 'approved', 'Day 2 batch approved.',                             '2026-04-06 09:00:00', 1),
(12, 7, 'approved', 'Final delivery approved. Campaign complete.',       '2026-05-16 11:00:00', 1),

-- Campaign 6
(13, 7, 'approved', 'Calendar approved.',                                '2026-06-11 10:00:00', 1),
(14, 7, 'revision', 'Caption tone needs adjustment. Too formal.',        '2026-06-21 14:00:00', 1);

-- ------------------------------------------------------------
--  TIME LOGS
-- ------------------------------------------------------------
INSERT INTO time_logs (campaign_id, staff_id, log_date, hours, hourly_rate, cost) VALUES

-- Juan on Campaign 1
(1, 2, '2026-05-05', 4.00, 350.00, 1400.00),
(1, 2, '2026-05-12', 6.00, 350.00, 2100.00),
(1, 2, '2026-05-19', 5.00, 350.00, 1750.00),
(1, 2, '2026-05-26', 3.50, 350.00, 1225.00),

-- Juan on Campaign 3
(3, 2, '2026-04-16', 8.00, 350.00, 2800.00),
(3, 2, '2026-04-23', 7.00, 350.00, 2450.00),
(3, 2, '2026-05-07', 6.50, 350.00, 2275.00),

-- Maria on Campaign 5
(5, 3, '2026-03-05', 8.00, 400.00, 3200.00),
(5, 3, '2026-03-15', 7.00, 400.00, 2800.00),
(5, 3, '2026-04-02', 8.00, 400.00, 3200.00),
(5, 3, '2026-05-10', 5.00, 400.00, 2000.00),

-- Maria on Campaign 6
(6, 3, '2026-06-05', 4.00, 400.00, 1600.00),
(6, 3, '2026-06-18', 5.00, 400.00, 2000.00),

-- Carlo on Campaign 7
(7, 4, '2026-05-16', 6.00, 375.00, 2250.00),
(7, 4, '2026-05-23', 7.00, 375.00, 2625.00),

-- Carlo on Campaign 4
(4, 4, '2026-11-05', 3.00, 375.00, 1125.00);

-- ------------------------------------------------------------
--  RETAINERS
-- ------------------------------------------------------------
INSERT INTO retainers (client_id, total_amount, used_amount, remaining_amount, created_at) VALUES

(5, 50000.00, 18750.00, 31250.00, '2026-01-01 00:00:00'),  -- Bright Co.
(6, 75000.00, 32500.00, 42500.00, '2026-01-01 00:00:00'),  -- Nexus Brand
(7, 40000.00, 40000.00,     0.00, '2026-01-01 00:00:00'),  -- Verde Foods (fully used)
(8, 60000.00,  9375.00, 50625.00, '2026-01-01 00:00:00');  -- Skyline Realty

-- ------------------------------------------------------------
--  NOTIFICATIONS
-- ------------------------------------------------------------
INSERT INTO notifications (user_id, title, message, created_at) VALUES

-- To clients
(5, 'Milestone Approved',     'Your milestone "Creative Brief Sign-off" has been approved.',           '2026-05-10 13:05:00'),
(5, 'Revision Requested',     'Feedback submitted on "Initial Concepts Delivery". Please review.',    '2026-05-26 10:05:00'),
(5, 'Milestone Approved',     '"Initial Concepts Delivery" revision has been approved.',              '2026-05-27 09:05:00'),

(6, 'Strategy Approved',      '"Strategy Deck Presentation" has been approved.',                      '2026-04-23 10:05:00'),
(6, 'Ad Creatives Approved',  'All ad creative milestones have been approved. Campaign in review.',   '2026-06-11 10:05:00'),

(7, 'Campaign Completed',     'Your campaign "Verde Product Showcase" has been marked complete.',     '2026-05-16 11:05:00'),
(7, 'Revision Requested',     'Feedback on "June Content Batch" — caption tone needs adjustment.',   '2026-06-21 14:05:00'),

(8, 'Moodboard Approved',     '"Event Brief & Moodboard" approved. Moving to next milestone.',        '2026-05-22 11:05:00'),

-- To staff
(2, 'New Campaign Assigned',  'You have been assigned to "Bright Summer Launch".',                    '2026-05-01 08:00:00'),
(2, 'New Campaign Assigned',  'You have been assigned to "Nexus Q3 Push".',                           '2026-04-15 08:00:00'),
(3, 'New Campaign Assigned',  'You have been assigned to "Verde Product Showcase".',                  '2026-03-01 08:00:00'),
(3, 'New Campaign Assigned',  'You have been assigned to "Verde Social Series".',                     '2026-06-01 08:00:00'),
(4, 'New Campaign Assigned',  'You have been assigned to "Skyline Grand Opening".',                   '2026-05-15 08:00:00'),

-- Broadcast (user_id NULL = system-wide)
(NULL, 'System Notice', 'AdHub V2 has launched. Welcome to the new platform!', '2026-01-01 00:00:00');