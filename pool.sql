-- ============================================================
-- Darken Shadows Swimming Booking — DB Schema  (v2 — clean)
-- ============================================================
-- Philosophy:
--   • users table stores ONLY login credentials (name for display,
--     phone, email, password). No "full_name" column needed.
--   • Each program table stores swimmer_full_name (entered on the
--     form — may be the user themselves or someone they booked for).
--   • bookings table is the central hub; it stores swimmer_full_name,
--     swimmer_dob, relation, emergency contact, etc. copied from the
--     form so the dashboard can show everything without joining every
--     program table.
--   • The previous migration block at the bottom has been removed;
--     this file is meant to be run FRESH on an empty database.
-- ============================================================
show tables;
CREATE DATABASE IF NOT EXISTS darken_shadows
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE darken_shadows;
drop database darken_shadows;

-- ============================================================
--  USERS  (login identity only)
-- ============================================================

CREATE TABLE users (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)     NOT NULL,
    middle_name     VARCHAR(50)     DEFAULT NULL,
    last_name       VARCHAR(50)     NOT NULL,
    phone           VARCHAR(20)     NOT NULL UNIQUE,
    email           VARCHAR(100)    DEFAULT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  REMEMBER-ME TOKENS
-- ============================================================

CREATE TABLE remember_tokens (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(64)     NOT NULL UNIQUE,
    expires_at  DATETIME        NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_token   (token),
    INDEX idx_user_id (user_id),
    CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  USER SESSIONS  (optional — for session tracking)
-- ============================================================

CREATE TABLE user_sessions (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    session_token   VARCHAR(128)    NOT NULL UNIQUE,
    ip_address      VARCHAR(45)     DEFAULT NULL,
    user_agent      TEXT            DEFAULT NULL,
    last_active     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_us_user (user_id),
    CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  PROGRAM 1 — JUNIOR DEVELOPMENT  (Ages 6–14)
--  Columns match Program_junior_development.php INSERT exactly.
-- ============================================================

CREATE TABLE prog_junior_development (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,

    -- Who booked (FK to users)
    user_id             INT UNSIGNED    NOT NULL,

    -- Self or third-party booking
    is_for_self         TINYINT(1)      NOT NULL DEFAULT 1,
    relation            VARCHAR(80)     DEFAULT NULL,   -- e.g. parent, guardian

    -- Swimmer info (entered on form — NOT pulled from users)
    swimmer_full_name   VARCHAR(120)    NOT NULL,
    swimmer_dob         DATE            NOT NULL,
    swimmer_email       VARCHAR(180)    DEFAULT NULL,
    swimmer_phone       VARCHAR(20)     DEFAULT NULL,
    emergency_contact   VARCHAR(120)    DEFAULT NULL,
    medical_notes       TEXT            DEFAULT NULL,

    -- Program-specific
    skill_level         ENUM('beginner','novice','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    sessions_per_week   TINYINT         NOT NULL DEFAULT 3,
    strokes             SET('freestyle','backstroke','breaststroke','butterfly') NOT NULL DEFAULT 'freestyle,backstroke,breaststroke,butterfly',
    goals               TEXT            DEFAULT NULL,

    -- Scheduling (admin fills these)
    preferred_days      SET('mon','tue','wed','thu','fri','sat','sun') DEFAULT NULL,
    preferred_time      ENUM('morning','afternoon','evening')          DEFAULT NULL,
    start_date          DATE            DEFAULT NULL,

    -- Coach assignment (admin fills)
    coach_id            INT UNSIGNED    DEFAULT NULL,

    -- Status
    status              ENUM('pending','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes               TEXT            DEFAULT NULL,

    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_jd_user   (user_id),
    INDEX idx_jd_status (status),
    CONSTRAINT fk_jd_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_jd_coach FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  PROGRAM 2 — COMPETITIVE SQUAD  (Performance)
--  Columns match Program_competitive_squad.php INSERT exactly.
-- ============================================================

CREATE TABLE prog_competitive_squad (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,

    -- Who booked
    user_id             INT UNSIGNED    NOT NULL,

    -- Self or third-party booking
    is_for_self         TINYINT(1)      NOT NULL DEFAULT 1,
    relation            VARCHAR(80)     DEFAULT NULL,

    -- Swimmer info
    swimmer_full_name   VARCHAR(120)    NOT NULL,
    swimmer_dob         DATE            NOT NULL,
    swimmer_email       VARCHAR(180)    DEFAULT NULL,
    swimmer_phone       VARCHAR(20)     DEFAULT NULL,
    emergency_contact   VARCHAR(120)    DEFAULT NULL,
    medical_notes       TEXT            DEFAULT NULL,

    -- Program-specific
    skill_level         ENUM('club','regional','national','elite') NOT NULL DEFAULT 'club',
    target_event        VARCHAR(100)    DEFAULT NULL,
    current_pb_seconds  DECIMAL(6,2)   DEFAULT NULL,
    meet_tracking       TINYINT(1)      NOT NULL DEFAULT 0,
    goals               TEXT            DEFAULT NULL,

    -- Scheduling (admin fills)
    preferred_days      SET('mon','tue','wed','thu','fri','sat','sun') DEFAULT NULL,
    preferred_time      ENUM('morning','afternoon','evening')          DEFAULT NULL,
    start_date          DATE            DEFAULT NULL,

    -- Coach assignment (admin fills)
    assigned_coach_id   INT UNSIGNED    DEFAULT NULL,

    -- Status
    status              ENUM('pending','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes               TEXT            DEFAULT NULL,

    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_cs_user   (user_id),
    INDEX idx_cs_status (status),
    CONSTRAINT fk_cs_user  FOREIGN KEY (user_id)          REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cs_coach FOREIGN KEY (assigned_coach_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  PROGRAM 3 — ELITE COACHING  (1-on-1)
--  Columns match Program_elite_coaching.php INSERT exactly.
-- ============================================================

CREATE TABLE prog_elite_coaching (
    id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,

    -- Who booked
    user_id                 INT UNSIGNED    NOT NULL,

    -- Self or third-party booking
    is_for_self             TINYINT(1)      NOT NULL DEFAULT 1,
    relation                VARCHAR(80)     DEFAULT NULL,

    -- Swimmer info
    swimmer_full_name       VARCHAR(120)    NOT NULL,
    swimmer_dob             DATE            NOT NULL,
    swimmer_email           VARCHAR(180)    DEFAULT NULL,
    swimmer_phone           VARCHAR(20)     DEFAULT NULL,
    emergency_contact       VARCHAR(120)    DEFAULT NULL,
    medical_notes           TEXT            DEFAULT NULL,

    -- Program-specific
    skill_level             ENUM('advanced','regional','national','elite','professional') NOT NULL DEFAULT 'advanced',
    video_analysis          TINYINT(1)      NOT NULL DEFAULT 1,
    drills_custom           TINYINT(1)      NOT NULL DEFAULT 0,
    drills_notes            TEXT            DEFAULT NULL,
    performance_strategy    TEXT            DEFAULT NULL,
    preferred_schedule      VARCHAR(50)     DEFAULT NULL,
    goals                   TEXT            DEFAULT NULL,

    -- Scheduling (admin fills)
    preferred_days          SET('mon','tue','wed','thu','fri','sat','sun') DEFAULT NULL,
    preferred_time          ENUM('morning','afternoon','evening')          DEFAULT NULL,
    start_date              DATE            DEFAULT NULL,

    -- Coach assignment (admin fills)
    assigned_coach_id       INT UNSIGNED    DEFAULT NULL,

    -- Status
    status                  ENUM('pending','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes                   TEXT            DEFAULT NULL,

    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_ec_user   (user_id),
    INDEX idx_ec_status (status),
    CONSTRAINT fk_ec_user  FOREIGN KEY (user_id)          REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ec_coach FOREIGN KEY (assigned_coach_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  PROGRAM 4 — ADULT FITNESS SWIM  (18+ All Levels)
--  (no PHP form yet — columns kept consistent with pattern)
-- ============================================================

CREATE TABLE prog_adult_fitness_swim (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,

    -- Who booked
    user_id             INT UNSIGNED    NOT NULL,

    -- Self or third-party booking
    is_for_self         TINYINT(1)      NOT NULL DEFAULT 1,
    relation            VARCHAR(80)     DEFAULT NULL,

    -- Swimmer info
    swimmer_full_name   VARCHAR(120)    NOT NULL,
    swimmer_dob         DATE            NOT NULL,
    swimmer_email       VARCHAR(180)    DEFAULT NULL,
    swimmer_phone       VARCHAR(20)     DEFAULT NULL,
    emergency_contact   VARCHAR(120)    DEFAULT NULL,
    medical_notes       TEXT            DEFAULT NULL,

    -- Program-specific
    skill_level         ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    sessions_per_week   TINYINT         NOT NULL DEFAULT 3,
    fitness_goal        VARCHAR(255)    DEFAULT NULL,
    lane_preference     ENUM('slow','medium','fast')               DEFAULT NULL,

    -- Scheduling
    preferred_days      SET('mon','tue','wed','thu','fri','sat','sun')           DEFAULT NULL,
    preferred_time      ENUM('early_morning','morning','lunch','afternoon','evening') DEFAULT NULL,
    start_date          DATE            DEFAULT NULL,

    -- Coach assignment
    assigned_coach_id   INT UNSIGNED    DEFAULT NULL,

    -- Status
    status              ENUM('pending','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes               TEXT            DEFAULT NULL,

    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_afs_user   (user_id),
    INDEX idx_afs_status (status),
    CONSTRAINT fk_afs_user  FOREIGN KEY (user_id)          REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_afs_coach FOREIGN KEY (assigned_coach_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  PROGRAM 5 — MENTAL CONDITIONING  (Sport Psychology)
-- ============================================================

CREATE TABLE prog_mental_conditioning (
    id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,

    -- Who booked
    user_id                 INT UNSIGNED    NOT NULL,

    -- Self or third-party booking
    is_for_self             TINYINT(1)      NOT NULL DEFAULT 1,
    relation                VARCHAR(80)     DEFAULT NULL,

    -- Swimmer info
    swimmer_full_name       VARCHAR(120)    NOT NULL,
    swimmer_dob             DATE            NOT NULL,
    swimmer_email           VARCHAR(180)    DEFAULT NULL,
    swimmer_phone           VARCHAR(20)     DEFAULT NULL,
    emergency_contact       VARCHAR(120)    DEFAULT NULL,
    medical_notes           TEXT            DEFAULT NULL,

    -- Program-specific
    delivery_format         ENUM('workshop','integrated','one_on_one') NOT NULL DEFAULT 'workshop',
    sessions_per_week       TINYINT         NOT NULL DEFAULT 1,
    linked_program          ENUM('junior_development','competitive_squad','elite_coaching','adult_fitness_swim','masters_program') DEFAULT NULL,
    focus_visualisation     TINYINT(1)      NOT NULL DEFAULT 1,
    focus_stress_control    TINYINT(1)      NOT NULL DEFAULT 1,
    focus_race_readiness    TINYINT(1)      NOT NULL DEFAULT 0,
    focus_resilience        TINYINT(1)      NOT NULL DEFAULT 0,
    custom_focus            VARCHAR(255)    DEFAULT NULL,

    -- Scheduling
    preferred_days          SET('mon','tue','wed','thu','fri','sat','sun') DEFAULT NULL,
    preferred_time          ENUM('morning','afternoon','evening')          DEFAULT NULL,
    start_date              DATE            DEFAULT NULL,

    -- Coach assignment
    assigned_coach_id       INT UNSIGNED    DEFAULT NULL,

    -- Status
    status                  ENUM('pending','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes                   TEXT            DEFAULT NULL,

    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_mc_user   (user_id),
    INDEX idx_mc_status (status),
    CONSTRAINT fk_mc_user  FOREIGN KEY (user_id)          REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mc_coach FOREIGN KEY (assigned_coach_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  PROGRAM 6 — MASTERS PROGRAM  (35+ Swimmers)
-- ============================================================

CREATE TABLE prog_masters_program (
    id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,

    -- Who booked
    user_id                 INT UNSIGNED    NOT NULL,

    -- Self or third-party booking
    is_for_self             TINYINT(1)      NOT NULL DEFAULT 1,
    relation                VARCHAR(80)     DEFAULT NULL,

    -- Swimmer info
    swimmer_full_name       VARCHAR(120)    NOT NULL,
    swimmer_dob             DATE            NOT NULL,
    swimmer_email           VARCHAR(180)    DEFAULT NULL,
    swimmer_phone           VARCHAR(20)     DEFAULT NULL,
    emergency_contact       VARCHAR(120)    DEFAULT NULL,
    medical_notes           TEXT            DEFAULT NULL,

    -- Program-specific
    sessions_per_week       TINYINT         NOT NULL DEFAULT 3,
    primary_goal            ENUM('competition','fitness','social','all') NOT NULL DEFAULT 'fitness',
    competes_in_meets       TINYINT(1)      NOT NULL DEFAULT 0,
    age_group_category      VARCHAR(50)     DEFAULT NULL,
    social_events_opt_in    TINYINT(1)      NOT NULL DEFAULT 1,

    -- Scheduling
    preferred_days          SET('mon','tue','wed','thu','fri','sat','sun') DEFAULT NULL,
    preferred_time          ENUM('morning','afternoon','evening')          DEFAULT NULL,
    start_date              DATE            DEFAULT NULL,

    -- Coach assignment
    assigned_coach_id       INT UNSIGNED    DEFAULT NULL,

    -- Status
    status                  ENUM('pending','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes                   TEXT            DEFAULT NULL,

    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_mp_user   (user_id),
    INDEX idx_mp_status (status),
    CONSTRAINT fk_mp_user  FOREIGN KEY (user_id)          REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mp_coach FOREIGN KEY (assigned_coach_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  MASTER BOOKINGS TABLE
--  One row per booking regardless of program.
--  Stores swimmer info denormalised so the dashboard can show
--  everything with a single SELECT on bookings + JOIN users.
--  Columns match every PHP INSERT into bookings exactly.
-- ============================================================

CREATE TABLE bookings (
    id                              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,

    -- Who made the booking (the logged-in user)
    booked_by_user_id               INT UNSIGNED    NOT NULL,

    -- Self or third-party?
    is_for_self                     TINYINT(1)      NOT NULL DEFAULT 1,

    -- Swimmer details (from the form — same data as program table)
    swimmer_name                    VARCHAR(120)    NOT NULL,
    swimmer_dob                     DATE            DEFAULT NULL,
    swimmer_relation                VARCHAR(80)     DEFAULT NULL,   -- 'self' or e.g. 'parent'
    swimmer_phone                   VARCHAR(20)     DEFAULT NULL,
    swimmer_email                   VARCHAR(180)    DEFAULT NULL,
    swimmer_emergency_contact       VARCHAR(120)    DEFAULT NULL,
    swimmer_medical_notes           TEXT            DEFAULT NULL,

    -- Which program and which row in that program's table
    program                         ENUM(
                                        'junior_development',
                                        'competitive_squad',
                                        'elite_coaching',
                                        'adult_fitness_swim',
                                        'mental_conditioning',
                                        'masters_program'
                                    ) NOT NULL,
    program_record_id               INT UNSIGNED    NOT NULL,

    -- Booking meta
    booking_reference               VARCHAR(20)     NOT NULL UNIQUE,
    booking_date                    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    start_date                      DATE            DEFAULT NULL,
    end_date                        DATE            DEFAULT NULL,

    -- Payment
    payment_amount                  DECIMAL(8,2)    DEFAULT NULL,
    payment_status                  ENUM('unpaid','pending','paid','refunded','waived') NOT NULL DEFAULT 'unpaid',
    payment_method                  ENUM('card','bank_transfer','cash','voucher','online','cheque') DEFAULT NULL,
    payment_reference               VARCHAR(100)    DEFAULT NULL,
    paid_at                         DATETIME        DEFAULT NULL,

    -- Booking status
    status                          ENUM('pending','confirmed','active','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
    cancellation_reason             TEXT            DEFAULT NULL,
    cancelled_at                    DATETIME        DEFAULT NULL,

    -- Admin notes
    admin_notes                     TEXT            DEFAULT NULL,

    created_at                      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_bookings_user    (booked_by_user_id),
    INDEX idx_bookings_program (program, program_record_id),
    INDEX idx_bookings_status  (status),
    INDEX idx_bookings_ref     (booking_reference),

    CONSTRAINT fk_bookings_user FOREIGN KEY (booked_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TRIGGER — AUTO-GENERATE booking_reference
--  Format: DSC-YYYY-000001
-- ============================================================

DELIMITER $$

CREATE TRIGGER trg_booking_reference
BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    DECLARE next_id INT UNSIGNED;
    SET next_id = (
        SELECT AUTO_INCREMENT
        FROM   information_schema.TABLES
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = 'bookings'
    );
    SET NEW.booking_reference = CONCAT('DSC-', YEAR(NOW()), '-', LPAD(next_id, 6, '0'));
END$$

DELIMITER ;


-- ============================================================
--  ATTENDANCE LOG
-- ============================================================
select * from bookings;
CREATE TABLE attendance (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    booking_id      INT UNSIGNED    NOT NULL,
    session_date    DATE            NOT NULL,
    status          ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    notes           TEXT            DEFAULT NULL,
    recorded_by     INT UNSIGNED    DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_att_booking (booking_id),
    INDEX idx_att_date    (session_date),
    CONSTRAINT fk_att_booking FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_coach   FOREIGN KEY (recorded_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  QUICK-REFERENCE: what each PHP form stores where
-- ============================================================
--
--  Dashboard query to list a user's bookings:
--
--  SELECT
--      b.booking_reference,
--      b.program,
--      b.swimmer_name,
--      b.swimmer_relation,
--      b.is_for_self,
--      b.payment_status,
--      b.status,
--      b.created_at,
--      u.first_name,
--      u.last_name
--  FROM   bookings b
--  JOIN   users    u ON u.id = b.booked_by_user_id
--  WHERE  b.booked_by_user_id = :user_id
--  ORDER  BY b.created_at DESC;
--
--  To expand a booking detail, JOIN the relevant program table:
--
--  SELECT b.*, jd.*
--  FROM   bookings b
--  JOIN   prog_junior_development jd ON jd.id = b.program_record_id
--  WHERE  b.id = :booking_id
--    AND  b.program = 'junior_development';
--
-- ============================================================

CREATE TABLE IF NOT EXISTS memberships (
    id                 INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED    NOT NULL,
    plan               ENUM('bronze','silver','gold','platinum') NOT NULL,
    full_name          VARCHAR(120)    NOT NULL,
    dob                DATE            NOT NULL,
    phone              VARCHAR(20)     DEFAULT NULL,
    email              VARCHAR(180)    DEFAULT NULL,
    emergency_contact  VARCHAR(120)    NOT NULL,
    medical_notes      TEXT            DEFAULT NULL,
    start_date         DATE            NOT NULL,
    end_date           DATE            NOT NULL,
    duration_months    TINYINT         NOT NULL DEFAULT 1,
    auto_renew         TINYINT(1)      NOT NULL DEFAULT 0,
    payment_amount     DECIMAL(9,2)    NOT NULL,
    payment_method     ENUM('cash','bank_transfer','online','cheque','card') DEFAULT NULL,
    payment_reference  VARCHAR(100)    DEFAULT NULL,
    status             ENUM('pending','active','paused','expired','cancelled') NOT NULL DEFAULT 'pending',
    created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mem_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE bookings MODIFY COLUMN program ENUM(
    'junior_development',
    'competitive_squad',
    'elite_coaching',
    'adult_fitness_swim',
    'mental_conditioning',
    'masters_program',
    'membership'
) NOT NULL;

ALTER TABLE users 
ADD COLUMN role ENUM('coach', 'admin', 'user') NOT NULL DEFAULT 'user' 
AFTER is_active;
-- Already in pool.sql but included for completeness
ALTER TABLE users 
ADD COLUMN role ENUM('coach', 'admin', 'user') NOT NULL DEFAULT 'user' 
AFTER is_active;

-- coaches table (required by coach.php)
CREATE TABLE coaches (
    id                INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED   NOT NULL UNIQUE,
    specialisation    VARCHAR(120)   DEFAULT NULL,
    qualification     VARCHAR(200)   DEFAULT NULL,
    bio               TEXT           DEFAULT NULL,
    phone_direct      VARCHAR(20)    DEFAULT NULL,
    hire_date         DATE           DEFAULT NULL,
    is_head_coach     TINYINT(1)     NOT NULL DEFAULT 0,
    assigned_programs SET(
        'junior_development',
        'competitive_squad',
        'elite_coaching',
        'adult_fitness_swim',
        'mental_conditioning',
        'masters_program'
    ) DEFAULT NULL,
    created_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_coach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- coach_attendance table (required by coach.php)
CREATE TABLE coach_attendance (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    coach_id     INT UNSIGNED   NOT NULL,
    session_date DATE           NOT NULL,
    status       ENUM('present','absent','late','leave') NOT NULL DEFAULT 'present',
    notes        TEXT           DEFAULT NULL,
    recorded_by  INT UNSIGNED   DEFAULT NULL,
    created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ca (coach_id, session_date),
    INDEX idx_ca_coach (coach_id),
    INDEX idx_ca_date  (session_date),
    CONSTRAINT fk_ca_coach    FOREIGN KEY (coach_id)    REFERENCES coaches(id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_recorder FOREIGN KEY (recorded_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

update users set role = "admin" where first_name = "aman";
select * from users;
SET SQL_SAFE_UPDATES = 1;
UPDATE users SET is_active = 0 WHERE id = 1;
UPDATE users 
SET first_name = 'John', last_name = 'Doe', email = 'johnx3@gmail.com' 
WHERE id = 1;
select * from users;