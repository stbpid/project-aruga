-- Region Targets: per-region completion targets used for coverage progress
-- bars across the dashboards (System Settings > Region Targets tab).
CREATE TABLE IF NOT EXISTS region_targets (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  region_name TEXT NOT NULL UNIQUE,
  target INTEGER NOT NULL DEFAULT 0 CHECK (target >= 0),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Seed with the values that were previously hardcoded in
-- api/lib/region-coverage-helper.php::getRegionTargets(), plus the regions
-- that had no target before (defaulting to 0, same as their prior behavior).
INSERT INTO region_targets (region_name, target) VALUES
  ('NCR (National Capital Region)', 141),
  ('Region I (Ilocos Region)', 150),
  ('Region II (Cagayan Valley)', 100),
  ('Region III (Central Luzon)', 100),
  ('Region IV-A (CALABARZON)', 100),
  ('Region IV-B (MIMAROPA)', 150),
  ('Region V (Bicol Region)', 100),
  ('Region VI (Western Visayas)', 150),
  ('Region VII (Central Visayas)', 0),
  ('Region VIII (Eastern Visayas)', 0),
  ('Region IX (Zamboanga Peninsula)', 0),
  ('Region X (Northern Mindanao)', 0),
  ('Region XI (Davao Region)', 150),
  ('Region XII (SOCCSKSARGEN)', 0),
  ('Region XIII (Caraga)', 0),
  ('CAR (Cordillera Administrative Region)', 0),
  ('BARMM (Bangsamoro)', 0)
ON CONFLICT (region_name) DO NOTHING;
