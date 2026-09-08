-- Documents: Forms & Templates entries shown in the admin Documents tab
-- (System > Documents > Forms & Templates), now database-backed instead of
-- hardcoded in dashboard-admin.html.
CREATE TABLE IF NOT EXISTS documents (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  file_name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  file_url TEXT NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Seed with the entries that were previously hardcoded as cards in
-- dashboard-admin.html's Forms & Templates / Reports tabs.
INSERT INTO documents (file_name, description, file_url) VALUES
  ('Profiling Tool', 'Official child assessment profiling form', 'https://docs.google.com/document/d/19vipJgN7MWBNyuYUI8g5x_ygauiAuVBE/edit?usp=sharing&ouid=100444016392760639585&rtpof=true&sd=true'),
  ('NDA Consent Form', 'Non-disclosure agreement for field interviewers', 'https://docs.google.com/document/d/102Fmul91yaojDikMLqCWwGO08_D0zzQIiYRV20y-rBY/edit?usp=sharing'),
  ('System User Manual', 'Step-by-step guide for using the platform', 'https://canva.link/projectarugasmsusermanual'),
  ('Aruga Briefer', 'Project overview and program briefer', 'https://drive.google.com/file/d/1Eo6atc24dfkT9kKS9-_dHQvOmrSr8Uh-/view?usp=sharing'),
  ('Project Aruga Guidelines', 'Official operational guidelines and standards', 'https://drive.google.com/file/d/1YUMYOTyl3Dn-ihLnItDlC6-SwMve61ri/view?usp=sharing'),
  ('Presentation Deck', 'Official Project Aruga slide deck', 'https://canva.link/pjlvnbo7b634t1w'),
  ('MOA Template', 'Memorandum of Agreement template', 'https://docs.google.com/document/d/1IRvrWzbtgDs12eOvL2wGgGU-NnEK6Ty1tYvjhCnr7JM/edit?usp=sharing'),
  ('Evaluation Result', 'Evaluation form for project presentations', 'https://drive.google.com/file/d/1FYU8_tRo1G1R0XPstGCkVqL5OPqmsqin/view?usp=sharing')
ON CONFLICT DO NOTHING;
