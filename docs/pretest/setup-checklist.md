# Pretest Setup Checklist (Phase 1)

Steps for the system admin, in order. Nothing here changes the live tool or live data.

## 1. Database (Supabase → SQL Editor)

- [ ] Run `supabase-migrations/pretest_v2_001_schema.sql`.
      The last result table should list the pretest tables, all with `rls_enabled = true`.
- [ ] Edit `supabase-migrations/pretest_v2_002_add_testers.sql`: put the testers'
      Interviewer Codes (about 3 per region) in Option A, then run it.
      The result shows the enrolled testers per region.
- [ ] Do **not** add `pretest` to *Settings → API → Exposed schemas*. It must stay hidden.

## 2. API (Vercel)

- [ ] Deploy `api/pretest-router.php` when you are ready (your decision; nothing has been pushed).
      It is the only new Vercel function, bringing the total to **10 of 12**.
- [ ] Check it is live: open `https://projectaruga.com/api/pretest-router.php?action=ping`
      and look for `"success": true`.

## 3. Desktop app

- [ ] Set up the build computer and build the installer (see `desktop-app/README.md`).
- [ ] Install it on one laptop and run a test profile end to end.
- [ ] In Supabase *Table Editor → schema "pretest" → assessments*, confirm the
      record is there, and that the live `assessments` table has **no** new record.
- [ ] Share the installer and `docs/pretest/tester-guide.md` with testers.

## Viewing results

- Profiles: tables in the `pretest` schema (Table Editor → choose schema `pretest`).
- Tester feedback, newest first:

```sql
SELECT f.created_at, i.region, i.interviewer_code, a.aruga_id, f.step, f.comment
  FROM pretest.feedback f
  JOIN pretest.assessments a ON a.id = f.assessment_id
  JOIN public.interviewers i ON i.id = f.interviewer_id
 ORDER BY f.created_at DESC;
```

## After the pretest

| Goal | Run |
|---|---|
| Remove test data but keep the setup | `supabase-migrations/pretest_v2_cleanup_data.sql` |
| Remove everything (v2 rejected, or v2 moved into the live tables) | `supabase-migrations/pretest_v2_drop_all.sql`, then delete `api/pretest-router.php` to free the Vercel function slot |
