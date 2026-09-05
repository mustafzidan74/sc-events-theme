-- =============================================
-- SQL Script: Drop Removed Module Tables
--
-- This script removes all database tables for the
-- following removed modules:
-- 1. Sessions (4 tables)
-- 2. Venues (5 tables)
-- 3. Staff (3 tables)
-- 4. Crowd Analytics (2 tables)
-- 5. Emergency (6 tables)
--
-- Total: 20 tables
--
-- IMPORTANT: Run this ONLY after confirming all code
-- references have been removed and you have a backup!
--
-- Database prefix: scev_ (change if different)
-- =============================================

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- Sessions Module Tables (4)
-- =============================================
DROP TABLE IF EXISTS scev_session_registrations;
DROP TABLE IF EXISTS scev_session_attendance;
DROP TABLE IF EXISTS scev_session_speakers;
DROP TABLE IF EXISTS scev_sessions;

-- =============================================
-- Venues Module Tables (5)
-- Order matters due to foreign key dependencies
-- =============================================
DROP TABLE IF EXISTS scev_gate_logs;
DROP TABLE IF EXISTS scev_zone_counts;
DROP TABLE IF EXISTS scev_gates;
DROP TABLE IF EXISTS scev_zones;
DROP TABLE IF EXISTS scev_venues;

-- =============================================
-- Staff Module Tables (3)
-- =============================================
DROP TABLE IF EXISTS scev_staff_assignments;
DROP TABLE IF EXISTS scev_staff_shifts;
DROP TABLE IF EXISTS scev_staff;

-- =============================================
-- Crowd Analytics Module Tables (2)
-- =============================================
DROP TABLE IF EXISTS scev_crowd_predictions;
DROP TABLE IF EXISTS scev_crowd_logs;

-- =============================================
-- Emergency Module Tables (6)
-- Order matters due to foreign key dependencies
-- =============================================
DROP TABLE IF EXISTS scev_evacuation_logs;
DROP TABLE IF EXISTS scev_evacuations;
DROP TABLE IF EXISTS scev_emergency_logs;
DROP TABLE IF EXISTS scev_emergency_protocols;
DROP TABLE IF EXISTS scev_emergency_incidents;
DROP TABLE IF EXISTS scev_assembly_points;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- Verification Query
-- Run this to verify all tables have been dropped
-- =============================================
-- SELECT TABLE_NAME
-- FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE()
-- AND TABLE_NAME LIKE 'scev_%'
-- AND TABLE_NAME IN (
--     'scev_sessions', 'scev_session_speakers',
--     'scev_session_attendance', 'scev_session_registrations',
--     'scev_venues', 'scev_zones', 'scev_gates',
--     'scev_zone_counts', 'scev_gate_logs',
--     'scev_staff', 'scev_staff_shifts', 'scev_staff_assignments',
--     'scev_crowd_logs', 'scev_crowd_predictions',
--     'scev_emergency_incidents', 'scev_emergency_protocols',
--     'scev_emergency_logs', 'scev_evacuations',
--     'scev_evacuation_logs', 'scev_assembly_points'
-- );
-- Should return 0 rows if successful

-- =============================================
-- Summary
-- =============================================
-- Tables dropped:
--
-- Sessions:
--   - scev_sessions
--   - scev_session_speakers
--   - scev_session_attendance
--   - scev_session_registrations
--
-- Venues:
--   - scev_venues
--   - scev_zones
--   - scev_gates
--   - scev_zone_counts
--   - scev_gate_logs
--
-- Staff:
--   - scev_staff
--   - scev_staff_shifts
--   - scev_staff_assignments
--
-- Crowd:
--   - scev_crowd_logs
--   - scev_crowd_predictions
--
-- Emergency:
--   - scev_emergency_incidents
--   - scev_emergency_protocols
--   - scev_emergency_logs
--   - scev_evacuations
--   - scev_evacuation_logs
--   - scev_assembly_points
-- =============================================
