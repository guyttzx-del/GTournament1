-- Match evidence and staff dispute resolution.
alter table public.match_evidence alter column storage_path drop not null;
alter table public.match_evidence add column if not exists evidence_type text not null default 'screenshot';
alter table public.match_evidence add column if not exists source_url text;
alter table public.match_evidence drop constraint if exists match_evidence_type_check;
alter table public.match_evidence add constraint match_evidence_type_check check (evidence_type in ('screenshot', 'video_link'));
alter table public.match_evidence drop constraint if exists match_evidence_source_check;
alter table public.match_evidence add constraint match_evidence_source_check check (
  (evidence_type = 'screenshot' and storage_path is not null and source_url is null)
  or (evidence_type = 'video_link' and storage_path is null and source_url is not null)
);

insert into storage.buckets (id, name, public) values ('match-evidence', 'match-evidence', false)
on conflict (id) do nothing;

drop policy if exists "staff read evidence" on public.match_evidence;
drop policy if exists "players upload evidence" on public.match_evidence;
drop policy if exists "players and staff read match evidence" on public.match_evidence;
drop policy if exists "match players upload evidence" on public.match_evidence;
drop policy if exists "staff manage match evidence" on public.match_evidence;
create policy "players and staff read match evidence" on public.match_evidence
  for select using (public.player_match_access(match_id) or public.is_staff());
create policy "match players upload evidence" on public.match_evidence
  for insert with check (public.player_match_access(match_id) and auth.uid() = uploaded_by);
create policy "staff manage match evidence" on public.match_evidence
  for all using (public.is_staff()) with check (public.is_staff());

drop policy if exists "match players upload evidence objects" on storage.objects;
drop policy if exists "match players read evidence objects" on storage.objects;
drop policy if exists "staff read match evidence objects" on storage.objects;
create policy "match players upload evidence objects" on storage.objects
  for insert to authenticated
  with check (bucket_id = 'match-evidence' and (storage.foldername(name))[1] = auth.uid()::text);
create policy "match players read evidence objects" on storage.objects
  for select to authenticated
  using (bucket_id = 'match-evidence' and (public.is_staff() or public.player_match_access(((storage.foldername(name))[2])::uuid)));
create policy "staff read match evidence objects" on storage.objects
  for select to authenticated
  using (bucket_id = 'match-evidence' and public.is_staff());

create or replace function public.match_evidence_ready(p_match_id uuid)
returns boolean language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.match_evidence where match_id = p_match_id and evidence_type = 'screenshot')
    and exists (
      select 1 from public.matches m
      where m.id = p_match_id
        and (m.stage not in ('semi_final', 'final') or exists (
          select 1 from public.match_evidence where match_id = p_match_id and evidence_type = 'video_link'
        ))
    );
$$;

create or replace function public.confirm_match_result(p_match_id uuid, p_user_id uuid)
returns void language plpgsql security definer set search_path = public as $$
declare m public.matches; r public.match_results;
begin
  select * into m from public.matches where id = p_match_id for update;
  if not exists (select 1 from public.match_player_access where match_id = p_match_id and user_id = p_user_id) then raise exception 'not_authorized'; end if;
  select * into r from public.match_results where match_id = p_match_id order by submitted_at desc limit 1;
  if r.id is null or m.status not in ('awaiting_result', 'disputed') then raise exception 'invalid_match_state'; end if;
  if r.submitted_by = p_user_id then raise exception 'opponent_confirmation_required'; end if;
  if not public.match_evidence_ready(p_match_id) then raise exception 'evidence_required'; end if;
  update public.match_results set confirmed_at = now() where id = r.id;
  update public.matches set status = 'confirmed', confirmed_by = p_user_id, confirmed_at = now(),
    winner_registration_id = case when r.score_a > r.score_b then player_a_registration_id when r.score_b > r.score_a then player_b_registration_id else null end
    where id = p_match_id;
  insert into public.audit_logs(actor_id, action, entity_type, entity_id, metadata)
    values (p_user_id, 'match.confirmed', 'match', p_match_id, jsonb_build_object('result_id', r.id));
end; $$;

create or replace function public.resolve_match_dispute(p_match_id uuid, p_user_id uuid, p_outcome text, p_note text)
returns void language plpgsql security definer set search_path = public as $$
declare m public.matches; r public.match_results;
begin
  if not public.is_staff() or auth.uid() <> p_user_id then raise exception 'not_authorized'; end if;
  if p_outcome not in ('confirmed', 'void') or length(trim(coalesce(p_note, ''))) = 0 then raise exception 'invalid_resolution'; end if;
  select * into m from public.matches where id = p_match_id for update;
  select * into r from public.match_results where match_id = p_match_id order by submitted_at desc limit 1;
  if m.id is null or m.status <> 'disputed' or r.id is null then raise exception 'invalid_match_state'; end if;
  if p_outcome = 'confirmed' and not public.match_evidence_ready(p_match_id) then raise exception 'evidence_required'; end if;
  if p_outcome = 'confirmed' then
    update public.match_results set confirmed_at = now() where id = r.id;
    update public.matches set status = 'confirmed', confirmed_by = p_user_id, confirmed_at = now(),
      winner_registration_id = case when r.score_a > r.score_b then player_a_registration_id when r.score_b > r.score_a then player_b_registration_id else null end
      where id = p_match_id;
  else
    update public.matches set status = 'void', confirmed_by = p_user_id, confirmed_at = now(), winner_registration_id = null where id = p_match_id;
  end if;
  insert into public.audit_logs(actor_id, action, entity_type, entity_id, metadata)
    values (p_user_id, 'match.dispute_resolved', 'match', p_match_id, jsonb_build_object('outcome', p_outcome, 'note', trim(p_note)));
end; $$;

revoke all on function public.match_evidence_ready(uuid) from public;
revoke all on function public.confirm_match_result(uuid, uuid) from public;
revoke all on function public.resolve_match_dispute(uuid, uuid, text, text) from public;
grant execute on function public.match_evidence_ready(uuid) to authenticated;
grant execute on function public.confirm_match_result(uuid, uuid) to authenticated;
grant execute on function public.resolve_match_dispute(uuid, uuid, text, text) to authenticated;
