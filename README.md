# GTournament1

PHP + Supabase foundation for the GTournament1 community tournament.

## Local setup

1. Copy `.env.example` to `.env` and set `SUPABASE_URL` and `SUPABASE_ANON_KEY`.
2. Run `database/migrations/001_initial.sql` in the Supabase SQL editor.
3. Run `database/migrations/002_competition_and_payments.sql` after migration 001.
4. Run `database/seed.sql` once to create the first open season.
4. Ensure PHP has `curl`, `fileinfo`, and sessions enabled.
5. Serve this directory with PHP 7.4+ (PHP 8 recommended).

The service role key is intentionally not required by the public app and must never be exposed to the browser. The `slips` bucket is private; uploaded files are addressed by user-scoped paths and are never rendered as public URLs.

## Current flows

- Public dashboard, tournaments, rules and rankings remain available without Supabase configuration.
- Account creation and password login use Supabase Auth.
- Registration requires an authenticated user, accepts JPG/PNG/PDF slips up to 5MB, and stores them in private Storage.
- The database enforces one registration per user/season and protects season capacity with a row lock and trigger.
- Competition domain services generate 8 groups of 4 and 6 round-robin fixtures per group (48 fixtures total).
- Ranking domain service calculates W=3, D=1, L=0 with points, goal difference and goals-for ordering.

## Before production

- Configure email templates and SMTP in Supabase Auth.
- Add Admin/Staff users in `staff_roles`.
- Add Staff review screens and signed-URL endpoints.
- Implement Group Stage/Knockout, match results, ranking formula and result audit actions.
- Run the security, backup/restore and UAT checklist in `SYSTEM_ANALYSIS_AND_DEVELOPMENT_SPEC.md`.
