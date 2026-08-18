-- Production hardening: trusted audit writes, atomic match result submission and storage ownership.
drop policy if exists "users write own audit" on public.audit_logs;

create or replace function public.append_audit_log(
  p_actor_id uuid,
  p_action text,
  p_entity_type text,
  p_entity_id uuid,
  p_metadata jsonb default '{}'::jsonb
) returns void language plpgsql security definer set search_path = public as $$
begin
  if auth.uid() is null or auth.uid() <> p_actor_id then raise exception 'not_authorized'; end if;
  if p_action is null or length(trim(p_action)) = 0 then raise exception 'invalid_audit_action'; end if;
  insert into public.audit_logs(actor_id, action, entity_type, entity_id, metadata)
  values (p_actor_id, trim(p_action), p_entity_type, p_entity_id, coalesce(p_metadata, '{}'::jsonb));
end; $$;
revoke all on function public.append_audit_log(uuid, text, text, uuid, jsonb) from public;
grant execute on function public.append_audit_log(uuid, text, text, uuid, jsonb) to authenticated;

create or replace function public.submit_match_result(p_match_id uuid, p_user_id uuid, p_score_a integer, p_score_b integer)
returns public.match_results language plpgsql security definer set search_path = public as $$
declare m public.matches; r public.match_results;
begin
  if auth.uid() is null or auth.uid() <> p_user_id then raise exception 'not_authorized'; end if;
  if p_score_a < 0 or p_score_b < 0 then raise exception 'invalid_score'; end if;
  select * into m from public.matches where id = p_match_id for update;
  if m.id is null or not public.player_match_access(p_match_id) then raise exception 'not_authorized'; end if;
  if m.status not in ('scheduled', 'awaiting_result', 'disputed') then raise exception 'invalid_match_state'; end if;
  select * into r from public.match_results where match_id = p_match_id for update;
  if r.id is not null then
    if r.confirmed_at is not null or r.submitted_by <> p_user_id then raise exception 'result_already_submitted'; end if;
    update public.match_results set score_a = p_score_a, score_b = p_score_b, submitted_at = now() where id = r.id returning * into r;
  else
    insert into public.match_results(match_id, score_a, score_b, submitted_by) values (p_match_id, p_score_a, p_score_b, p_user_id) returning * into r;
  end if;
  update public.matches set status = 'awaiting_result' where id = p_match_id;
  return r;
end; $$;
revoke all on function public.submit_match_result(uuid, uuid, integer, integer) from public;
grant execute on function public.submit_match_result(uuid, uuid, integer, integer) to authenticated;

drop policy if exists "match players upload evidence objects" on storage.objects;
create policy "match players upload evidence objects" on storage.objects
  for insert to authenticated
  with check (
    bucket_id = 'match-evidence'
    and (storage.foldername(name))[1] = auth.uid()::text
    and public.player_match_access(((storage.foldername(name))[2])::uuid)
  );
