-- Migration: Beneficiary Edit Requests
-- Field officers submit edit requests; STU heads review and approve/decline/request-update

CREATE TABLE IF NOT EXISTS beneficiary_edit_requests (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  aruga_id        TEXT NOT NULL,
  assessment_id   UUID NOT NULL REFERENCES assessments(id) ON DELETE CASCADE,
  interviewer_id  UUID NOT NULL REFERENCES interviewers(id) ON DELETE CASCADE,
  payload         JSONB NOT NULL,          -- full update payload (same shape as update-beneficiary.php)
  status          TEXT NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','approved','for_update','declined','superseded')),
  reviewer_note   TEXT,                    -- optional note from STU head (used for "for_update")
  reviewed_by     UUID REFERENCES interviewers(id),
  reviewed_at     TIMESTAMPTZ,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_edit_requests_aruga_id       ON beneficiary_edit_requests(aruga_id);
CREATE INDEX IF NOT EXISTS idx_edit_requests_status         ON beneficiary_edit_requests(status);
CREATE INDEX IF NOT EXISTS idx_edit_requests_interviewer    ON beneficiary_edit_requests(interviewer_id);
CREATE INDEX IF NOT EXISTS idx_edit_requests_assessment     ON beneficiary_edit_requests(assessment_id);
