-- ============================================================
-- Migration: Payroll generation tracking
--
-- Every generated payroll gets a unique Payroll ID in the format
--   DSA-MMDDYYYY-NNNN   e.g. DSA-09302026-0001
-- where the date is the Philippine (Asia/Manila) date and NNNN
-- restarts at 0001 each day.
--
-- Review before running. Safe to run once; IF NOT EXISTS and
-- CREATE OR REPLACE make re-runs idempotent.
-- ============================================================

-- 1. Counter table — one row per Philippine calendar date
create table if not exists payroll_id_counters (
    gen_date     date primary key,
    last_number  int not null default 0,
    updated_at   timestamptz not null default now()
);

-- 2. One row per generated payroll
create table if not exists payroll_generations (
    id                 uuid primary key default gen_random_uuid(),
    payroll_id         varchar(20) not null unique,
    generated_by       uuid references interviewers(id),
    region             text,
    period             text,
    beneficiary_count  int not null default 0,
    total_amount       numeric(14,2) not null default 0,
    ip_address         text,
    created_at         timestamptz not null default now()
);

-- For databases where the table was created before ip_address existed
alter table payroll_generations add column if not exists ip_address text;

create index if not exists idx_payroll_generations_created_at
    on payroll_generations (created_at desc);

-- 3. Atomic create — called via Supabase RPC.
--    Increments the day's counter and inserts the record in one
--    transaction, so concurrent callers never receive the same ID.
--    Drop the earlier 5-argument version so only one signature exists.
drop function if exists create_payroll_generation(uuid, text, text, int, numeric);

create or replace function create_payroll_generation(
    p_generated_by      uuid,
    p_region            text,
    p_period            text,
    p_beneficiary_count int,
    p_total_amount      numeric,
    p_ip_address        text default null
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

    insert into payroll_generations (payroll_id, generated_by, region, period, beneficiary_count, total_amount, ip_address)
    values (v_payroll_id, p_generated_by, p_region, p_period, p_beneficiary_count, p_total_amount, p_ip_address);

    return v_payroll_id;
end;
$$;

-- ============================================================
-- Verification queries (run manually after applying, not part
-- of the migration itself):
--
--   select create_payroll_generation(null, 'NCR (National Capital Region)', 'OCTOBER 2026', 10, 20000);
--     -- should return DSA-<today>-0001, then -0002 on the next call
--   select * from payroll_generations order by created_at desc;
--
-- Clean up test rows afterwards:
--   delete from payroll_generations where generated_by is null;
-- ============================================================
