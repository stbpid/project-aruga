-- Signatories table for System Settings
CREATE TABLE IF NOT EXISTS signatories (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  signatory_fullname TEXT NOT NULL,
  signatory_position TEXT NOT NULL,
  signatory_office TEXT NOT NULL,
  signatory_region TEXT NOT NULL,
  signatory_status TEXT NOT NULL DEFAULT 'active'
    CHECK (signatory_status IN ('active', 'inactive')),
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);
