-- HFCCF auth schema aligned to the current frontend system.
-- Database target: MySQL / MariaDB
--
-- This file is based on the actual Vue auth model in:
-- - src/mocks/users.json
-- - src/services/auth.js
-- - src/services/mappers/userMapper.js
-- - src/constants/roles.js
-- - src/constants/access.js
-- - src/services/accessControl.js
-- - src/mocks/sport/admin-dashboard-data.json
-- - src/mocks/sport/teams-management-data.json
-- - src/mocks/sport/matches-management-data.json
--
-- Important:
-- 1. User IDs in this system are strings like `usr_001`, not bigint IDs.
-- 2. The role code `adminscholaship` is intentionally kept as-is because the
--    frontend currently uses that exact value.
-- 3. Frontend RBAC is scope + domain based, so roles should persist both.
-- 4. Sanctum-style tokens must therefore use a string `tokenable_id`.
-- 5. Profile, settings, and website-team data below are the backend contract for
--    the current frontend, so keep them aligned when the UI changes later.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `personal_access_tokens`;
DROP TABLE IF EXISTS `password_reset_otps`;
DROP TABLE IF EXISTS `password_change_requests`;
DROP TABLE IF EXISTS `admin_audit_logs`;
DROP TABLE IF EXISTS `preschool_payments`;
DROP TABLE IF EXISTS `preschool_attendance_records`;
DROP TABLE IF EXISTS `preschool_class_students`;
DROP TABLE IF EXISTS `preschool_students`;
DROP TABLE IF EXISTS `preschool_classes`;
DROP TABLE IF EXISTS `sport_standings`;
DROP TABLE IF EXISTS `sport_top_scorers`;
DROP TABLE IF EXISTS `sport_match_goal_events`;
DROP TABLE IF EXISTS `sport_matches`;
DROP TABLE IF EXISTS `sport_tournament_alerts`;
DROP TABLE IF EXISTS `sport_player_documents`;
DROP TABLE IF EXISTS `sport_players`;
DROP TABLE IF EXISTS `sport_teams`;
DROP TABLE IF EXISTS `sport_tournaments`;
DROP TABLE IF EXISTS `website_team_expertise`;
DROP TABLE IF EXISTS `website_team_departments`;
DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `departments`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `departments` (
  `code` VARCHAR(32) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `display_order` TINYINT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`),
  KEY `departments_is_active_index` (`is_active`),
  KEY `departments_display_order_index` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `code` VARCHAR(32) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `scope` ENUM('super_admin', 'admin', 'staff') NOT NULL,
  `domain_code` ENUM('global', 'english', 'preschool', 'scholarship', 'sport') NOT NULL,
  `department_code` VARCHAR(32) NOT NULL,
  `sort_order` TINYINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`),
  KEY `roles_scope_index` (`scope`),
  KEY `roles_domain_code_index` (`domain_code`),
  KEY `roles_department_code_index` (`department_code`),
  CONSTRAINT `fk_roles_department`
    FOREIGN KEY (`department_code`) REFERENCES `departments` (`code`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `role_code` VARCHAR(32) NOT NULL,
  `permission_code` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_code`, `permission_code`),
  CONSTRAINT `fk_role_permissions_role`
    FOREIGN KEY (`role_code`) REFERENCES `roles` (`code`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_role_permissions_permission`
    FOREIGN KEY (`permission_code`) REFERENCES `permissions` (`code`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` VARCHAR(16) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `role_code` VARCHAR(32) NOT NULL,
  -- User-specific department override. Role/domain still controls RBAC.
  `department_code` VARCHAR(32) NOT NULL,
  `bio` TEXT DEFAULT NULL,
  `status` ENUM('active', 'pending', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(2048) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_code_index` (`role_code`),
  KEY `users_status_index` (`status`),
  KEY `users_department_code_index` (`department_code`),
  KEY `users_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_users_role`
    FOREIGN KEY (`role_code`) REFERENCES `roles` (`code`)
    ON UPDATE CASCADE,
  CONSTRAINT `fk_users_department`
    FOREIGN KEY (`department_code`) REFERENCES `departments` (`code`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `website_team_departments` (
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `expertise` JSON NOT NULL,
  `display_order` TINYINT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`),
  KEY `website_team_departments_is_active_index` (`is_active`),
  KEY `website_team_departments_display_order_index` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `website_team_expertise` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_code` VARCHAR(64) NOT NULL,
  `expertise_text` VARCHAR(191) NOT NULL,
  `display_order` TINYINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `website_team_expertise_department_order_unique` (`department_code`, `display_order`),
  KEY `website_team_expertise_department_index` (`department_code`),
  CONSTRAINT `fk_website_team_expertise_department`
    FOREIGN KEY (`department_code`) REFERENCES `website_team_departments` (`code`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_change_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` VARCHAR(16) NOT NULL,
  `new_password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `approval_note` VARCHAR(255) DEFAULT NULL,
  `requested_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` VARCHAR(16) DEFAULT NULL,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `password_change_requests_user_index` (`user_id`),
  KEY `password_change_requests_status_index` (`status`),
  KEY `password_change_requests_reviewed_by_index` (`reviewed_by`),
  CONSTRAINT `fk_password_change_requests_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_password_change_requests_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_otps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` VARCHAR(16) NOT NULL,
  -- Snapshot of the account email used when this OTP was issued.
  `email` VARCHAR(191) NOT NULL,
  -- Store only a hash of the OTP; never persist the 6-digit code in plain text.
  `otp_hash` VARCHAR(255) NOT NULL,
  -- Forgot Password currently allows only active Super Admin accounts.
  -- Keep this table separate from admin-approved password_change_requests.
  `purpose` ENUM('forgot_password') NOT NULL DEFAULT 'forgot_password',
  `channel` ENUM('email') NOT NULL DEFAULT 'email',
  `status` ENUM('pending', 'verified', 'used', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `resend_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` TIMESTAMP NOT NULL,
  `verified_at` TIMESTAMP NULL DEFAULT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `last_sent_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `request_ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `password_reset_otps_user_index` (`user_id`),
  KEY `password_reset_otps_email_index` (`email`),
  KEY `password_reset_otps_status_index` (`status`),
  KEY `password_reset_otps_expires_at_index` (`expires_at`),
  KEY `password_reset_otps_user_status_index` (`user_id`, `status`),
  CONSTRAINT `fk_password_reset_otps_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_permissions` (
  `user_id` VARCHAR(16) NOT NULL,
  `permission_code` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `permission_code`),
  CONSTRAINT `fk_user_permissions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_user_permissions_permission`
    FOREIGN KEY (`permission_code`) REFERENCES `permissions` (`code`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL DEFAULT 'App\\Models\\User',
  `tokenable_id` VARCHAR(16) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_index` (`tokenable_type`, `tokenable_id`),
  CONSTRAINT `fk_personal_access_tokens_user`
    FOREIGN KEY (`tokenable_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` VARCHAR(16) DEFAULT NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(100) NOT NULL,
  `entity_id` VARCHAR(64) NOT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_audit_logs_actor_user_index` (`actor_user_id`),
  KEY `admin_audit_logs_entity_index` (`entity_type`, `entity_id`),
  KEY `admin_audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `fk_admin_audit_logs_actor_user`
    FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preschool module source entities currently used by the frontend.
-- Notes:
-- 1. Preschool teacher accounts live in `users` with role_code = 'teacher-preschool'.
-- 2. Class rows preserve `teacher_display_name` because the current frontend mock stores
--    teacher names as text; `teacher_user_id` is nullable for future normalized assignment.
-- 3. Student records are preschool data records, not system users.

CREATE TABLE `preschool_classes` (
  `id` VARCHAR(32) NOT NULL,
  `code` VARCHAR(32) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `teacher_user_id` VARCHAR(16) DEFAULT NULL,
  `teacher_display_name` VARCHAR(191) DEFAULT NULL,
  `level` ENUM('Nursery', 'Kindergarten A', 'Kindergarten B', 'Prep') NOT NULL,
  `schedule` VARCHAR(191) NOT NULL,
  -- Cached denormalized count for the current class table UI. Recalculate from preschool_class_students when assignments change.
  `students_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('active', 'pending', 'closed') NOT NULL DEFAULT 'active',
  `room` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `preschool_classes_code_unique` (`code`),
  KEY `preschool_classes_teacher_index` (`teacher_user_id`),
  KEY `preschool_classes_level_index` (`level`),
  KEY `preschool_classes_status_index` (`status`),
  KEY `preschool_classes_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_preschool_classes_teacher`
    FOREIGN KEY (`teacher_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `preschool_students` (
  `id` VARCHAR(32) NOT NULL,
  `student_code` VARCHAR(32) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `guardian_name` VARCHAR(191) DEFAULT NULL,
  `guardian_phone` VARCHAR(32) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'pending', 'inactive', 'graduated') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `preschool_students_code_unique` (`student_code`),
  KEY `preschool_students_status_index` (`status`),
  KEY `preschool_students_gender_index` (`gender`),
  KEY `preschool_students_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `preschool_class_students` (
  `class_id` VARCHAR(32) NOT NULL,
  `student_id` VARCHAR(32) NOT NULL,
  `enrolled_at` DATE DEFAULT NULL,
  `status` ENUM('active', 'transferred', 'completed', 'dropped') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`, `student_id`),
  KEY `preschool_class_students_student_index` (`student_id`),
  KEY `preschool_class_students_status_index` (`status`),
  CONSTRAINT `fk_preschool_class_students_class`
    FOREIGN KEY (`class_id`) REFERENCES `preschool_classes` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_preschool_class_students_student`
    FOREIGN KEY (`student_id`) REFERENCES `preschool_students` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `preschool_attendance_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` VARCHAR(32) NOT NULL,
  `student_id` VARCHAR(32) NOT NULL,
  `recorded_by_user_id` VARCHAR(16) DEFAULT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `preschool_attendance_unique` (`class_id`, `student_id`, `attendance_date`),
  KEY `preschool_attendance_student_index` (`student_id`),
  KEY `preschool_attendance_recorded_by_index` (`recorded_by_user_id`),
  KEY `preschool_attendance_date_index` (`attendance_date`),
  KEY `preschool_attendance_status_index` (`status`),
  CONSTRAINT `fk_preschool_attendance_class`
    FOREIGN KEY (`class_id`) REFERENCES `preschool_classes` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_preschool_attendance_student`
    FOREIGN KEY (`student_id`) REFERENCES `preschool_students` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_preschool_attendance_recorded_by`
    FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `preschool_payments` (
  `id` VARCHAR(32) NOT NULL,
  `student_id` VARCHAR(32) DEFAULT NULL,
  `class_id` VARCHAR(32) DEFAULT NULL,
  `payment_reference` VARCHAR(64) DEFAULT NULL,
  `amount` DECIMAL(10, 2) UNSIGNED NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `payment_method` ENUM('cash', 'bank_transfer', 'mobile_payment', 'other') NOT NULL DEFAULT 'cash',
  `payment_status` ENUM('pending', 'paid', 'overdue', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
  `paid_at` DATETIME DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `preschool_payments_student_index` (`student_id`),
  KEY `preschool_payments_class_index` (`class_id`),
  KEY `preschool_payments_status_index` (`payment_status`),
  KEY `preschool_payments_due_date_index` (`due_date`),
  KEY `preschool_payments_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_preschool_payments_student`
    FOREIGN KEY (`student_id`) REFERENCES `preschool_students` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_preschool_payments_class`
    FOREIGN KEY (`class_id`) REFERENCES `preschool_classes` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sport module source entities currently used by the frontend.
-- Notes:
-- 1. Coach accounts already live in `users` with role_code = 'coach'.
-- 2. Dashboard "cards" such as total teams or upcoming matches are aggregate KPIs
--    and should usually be derived from source tables instead of stored directly.
-- 3. Player records are normalized through team_id. The API should join team names
--    from sport_teams when returning frontend player rows.

CREATE TABLE `sport_tournaments` (
  `id` VARCHAR(16) NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `location` VARCHAR(191) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL,
  `planned_matches` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sport_tournaments_status_index` (`status`),
  KEY `sport_tournaments_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sport_teams` (
  `id` VARCHAR(16) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `division` VARCHAR(100) NOT NULL,
  `coach_name` VARCHAR(191) DEFAULT NULL,
  `captain_name` VARCHAR(191) DEFAULT NULL,
  -- Cached denormalized count for the current teams table UI. Recalculate from sport_players when player assignments change.
  `players_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('active', 'pending', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `venue` VARCHAR(191) DEFAULT NULL,
  `wins` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `draws` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `losses` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `points` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sport_teams_status_index` (`status`),
  KEY `sport_teams_division_index` (`division`),
  KEY `sport_teams_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Players are data records (not system users). Keep player lifecycle separate from user/account lifecycle.
-- This table is the backend contract for the Sport Admin "Player Information" UI.
CREATE TABLE `sport_players` (
  `id` VARCHAR(16) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  -- Profile image is stored as a URL/path (actual file storage handled by the backend).
  `profile_image` VARCHAR(2048) DEFAULT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT NULL,
  `age` TINYINT UNSIGNED DEFAULT NULL,

  -- Personal information (captured on the player record).
  `height_cm` SMALLINT UNSIGNED DEFAULT NULL,
  `weight_kg` DECIMAL(5, 2) UNSIGNED DEFAULT NULL,
  `preferred_foot` ENUM('Right', 'Left', 'Both') DEFAULT NULL,
  `blood_type` ENUM('A', 'B', 'AB', 'O') DEFAULT NULL,
  `village` VARCHAR(100) DEFAULT NULL,
  `commune` VARCHAR(100) DEFAULT NULL,
  `district` VARCHAR(100) DEFAULT NULL,
  `province` VARCHAR(100) DEFAULT NULL,
  `current_school` VARCHAR(191) DEFAULT NULL,
  `grade_year` VARCHAR(64) DEFAULT NULL,

  -- Parent / guardian information (UI section #3).
  `father_name` VARCHAR(191) DEFAULT NULL,
  `father_age` TINYINT UNSIGNED DEFAULT NULL,
  `father_occupation` VARCHAR(191) DEFAULT NULL,
  `mother_name` VARCHAR(191) DEFAULT NULL,
  `mother_age` TINYINT UNSIGNED DEFAULT NULL,
  `mother_occupation` VARCHAR(191) DEFAULT NULL,
  `guardian_phone` VARCHAR(32) DEFAULT NULL,
  `guardian_relationship` ENUM('father', 'mother', 'guardian') DEFAULT NULL,

  -- Sports profile & administrative status.
  `team_id` VARCHAR(16) DEFAULT NULL,
  `division` VARCHAR(100) NOT NULL,
  `primary_position` VARCHAR(64) DEFAULT NULL,
  `registration_status` ENUM('registered', 'pending', 'unregistered') NOT NULL DEFAULT 'registered',

  -- Player record status (separate from system users).
  `jersey_number` SMALLINT UNSIGNED DEFAULT NULL,
  `status` ENUM('active', 'pending', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `matches_played` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `goals_scored` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sport_players_team_index` (`team_id`),
  KEY `sport_players_primary_position_index` (`primary_position`),
  KEY `sport_players_status_index` (`status`),
  KEY `sport_players_registration_status_index` (`registration_status`),
  KEY `sport_players_gender_index` (`gender`),
  KEY `sport_players_guardian_relationship_index` (`guardian_relationship`),
  KEY `sport_players_division_index` (`division`),
  KEY `sport_players_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_sport_players_team`
    FOREIGN KEY (`team_id`) REFERENCES `sport_teams` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Player documents (UI section #4: signed contract upload).
-- Store file metadata separately so multiple files can be attached to a single player record.
CREATE TABLE `sport_player_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_id` VARCHAR(16) NOT NULL,
  `document_type` ENUM('signed_contract') NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_url` VARCHAR(2048) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size_bytes` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sport_player_documents_player_index` (`player_id`),
  KEY `sport_player_documents_type_index` (`document_type`),
  KEY `sport_player_documents_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_sport_player_documents_player`
    FOREIGN KEY (`player_id`) REFERENCES `sport_players` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sport_tournament_alerts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tournament_id` VARCHAR(16) NOT NULL,
  `alert_type` ENUM('info', 'warning', 'error', 'success') NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sport_tournament_alerts_tournament_index` (`tournament_id`),
  CONSTRAINT `fk_sport_tournament_alerts_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `sport_tournaments` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sport_matches` (
  `id` VARCHAR(16) NOT NULL,
  `tournament_id` VARCHAR(16) DEFAULT NULL,
  `competition` VARCHAR(100) NOT NULL,
  `home_team_name` VARCHAR(191) NOT NULL,
  `away_team_name` VARCHAR(191) NOT NULL,
  `home_score` TINYINT UNSIGNED DEFAULT NULL,
  `away_score` TINYINT UNSIGNED DEFAULT NULL,
  `scheduled_at` DATETIME DEFAULT NULL,
  `venue` VARCHAR(191) DEFAULT NULL,
  -- Result-entry note/report saved from the Sport Admin result form.
  `result_report` TEXT DEFAULT NULL,
  `match_group` ENUM('live', 'today', 'general') NOT NULL DEFAULT 'general',
  `status` ENUM('scheduled', 'live', 'completed', 'postponed', 'cancelled') NOT NULL DEFAULT 'scheduled',
  `display_time_label` VARCHAR(64) DEFAULT NULL,
  `live_minute` VARCHAR(16) DEFAULT NULL,
  `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sport_matches_tournament_index` (`tournament_id`),
  KEY `sport_matches_competition_index` (`competition`),
  KEY `sport_matches_status_index` (`status`),
  KEY `sport_matches_group_index` (`match_group`),
  KEY `sport_matches_scheduled_at_index` (`scheduled_at`),
  KEY `sport_matches_deleted_at_index` (`deleted_at`),
  CONSTRAINT `fk_sport_matches_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `sport_tournaments` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sport_match_goal_events` (
  `id` VARCHAR(36) NOT NULL,
  `match_id` VARCHAR(16) NOT NULL,
  `team_type` ENUM('home', 'away') NOT NULL,
  `player_name` VARCHAR(191) NOT NULL,
  `minute` SMALLINT UNSIGNED NOT NULL,
  `goal_types` JSON NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sport_match_goal_events_match_index` (`match_id`),
  CONSTRAINT `fk_sport_match_goal_events_match`
    FOREIGN KEY (`match_id`) REFERENCES `sport_matches` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sport_top_scorers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tournament_id` VARCHAR(16) DEFAULT NULL,
  `player_name` VARCHAR(191) NOT NULL,
  `team_name` VARCHAR(191) NOT NULL,
  `goals` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sport_top_scorers_tournament_index` (`tournament_id`),
  CONSTRAINT `fk_sport_top_scorers_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `sport_tournaments` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sport_standings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tournament_id` VARCHAR(16) DEFAULT NULL,
  `position` SMALLINT UNSIGNED NOT NULL,
  `team_name` VARCHAR(191) NOT NULL,
  `played` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `wins` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `draws` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `losses` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `goals_for` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `goals_against` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `goal_difference` SMALLINT NOT NULL DEFAULT 0,
  `points` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sport_standings_tournament_position_unique` (`tournament_id`, `position`),
  KEY `sport_standings_tournament_index` (`tournament_id`),
  CONSTRAINT `fk_sport_standings_tournament`
    FOREIGN KEY (`tournament_id`) REFERENCES `sport_tournaments` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`code`, `name`, `display_order`, `is_active`) VALUES
  ('operations', 'Operations', 1, 1),
  ('education', 'Education', 2, 1),
  ('sports', 'Sports', 3, 1),
  ('administration', 'Administration', 4, 1);

INSERT INTO `roles` (`code`, `name`, `scope`, `domain_code`, `department_code`, `sort_order`) VALUES
  ('superadmin', 'Super Admin', 'super_admin', 'global', 'operations', 1),
  ('adminenglish', 'English Admin', 'admin', 'english', 'education', 2),
  ('adminpreschool', 'Preschool Admin', 'admin', 'preschool', 'education', 3),
  ('adminscholaship', 'Scholarship Admin', 'admin', 'scholarship', 'education', 4),
  ('adminsport', 'Sport Admin', 'admin', 'sport', 'sports', 5),
  ('teacher-english', 'English Teacher', 'staff', 'english', 'education', 6),
  ('teacher-preschool', 'Preschool Teacher', 'staff', 'preschool', 'education', 7),
  ('teacher-scholarship', 'Scholarship Teacher', 'staff', 'scholarship', 'education', 8),
  ('coach', 'Coach', 'staff', 'sport', 'sports', 9);

INSERT INTO `permissions` (`code`, `name`) VALUES
  ('all:*', 'Full system access'),
  ('athletes:read', 'Read athletes'),
  ('attendance:write', 'Manage attendance'),
  ('classes:write', 'Manage classes'),
  ('dashboard:read', 'Read dashboard'),
  ('programs:write', 'Manage programs'),
  ('reports:read', 'Read reports'),
  ('settings:read', 'Read settings'),
  ('students:read', 'Read students'),
  ('students:write', 'Manage students'),
  ('tasks:read', 'Read tasks'),
  ('tasks:write', 'Manage tasks'),
  ('training:write', 'Manage training'),
  ('users:read', 'Read users'),
  ('users:write', 'Manage users');

INSERT INTO `role_permissions` (`role_code`, `permission_code`) VALUES
  ('superadmin', 'all:*'),

  ('adminenglish', 'dashboard:read'),
  ('adminenglish', 'users:read'),
  ('adminenglish', 'reports:read'),
  ('adminenglish', 'programs:write'),

  ('adminpreschool', 'dashboard:read'),
  ('adminpreschool', 'users:read'),
  ('adminpreschool', 'users:write'),
  ('adminpreschool', 'reports:read'),
  ('adminpreschool', 'classes:write'),
  ('adminpreschool', 'students:read'),
  ('adminpreschool', 'students:write'),
  ('adminpreschool', 'attendance:write'),
  ('adminpreschool', 'settings:read'),

  ('adminscholaship', 'dashboard:read'),
  ('adminscholaship', 'users:read'),
  ('adminscholaship', 'users:write'),
  ('adminscholaship', 'reports:read'),
  ('adminscholaship', 'settings:read'),

  ('adminsport', 'dashboard:read'),
  ('adminsport', 'users:read'),
  ('adminsport', 'reports:read'),
  ('adminsport', 'programs:write'),

  ('coach', 'dashboard:read'),
  ('coach', 'athletes:read'),
  ('coach', 'training:write'),

  ('teacher-english', 'dashboard:read'),
  ('teacher-english', 'tasks:read'),
  ('teacher-english', 'tasks:write'),

  ('teacher-preschool', 'dashboard:read'),
  ('teacher-preschool', 'classes:write'),
  ('teacher-preschool', 'students:read'),
  ('teacher-preschool', 'attendance:write'),
  ('teacher-preschool', 'tasks:read'),
  ('teacher-preschool', 'tasks:write'),

  ('teacher-scholarship', 'dashboard:read'),
  ('teacher-scholarship', 'tasks:read'),
  ('teacher-scholarship', 'tasks:write');

-- Seed login accounts aligned to the current mock system.
-- Passwords are bcrypt hashes of the frontend mock passwords.
INSERT INTO `users` (
  `id`,
  `first_name`,
  `last_name`,
  `username`,
  `email`,
  `phone`,
  `role_code`,
  `department_code`,
  `status`,
  `avatar`,
  `password`,
  `last_login_at`,
  `created_at`,
  `updated_at`
) VALUES
  (
    'usr_001',
    'Vanna',
    'Nop',
    'Vanna Nop',
    'superadmin01@hfccf.org',
    '+855 12 301 001',
    'superadmin',
    'operations',
    'active',
    'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
    '$2y$10$VDxiTyF8hTrUu9rcWfcAveVDiHxBP5NdngXzfVCalxnrOt8oqlLOG',
    '2026-03-04 04:15:00',
    '2026-02-01 08:00:00',
    '2026-02-01 08:00:00'
  ),
  (
    'usr_016',
    'Sovann',
    'Lim',
    'Sovann Lim',
    'preschool.admin01@hfccf.org',
    '+855 12 316 016',
    'adminpreschool',
    'education',
    'active',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
    '$2y$10$DL4pVR9wQwhWbiB.UqWUteKxr.mA0nELKCblW1LWNGTxV6w.fLKaG',
    '2026-03-03 04:15:00',
    '2026-02-16 08:00:00',
    '2026-02-16 08:00:00'
  ),
  (
    'usr_031',
    'Sokun',
    'Nop',
    'Sokun Nop',
    'scholarship.admin01@hfccf.org',
    '+855 12 331 031',
    'adminscholaship',
    'education',
    'active',
    'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
    '$2y$10$JFyQX2264zF7398Hhf7.zOXyVQutg2T/pMZ0yJ5KZTp2c.NMNpI9a',
    '2026-03-02 04:15:00',
    '2026-02-04 08:00:00',
    '2026-02-04 08:00:00'
  ),
  (
    'usr_046',
    'Sophea',
    'Lim',
    'Sophea Lim',
    'english.admin01@hfccf.org',
    '+855 12 346 046',
    'adminenglish',
    'education',
    'active',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
    '$2y$10$FPHRt1Qo.Bqdz66ebsxok.HowfnX23ivq95NXFFltbQRINlnmsH2O',
    '2026-03-01 04:15:00',
    '2026-02-19 08:00:00',
    '2026-02-19 08:00:00'
  ),
  (
    'usr_061',
    'Pisey',
    'Nop',
    'Pisey Nop',
    'sport.admin01@hfccf.org',
    '+855 12 361 061',
    'adminsport',
    'sports',
    'active',
    'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
    '$2y$10$.ABQclzC/hcDA76xiptxeuNmMQBppVFkhRJXnLo2Mr9JFTBG0/s2a',
    '2026-03-08 04:15:00',
    '2026-02-07 08:00:00',
    '2026-02-07 08:00:00'
  ),
  (
    'usr_076',
    'Rina',
    'Lim',
    'Rina Lim',
    'coach01@hfccf.org',
    '+855 12 376 076',
    'coach',
    'sports',
    'active',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
    '$2y$10$0xolL2LnIuMQXe7W0l1.nu4vjDUgK1X38Ne./LJhk0tFxXrnzZ8fK',
    '2026-03-07 04:15:00',
    '2026-02-22 08:00:00',
    '2026-02-22 08:00:00'
  ),
  (
    'usr_091',
    'Sreypov',
    'Nop',
    'Sreypov Nop',
    'teacher.english01@hfccf.org',
    '+855 12 391 091',
    'teacher-english',
    'education',
    'active',
    'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
    '$2y$10$ZhQvc5g8xmR798XPkHtX.OJAFPU20VZqVjqwmu1KMCZWrfGY416hy',
    '2026-03-06 04:15:00',
    '2026-02-10 08:00:00',
    '2026-02-10 08:00:00'
  ),
  (
    'usr_106',
    'Vannak',
    'Lim',
    'Vannak Lim',
    'teacher.preschool01@hfccf.org',
    '+855 12 406 106',
    'teacher-preschool',
    'education',
    'active',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=200&q=80',
    '$2y$10$8Yhy10mQzt0AS9hkzIrF4.wvhAr3spix6cJqfUTzd89RmBC.GRAXe',
    '2026-03-05 04:15:00',
    '2026-02-25 08:00:00',
    '2026-02-25 08:00:00'
  ),
  (
    'usr_121',
    'Nita',
    'Nop',
    'Nita Nop',
    'teacher.scholarship01@hfccf.org',
    '+855 12 421 121',
    'teacher-scholarship',
    'education',
    'active',
    'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?auto=format&fit=crop&w=200&q=80',
    '$2y$10$XVIt6GQJliG6e6phQumQeeD0gbWdEzH/fEUSeetq.8Ttj6Gxj1BWG',
    '2026-03-04 04:15:00',
    '2026-02-13 08:00:00',
    '2026-02-13 08:00:00'
  );

-- Seed direct user permissions from the role defaults so API responses can
-- expose permission arrays without custom per-user grants yet.
INSERT INTO `user_permissions` (`user_id`, `permission_code`)
SELECT `u`.`id`, `rp`.`permission_code`
FROM `users` AS `u`
INNER JOIN `role_permissions` AS `rp`
  ON `rp`.`role_code` = `u`.`role_code`;

INSERT INTO `website_team_departments` (`code`, `name`, `expertise`, `display_order`, `is_active`) VALUES
  (
    'ui-ux-design',
    'UI/UX Design',
    JSON_ARRAY(
      'Interface layout and visual hierarchy',
      'User journey planning and wireframing',
      'Accessibility-focused design review'
    ),
    1,
    1
  ),
  (
    'frontend-development',
    'Frontend Development',
    JSON_ARRAY(
      'Reusable Vue components and page structure',
      'Responsive layouts and interaction states',
      'State handling and UI integration'
    ),
    2,
    1
  ),
  (
    'backend-development',
    'Backend Development',
    JSON_ARRAY(
      'API design and data flow',
      'Authentication and permission logic',
      'System integration and maintenance'
    ),
    3,
    1
  ),
  (
    'quality-assurance',
    'Quality Assurance',
    JSON_ARRAY(
      'Functional testing and regression checks',
      'Bug reporting and verification',
      'Release readiness review'
    ),
    4,
    1
  ),
  (
    'project-coordination',
    'Project Coordination',
    JSON_ARRAY(
      'Feature planning and delivery tracking',
      'Cross-team communication',
      'Scope, timeline, and handoff coordination'
    ),
    5,
    1
  );

INSERT INTO `website_team_expertise` (`department_code`, `expertise_text`, `display_order`) VALUES
  ('ui-ux-design', 'Interface layout and visual hierarchy', 1),
  ('ui-ux-design', 'User journey planning and wireframing', 2),
  ('ui-ux-design', 'Accessibility-focused design review', 3),
  ('frontend-development', 'Reusable Vue components and page structure', 1),
  ('frontend-development', 'Responsive layouts and interaction states', 2),
  ('frontend-development', 'State handling and UI integration', 3),
  ('backend-development', 'API design and data flow', 1),
  ('backend-development', 'Authentication and permission logic', 2),
  ('backend-development', 'System integration and maintenance', 3),
  ('quality-assurance', 'Functional testing and regression checks', 1),
  ('quality-assurance', 'Bug reporting and verification', 2),
  ('quality-assurance', 'Release readiness review', 3),
  ('project-coordination', 'Feature planning and delivery tracking', 1),
  ('project-coordination', 'Cross-team communication', 2),
  ('project-coordination', 'Scope, timeline, and handoff coordination', 3);

INSERT INTO `preschool_classes` (
  `id`,
  `code`,
  `name`,
  `teacher_user_id`,
  `teacher_display_name`,
  `level`,
  `schedule`,
  `students_count`,
  `status`,
  `room`,
  `notes`
) VALUES
  ('preschool-class-1', 'PS-NUR-01', 'Morning Nursery', NULL, 'Srey Pov', 'Nursery', 'Mon-Fri, 8:00 AM', 18, 'active', 'Room A1', ''),
  ('preschool-class-2', 'PS-KA-02', 'Kindergarten A Blue', NULL, 'Dara', 'Kindergarten A', 'Mon-Fri, 9:30 AM', 22, 'active', 'Room B2', ''),
  ('preschool-class-3', 'PS-KB-01', 'Kindergarten B Red', NULL, 'Malis', 'Kindergarten B', 'Mon-Fri, 1:00 PM', 20, 'pending', 'Room C1', ''),
  ('preschool-class-4', 'PS-PRE-01', 'Prep Readiness Group', NULL, 'Sokha', 'Prep', 'Mon-Fri, 2:30 PM', 16, 'active', 'Room D1', ''),
  ('preschool-class-5', 'PS-NUR-02', 'Afternoon Nursery', NULL, 'Chanthy', 'Nursery', 'Mon-Fri, 1:30 PM', 17, 'closed', 'Room A2', ''),
  ('preschool-class-6', 'PS-KA-03', 'Kindergarten A Green', NULL, 'Pisey', 'Kindergarten A', 'Sat, 8:30 AM', 14, 'active', 'Room B1', ''),
  ('preschool-class-7', 'PS-KB-02', 'Kindergarten B Yellow', NULL, 'Ratha', 'Kindergarten B', 'Sat, 10:00 AM', 15, 'pending', 'Room C2', '');

INSERT INTO `preschool_students` (
  `id`,
  `student_code`,
  `first_name`,
  `last_name`,
  `gender`,
  `date_of_birth`,
  `guardian_name`,
  `guardian_phone`,
  `address`,
  `status`
) VALUES
  ('preschool-student-1', 'PS-STU-001', 'Sok', 'Dara', 'male', '2021-03-12', 'Sok Vannak', '012 987 654', 'Phnom Penh', 'active'),
  ('preschool-student-2', 'PS-STU-002', 'Chan', 'Sreyneang', 'female', '2020-11-05', 'Chan Srey', '015 456 789', 'Kandal', 'active'),
  ('preschool-student-3', 'PS-STU-003', 'Lim', 'Vicheka', 'female', '2020-06-24', 'Lim Sovann', '017 222 333', 'Phnom Penh', 'pending'),
  ('preschool-student-4', 'PS-STU-004', 'Kim', 'Rithy', 'male', '2019-09-18', 'Kim Sophat', '010 333 444', 'Takeo', 'active');

INSERT INTO `preschool_class_students` (`class_id`, `student_id`, `enrolled_at`, `status`) VALUES
  ('preschool-class-1', 'preschool-student-1', '2026-01-08', 'active'),
  ('preschool-class-2', 'preschool-student-2', '2026-01-08', 'active'),
  ('preschool-class-2', 'preschool-student-3', '2026-02-01', 'active'),
  ('preschool-class-4', 'preschool-student-4', '2026-01-15', 'active');

INSERT INTO `preschool_attendance_records` (
  `class_id`,
  `student_id`,
  `recorded_by_user_id`,
  `attendance_date`,
  `status`,
  `note`
) VALUES
  ('preschool-class-1', 'preschool-student-1', 'usr_106', '2026-05-06', 'present', NULL),
  ('preschool-class-2', 'preschool-student-2', 'usr_106', '2026-05-06', 'late', 'Arrived after morning activity'),
  ('preschool-class-2', 'preschool-student-3', 'usr_106', '2026-05-06', 'absent', 'Guardian called ahead'),
  ('preschool-class-4', 'preschool-student-4', 'usr_106', '2026-05-06', 'present', NULL);

INSERT INTO `preschool_payments` (
  `id`,
  `student_id`,
  `class_id`,
  `payment_reference`,
  `amount`,
  `currency`,
  `payment_method`,
  `payment_status`,
  `paid_at`,
  `due_date`,
  `note`
) VALUES
  ('preschool-payment-1', 'preschool-student-1', 'preschool-class-1', 'PS-PAY-2026-001', 25.00, 'USD', 'cash', 'paid', '2026-05-01 09:15:00', '2026-05-05', 'May tuition'),
  ('preschool-payment-2', 'preschool-student-2', 'preschool-class-2', 'PS-PAY-2026-002', 25.00, 'USD', 'mobile_payment', 'paid', '2026-05-02 10:30:00', '2026-05-05', 'May tuition'),
  ('preschool-payment-3', 'preschool-student-3', 'preschool-class-2', 'PS-PAY-2026-003', 25.00, 'USD', 'cash', 'pending', NULL, '2026-05-10', 'Awaiting guardian payment'),
  ('preschool-payment-4', 'preschool-student-4', 'preschool-class-4', 'PS-PAY-2026-004', 30.00, 'USD', 'bank_transfer', 'overdue', NULL, '2026-05-01', 'Follow up with guardian');

INSERT INTO `sport_tournaments` (`id`, `title`, `subtitle`, `location`, `status`, `planned_matches`) VALUES
  (
    'tour_001',
    'Inter-Club Summer Showcase',
    'Final week structure, fixtures, and broadcast-ready highlights.',
    'Phnom Penh Sports Complex',
    'live',
    9
  );

INSERT INTO `sport_teams` (
  `id`,
  `name`,
  `division`,
  `coach_name`,
  `captain_name`,
  `players_count`,
  `status`,
  `venue`,
  `wins`,
  `draws`,
  `losses`,
  `points`
) VALUES
  ('team_001', 'Falcon United', 'U-18 Premier', 'Dara Sok', 'Vanna Lim', 18, 'active', 'Main Field', 7, 1, 1, 22),
  ('team_002', 'River Panthers', 'U-18 Premier', 'Kunthea Mean', 'Piseth Chan', 17, 'active', 'North Pitch', 6, 2, 1, 20),
  ('team_003', 'Golden Tigers', 'U-16 Elite', 'Malis Kim', 'Sovann Roth', 16, 'pending', 'Training Hall B', 4, 2, 2, 14),
  ('team_004', 'Blue Comets', 'U-16 Elite', 'Sreyleak Hong', 'Kanika Pov', 15, 'active', 'West Arena', 5, 1, 2, 16),
  ('team_005', 'Summit Strikers', 'Senior Development', 'Panhara Lim', 'Ravy Hong', 20, 'active', 'South Stadium', 8, 0, 1, 24),
  ('team_006', 'Harbor Rangers', 'Senior Development', 'Pisey Mean', 'Kimheng Pov', 19, 'inactive', 'Dockside Ground', 2, 3, 4, 9),
  ('team_007', 'Victory Academy', 'U-14 Future', 'Nita Chhun', 'Mony Pen', 14, 'pending', 'Academy Court', 3, 2, 2, 11),
  ('team_008', 'Lotus Warriors', 'U-14 Future', 'Sokunthea Nop', 'Thyda Keo', 13, 'active', 'East Pavilion', 6, 0, 1, 18),
  ('team_009', 'Crown Legends', 'Girls Competitive', 'Mony Pov', 'Sophal Em', 16, 'active', 'Central Arena', 5, 2, 1, 17),
  ('team_010', 'Skyline Youth', 'Girls Competitive', 'Thyda Pen', 'Bopha Meng', 15, 'inactive', 'Indoor Dome', 1, 2, 5, 5);

INSERT INTO `sport_players` (
  `id`,
  `name`,
  `team_id`,
  `division`,
  `primary_position`,
  `registration_status`,
  `jersey_number`,
  `status`,
  `matches_played`,
  `goals_scored`
) VALUES
  ('player_001', 'Dara Sok', 'team_001', 'U-18 Premier', 'Forward', 'registered', 9, 'active', 12, 8),
  ('player_002', 'Vanna Lim', 'team_001', 'U-18 Premier', 'Midfielder', 'registered', 10, 'active', 11, 4),
  ('player_003', 'Piseth Chan', 'team_002', 'U-18 Premier', 'Defender', 'pending', 5, 'pending', 8, 1);

INSERT INTO `sport_player_documents` (
  `player_id`,
  `document_type`,
  `file_name`,
  `file_url`,
  `mime_type`,
  `file_size_bytes`
) VALUES
  ('player_001', 'signed_contract', 'dara-sok-contract.pdf', '/uploads/sport/players/player_001/contract.pdf', 'application/pdf', 245760),
  ('player_002', 'signed_contract', 'vanna-lim-contract.jpg', '/uploads/sport/players/player_002/contract.jpg', 'image/jpeg', 184320);

INSERT INTO `sport_tournament_alerts` (`tournament_id`, `alert_type`, `message`, `display_order`) VALUES
  ('tour_001', 'warning', 'Hydration stations require restocking before Saturday matches.', 1),
  ('tour_001', 'info', 'Live stream overlay graphics are scheduled for deployment tomorrow.', 2);

INSERT INTO `sport_matches` (
  `id`,
  `tournament_id`,
  `competition`,
  `home_team_name`,
  `away_team_name`,
  `home_score`,
  `away_score`,
  `scheduled_at`,
  `venue`,
  `match_group`,
  `status`,
  `display_time_label`,
  `live_minute`,
  `display_order`
) VALUES
  ('match_001', 'tour_001', 'U-18 Premier', 'Victory Academy', 'HFCCF Juniors', 2, 1, '2026-05-06 15:30:00', 'Main Stadium', 'general', 'completed', NULL, NULL, 1),
  ('match_002', 'tour_001', 'U-16 Elite', 'Youth Stars', 'Victory Academy', NULL, NULL, '2026-05-09 09:00:00', 'Training Ground A', 'today', 'scheduled', '9:00 AM', NULL, 2),
  ('match_003', 'tour_001', 'Senior Development', 'HFCCF Seniors', 'City Academy', 0, 0, '2026-05-10 18:00:00', 'City Stadium', 'live', 'live', NULL, NULL, 3),
  ('match_004', 'tour_001', 'U-18 Premier', 'Panther FC', 'River Youth', 1, 1, '2026-05-12 14:00:00', 'North Ground', 'today', 'postponed', '2:00 PM', NULL, 4),
  ('match_005', 'tour_001', 'U-16 Elite', 'Blue Hawks', 'Golden Lions', 3, 0, '2026-05-13 16:30:00', 'Main Stadium', 'general', 'completed', NULL, NULL, 5),
  ('match_006', 'tour_001', 'Senior Development', 'City Academy', 'Victory Academy', NULL, NULL, '2026-05-15 19:00:00', 'City Stadium', 'today', 'scheduled', '7:00 PM', NULL, 6),
  ('match_007', 'tour_001', 'U-18 Premier', 'HFCCF Juniors', 'Panther FC', 0, 2, '2026-05-16 15:30:00', 'South Pitch', 'general', 'completed', NULL, NULL, 7),
  ('match_008', 'tour_001', 'U-16 Elite', 'River Youth', 'Blue Hawks', 1, 0, '2026-05-18 08:30:00', 'Training Ground A', 'today', 'scheduled', '8:30 AM', NULL, 8),
  ('match_009', 'tour_001', 'Senior Development', 'Golden Lions', 'HFCCF Seniors', NULL, NULL, '2026-05-20 18:15:00', 'North Ground', 'live', 'live', NULL, NULL, 9);

INSERT INTO `sport_match_goal_events` (`id`, `match_id`, `team_type`, `player_name`, `minute`, `goal_types`) VALUES
  ('goal_event_001', 'match_001', 'home', 'Dara Sok', 24, JSON_ARRAY('goal')),
  ('goal_event_002', 'match_001', 'home', 'Vanna Lim', 61, JSON_ARRAY('goal')),
  ('goal_event_003', 'match_001', 'away', 'Player B', 75, JSON_ARRAY('goal', 'yellow'));

INSERT INTO `sport_top_scorers` (`tournament_id`, `player_name`, `team_name`, `goals`, `display_order`) VALUES
  ('tour_001', 'Player A', 'Team A', 6, 1),
  ('tour_001', 'Player B', 'Team B', 5, 2),
  ('tour_001', 'Player C', 'Team C', 4, 3);

INSERT INTO `sport_standings` (
  `tournament_id`,
  `position`,
  `team_name`,
  `played`,
  `wins`,
  `draws`,
  `losses`,
  `goals_for`,
  `goals_against`,
  `goal_difference`,
  `points`
) VALUES
  ('tour_001', 1, 'Team A', 4, 3, 1, 0, 9, 3, 6, 10),
  ('tour_001', 2, 'Team B', 4, 2, 2, 0, 7, 4, 3, 8),
  ('tour_001', 3, 'Team C', 4, 2, 1, 1, 6, 5, 1, 7),
  ('tour_001', 4, 'Team D', 4, 2, 0, 2, 5, 5, 0, 6);

-- Query shape to match the frontend user model:
-- SELECT
--   u.id,
--   u.first_name AS firstName,
--   u.last_name AS lastName,
--   u.username,
--   u.email,
--   u.phone,
--   u.role_code AS role,
--   r.scope,
--   r.domain_code AS domain,
--   u.department_code AS departmentCode,
--   d.name AS department,
--   u.bio,
--   u.status,
--   u.avatar,
--   u.created_at AS createdAt,
--   u.last_login_at AS lastLoginAt,
--   COALESCE(p.permissions, JSON_ARRAY()) AS role_permission
-- FROM users u
-- INNER JOIN roles r ON r.code = u.role_code
-- INNER JOIN departments d ON d.code = u.department_code
-- LEFT JOIN (
--   SELECT
--     user_id,
--     JSON_ARRAYAGG(permission_code) AS permissions
--   FROM user_permissions
--   GROUP BY user_id
-- ) p ON p.user_id = u.id;

-- Auth recovery API shape:
-- 1. Forgot-password email verification should find an active Super Admin user.
-- 2. Sending/resending OTP should create a password_reset_otps row with otp_hash,
--    channel = 'email', resend_count, expires_at, request_ip, and user_agent.
--    The plain OTP is only sent to the user and must never be stored.
-- 3. OTP verification should compare the submitted code with otp_hash, increment
--    attempts for failed checks, mark status = 'verified', and set verified_at.
-- 4. Creating the new password should require a verified, unexpired OTP, update users.password,
--    then mark the OTP status = 'used' and set used_at.
-- 5. password_change_requests is separate: use it for admin-approved password changes,
--    not for the public forgot-password OTP flow.

-- Current Preschool frontend data shape:
-- 1. Preschool teachers are system users with role_code = 'teacher-preschool'.
-- 2. Class management comes from `preschool_classes`; status values should be mapped
--    between API lowercase values and frontend labels such as Active/Pending/Closed.
-- 3. Student information comes from `preschool_students`; students are data records,
--    not system users.
-- 4. Teacher "My Students" should join users -> preschool_classes.teacher_user_id
--    -> preschool_class_students -> preschool_students when teacher assignments exist.
-- 5. Attendance pages should read/write `preschool_attendance_records`.
-- 6. Payment pages should read/write `preschool_payments`.

-- Current Sport frontend data shape:
-- 1. Coaches come from `users` filtered by role_code = 'coach'.
-- 2. Teams management comes from `sport_teams`.
-- 3. Player information comes from `sport_players` (players are not users);
--    join `sport_teams` by team_id to expose the display team name.
-- 4. Tournament banner, alerts, live matches, today's matches, top scorers,
--    and standings come from the `sport_*` tournament tables above.
-- 5. Match API responses should format score text from home_score/away_score:
--    both NULL => "- - -", otherwise "{home_score} - {away_score}".
-- 6. Match result reports should map to sport_matches.result_report.
-- 7. Standings API responses should format positive goal_difference values with "+".
