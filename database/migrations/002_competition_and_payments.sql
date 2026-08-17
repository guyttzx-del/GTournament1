-- Phase 1: payment settings per season.
alter table public.seasons add column if not exists promptpay_name text;
alter table public.seasons add column if not exists promptpay_number text;
alter table public.seasons add column if not exists expected_payment numeric(10,2);

-- Phase 2: 8 groups x 4 players, fixtures, result evidence and knockout bracket.
create type public.match_stage as enum ('group', 'round_of_16', 'quarter_final', 'semi_final', 'final', 'third_place');
create type public.match_status as enum ('scheduled', 'awaiting_result', 'disputed', 'confirmed', 'void');

create table public.groups (
  id uuid primary key default gen_random_uuid(),
  season_id uuid not null references public.seasons(id) on delete cascade,
  group_code text not null,
  seed_order integer not null,
  locked_at timestamptz,
  unique (season_id, group_code),
  unique (season_id, seed_order)
);

create table public.group_members (
  group_id uuid not null references public.groups(id) on delete cascade,
  registration_id uuid not null references public.registrations(id) on delete cascade,
  seed integer not null check (seed between 1 and 4),
  primary key (group_id, registration_id),
  unique (group_id, seed)
);

create table public.matches (
  id uuid primary key default gen_random_uuid(),
  season_id uuid not null references public.seasons(id) on delete cascade,
  group_id uuid references public.groups(id),
  stage public.match_stage not null,
  player_a_registration_id uuid not null references public.registrations(id),
  player_b_registration_id uuid not null references public.registrations(id),
  scheduled_at timestamptz,
  deadline_at timestamptz,
  status public.match_status not null default 'scheduled',
  winner_registration_id uuid references public.registrations(id),
  confirmed_by uuid references auth.users(id),
  confirmed_at timestamptz,
  created_at timestamptz not null default now(),
  check (player_a_registration_id <> player_b_registration_id)
);

create table public.match_results (
  id uuid primary key default gen_random_uuid(),
  match_id uuid unique not null references public.matches(id) on delete cascade,
  score_a integer not null check (score_a >= 0),
  score_b integer not null check (score_b >= 0),
  submitted_by uuid not null references auth.users(id),
  submitted_at timestamptz not null default now(),
  confirmed_at timestamptz,
  correction_of uuid references public.match_results(id)
);

create table public.match_evidence (
  id uuid primary key default gen_random_uuid(),
  match_id uuid not null references public.matches(id) on delete cascade,
  storage_path text not null,
  uploaded_by uuid not null references auth.users(id),
  created_at timestamptz not null default now()
);

create table public.brackets (
  id uuid primary key default gen_random_uuid(),
  season_id uuid not null references public.seasons(id) on delete cascade,
  match_id uuid not null references public.matches(id) on delete cascade,
  slot integer not null,
  source_match_a uuid references public.matches(id),
  source_match_b uuid references public.matches(id),
  unique (season_id, slot)
);

alter table public.groups enable row level security;
alter table public.group_members enable row level security;
alter table public.matches enable row level security;
alter table public.match_results enable row level security;
alter table public.match_evidence enable row level security;
alter table public.brackets enable row level security;

create policy "public can read groups" on public.groups for select using (true);
create policy "public can read group members" on public.group_members for select using (true);
create policy "public can read matches" on public.matches for select using (true);
create policy "players read match results" on public.match_results for select using (auth.uid() = submitted_by or public.is_staff());
create policy "players submit match results" on public.match_results for insert with check (auth.uid() = submitted_by);
create policy "staff manage groups" on public.groups for all using (public.is_staff()) with check (public.is_staff());
create policy "staff manage group members" on public.group_members for all using (public.is_staff()) with check (public.is_staff());
create policy "staff manage matches" on public.matches for all using (public.is_staff()) with check (public.is_staff());
create policy "staff manage results" on public.match_results for all using (public.is_staff()) with check (public.is_staff());
create policy "staff read evidence" on public.match_evidence for select using (public.is_staff() or auth.uid() = uploaded_by);
create policy "players upload evidence" on public.match_evidence for insert with check (auth.uid() = uploaded_by);
create policy "staff manage brackets" on public.brackets for all using (public.is_staff()) with check (public.is_staff());

create or replace function public.player_match_access(match_uuid uuid)
returns boolean language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from public.matches m
    join public.registrations r on r.id in (m.player_a_registration_id, m.player_b_registration_id)
    where m.id = match_uuid and r.user_id = auth.uid()
  );
$$;

drop policy if exists "players read match results" on public.match_results;
create policy "players read match results" on public.match_results for select using (public.player_match_access(match_id) or public.is_staff());
