-- ============================================================
-- Migration: Link DSA releases to generated payrolls
--
-- Run AFTER payroll_generations.sql.
--
-- Each generated payroll now keeps a snapshot of exactly what was
-- printed (beneficiary IDs, months, amount, type) so a DSA release
-- can be recorded against it, and only against it, once.
--
-- Review before running. Safe to run once; IF NOT EXISTS and
-- CREATE OR REPLACE make re-runs idempotent.
-- ============================================================

-- 1. Snapshot columns on payroll_generations.
--    Rows created before this migration keep these NULL and cannot
--    be used to record a release (they never captured their list).
alter table payroll_generations add column if not exists payroll_type     text;     -- 'complete' | 'special'
alter table payroll_generations add column if not exists months_covered   text[];   -- e.g. {2026-07,2026-08,2026-09}
alter table payroll_generations add column if not exists amount_per_month integer;
alter table payroll_generations add column if not exists beneficiary_ids  text[];   -- Aruga IDs printed on the payroll

-- 2. Each release records which payroll it came from. The unique index
--    guarantees a payroll can be released only once, even if two people
--    save at the same moment. Older releases keep payroll_id NULL.
alter table dsa_releases add column if not exists payroll_id varchar(20)
    references payroll_generations(payroll_id);

create unique index if not exists uq_dsa_releases_payroll_id
    on dsa_releases (payroll_id);

-- 3. create_payroll_generation now also stores the snapshot.
--    Drop the previous 6-argument version so only one signature exists.
drop function if exists create_payroll_generation(uuid, text, text, int, numeric, text);

create or replace function create_payroll_generation(
    p_generated_by      uuid,
    p_region            text,
    p_period            text,
    p_beneficiary_count int,
    p_total_amount      numeric,
    p_ip_address        text    default null,
    p_payroll_type      text    default null,
    p_months_covered    text[]  default null,
    p_amount_per_month  int     default null,
    p_beneficiary_ids   text[]  default null
)
returns text
language plpgsql
security invoker
set search_path = public
as $$
declare
    v_date       date := (now() at time zone 'Asia/Manila')::date;
    v_next       int;
    v_payroll_id text;
begin
    insert into payroll_id_counters (gen_date, last_number)
    values (v_date, 1)
    on conflict (gen_date)
    do update set last_number = payroll_id_counters.last_number + 1,
                  updated_at  = now()
    returning last_number into v_next;

    v_payroll_id := 'DSA-' || to_char(v_date, 'MMDDYYYY') || '-' || lpad(v_next::text, 4, '0');

    insert into payroll_generations (
        payroll_id, generated_by, region, period, beneficiary_count, total_amount, ip_address,
        payroll_type, months_covered, amount_per_month, beneficiary_ids)
    values (
        v_payroll_id, p_generated_by, p_region, p_period, p_beneficiary_count, p_total_amount, p_ip_address,
        p_payroll_type, p_months_covered, p_amount_per_month, p_beneficiary_ids);

    return v_payroll_id;
end;
$$;

-- ============================================================
-- Verification queries (run manually after applying):
--
--   select payroll_id, payroll_type, months_covered, amount_per_month,
--          cardinality(beneficiary_ids) as ids
--     from payroll_generations order by created_at desc limit 5;
--
--   select id, payroll_id, region_name, months_covered
--     from dsa_releases order by created_at desc limit 5;
-- ============================================================
