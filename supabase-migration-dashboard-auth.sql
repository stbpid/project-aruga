-- ================================================================
-- Project Aruga — Add dashboard auth columns to interviewers table
-- Run this in Supabase SQL Editor
-- ================================================================

ALTER TABLE interviewers
  ADD COLUMN IF NOT EXISTS email         TEXT,
  ADD COLUMN IF NOT EXISTS password_hash TEXT,
  ADD COLUMN IF NOT EXISTS dashboard_role TEXT CHECK (
    dashboard_role IN ('admin', 'central', 'stu_head', 'field_officer')
  );

-- Unique index on email (only for rows that have one set)
CREATE UNIQUE INDEX IF NOT EXISTS interviewers_email_unique
  ON interviewers (email)
  WHERE email IS NOT NULL;

-- ================================================================
-- To set a password for an existing interviewer, run:
-- (replace the email, role, and password hash accordingly)
--
-- You CANNOT store plain text passwords. Generate a bcrypt hash first.
-- Use: https://bcrypt-generator.com  (cost factor 10 or 12)
--
-- Example:
-- UPDATE interviewers
-- SET email         = 'admin@dswd.gov.ph',
--     password_hash = '$2y$12$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
--     dashboard_role = 'admin'
-- WHERE interviewer_code = 'YOURCODE';
-- ================================================================
