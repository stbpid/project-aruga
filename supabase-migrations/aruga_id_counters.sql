-- ============================================================
-- Migration: Atomic ARUGA ID counter (fixes race condition on
-- concurrent profiling submissions causing "Failed to submit
-- assessment. Please try again.")
--
-- Review before running. Safe to run once; INSERT ... ON CONFLICT
-- and CREATE OR REPLACE make re-runs idempotent.
-- ============================================================

-- 1. Counter table — one row per region_code + year
create table if not exists aruga_id_counters (
    id           uuid primary key default gen_random_uuid(),
    region_code  varchar(10) not null,
    year         int not null,
    last_number  int not null default 0,
    updated_at   timestamptz not null default now(),
    unique (region_code, year)
);

-- 2. Backfill from existing assessments so new IDs continue from
--    the current max instead of restarting at 1 (which would collide
--    with existing aruga_id values).
--    aruga_id format: ARUGA-2026-R3-0048  ->  parts: [ARUGA, 2026, R3, 0048]
insert into aruga_id_counters (region_code, year, last_number)
select
    split_part(aruga_id, '-', 3)         as region_code,
    split_part(aruga_id, '-', 2)::int    as year,
    max(split_part(aruga_id, '-', 4)::int) as last_number
from assessments
where aruga_id is not null
  and aruga_id ~ '^ARUGA-\d{4}-[A-Z0-9]+-\d+$'
group by 1, 2
on conflict (region_code, year)
do update set last_number = greatest(aruga_id_counters.last_number, excluded.last_number);

-- 3. Atomic increment function — called via Supabase RPC.
--    Guarantees no two concurrent callers can receive the same number,
--    because the UPDATE happens inside one atomic statement.
create or replace function increment_aruga_counter(p_region_code text, p_year int)
returns int
language plpgsql
security invoker
set search_path = public
as $$
declare
    v_next int;
begin
    insert into aruga_id_counters (region_code, year, last_number)
    values (p_region_code, p_year, 1)
    on conflict (region_code, year)
    do update set last_number = aruga_id_counters.last_number + 1,
                  updated_at  = now()
    returning last_number into v_next;

    return v_next;
end;
$$;

-- ============================================================
-- Verification queries (run manually after applying, not part
-- of the migration itself):
--
--   select * from aruga_id_counters order by year, region_code;
--
--   select increment_aruga_counter('R3', 2026);  -- should return next number
--   select increment_aruga_counter('R3', 2026);  -- should return next+1
-- ============================================================
