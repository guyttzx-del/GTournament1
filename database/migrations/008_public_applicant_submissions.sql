create table if not exists public.applicant_submissions (
    id uuid primary key default gen_random_uuid(),
    season_id uuid not null references public.seasons(id),
    applicant_type text not null check (applicant_type in ('returning', 'new')),
    competition_name text not null check (char_length(trim(competition_name)) between 2 and 80),
    nickname text not null check (char_length(trim(nickname)) between 2 and 40),
    facebook_name text,
    facebook_url text,
    club text,
    profile_image_path text,
    status text not null default 'pending_review' check (status in ('pending_review', 'approved', 'rejected')),
    rejection_reason text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create index if not exists applicant_submissions_season_status_idx
    on public.applicant_submissions(season_id, status, created_at);

alter table public.applicant_submissions enable row level security;

create policy "public can submit applicant"
    on public.applicant_submissions for insert
    to anon, authenticated
    with check (
        exists (
            select 1 from public.seasons s
            where s.id = season_id and s.status = 'open'
        )
    );

create policy "staff manage applicant submissions"
    on public.applicant_submissions for all
    to authenticated
    using (public.is_staff())
    with check (public.is_staff());

revoke all on public.applicant_submissions from public;
grant insert on public.applicant_submissions to anon, authenticated;
grant select, update, delete on public.applicant_submissions to authenticated;
