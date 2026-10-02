-- =============================================================================
-- Project Aruga — v2 PRETEST: WIPE PRETEST DATA (keeps the tables)
--
-- !! DESTRUCTIVE: permanently deletes every pretest profile, session, feedback
--    entry and login attempt, and restarts PRETEST ID numbering at 0001.
-- !! Export anything you need first (Table Editor → pretest schema → Export).
--
-- Touches ONLY tables in the "pretest" schema. No live (public) table is
-- named here, so live data cannot be affected.
-- Tester list (pretest.pretest_users) is KEPT, so testers can continue.
-- =============================================================================

BEGIN;

TRUNCATE TABLE
  pretest.feedback,
  pretest.assessment_notes,
  pretest.service_availment,
  pretest.economic_capacity,
  pretest.education_info,
  pretest.health_info,
  pretest.socio_economic,
  pretest.family_members,
  pretest.child_education_health,
  pretest.children,
  pretest.respondents,
  pretest.pre_qualification,
  pretest.assessments,
  pretest.sessions,
  pretest.login_attempts,
  pretest.id_counters;

-- Uncomment to also remove all testers:
-- TRUNCATE TABLE pretest.pretest_users;

COMMIT;

-- Check: should be 0.
SELECT count(*) AS remaining_pretest_profiles FROM pretest.assessments;
