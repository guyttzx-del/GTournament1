-- Production access and match-integrity hardening.
-- This migration is additive and does not delete existing tournament data.

-- Supabase projects can have explicit anon ACLs on functions. Revoke them
-- explicitly instead of relying on REVOKE FROM public alone.
revoke execute on function public.append_audit_log(uuid, text, text, uuid, jsonb) from anon, public;
revoke execute on function public.submit_match_result(uuid, uuid, integer, integer) from anon, public;
revoke execute on function public.confirm_match_result(uuid, uuid) from anon, public;
revoke execute on function public.dispute_match_result(uuid, uuid, text) from anon, public;
revoke execute on function public.resolve_match_dispute(uuid, uuid, text, text) from anon, public;
revoke execute on function public.match_evidence_ready(uuid) from anon, public;

grant execute on function public.append_audit_log(uuid, text, text, uuid, jsonb) to authenticated;
grant execute on function public.submit_match_result(uuid, uuid, integer, integer) to authenticated;
grant execute on function public.confirm_match_result(uuid, uuid) to authenticated;
grant execute on function public.dispute_match_result(uuid, uuid, text) to authenticated;
grant execute on function public.resolve_match_dispute(uuid, uuid, text, text) to authenticated;
grant execute on function public.match_evidence_ready(uuid) to authenticated;

-- Confirmation must be performed by the authenticated opponent, not by a
-- caller that merely supplies another user's UUID.
create or replace function public.confirm_match_result(p_match_id uuid, p_user_id uuid)
returns void language plpgsql security definer set search_path = public as $$
declare
  m public.matches;
  r public.match_results;
begin
  if auth.uid() is null or auth.uid() <> p_user_id then
    raise exception 'not_authorized';
  end if;
  select * into m from public.matches where id = p_match_id for update;
  if not exists (select 1 from public.match_player_access where match_id = p_match_id and user_id = p_user_id) then
    raise exception 'not_authorized';
  end if;
  select * into r from public.match_results where match_id = p_match_id order by submitted_at desc limit 1;
  if r.id is null or m.status not in ('awaiting_result', 'disputed') then
    raise exception 'invalid_match_state';
  end if;
  if r.submitted_by = p_user_id then
    raise exception 'opponent_confirmation_required';
  end if;
  if not public.match_evidence_ready(p_match_id) then
    raise exception 'evidence_required';
  end if;
  update public.match_results set confirmed_at = now() where id = r.id;
  update public.matches
    set status = 'confirmed', confirmed_by = p_user_id, confirmed_at = now(),
        winner_registration_id = case
          when r.score_a > r.score_b then player_a_registration_id
          when r.score_b > r.score_a then player_b_registration_id
          else null
        end
    where id = p_match_id;
  insert into public.audit_logs(actor_id, action, entity_type, entity_id, metadata)
    values (p_user_id, 'match.confirmed', 'match', p_match_id, jsonb_build_object('result_id', r.id));
end; $$;

-- Dispute can only be opened by a participant and only once while awaiting
-- confirmation. The row-count check prevents audit records for no-op updates.
create or replace function public.dispute_match_result(p_match_id uuid, p_user_id uuid, p_reason text)
returns void language plpgsql security definer set search_path = public as $$
begin
  if auth.uid() is null or auth.uid() <> p_user_id then
    raise exception 'not_authorized';
  end if;
  if not exists (select 1 from public.match_player_access where match_id = p_match_id and user_id = p_user_id) then
    raise exception 'not_authorized';
  end if;
  if length(trim(coalesce(p_reason, ''))) = 0 then
    raise exception 'reason_required';
  end if;
  update public.matches set status = 'disputed'
    where id = p_match_id and status = 'awaiting_result';
  if not found then
    raise exception 'invalid_match_state';
  end if;
  insert into public.audit_logs(actor_id, action, entity_type, entity_id, metadata)
    values (p_user_id, 'match.disputed', 'match', p_match_id,
      jsonb_build_object('reason', trim(p_reason)));
end; $$;

-- Result writes must go through the locked SECURITY DEFINER RPC, which checks
-- participant access, state, score validity, and duplicate submissions.
drop policy if exists "players submit match results" on public.match_results;
revoke insert, update, delete on public.match_results from anon, authenticated;

-- A player may inspect their own match even before its season is public-running.
drop policy if exists "players read own matches" on public.matches;
create policy "players read own matches" on public.matches
  for select using (public.player_match_access(id) or public.is_staff());

-- Staff need read-only access to the immutable audit trail for dispute review.
drop policy if exists "staff read audit logs" on public.audit_logs;
create policy "staff read audit logs" on public.audit_logs
  for select using (public.is_staff());

