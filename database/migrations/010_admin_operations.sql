-- Admin operations: season lifecycle, staff disable state and audit metadata.
-- Additive migration; it does not delete existing tournament data.

alter table public.seasons add column if not exists updated_at timestamptz not null default now();
alter table public.seasons add column if not exists updated_by uuid references auth.users(id);
alter table public.seasons add column if not exists archived_at timestamptz;
alter table public.seasons add column if not exists archived_by uuid references auth.users(id);
alter table public.staff_roles add column if not exists disabled_at timestamptz;
alter table public.staff_roles add column if not exists disabled_by uuid references auth.users(id);
alter table public.audit_logs add column if not exists request_id text;

alter table public.seasons drop constraint if exists seasons_status_check;
alter table public.seasons add constraint seasons_status_check
  check (status in ('draft','open','closed','running','completed','archived'));

create or replace function public.is_staff()
returns boolean language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from public.staff_roles
    where user_id = auth.uid() and disabled_at is null
  );
$$;

create or replace function public.is_admin()
returns boolean language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from public.staff_roles
    where user_id = auth.uid()
      and role = 'admin'::public.staff_role
      and disabled_at is null
  );
$$;

create or replace function public.admin_set_staff_role(p_target_user_id uuid, p_role public.staff_role)
returns void language plpgsql security definer set search_path = public as $$
begin
  if not public.is_admin() then raise exception 'not_authorized'; end if;
  insert into public.staff_roles(user_id, role, disabled_at, disabled_by)
  values (p_target_user_id, p_role, null, null)
  on conflict (user_id) do update set role = excluded.role, disabled_at = null, disabled_by = null;
  insert into public.audit_logs(actor_id, action, entity_type, entity_id, metadata)
  values (auth.uid(), 'staff.role_changed', 'staff_role', p_target_user_id,
    jsonb_build_object('role', p_role));
end; $$;

create or replace function public.admin_disable_staff(p_target_user_id uuid)
returns void language plpgsql security definer set search_path = public as $$
begin
  if not public.is_admin() then raise exception 'not_authorized'; end if;
  if p_target_user_id = auth.uid() then raise exception 'cannot_disable_self'; end if;
  update public.staff_roles set disabled_at = now(), disabled_by = auth.uid()
    where user_id = p_target_user_id;
  if not found then raise exception 'staff_role_not_found'; end if;
  insert into public.audit_logs(actor_id, action, entity_type, entity_id, metadata)
  values (auth.uid(), 'staff.disabled', 'staff_role', p_target_user_id, '{}'::jsonb);
end; $$;

revoke all on function public.admin_set_staff_role(uuid, public.staff_role) from public;
revoke all on function public.admin_disable_staff(uuid) from public;
grant execute on function public.admin_set_staff_role(uuid, public.staff_role) to authenticated;
grant execute on function public.admin_disable_staff(uuid) to authenticated;

drop policy if exists "admins manage staff roles" on public.staff_roles;
create policy "admins manage staff roles" on public.staff_roles
  for all using (public.is_admin()) with check (public.is_admin());

drop policy if exists "admins manage seasons" on public.seasons;
create policy "admins manage seasons" on public.seasons
  for all using (public.is_admin()) with check (public.is_admin());

drop policy if exists "public can read open seasons" on public.seasons;
create policy "public can read public seasons" on public.seasons
  for select using (status in ('open','closed','running','completed'));
