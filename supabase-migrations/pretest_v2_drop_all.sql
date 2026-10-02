-- =============================================================================
-- Project Aruga — v2 PRETEST: REMOVE EVERYTHING (use only if v2 is rejected,
-- or after v2 has been moved into the live tables)
--
-- !! DESTRUCTIVE: deletes the whole "pretest" schema (all pretest tables and
--    data) and the four public.pretest_* functions.
-- !! Export anything you need first.
--
-- Live tables are not touched: nothing outside the pretest schema depends on it,
-- and the only objects removed outside it are the pretest_* functions below.
-- After running this, also remove api/pretest-router.php from the repo so the
-- Vercel function slot is freed.
-- =============================================================================

BEGIN;

DROP FUNCTION IF EXISTS public.pretest_submit_assessment(UUID, UUID, TEXT, JSONB);
DROP FUNCTION IF EXISTS public.pretest_logout(UUID, UUID);
DROP FUNCTION IF EXISTS public.pretest_check_session(UUID, UUID);
DROP FUNCTION IF EXISTS public.pretest_login(TEXT, TEXT, TEXT);

DROP SCHEMA IF EXISTS pretest CASCADE;

COMMIT;

NOTIFY pgrst, 'reload schema';
