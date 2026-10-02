-- =============================================================================
-- Project Aruga — v2 PRETEST: add testers
-- Run in Supabase → SQL Editor AFTER pretest_v2_001_schema.sql
--
-- Testers log in with their EXISTING Interviewer Code. This file only records
-- WHO may use the pretest app. Names, emails and other details are NOT copied;
-- they stay in public.interviewers, which this file only reads.
--
-- Plan: about 3 testers per region.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- OPTION A (recommended): add specific testers by Interviewer Code.
-- Replace the sample codes, then run.
-- -----------------------------------------------------------------------------
INSERT INTO pretest.pretest_users (interviewer_id, added_by, notes)
SELECT i.id, 'seed script', 'Pretest tester'
  FROM public.interviewers i
 WHERE i.status = 'active'
   AND i.interviewer_code IN (
     'AAAA1111',   -- replace with real codes, e.g. 3 per region
     'BBBB2222'
   )
ON CONFLICT (interviewer_id) DO UPDATE SET is_active = TRUE;

-- -----------------------------------------------------------------------------
-- OPTION B: make EVERY active interviewer a tester.
-- Only use this if everyone should be able to open the pretest app.
-- Remove the leading "--" from the next 4 lines to use it.
-- -----------------------------------------------------------------------------
-- INSERT INTO pretest.pretest_users (interviewer_id, added_by, notes)
-- SELECT i.id, 'seed script', 'All active interviewers'
--   FROM public.interviewers i WHERE i.status = 'active'
-- ON CONFLICT (interviewer_id) DO UPDATE SET is_active = TRUE;

-- -----------------------------------------------------------------------------
-- Remove a tester's access (keeps their submitted pretest records):
-- UPDATE pretest.pretest_users SET is_active = FALSE
--  WHERE interviewer_id = (SELECT id FROM public.interviewers WHERE interviewer_code = 'AAAA1111');
-- -----------------------------------------------------------------------------

-- Check: current testers per region.
SELECT i.region, i.interviewer_code, i.full_name, p.is_active, p.added_at
  FROM pretest.pretest_users p
  JOIN public.interviewers i ON i.id = p.interviewer_id
 ORDER BY i.region, i.full_name;
