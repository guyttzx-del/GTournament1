create or replace function public.confirm_match_result(p_match_id uuid, p_user_id uuid)
returns void language plpgsql security definer set search_path = public as $$
declare m public.matches; r public.match_results;
begin
  select * into m from public.matches where id=p_match_id for update;
  if not exists (select 1 from public.match_player_access where match_id=p_match_id and user_id=p_user_id) then raise exception 'not_authorized'; end if;
  select * into r from public.match_results where match_id=p_match_id order by submitted_at desc limit 1;
  if r.id is null or m.status not in ('awaiting_result','disputed') then raise exception 'invalid_match_state'; end if;
  update public.match_results set confirmed_at=now() where id=r.id;
  update public.matches set status='confirmed', confirmed_by=p_user_id, confirmed_at=now(), winner_registration_id=case when r.score_a>r.score_b then player_a_registration_id when r.score_b>r.score_a then player_b_registration_id else null end where id=p_match_id;
  insert into public.audit_logs(actor_id,action,entity_type,entity_id,metadata) values(p_user_id,'match.confirmed','match',p_match_id,jsonb_build_object('result_id',r.id));
end; $$;

create or replace function public.dispute_match_result(p_match_id uuid, p_user_id uuid, p_reason text)
returns void language plpgsql security definer set search_path = public as $$
begin
  if not exists (select 1 from public.match_player_access where match_id=p_match_id and user_id=p_user_id) then raise exception 'not_authorized'; end if;
  if length(trim(coalesce(p_reason,'')))=0 then raise exception 'reason_required'; end if;
  update public.matches set status='disputed' where id=p_match_id and status='awaiting_result';
  insert into public.audit_logs(actor_id,action,entity_type,entity_id,metadata) values(p_user_id,'match.disputed','match',p_match_id,jsonb_build_object('reason',trim(p_reason)));
end; $$;
revoke all on function public.confirm_match_result(uuid,uuid) from public;
revoke all on function public.dispute_match_result(uuid,uuid,text) from public;
grant execute on function public.confirm_match_result(uuid,uuid) to authenticated;
grant execute on function public.dispute_match_result(uuid,uuid,text) to authenticated;
