-- ============================================================================
-- No9ati - Complete Database Schema
-- Database: no9ati_db
-- Engine: InnoDB | Charset: utf8mb4
-- Generated: 2026-02-16
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `no9ati_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `no9ati_db`;

-- ============================================================================
-- 1. ENSEIGNANTS (Teachers)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `enseignants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. YEAR_LEVELS (Teacher-defined year levels)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `year_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Display name like "1ère année", "Master 1", etc.',
  `short_name` varchar(20) DEFAULT NULL COMMENT 'Short code like "1A", "M1", etc.',
  `order_index` int(11) NOT NULL DEFAULT 0 COMMENT 'For sorting display',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_year_teacher` (`created_by`),
  CONSTRAINT `fk_year_teacher` FOREIGN KEY (`created_by`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. CLASSES (Groups)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `enseignant_id` int(11) NOT NULL,
  `year_level_id` int(11) DEFAULT NULL COMMENT 'Reference to year_levels table',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_class_teacher` (`enseignant_id`),
  KEY `fk_class_year_level` (`year_level_id`),
  CONSTRAINT `fk_class_teacher` FOREIGN KEY (`enseignant_id`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_class_year_level` FOREIGN KEY (`year_level_id`) REFERENCES `year_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. ELEVES (Students)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `eleves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `classe_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_student_class` (`classe_id`),
  CONSTRAINT `fk_student_class` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. STUDENT_ABSENCES
-- ============================================================================
CREATE TABLE IF NOT EXISTS `student_absences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `absence_date` date NOT NULL,
  `statut` enum('present','absent','late','justified') DEFAULT 'absent',
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_absence` (`student_id`, `class_id`, `absence_date`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_absence_date` (`absence_date`),
  CONSTRAINT `fk_absence_student` FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_absence_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. ABSENCE_SUBMISSIONS (Submission tracking history)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `absence_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `submission_date` date NOT NULL,
  `total_students` int(11) NOT NULL DEFAULT 0,
  `present_count` int(11) NOT NULL DEFAULT 0,
  `absent_count` int(11) NOT NULL DEFAULT 0,
  `late_count` int(11) NOT NULL DEFAULT 0,
  `justified_count` int(11) NOT NULL DEFAULT 0,
  `submitted_by` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission` (`class_id`, `submission_date`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_submission_date` (`submission_date`),
  KEY `idx_submitted_by` (`submitted_by`),
  CONSTRAINT `fk_submission_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submission_teacher` FOREIGN KEY (`submitted_by`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. ABSENCE_CHANGE_LOG (Individual change tracking)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `absence_change_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_submission_id` (`submission_id`),
  KEY `idx_student_id` (`student_id`),
  CONSTRAINT `fk_changelog_submission` FOREIGN KEY (`submission_id`) REFERENCES `absence_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_changelog_student` FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_changelog_teacher` FOREIGN KEY (`changed_by`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. TEAMS
-- ============================================================================
CREATE TABLE IF NOT EXISTS `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_team_class` (`class_id`),
  CONSTRAINT `fk_team_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. TEAM_MEMBERS
-- ============================================================================
CREATE TABLE IF NOT EXISTS `team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_per_class_team` (`student_id`),
  KEY `fk_member_team` (`team_id`),
  CONSTRAINT `fk_member_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_student` FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. CONTROLE_TEMPLATES (Structure definition per year level)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `controle_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `year_level_id` int(11) NOT NULL COMMENT 'Reference to year_levels table',
  `total_points` decimal(5,2) NOT NULL DEFAULT 20.00,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_year_level` (`year_level_id`),
  KEY `fk_template_teacher` (`created_by`),
  CONSTRAINT `fk_template_year_level` FOREIGN KEY (`year_level_id`) REFERENCES `year_levels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_template_teacher` FOREIGN KEY (`created_by`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. CONTROLE_TEMPLATE_PHASES (Phases within a template)
--     After migration 006: uses direct `points` instead of percentage/max_points
-- ============================================================================
CREATE TABLE IF NOT EXISTS `controle_template_phases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `points` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Direct point value for this phase',
  `grading_mode` enum('individual','team') NOT NULL DEFAULT 'individual'
    COMMENT 'individual=each student graded separately, team=all team members get same note',
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_phases_template` (`template_id`),
  CONSTRAINT `fk_phases_template` FOREIGN KEY (`template_id`) REFERENCES `controle_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. CONTROLE_INSTANCES (Apply template to a class for grading)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `controle_instances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `session_name` varchar(255) DEFAULT NULL COMMENT 'Optional name like "Session Normale 2026"',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Lock to prevent further edits',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_instance_template` (`template_id`),
  KEY `fk_instance_class` (`class_id`),
  KEY `fk_instance_teacher` (`created_by`),
  CONSTRAINT `fk_instance_template` FOREIGN KEY (`template_id`) REFERENCES `controle_templates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_instance_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_instance_teacher` FOREIGN KEY (`created_by`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 13. CONTROLE_NOTES_INDIVIDUAL (Notes for individual-graded phases)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `controle_notes_individual` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` int(11) NOT NULL,
  `phase_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `note` decimal(5,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_instance_phase_student` (`instance_id`, `phase_id`, `student_id`),
  KEY `fk_notes_ind_instance` (`instance_id`),
  KEY `fk_notes_ind_phase` (`phase_id`),
  KEY `fk_notes_ind_student` (`student_id`),
  CONSTRAINT `fk_notes_ind_instance` FOREIGN KEY (`instance_id`) REFERENCES `controle_instances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_ind_phase` FOREIGN KEY (`phase_id`) REFERENCES `controle_template_phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_ind_student` FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 14. CONTROLE_NOTES_TEAM (Notes for team-graded phases)
--     Note given to a team applies to ALL members of that team
-- ============================================================================
CREATE TABLE IF NOT EXISTS `controle_notes_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` int(11) NOT NULL,
  `phase_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `note` decimal(5,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_instance_phase_team` (`instance_id`, `phase_id`, `team_id`),
  KEY `fk_notes_team_instance` (`instance_id`),
  KEY `fk_notes_team_phase` (`phase_id`),
  KEY `fk_notes_team_team` (`team_id`),
  CONSTRAINT `fk_notes_team_instance` FOREIGN KEY (`instance_id`) REFERENCES `controle_instances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_team_phase` FOREIGN KEY (`phase_id`) REFERENCES `controle_template_phases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_team_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 15. CONTROLE_RESULTS (Cached final notes per student per instance)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `controle_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `final_note` decimal(5,2) NOT NULL COMMENT '-1 means not yet fully graded',
  `breakdown_json` text DEFAULT NULL COMMENT 'JSON with phase-by-phase breakdown',
  `computed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_instance_student` (`instance_id`, `student_id`),
  KEY `fk_results_instance` (`instance_id`),
  KEY `fk_results_student` (`student_id`),
  CONSTRAINT `fk_results_instance` FOREIGN KEY (`instance_id`) REFERENCES `controle_instances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_results_student` FOREIGN KEY (`student_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- VIEWS
-- ============================================================================

-- Templates with phase count and total points
CREATE OR REPLACE VIEW `v_controle_templates` AS
SELECT
  t.*,
  y.name AS year_level_name,
  COUNT(p.id) AS phase_count,
  COALESCE(SUM(p.points), 0) AS total_phase_points
FROM `controle_templates` t
LEFT JOIN `year_levels` y ON t.year_level_id = y.id
LEFT JOIN `controle_template_phases` p ON t.id = p.template_id
GROUP BY t.id;

-- Available templates for each class based on year level
CREATE OR REPLACE VIEW `v_class_available_templates` AS
SELECT
  c.id AS class_id,
  c.nom AS class_name,
  c.year_level_id,
  y.name AS year_level_name,
  t.id AS template_id,
  t.title AS template_title,
  t.total_points
FROM `classes` c
JOIN `year_levels` y ON c.year_level_id = y.id
JOIN `controle_templates` t ON c.year_level_id = t.year_level_id
WHERE t.is_active = 1;

SET FOREIGN_KEY_CHECKS = 1;
