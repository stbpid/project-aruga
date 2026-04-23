-- Run this in Supabase SQL Editor (once only)
ALTER TABLE assessments ADD COLUMN IF NOT EXISTS aruga_id VARCHAR(30) UNIQUE;
CREATE INDEX IF NOT EXISTS idx_assessments_aruga_id ON assessments(aruga_id);
