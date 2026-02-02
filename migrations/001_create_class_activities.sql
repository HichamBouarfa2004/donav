-- Migration: Create class_activities table
-- Date: 2025-10-13
-- Description: Add per-class activities functionality

CREATE TABLE IF NOT EXISTS class_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_class_id (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some default activities for existing classes (optional)
-- You can remove this section if you prefer to start with empty activity lists
INSERT IGNORE INTO class_activities (class_id, title) 
SELECT id, 'Participation' FROM classes WHERE id > 0;

INSERT IGNORE INTO class_activities (class_id, title) 
SELECT id, 'Homework' FROM classes WHERE id > 0;

INSERT IGNORE INTO class_activities (class_id, title) 
SELECT id, 'Good Work' FROM classes WHERE id > 0;