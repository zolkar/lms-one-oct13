-- SQL script to create missing mdl_local_aicc_hacp_sessions table
-- Run this in MySQL

USE lms_one;

CREATE TABLE IF NOT EXISTS mdl_local_aicc_hacp_sessions (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(100) NOT NULL,
    scormid INT(10) NOT NULL,
    scoid INT(10) NOT NULL,
    student_id VARCHAR(100) NOT NULL,
    origin VARCHAR(255) DEFAULT NULL,
    persistent_session_id INT(10) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at INT(10) NOT NULL,
    last_activity_at INT(10) NOT NULL,
    expires_at INT(10) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY session_id_uk (session_id),
    KEY student_scorm_idx (student_id, scormid, scoid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
