-- Production workflow hardening: counters, atomic reservation, role-aware public reads.
create or replace view public.registration_counts as
select s.id as season_id, s.capacity,
  count(r.id) filter (where r.status = 'approved')::integer as approved_count,
  count(r.id) filter (where r.status in ('pending_payment','pending_review'))::integer as pending_count
from public.seasons s left join public.registrations r on r.season_id = s.id group by s.id, s.capacity;

create or replace function public.reserve_registration(p_season_id uuid, p_user_id uuid, p_competition_name text, p_nickname text, p_contact_url text, p_club text default null)
returns public.registrations language plpgsql security definer set search_path = public as $$
declare result public.registrations;
begin
  if auth.uid() is null or auth.uid() <> p_user_id then raise exception 'not_authorized'; end if;
  insert into public.registrations(season_id,user_id,competition_name,nickname,contact_url,club,status)
  values(p_season_id,p_user_id,p_competition_name,p_nickname,p_contact_url,p_club,'pending_payment') returning * into result;
  return result;
exception when unique_violation then raise exception 'duplicate_registration';
end; $$;
revoke all on function public.reserve_registration(uuid,uuid,text,text,text,text) from public;
grant execute on function public.reserve_registration(uuid,uuid,text,text,text,text) to authenticated;

create or replace view public.match_player_access as
select m.id as match_id, r.user_id from public.matches m join public.registrations r on r.id in (m.player_a_registration_id,m.player_b_registration_id);
alter view public.match_player_access set (security_barrier = true);

drop policy if exists "public can read groups" on public.groups;
create policy "public can read groups" on public.groups for select using (exists (select 1 from public.seasons s where s.id = season_id and s.status in ('running','completed')));
drop policy if exists "public can read group members" on public.group_members;
create policy "public can read group members" on public.group_members for select using (exists (select 1 from public.groups g join public.seasons s on s.id=g.season_id where g.id=group_id and s.status in ('running','completed')));
drop policy if exists "public can read matches" on public.matches;
create policy "public can read matches" on public.matches for select using (exists (select 1 from public.seasons s where s.id=season_id and s.status in ('running','completed')));
