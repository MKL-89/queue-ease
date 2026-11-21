-- =====================================================================
--  QueueEase - Web-Based Digital Queue Management System
--  Database: dqms_db
--  Import this file through phpMyAdmin (Import tab) or the MySQL CLI:
--      mysql -u root < dqms_db.sql
--  Compatible with MySQL 5.7+/8.x and MariaDB 10.4+ (XAMPP)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS dqms_db;
CREATE DATABASE dqms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dqms_db;

-- ---------------------------------------------------------------------
-- 1. users : every account that can log in (admin, staff, customer)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    phone           VARCHAR(20)  DEFAULT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   DATETIME     DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. customers : customer profile (registered OR guest/walk-in)
--    user_id is NULL for guests who took a ticket without registering
-- ---------------------------------------------------------------------
CREATE TABLE customers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED DEFAULT NULL,
    full_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    is_guest        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_user (user_id),
    KEY idx_customers_phone (phone),
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. services : service categories (each has its own ticket prefix)
-- ---------------------------------------------------------------------
CREATE TABLE services (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                 VARCHAR(100) NOT NULL,
    prefix               VARCHAR(5)   NOT NULL,
    description          VARCHAR(255) DEFAULT NULL,
    avg_service_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_name (name),
    UNIQUE KEY uq_services_prefix (prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. counters : service points; each counter serves one service
-- ---------------------------------------------------------------------
CREATE TABLE counters (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(50)  NOT NULL,
    service_id      INT UNSIGNED NOT NULL,
    location        VARCHAR(100) DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_counters_name (name),
    KEY idx_counters_service (service_id),
    CONSTRAINT fk_counters_service FOREIGN KEY (service_id) REFERENCES services (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. staff_counters : which staff member may work at which counter
-- ---------------------------------------------------------------------
CREATE TABLE staff_counters (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    counter_id      INT UNSIGNED NOT NULL,
    assigned_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_counter (user_id, counter_id),
    KEY idx_staff_counters_counter (counter_id),
    CONSTRAINT fk_sc_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sc_counter FOREIGN KEY (counter_id) REFERENCES counters (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. queue_tickets : the queue itself
--    ticket_number = prefix + 3-digit daily sequence (e.g. MED004)
--    (service_id, ticket_date, sequence_no) is UNIQUE -> no duplicates
-- ---------------------------------------------------------------------
CREATE TABLE queue_tickets (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_number       VARCHAR(10)  NOT NULL,
    reference_code      VARCHAR(12)  NOT NULL,
    service_id          INT UNSIGNED NOT NULL,
    customer_id         INT UNSIGNED DEFAULT NULL,
    counter_id          INT UNSIGNED DEFAULT NULL,
    served_by           INT UNSIGNED DEFAULT NULL,
    ticket_date         DATE         NOT NULL,
    sequence_no         INT UNSIGNED NOT NULL,
    status              ENUM('waiting','called','serving','served','skipped','cancelled') NOT NULL DEFAULT 'waiting',
    recall_count        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    notes               VARCHAR(255) DEFAULT NULL,
    called_at           DATETIME     DEFAULT NULL,
    service_started_at  DATETIME     DEFAULT NULL,
    completed_at        DATETIME     DEFAULT NULL,
    cancelled_at        DATETIME     DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ticket_reference (reference_code),
    UNIQUE KEY uq_ticket_sequence (service_id, ticket_date, sequence_no),
    KEY idx_tickets_status_service (status, service_id),
    KEY idx_tickets_date (ticket_date),
    KEY idx_tickets_customer_status (customer_id, status),
    KEY idx_tickets_counter_status (counter_id, status),
    KEY idx_tickets_served_by (served_by),
    CONSTRAINT fk_tickets_service FOREIGN KEY (service_id) REFERENCES services (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_counter FOREIGN KEY (counter_id) REFERENCES counters (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_staff FOREIGN KEY (served_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. announcements : messages for the display screen and customers
-- ---------------------------------------------------------------------
CREATE TABLE announcements (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title           VARCHAR(150) NOT NULL,
    message         TEXT         NOT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    start_date      DATE         DEFAULT NULL,
    end_date        DATE         DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_active (is_active),
    CONSTRAINT fk_announcements_user FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. activity_logs : audit trail of important actions
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED DEFAULT NULL,
    action          VARCHAR(50)  NOT NULL,
    description     VARCHAR(255) NOT NULL,
    ip_address      VARCHAR(45)  DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user (user_id),
    KEY idx_logs_created (created_at),
    KEY idx_logs_action (action),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. service_feedback : customer star rating after being served
--    one rating per ticket (UNIQUE ticket_id)
-- ---------------------------------------------------------------------
CREATE TABLE service_feedback (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id       INT UNSIGNED NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL,
    comment         VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feedback_ticket (ticket_id),
    KEY idx_feedback_rating (rating),
    CONSTRAINT fk_feedback_ticket FOREIGN KEY (ticket_id) REFERENCES queue_tickets (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_feedback_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  DEFAULT DATA
--  Default passwords (change them after first login):
--      Admin     admin@queueease.local      Admin@123
--      Staff     (any staff email below)    Staff@123
--      Customer  customer@queueease.local   Customer@123
-- =====================================================================

INSERT INTO users (id, full_name, email, phone, password_hash, role) VALUES
(1, 'System Administrator', 'admin@queueease.local',    '0700000001', '$2y$10$svrnLmRNm70Hk8UGbCetW.J9lp1K3PnLytMYgACQYxgIdeEU2NIYK', 'admin'),
(2, 'Grace Wanjiku',        'grace@queueease.local',    '0700000002', '$2y$10$eIC0kq1mq7Xpwt.aTjx0dOjd9hXET5ALKQ8azhPE3EH75rt4ZzdBm', 'staff'),
(3, 'Brian Otieno',         'brian@queueease.local',    '0700000003', '$2y$10$eIC0kq1mq7Xpwt.aTjx0dOjd9hXET5ALKQ8azhPE3EH75rt4ZzdBm', 'staff'),
(4, 'Faith Mutua',          'faith@queueease.local',    '0700000004', '$2y$10$eIC0kq1mq7Xpwt.aTjx0dOjd9hXET5ALKQ8azhPE3EH75rt4ZzdBm', 'staff'),
(5, 'Kevin Kamau',          'kevin@queueease.local',    '0700000005', '$2y$10$eIC0kq1mq7Xpwt.aTjx0dOjd9hXET5ALKQ8azhPE3EH75rt4ZzdBm', 'staff'),
(6, 'Demo Customer',        'customer@queueease.local', '0711000001', '$2y$10$7a2Ew4q2cmRqJNdA2m2/TuR6XVuFYsIuTKUINdxUkY//JJtRZJgVm', 'customer');

INSERT INTO customers (id, user_id, full_name, phone, email, is_guest) VALUES
(1, 6, 'Demo Customer', '0711000001', 'customer@queueease.local', 0);

INSERT INTO services (id, name, prefix, description, avg_service_minutes) VALUES
(1, 'Registration',         'REG', 'New registrations, account opening and record updates', 5),
(2, 'Medical Consultation', 'MED', 'Consultation with a doctor or clinical officer',        10),
(3, 'Payments',             'PAY', 'Fees, bills and other payments',                         4),
(4, 'Enquiries',            'ENQ', 'General enquiries and customer care',                    3);

INSERT INTO counters (id, name, service_id, location) VALUES
(1, 'Counter 1', 1, 'Ground Floor - Hall A'),
(2, 'Counter 2', 2, 'Ground Floor - Room 3'),
(3, 'Counter 3', 2, 'Ground Floor - Room 4'),
(4, 'Counter 4', 3, 'Ground Floor - Hall B'),
(5, 'Counter 5', 4, 'Reception');

INSERT INTO staff_counters (user_id, counter_id) VALUES
(2, 1), (2, 5),
(3, 2),
(4, 3),
(5, 4);

INSERT INTO announcements (title, message, is_active, created_by) VALUES
('Welcome to QueueEase', 'Please keep your ticket number ready and watch the screen for your turn.', 1, 1),
('Service Hours', 'We serve customers Monday to Friday 8:00 AM - 5:00 PM and Saturday 9:00 AM - 1:00 PM.', 1, 1);

INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'system_install', 'Database installed with default data', '127.0.0.1');
