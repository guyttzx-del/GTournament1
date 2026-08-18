-- Allow only Admin roles to create and update tournament seasons.
-- This fixes the Admin Season form while keeping public season reads intact.

create or replace function public.is_admin()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.staff_roles
    where user_id = auth.uid()
      and role = 'admin'::public.staff_role
  );
$$;

revoke all on function public.is_admin() from public;
grant execute on function public.is_admin() to authenticated;

drop policy if exists "admins manage seasons" on public.seasons;
create policy "admins manage seasons"
  on public.seasons
  for all
  using (public.is_admin())
  with check (public.is_admin());
