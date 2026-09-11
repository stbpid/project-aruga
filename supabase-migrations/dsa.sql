-- DSA Monitoring: releases, per-month payments, standing eligibility, audit.
-- A "release" is one disbursement event covering N months for one region.
-- A "payment" is one beneficiary's status for one month.

CREATE TABLE IF NOT EXISTS dsa_releases (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  region_name      TEXT NOT NULL,
  release_date     DATE NOT NULL,
  months_covered   TEXT[] NOT NULL,
  amount_per_month INTEGER NOT NULL DEFAULT 2000 CHECK (amount_per_month > 0),
  recorded_by      UUID NOT NULL REFERENCES interviewers(id),
  is_locked        BOOLEAN NOT NULL DEFAULT TRUE,
  created_at       TIMESTAMPTZ DEFAULT NOW(),
  CHECK (array_length(months_covered, 1) > 0)
);

CREATE TABLE IF NOT EXISTS dsa_payments (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  release_id   UUID NOT NULL REFERENCES dsa_releases(id) ON DELETE CASCADE,
  aruga_id     TEXT NOT NULL,
  period_month TEXT NOT NULL CHECK (period_month ~ '^\d{4}-\d{2}$'),
  status       TEXT NOT NULL CHECK (status IN
                 ('paid','deceased','transferred','not_claimed')),
  created_at   TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE (aruga_id, period_month)
);

-- Standing eligibility. Absence of a row means active.
CREATE TABLE IF NOT EXISTS dsa_beneficiary_status (
  aruga_id       TEXT PRIMARY KEY,
  status         TEXT NOT NULL CHECK (status IN ('deceased','transferred')),
  effective_from TEXT NOT NULL CHECK (effective_from ~ '^\d{4}-\d{2}$'),
  set_by_release UUID REFERENCES dsa_releases(id) ON DELETE SET NULL,
  created_at     TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS dsa_release_audit (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  release_id   UUID NOT NULL REFERENCES dsa_releases(id) ON DELETE CASCADE,
  action       TEXT NOT NULL CHECK (action IN ('reopen','correct','relock')),
  reason       TEXT NOT NULL,
  performed_by UUID NOT NULL REFERENCES interviewers(id),
  details      JSONB,
  created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_dsa_payments_aruga   ON dsa_payments(aruga_id);
CREATE INDEX IF NOT EXISTS idx_dsa_payments_month   ON dsa_payments(period_month);
CREATE INDEX IF NOT EXISTS idx_dsa_payments_release ON dsa_payments(release_id);
CREATE INDEX IF NOT EXISTS idx_dsa_releases_region  ON dsa_releases(region_name);
