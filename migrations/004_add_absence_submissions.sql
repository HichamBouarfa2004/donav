-- Migration: Add submission tracking to absences
-- Date: 2026-02-03
-- Description: Add statut column and submission history table

-- Add statut column if not exists
ALTER TABLE student_absences 
ADD COLUMN IF NOT EXISTS statut ENUM('present', 'absent', 'late', 'justified') DEFAULT 'absent' AFTER absence_date;

-- Create absence submissions table for tracking submission history
CREATE TABLE IF NOT EXISTS absence_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    submission_date DATE NOT NULL,
    total_students INT NOT NULL DEFAULT 0,
    present_count INT NOT NULL DEFAULT 0,
    absent_count INT NOT NULL DEFAULT 0,
    late_count INT NOT NULL DEFAULT 0,
    justified_count INT NOT NULL DEFAULT 0,
    submitted_by INT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes TEXT,
    UNIQUE KEY unique_submission (class_id, submission_date),
    INDEX idx_class_id (class_id),
    INDEX idx_submission_date (submission_date),
    INDEX idx_submitted_by (submitted_by),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES enseignants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create absence change log for tracking individual changes
CREATE TABLE IF NOT EXISTS absence_change_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    student_id INT NOT NULL,
    old_status VARCHAR(20),
    new_status VARCHAR(20) NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_submission_id (submission_id),
    INDEX idx_student_id (student_id),
    FOREIGN KEY (submission_id) REFERENCES absence_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES enseignants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
