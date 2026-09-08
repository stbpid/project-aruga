-- Documents: Forms & Templates entries shown in the admin Documents tab
-- (System > Documents > Forms & Templates), now database-backed instead of
-- hardcoded in dashboard-admin.html.
CREATE TABLE IF NOT EXISTS documents (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  file_name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  file_url TEXT NOT NULL,
  category TEXT NOT NULL DEFAULT 'Form' CHECK (category IN (
    'Issuances', 'Manual', 'Guidelines', 'Briefer', 'Storybook', 'Primer', 'Form', 'Presentation', 'AVPs'
  )),
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Add category to a pre-existing table (safe to re-run).
ALTER TABLE documents ADD COLUMN IF NOT EXISTS category TEXT NOT NULL DEFAULT 'Form';
ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_category_check;
ALTER TABLE documents ADD CONSTRAINT documents_category_check CHECK (category IN (
  'Issuances', 'Manual', 'Guidelines', 'Briefer', 'Storybook', 'Primer', 'Form', 'Presentation', 'AVPs'
));

-- Seed with the entries that were previously hardcoded as cards in
-- dashboard-admin.html's Forms & Templates / Reports tabs.
INSERT INTO documents (file_name, description, file_url, category) VALUES
  ('Profiling Tool', 'Official child assessment profiling form', 'https://docs.google.com/document/d/19vipJgN7MWBNyuYUI8g5x_ygauiAuVBE/edit?usp=sharing&ouid=100444016392760639585&rtpof=true&sd=true', 'Form'),
  ('NDA Consent Form', 'Non-disclosure agreement for field interviewers', 'https://docs.google.com/document/d/102Fmul91yaojDikMLqCWwGO08_D0zzQIiYRV20y-rBY/edit?usp=sharing', 'Form'),
  ('System User Manual', 'Step-by-step guide for using the platform', 'https://canva.link/projectarugasmsusermanual', 'Manual'),
  ('Aruga Briefer', 'Project overview and program briefer', 'https://drive.google.com/file/d/1Eo6atc24dfkT9kKS9-_dHQvOmrSr8Uh-/view?usp=sharing', 'Briefer'),
  ('Project Aruga Guidelines', 'Official operational guidelines and standards', 'https://drive.google.com/file/d/1YUMYOTyl3Dn-ihLnItDlC6-SwMve61ri/view?usp=sharing', 'Guidelines'),
  ('Presentation Deck', 'Official Project Aruga slide deck', 'https://canva.link/pjlvnbo7b634t1w', 'Presentation'),
  ('MOA Template', 'Memorandum of Agreement template', 'https://docs.google.com/document/d/1IRvrWzbtgDs12eOvL2wGgGU-NnEK6Ty1tYvjhCnr7JM/edit?usp=sharing', 'Form'),
  ('Evaluation Result', 'Evaluation form for project presentations', 'https://drive.google.com/file/d/1FYU8_tRo1G1R0XPstGCkVqL5OPqmsqin/view?usp=sharing', 'Form')
ON CONFLICT DO NOTHING;

-- Backfill category for rows inserted before this column existed (re-run safe).
UPDATE documents SET category = 'Form'         WHERE file_name IN ('Profiling Tool', 'NDA Consent Form', 'MOA Template', 'Evaluation Result') AND category = 'Form';
UPDATE documents SET category = 'Manual'       WHERE file_name = 'System User Manual';
UPDATE documents SET category = 'Briefer'      WHERE file_name = 'Aruga Briefer';
UPDATE documents SET category = 'Guidelines'   WHERE file_name = 'Project Aruga Guidelines';
UPDATE documents SET category = 'Presentation' WHERE file_name = 'Presentation Deck';
