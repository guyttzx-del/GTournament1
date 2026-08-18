-- Controlled hard delete for empty seasons only.
-- Existing seasons with registrations, matches or public submissions remain protected.
create or replace function public.admin_delete_season(p_season_id uuid)
returns void language plpgsql security definer set search_path = public as $$
begin
  if not public.is_admin() then raise exception 'not_authorized'; end if;
  perform 1 from public.seasons where id = p_season_id for update;
  if not found then raise exception 'season_not_found'; end if;
  if exists (select 1 from public.registrations where season_id = p_season_id)
     or exists (select 1 from public.applicant_submissions where season_id = p_season_id)
     or exists (
       select 1 from public.matches m
       join public.registrations r on r.id in (m.player_a_registration_id, m.player_b_registration_id)
       where r.season_id = p_season_id
     ) then
    raise exception 'season_has_dependencies';
  end if;
  insert into public.audit_logs(actor_id, action, entity_type, entity_id, metadata)
  values (auth.uid(), 'season.deleted', 'season', p_season_id,
    jsonb_build_object('reason', 'admin_empty_season_delete'));
  delete from public.seasons where id = p_season_id;
end; $$;

revoke all on function public.admin_delete_season(uuid) from public;
grant execute on function public.admin_delete_season(uuid) to authenticated;
