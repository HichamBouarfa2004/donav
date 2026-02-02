-- Migration: Add activity tracking to points
-- Date: 2025-10-13
-- Description: Add activity_id to journal_points to track points per activity

-- Add activity_id column to journal_points table
ALTER TABLE journal_points ADD COLUMN activity_id INT NULL AFTER eleve_id;

-- Add index for better performance
CREATE INDEX idx_activity_points ON journal_points(eleve_id, activity_id);

-- Update existing points to have no specific activity (NULL means general points)
-- Existing points will be considered as "general participation" points