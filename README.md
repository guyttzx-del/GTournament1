# GTournament1

PHP + Supabase foundation for the GTournament1 community tournament.

## Local setup

1. Copy `.env.example` to `.env`. Local mode uses the safe mock provider by default (`APP_ENV=local`, `LOCAL_MOCK=true`); set `LOCAL_MOCK=false` with Supabase credentials when connecting to a project.
2. Run `database/migrations/001_initial.sql` in the Supabase SQL editor.
3. Run `database/migrations/002_competition_and_payments.sql` after migration 001.
4. Run `database/seed.sql` once to create the first open season.
5. Ensure PHP 8.1+ has `curl`, `fileinfo`, and sessions enabled.
6. Serve this directory with PHP 8.1+.

The service role key is intentionally not required by the public app and must never be exposed to the browser. The `slips` bucket is private; uploaded files are addressed by user-scoped paths and are never rendered as public URLs.

## Current flows

- Public dashboard, tournaments, rules and rankings remain available without Supabase configuration.
- Account creation and password login use Supabase Auth.
- Registration requires an authenticated user, accepts JPG/PNG/PDF slips up to 5MB, and stores them in private Storage.
- The database enforces one registration per user/season and protects season capacity with a row lock and trigger.
- Competition domain services generate 8 groups of 4 and 6 round-robin fixtures per group (48 fixtures total).
- Ranking domain service calculates W=3, D=1, L=0 with points, goal difference and goals-for ordering.
- Auth service now handles email verification checks, password reset requests, session expiry and local/production cookie defaults.
- Registration re-submission is supported for rejected/pending-payment entries; migration 003 adds an atomic capacity reservation RPC.
- Match result submission, confirmation and dispute contracts are available through `MatchService` and migration 004 RPCs.
- Match evidence workflow is available through `MatchService` and migrations 005–007: private Screenshot upload for every match, allowed YouTube/Google Drive recording links for Semi-Finals/Finals, Staff/Admin dispute resolution, and production access hardening.
- Local mock mode includes a demo player, staff account, registration, opponent and match so the core workflow can be tested without transmitting data to Supabase.
- `GET /?view=health` returns a secret-free environment/session/Supabase readiness response for deployment checks.

## Before production

- Configure email templates and SMTP in Supabase Auth.
- Add Admin/Staff users in `staff_roles`.
- Apply migrations 001–007 and validate RLS with anonymous/player/staff/admin test accounts before public launch.
- Add Admin/Staff users in `staff_roles` and configure Auth email/SMTP.
- Configure the private `match-evidence` bucket by applying migration 005 after migrations 001-004, then apply hardening migrations 006–007.
- Run the security, backup/restore and UAT checklist in `SYSTEM_ANALYSIS_AND_DEVELOPMENT_SPEC.md`.
- Set `APP_URL=https://gtournament.online` and `SUPABASE_AUTH_REDIRECT_URL=https://gtournament.online/?view=auth` in the VPS secret store.
- Use `deploy/PRODUCTION_CHECKLIST.md` and `deploy/UBUNTU_DEPLOY.md` before exposing the site publicly.
