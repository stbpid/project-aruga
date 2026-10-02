-- =============================================================================
-- Project Aruga — v2 PRETEST: SELF-TEST (leaves NO data behind)
-- Run in Supabase → SQL Editor after pretest_v2_001_schema.sql.
--
-- Uses a sample (fake) family and tries a pretest login, a full profile save,
-- and a duplicate upload, then UNDOES EVERYTHING.
--
-- How to read the result: the script always ends with a red "ERROR" on purpose,
-- because raising an error is what undoes the changes.
--   * Message starts with "SELFTEST PASSED" → everything works.
--   * Any other message → copy it and send it to the developer.
--
-- It borrows one active interviewer account for the login test, only inside
-- this undone test. No live table is written to. The test also checks that the
-- live assessments table did not change.
-- =============================================================================

DO $$
DECLARE
  v_int_id      UUID;
  v_code        TEXT;
  v_login       JSONB;
  v_first       JSONB;
  v_again       JSONB;
  v_record_id   UUID := gen_random_uuid();
  v_live_before BIGINT;
  v_live_after  BIGINT;
  v_members     INTEGER;
  v_feedback    INTEGER;
  v_tables      INTEGER;
  v_payload     JSONB;
BEGIN
  SELECT id, interviewer_code INTO v_int_id, v_code
    FROM public.interviewers
   WHERE status = 'active' AND interviewer_code ~ '^[A-Z0-9]{8}$'
   LIMIT 1;
  IF v_int_id IS NULL THEN
    RAISE EXCEPTION 'SELFTEST FAILED: no active interviewer to test with';
  END IF;

  INSERT INTO pretest.pretest_users (interviewer_id, added_by, notes)
  VALUES (v_int_id, 'selftest', 'temporary, undone')
  ON CONFLICT (interviewer_id) DO UPDATE SET is_active = TRUE;

  SELECT count(*) INTO v_live_before FROM public.assessments;

  -- 1. Login
  v_login := public.pretest_login(v_code, '127.0.0.1', 'pretest selftest');
  IF NOT coalesce((v_login->>'ok')::BOOLEAN, FALSE) THEN
    RAISE EXCEPTION 'SELFTEST FAILED at login: %', v_login;
  END IF;
  IF public.pretest_check_session((v_login->>'session_id')::UUID, v_int_id) IS NULL THEN
    RAISE EXCEPTION 'SELFTEST FAILED: new session is not recognised';
  END IF;

  -- 2. Full sample profile (fake data, same shape the API sends)
  v_payload := jsonb_build_object(
    'assessment_id',        v_record_id,
    'readiness_score',      'moderate',
    'created_on_device_at', now() - INTERVAL '5 minutes',
    'app_version',          '0.1.0-selftest',
    'pre_qualification', jsonb_build_object('is_4ps_member', TRUE, 'household_id', '1234567890123'),
    'respondent', jsonb_build_object(
      'full_name', 'Test Respondent', 'relationship_to_child', 'Parent',
      'email', NULL, 'contact_number', '09171234567'),
    'child', jsonb_build_object(
      'first_name', 'Test', 'middle_name', NULL, 'last_name', 'Child', 'name_extension', NULL,
      'region', 'Region IV-A (CALABARZON)', 'province', 'Laguna', 'city_municipality', 'Biñan City',
      'barangay', 'Biñan', 'street_address', '123 Sample Street', 'contact_number', NULL,
      'date_of_birth', '2018-05-20', 'sex', 'Male', 'religion', 'Roman Catholic', 'religion_other', NULL,
      'ip_membership', 'Not a member', 'ip_membership_other', NULL),
    'child_education_health', jsonb_build_object(
      'highest_education', 'Elementary Undergraduate', 'highest_education_other', NULL,
      'disabilities', jsonb_build_array('Visual Disability'), 'critical_illnesses', jsonb_build_array('None'),
      'illness_other', NULL),
    'family_members', jsonb_build_array(
      jsonb_build_object('member_number', 1, 'full_name', 'Test Head', 'relationship_to_head', 'Head',
        'is_solo_parent', FALSE, 'is_authorized_claimant', TRUE, 'civil_status', 'Married', 'age', 38,
        'sex', 'Female', 'occupation', 'Unemployed', 'occupation_class', 'Clerical',
        'disabilities', jsonb_build_array('None'), 'critical_illnesses', jsonb_build_array('None')),
      jsonb_build_object('member_number', 2, 'full_name', 'Test Spouse', 'relationship_to_head', 'Spouse',
        'is_solo_parent', FALSE, 'is_authorized_claimant', FALSE, 'civil_status', 'Married', 'age', 40,
        'sex', 'Male', 'occupation', 'Employed (Private Sector)', 'occupation_class', 'Technical',
        'disabilities', jsonb_build_array('None'), 'critical_illnesses', jsonb_build_array('None'))),
    'socio_economic', jsonb_build_object(
      'housing_materials', 'Strong materials (Concrete, brick, stone)', 'housing_materials_other', NULL,
      'tenure_status', 'Own house and lot', 'tenure_status_other', NULL,
      'has_accessibility_modifications', FALSE, 'modification_details', NULL,
      'electricity_source', 'Electricity from distribution company', 'electricity_source_other', NULL,
      'water_source', 'Own use, faucet, community water system', 'water_source_other', NULL,
      'toilet_type', 'Water-sealed, sewer/septic tank, used exclusively by household', 'toilet_type_other', NULL,
      'is_toilet_accessible', TRUE, 'garbage_disposal', 'Picked up by garbage truck', 'garbage_disposal_other', NULL),
    'health_info', jsonb_build_object(
      'has_all_vaccinations', TRUE, 'has_ongoing_health_conditions', FALSE, 'health_conditions_details', NULL,
      'expense_food', 3000, 'expense_medication', 500, 'expense_therapy', 0, 'expense_hygiene', 200,
      'expense_assistive_device', 0, 'expense_other', 0, 'availed_services_6months', FALSE,
      'availed_services_details', NULL, 'is_facility_accessible', TRUE, 'has_barriers_to_healthcare', FALSE,
      'healthcare_barriers_details', NULL),
    'education_info', jsonb_build_object(
      'is_currently_enrolled', TRUE, 'grade_year_level', 'Grade 1', 'not_enrolled_reason', NULL,
      'has_accessibility_features', FALSE, 'accessibility_features_details', NULL,
      'has_sped_programs', FALSE, 'sped_programs_details', NULL,
      'receives_learning_support', FALSE, 'learning_support_details', NULL),
    'economic_capacity', jsonb_build_object(
      'primary_income_source', 'Sari-sari store', 'monthly_income', 9000,
      'income_classification', 'Below Minimum / Low Income', 'are_parents_employed', TRUE,
      'employment_details', 'Spouse works in a factory'),
    'service_availment', jsonb_build_object(
      'receives_financial_assistance', FALSE, 'financial_assistance_details', NULL,
      'is_aware_of_social_services', TRUE, 'awareness_details', 'Knows about 4Ps',
      'has_availed_services', FALSE, 'availed_services_details', NULL,
      'service_challenges', NULL, 'service_challenges_other', NULL),
    'assessment_notes', jsonb_build_object(
      'strengths', 'Supportive family', 'assessment_details', 'Self-test record',
      'recommended_actions', 'None', 'readiness_score', 'moderate'),
    'feedback', jsonb_build_array(jsonb_build_object('step', 5, 'comment', 'Self-test feedback'))
  );

  v_first := public.pretest_submit_assessment((v_login->>'session_id')::UUID, v_int_id, 'R4A', v_payload);
  IF NOT coalesce((v_first->>'ok')::BOOLEAN, FALSE) OR (v_first->>'duplicate')::BOOLEAN THEN
    RAISE EXCEPTION 'SELFTEST FAILED at first save: %', v_first;
  END IF;

  -- 3. Same profile again must NOT create a duplicate
  v_again := public.pretest_submit_assessment((v_login->>'session_id')::UUID, v_int_id, 'R4A', v_payload);
  IF NOT coalesce((v_again->>'duplicate')::BOOLEAN, FALSE) OR v_again->>'aruga_id' <> v_first->>'aruga_id' THEN
    RAISE EXCEPTION 'SELFTEST FAILED at duplicate check: %', v_again;
  END IF;

  -- 4. Every step table got exactly one row; family and feedback rows saved
  SELECT (SELECT count(*) FROM pretest.pre_qualification      WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.respondents            WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.children               WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.child_education_health WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.socio_economic         WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.health_info            WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.education_info         WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.economic_capacity      WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.service_availment      WHERE assessment_id = v_record_id)
       + (SELECT count(*) FROM pretest.assessment_notes       WHERE assessment_id = v_record_id)
    INTO v_tables;
  SELECT count(*) INTO v_members  FROM pretest.family_members WHERE assessment_id = v_record_id;
  SELECT count(*) INTO v_feedback FROM pretest.feedback       WHERE assessment_id = v_record_id;
  IF v_tables <> 10 OR v_members <> 2 OR v_feedback <> 1 THEN
    RAISE EXCEPTION 'SELFTEST FAILED: expected 10 step rows, 2 family, 1 feedback; got %, %, %',
      v_tables, v_members, v_feedback;
  END IF;

  -- 5. Live table untouched
  SELECT count(*) INTO v_live_after FROM public.assessments;
  IF v_live_after <> v_live_before THEN
    RAISE EXCEPTION 'SELFTEST FAILED: live assessments count changed (% -> %)', v_live_before, v_live_after;
  END IF;

  -- 6. Logout
  IF NOT public.pretest_logout((v_login->>'session_id')::UUID, v_int_id) THEN
    RAISE EXCEPTION 'SELFTEST FAILED at logout';
  END IF;

  RAISE EXCEPTION 'SELFTEST PASSED (all test data undone): login OK, saved % with all 10 sections + 2 family members + 1 feedback, duplicate upload blocked, live tables unchanged, logout OK',
    v_first->>'aruga_id';
END $$;
