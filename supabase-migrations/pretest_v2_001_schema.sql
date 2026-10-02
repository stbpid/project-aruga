-- =============================================================================
-- Project Aruga — v2 PRETEST: schema, tables and database functions
-- Phase 1 · run ONCE in Supabase → SQL Editor (safe to re-run)
--
-- What this does
--   * Creates a separate "pretest" schema. Pretest records never go into the
--     live (public) tables.
--   * Copies the STRUCTURE (not the data) of the live profiling tables, so the
--     pretest form asks exactly the same questions.
--   * Adds pretest-only tables: pretest_users, feedback, id_counters,
--     login_attempts.
--   * Adds public.pretest_* functions. These are the ONLY way into the pretest
--     schema, and only the server (service_role) may call them.
--
-- What this does NOT do
--   * It does not ALTER, INSERT, UPDATE or DELETE anything in a live table.
--     Live tables are only read: their column definitions (CREATE TABLE ... LIKE)
--     and public.interviewers at login, so testers use their usual Interviewer Code.
--   * No foreign keys point at live tables, so the live tables gain no new
--     dependencies.
--   * The pretest schema does not need to be added to "Exposed schemas" in the
--     Supabase API settings. Leave it unexposed.
-- =============================================================================

BEGIN;

CREATE SCHEMA IF NOT EXISTS pretest;
REVOKE ALL ON SCHEMA pretest FROM PUBLIC;
REVOKE ALL ON SCHEMA pretest FROM anon, authenticated;

-- -----------------------------------------------------------------------------
-- 1. Mirror the live profiling tables (same columns, types, defaults, checks)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pretest.sessions               (LIKE public.sessions               INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.assessments            (LIKE public.assessments            INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.pre_qualification      (LIKE public.pre_qualification      INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.respondents            (LIKE public.respondents            INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.children               (LIKE public.children               INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.child_education_health (LIKE public.child_education_health INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.family_members         (LIKE public.family_members         INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.socio_economic         (LIKE public.socio_economic         INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.health_info            (LIKE public.health_info            INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.education_info         (LIKE public.education_info         INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.economic_capacity      (LIKE public.economic_capacity      INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.service_availment      (LIKE public.service_availment      INCLUDING ALL);
CREATE TABLE IF NOT EXISTS pretest.assessment_notes       (LIKE public.assessment_notes       INCLUDING ALL);

-- Safety check: a copied column default must never draw numbers from a LIVE
-- sequence. If one does, stop and roll everything back.
DO $$
DECLARE r record;
BEGIN
  FOR r IN
    SELECT table_name, column_name, column_default
      FROM information_schema.columns
     WHERE table_schema = 'pretest'
       AND column_default ILIKE '%nextval(%'
  LOOP
    RAISE EXCEPTION 'pretest.%.% uses a live sequence (%). Aborting, nothing was created.',
      r.table_name, r.column_name, r.column_default;
  END LOOP;
END $$;

-- -----------------------------------------------------------------------------
-- 2. Pretest-only columns
-- -----------------------------------------------------------------------------
-- Session expiry (live sessions never expire; pretest sessions last 7 days).
ALTER TABLE pretest.sessions    ADD COLUMN IF NOT EXISTS expires_at           TIMESTAMPTZ;

-- Audit fields for the desktop app (also used by offline sync in Phase 3).
ALTER TABLE pretest.assessments ADD COLUMN IF NOT EXISTS created_on_device_at TIMESTAMPTZ;
ALTER TABLE pretest.assessments ADD COLUMN IF NOT EXISTS synced_at            TIMESTAMPTZ;
ALTER TABLE pretest.assessments ADD COLUMN IF NOT EXISTS app_version          TEXT;

-- Pretest IDs look like PRETEST-2026-R4A-0001, which may be longer than, or
-- not match the pattern of, the live ARUGA-... IDs. Relax the copied column only.
ALTER TABLE pretest.assessments ALTER COLUMN aruga_id TYPE TEXT;
DO $$
DECLARE c record;
BEGIN
  FOR c IN
    SELECT conname FROM pg_constraint
     WHERE conrelid = 'pretest.assessments'::regclass
       AND contype  = 'c'
       AND pg_get_constraintdef(oid) ILIKE '%aruga_id%'
  LOOP
    EXECUTE format('ALTER TABLE pretest.assessments DROP CONSTRAINT %I', c.conname);
  END LOOP;
END $$;

-- One pretest login (session) can save many profiles. If the live table enforces
-- one assessment per session, drop that rule on the pretest copy only.
DO $$
DECLARE c record;
BEGIN
  FOR c IN
    SELECT con.conname
      FROM pg_constraint con
     WHERE con.conrelid = 'pretest.assessments'::regclass
       AND con.contype  = 'u'
       AND con.conkey   = ARRAY[(SELECT attnum FROM pg_attribute
                                  WHERE attrelid = 'pretest.assessments'::regclass
                                    AND attname  = 'session_id')]::smallint[]
  LOOP
    EXECUTE format('ALTER TABLE pretest.assessments DROP CONSTRAINT %I', c.conname);
  END LOOP;
END $$;

-- -----------------------------------------------------------------------------
-- 3. Relationships inside the pretest schema only
-- -----------------------------------------------------------------------------
DO $$
DECLARE t text;
BEGIN
  FOREACH t IN ARRAY ARRAY[
    'pre_qualification','respondents','children','child_education_health',
    'family_members','socio_economic','health_info','education_info',
    'economic_capacity','service_availment','assessment_notes'
  ] LOOP
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pt_' || t || '_assessment_fk') THEN
      EXECUTE format(
        'ALTER TABLE pretest.%I ADD CONSTRAINT %I FOREIGN KEY (assessment_id)
           REFERENCES pretest.assessments(id) ON DELETE CASCADE',
        t, 'pt_' || t || '_assessment_fk');
    END IF;
  END LOOP;

  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'pt_child_education_health_child_fk') THEN
    ALTER TABLE pretest.child_education_health
      ADD CONSTRAINT pt_child_education_health_child_fk
      FOREIGN KEY (child_id) REFERENCES pretest.children(id) ON DELETE CASCADE;
  END IF;
END $$;

-- -----------------------------------------------------------------------------
-- 4. Pretest-only tables
-- -----------------------------------------------------------------------------
-- Who may use the pretest app. Links to the tester's existing interviewer
-- account (same Interviewer Code). No personal details are copied, and there is
-- deliberately no foreign key, so public.interviewers is untouched.
CREATE TABLE IF NOT EXISTS pretest.pretest_users (
  interviewer_id UUID        PRIMARY KEY,
  is_active      BOOLEAN     NOT NULL DEFAULT TRUE,
  added_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  added_by       TEXT,
  notes          TEXT
);

-- Tester comments ("this question is confusing", "dropdown missing X", ...).
CREATE TABLE IF NOT EXISTS pretest.feedback (
  id             UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  assessment_id  UUID        NOT NULL REFERENCES pretest.assessments(id) ON DELETE CASCADE,
  interviewer_id UUID        NOT NULL,
  step           SMALLINT    CHECK (step BETWEEN 1 AND 11),
  comment        TEXT        NOT NULL CHECK (char_length(comment) BETWEEN 1 AND 2000),
  created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS pt_feedback_assessment_idx ON pretest.feedback (assessment_id);

-- PRETEST-YYYY-REGION-NNNN numbering, separate from live ARUGA IDs.
CREATE TABLE IF NOT EXISTS pretest.id_counters (
  region_code TEXT    NOT NULL,
  year        INTEGER NOT NULL,
  last_value  INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (region_code, year)
);

-- Failed-login rate limiting (kept here so live audit_logs is not written to).
CREATE TABLE IF NOT EXISTS pretest.login_attempts (
  id               BIGINT      GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  ip_address       TEXT,
  interviewer_code TEXT,
  success          BOOLEAN     NOT NULL,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS pt_login_attempts_ip_idx ON pretest.login_attempts (ip_address, created_at);

-- -----------------------------------------------------------------------------
-- 5. Lock every pretest table: RLS on, no policies, no grants to anon or
--    authenticated users. Only the functions below (owned by postgres) can
--    read or write.
-- -----------------------------------------------------------------------------
DO $$
DECLARE t text;
BEGIN
  FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'pretest' LOOP
    EXECUTE format('ALTER TABLE pretest.%I ENABLE ROW LEVEL SECURITY', t);
    EXECUTE format('REVOKE ALL ON pretest.%I FROM PUBLIC, anon, authenticated', t);
  END LOOP;
END $$;

-- -----------------------------------------------------------------------------
-- 6. Functions (called only by api/pretest-router.php)
-- -----------------------------------------------------------------------------

-- 6a. Login with Interviewer Code. Must be an active interviewer AND an active
--     pretest user. Creates a pretest session valid for 7 days.
CREATE OR REPLACE FUNCTION public.pretest_login(p_code TEXT, p_ip TEXT, p_user_agent TEXT)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  v_code    TEXT := upper(btrim(coalesce(p_code, '')));
  v_fails   INTEGER;
  v_int     public.interviewers%ROWTYPE;
  v_session pretest.sessions%ROWTYPE;
BEGIN
  SELECT count(*) INTO v_fails
    FROM pretest.login_attempts
   WHERE ip_address = p_ip
     AND success = FALSE
     AND created_at > now() - INTERVAL '15 minutes';
  IF v_fails >= 5 THEN
    RETURN jsonb_build_object('ok', FALSE, 'error', 'rate_limited');
  END IF;

  IF v_code !~ '^[A-Z0-9]{8}$' THEN
    RETURN jsonb_build_object('ok', FALSE, 'error', 'invalid_format');
  END IF;

  SELECT i.* INTO v_int
    FROM public.interviewers i
    JOIN pretest.pretest_users p ON p.interviewer_id = i.id AND p.is_active
   WHERE i.interviewer_code = v_code
     AND i.status = 'active'
   LIMIT 1;

  IF NOT FOUND THEN
    INSERT INTO pretest.login_attempts (ip_address, interviewer_code, success)
    VALUES (p_ip, v_code, FALSE);
    RETURN jsonb_build_object('ok', FALSE, 'error', 'invalid');
  END IF;

  INSERT INTO pretest.login_attempts (ip_address, interviewer_code, success)
  VALUES (p_ip, v_code, TRUE);

  -- jsonb_populate_record converts each value to the copied column's exact type.
  INSERT INTO pretest.sessions (interviewer_id, interviewer_code, started_at, status, ip_address, user_agent, expires_at)
  SELECT r.interviewer_id, r.interviewer_code, r.started_at, r.status, r.ip_address, r.user_agent, r.expires_at
    FROM jsonb_populate_record(NULL::pretest.sessions, jsonb_build_object(
           'interviewer_id',   v_int.id,
           'interviewer_code', v_int.interviewer_code,
           'started_at',       now(),
           'status',           'active',
           'ip_address',       p_ip,
           'user_agent',       left(p_user_agent, 500),
           'expires_at',       now() + INTERVAL '7 days')) r
  RETURNING * INTO v_session;

  RETURN jsonb_build_object(
    'ok',          TRUE,
    'session_id',  v_session.id,
    'started_at',  v_session.started_at,
    'expires_at',  v_session.expires_at,
    'interviewer', jsonb_build_object(
      'id',               v_int.id,
      'interviewer_code', v_int.interviewer_code,
      'full_name',        v_int.full_name,
      'region',           v_int.region,
      'province',         v_int.province,
      'office',           v_int.office,
      'position',         v_int.position));
END $$;

-- 6b. Is this session still valid? Returns the interviewer, or NULL.
CREATE OR REPLACE FUNCTION public.pretest_check_session(p_session_id UUID, p_interviewer_id UUID)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE v JSONB;
BEGIN
  SELECT jsonb_build_object(
           'id',               i.id,
           'interviewer_code', i.interviewer_code,
           'full_name',        i.full_name,
           'region',           i.region,
           'province',         i.province,
           'office',           i.office,
           'position',         i.position,
           'expires_at',       s.expires_at)
    INTO v
    FROM pretest.sessions s
    JOIN public.interviewers i  ON i.id = s.interviewer_id AND i.status = 'active'
    JOIN pretest.pretest_users p ON p.interviewer_id = i.id AND p.is_active
   WHERE s.id = p_session_id
     AND s.interviewer_id = p_interviewer_id
     AND s.status = 'active'
     AND (s.expires_at IS NULL OR s.expires_at > now());
  RETURN v;
END $$;

-- 6c. End a session.
CREATE OR REPLACE FUNCTION public.pretest_logout(p_session_id UUID, p_interviewer_id UUID)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
BEGIN
  UPDATE pretest.sessions
     SET status = 'completed',
         ended_at = now(),
         duration_seconds = extract(epoch FROM now() - started_at)::INTEGER
   WHERE id = p_session_id
     AND interviewer_id = p_interviewer_id
     AND status = 'active';
  RETURN FOUND;
END $$;

-- 6d. Save one complete profile in ONE transaction: either every table is
--     written or none is. Idempotent on the device-generated assessment_id, so
--     sending the same profile twice never creates a duplicate.
CREATE OR REPLACE FUNCTION public.pretest_submit_assessment(
  p_session_id     UUID,
  p_interviewer_id UUID,
  p_region_code    TEXT,
  p_data           JSONB
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  v_int      JSONB;
  v_id       UUID;
  v_existing RECORD;
  v_rc       TEXT := CASE WHEN p_region_code ~ '^[A-Z0-9]{1,6}$' THEN p_region_code ELSE 'XX' END;
  v_year     INTEGER := extract(year FROM now())::INTEGER;
  v_n        INTEGER;
  v_aruga    TEXT;
  v_child_id UUID;
BEGIN
  v_int := public.pretest_check_session(p_session_id, p_interviewer_id);
  IF v_int IS NULL THEN
    RETURN jsonb_build_object('ok', FALSE, 'error', 'session_invalid');
  END IF;

  BEGIN
    v_id := (p_data->>'assessment_id')::UUID;
  EXCEPTION WHEN others THEN
    v_id := NULL;
  END;
  IF v_id IS NULL THEN
    RETURN jsonb_build_object('ok', FALSE, 'error', 'invalid_assessment_id');
  END IF;

  -- Already uploaded? Return the original result instead of a duplicate.
  SELECT id, interviewer_id, aruga_id INTO v_existing
    FROM pretest.assessments WHERE id = v_id;
  IF FOUND THEN
    IF v_existing.interviewer_id = p_interviewer_id THEN
      RETURN jsonb_build_object('ok', TRUE, 'duplicate', TRUE,
                                'assessment_id', v_existing.id, 'aruga_id', v_existing.aruga_id);
    END IF;
    RETURN jsonb_build_object('ok', FALSE, 'error', 'id_conflict');
  END IF;

  INSERT INTO pretest.id_counters (region_code, year, last_value)
  VALUES (v_rc, v_year, 1)
  ON CONFLICT (region_code, year)
  DO UPDATE SET last_value = pretest.id_counters.last_value + 1
  RETURNING last_value INTO v_n;
  v_aruga := format('PRETEST-%s-%s-%s', v_year, v_rc, lpad(v_n::TEXT, 4, '0'));

  INSERT INTO pretest.assessments (
    id, session_id, interviewer_id, interviewer_code, privacy_accepted, privacy_accepted_at,
    current_step, status, readiness_score, aruga_id, completed_at, submitted_at,
    created_on_device_at, synced_at, app_version)
  SELECT r.id, r.session_id, r.interviewer_id, r.interviewer_code, r.privacy_accepted, r.privacy_accepted_at,
         r.current_step, r.status, r.readiness_score, r.aruga_id, r.completed_at, r.submitted_at,
         r.created_on_device_at, r.synced_at, r.app_version
    FROM jsonb_populate_record(NULL::pretest.assessments, jsonb_build_object(
           'id',                   v_id,
           'session_id',           p_session_id,
           'interviewer_id',       p_interviewer_id,
           'interviewer_code',     v_int->>'interviewer_code',
           'privacy_accepted',     TRUE,
           'privacy_accepted_at',  now(),
           'current_step',         11,
           'status',               'completed',
           'readiness_score',      p_data->>'readiness_score',
           'aruga_id',             v_aruga,
           'completed_at',         now(),
           'submitted_at',         now(),
           'created_on_device_at', p_data->>'created_on_device_at',
           'synced_at',            now(),
           'app_version',          p_data->>'app_version')) r;

  -- Step 1
  INSERT INTO pretest.pre_qualification (assessment_id, is_4ps_member, household_id)
  SELECT v_id, r.is_4ps_member, r.household_id
    FROM jsonb_populate_record(NULL::pretest.pre_qualification, coalesce(p_data->'pre_qualification', '{}')) r;

  -- Step 2
  INSERT INTO pretest.respondents (assessment_id, full_name, relationship_to_child, email, contact_number)
  SELECT v_id, r.full_name, r.relationship_to_child, r.email, r.contact_number
    FROM jsonb_populate_record(NULL::pretest.respondents, coalesce(p_data->'respondent', '{}')) r;

  -- Step 3a
  INSERT INTO pretest.children (
    assessment_id, first_name, middle_name, last_name, name_extension, region, province,
    city_municipality, barangay, street_address, contact_number, date_of_birth, sex,
    religion, religion_other, ip_membership, ip_membership_other)
  SELECT v_id, r.first_name, r.middle_name, r.last_name, r.name_extension, r.region, r.province,
         r.city_municipality, r.barangay, r.street_address, r.contact_number, r.date_of_birth, r.sex,
         r.religion, r.religion_other, r.ip_membership, r.ip_membership_other
    FROM jsonb_populate_record(NULL::pretest.children, coalesce(p_data->'child', '{}')) r
  RETURNING id INTO v_child_id;

  -- Step 3b
  INSERT INTO pretest.child_education_health (
    assessment_id, child_id, highest_education, highest_education_other,
    disabilities, critical_illnesses, illness_other)
  SELECT v_id, v_child_id, r.highest_education, r.highest_education_other,
         r.disabilities, r.critical_illnesses, r.illness_other
    FROM jsonb_populate_record(NULL::pretest.child_education_health, coalesce(p_data->'child_education_health', '{}')) r;

  -- Step 4 (one row per family member)
  INSERT INTO pretest.family_members (
    assessment_id, member_number, full_name, relationship_to_head, is_solo_parent,
    is_authorized_claimant, civil_status, age, sex, occupation, occupation_class,
    disabilities, critical_illnesses)
  SELECT v_id, r.member_number, r.full_name, r.relationship_to_head, r.is_solo_parent,
         r.is_authorized_claimant, r.civil_status, r.age, r.sex, r.occupation, r.occupation_class,
         r.disabilities, r.critical_illnesses
    FROM jsonb_populate_recordset(NULL::pretest.family_members, coalesce(p_data->'family_members', '[]')) r;

  -- Step 5
  INSERT INTO pretest.socio_economic (
    assessment_id, housing_materials, housing_materials_other, tenure_status, tenure_status_other,
    has_accessibility_modifications, modification_details, electricity_source, electricity_source_other,
    water_source, water_source_other, toilet_type, toilet_type_other, is_toilet_accessible,
    garbage_disposal, garbage_disposal_other)
  SELECT v_id, r.housing_materials, r.housing_materials_other, r.tenure_status, r.tenure_status_other,
         r.has_accessibility_modifications, r.modification_details, r.electricity_source, r.electricity_source_other,
         r.water_source, r.water_source_other, r.toilet_type, r.toilet_type_other, r.is_toilet_accessible,
         r.garbage_disposal, r.garbage_disposal_other
    FROM jsonb_populate_record(NULL::pretest.socio_economic, coalesce(p_data->'socio_economic', '{}')) r;

  -- Step 6 (expense_total is a generated column, so it is left out)
  INSERT INTO pretest.health_info (
    assessment_id, has_all_vaccinations, has_ongoing_health_conditions, health_conditions_details,
    expense_food, expense_medication, expense_therapy, expense_hygiene, expense_assistive_device,
    expense_other, availed_services_6months, availed_services_details, is_facility_accessible,
    has_barriers_to_healthcare, healthcare_barriers_details)
  SELECT v_id, r.has_all_vaccinations, r.has_ongoing_health_conditions, r.health_conditions_details,
         r.expense_food, r.expense_medication, r.expense_therapy, r.expense_hygiene, r.expense_assistive_device,
         r.expense_other, r.availed_services_6months, r.availed_services_details, r.is_facility_accessible,
         r.has_barriers_to_healthcare, r.healthcare_barriers_details
    FROM jsonb_populate_record(NULL::pretest.health_info, coalesce(p_data->'health_info', '{}')) r;

  -- Step 7
  INSERT INTO pretest.education_info (
    assessment_id, is_currently_enrolled, grade_year_level, not_enrolled_reason,
    has_accessibility_features, accessibility_features_details, has_sped_programs,
    sped_programs_details, receives_learning_support, learning_support_details)
  SELECT v_id, r.is_currently_enrolled, r.grade_year_level, r.not_enrolled_reason,
         r.has_accessibility_features, r.accessibility_features_details, r.has_sped_programs,
         r.sped_programs_details, r.receives_learning_support, r.learning_support_details
    FROM jsonb_populate_record(NULL::pretest.education_info, coalesce(p_data->'education_info', '{}')) r;

  -- Step 8
  INSERT INTO pretest.economic_capacity (
    assessment_id, primary_income_source, monthly_income, income_classification,
    are_parents_employed, employment_details)
  SELECT v_id, r.primary_income_source, r.monthly_income, r.income_classification,
         r.are_parents_employed, r.employment_details
    FROM jsonb_populate_record(NULL::pretest.economic_capacity, coalesce(p_data->'economic_capacity', '{}')) r;

  -- Step 9
  INSERT INTO pretest.service_availment (
    assessment_id, receives_financial_assistance, financial_assistance_details,
    is_aware_of_social_services, awareness_details, has_availed_services,
    availed_services_details, service_challenges, service_challenges_other)
  SELECT v_id, r.receives_financial_assistance, r.financial_assistance_details,
         r.is_aware_of_social_services, r.awareness_details, r.has_availed_services,
         r.availed_services_details, r.service_challenges, r.service_challenges_other
    FROM jsonb_populate_record(NULL::pretest.service_availment, coalesce(p_data->'service_availment', '{}')) r;

  -- Step 10
  INSERT INTO pretest.assessment_notes (
    assessment_id, strengths, assessment_details, recommended_actions, readiness_score)
  SELECT v_id, r.strengths, r.assessment_details, r.recommended_actions, r.readiness_score
    FROM jsonb_populate_record(NULL::pretest.assessment_notes, coalesce(p_data->'assessment_notes', '{}')) r;

  -- Tester feedback
  INSERT INTO pretest.feedback (assessment_id, interviewer_id, step, comment)
  SELECT v_id, p_interviewer_id, NULLIF(f->>'step', '')::SMALLINT, btrim(f->>'comment')
    FROM jsonb_array_elements(coalesce(p_data->'feedback', '[]')) f
   WHERE coalesce(btrim(f->>'comment'), '') <> '';

  RETURN jsonb_build_object('ok', TRUE, 'duplicate', FALSE,
                            'assessment_id', v_id, 'aruga_id', v_aruga);
END $$;

-- Only the server may call these functions.
REVOKE ALL ON FUNCTION public.pretest_login(TEXT, TEXT, TEXT)                         FROM PUBLIC, anon, authenticated;
REVOKE ALL ON FUNCTION public.pretest_check_session(UUID, UUID)                       FROM PUBLIC, anon, authenticated;
REVOKE ALL ON FUNCTION public.pretest_logout(UUID, UUID)                              FROM PUBLIC, anon, authenticated;
REVOKE ALL ON FUNCTION public.pretest_submit_assessment(UUID, UUID, TEXT, JSONB)      FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.pretest_login(TEXT, TEXT, TEXT)                      TO service_role;
GRANT EXECUTE ON FUNCTION public.pretest_check_session(UUID, UUID)                    TO service_role;
GRANT EXECUTE ON FUNCTION public.pretest_logout(UUID, UUID)                           TO service_role;
GRANT EXECUTE ON FUNCTION public.pretest_submit_assessment(UUID, UUID, TEXT, JSONB)   TO service_role;

COMMIT;

-- Make the API see the new functions immediately.
NOTIFY pgrst, 'reload schema';

-- -----------------------------------------------------------------------------
-- Check: every pretest table should show rls_enabled = true.
-- -----------------------------------------------------------------------------
SELECT tablename, rowsecurity AS rls_enabled
  FROM pg_tables
 WHERE schemaname = 'pretest'
 ORDER BY tablename;
