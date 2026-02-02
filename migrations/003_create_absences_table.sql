-- Migration: Create student_absences table
-- Date: 2026-02-02
-- Description: Add absence tracking functionality for students

CREATE TABLE IF NOT EXISTS student_absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    absence_date DATE NOT NULL,
    reason VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_absence (student_id, class_id, absence_date),
    INDEX idx_student_id (student_id),
    INDEX idx_class_id (class_id),
    INDEX idx_absence_date (absence_date),
    FOREIGN KEY (student_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
