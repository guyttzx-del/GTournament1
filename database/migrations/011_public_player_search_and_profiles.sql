-- Public registration support: limited player search and private profile images.
alter table public.applicant_submissions add column if not exists existing_player_id uuid;

insert into storage.buckets (id, name, public)
values ('profile-images', 'profile-images', false)
on conflict (id) do update set public = false;

create or replace function public.search_public_players(p_query text)
returns table (id uuid, competition_name text, nickname text, facebook_name text, club text)
language sql stable security definer set search_path = public as $$
  select a.id, a.competition_name, a.nickname, a.facebook_name, a.club
  from public.applicant_submissions a
  where a.status = 'approved'
    and length(trim(coalesce(p_query, ''))) >= 2
    and (a.competition_name ilike '%' || trim(p_query) || '%'
      or a.nickname ilike '%' || trim(p_query) || '%'
      or coalesce(a.facebook_name, '') ilike '%' || trim(p_query) || '%')
  order by a.created_at desc
  limit 10;
$$;

revoke all on function public.search_public_players(text) from public;
grant execute on function public.search_public_players(text) to anon, authenticated;

drop policy if exists "public can upload profile images" on storage.objects;
create policy "public can upload profile images" on storage.objects
  for insert to anon, authenticated
  with check (bucket_id = 'profile-images' and (storage.foldername(name))[1] = 'public');

drop policy if exists "staff can read profile images" on storage.objects;
create policy "staff can read profile images" on storage.objects
  for select to authenticated
  using (bucket_id = 'profile-images' and public.is_staff());
