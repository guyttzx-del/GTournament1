# GTournament1 VPS Production Checklist

## Before deploy

- [ ] Provision a separate staging Supabase project.
- [ ] Apply migrations `001` through `007` in order.
- [ ] Confirm the production Supabase project has a recent backup before applying or seeding data.
- [ ] Configure Auth email verification, SMTP and the exact HTTPS redirect URL.
- [ ] Create private `slips` and `match-evidence` buckets.
- [ ] Add Staff/Admin rows only after verifying account IDs.
- [ ] Copy environment values to the VPS secret store; never commit `.env`.
- [ ] Set `APP_ENV=staging` or `production`, `LOCAL_MOCK=false`, HTTPS URLs and secure cookies.
- [ ] Configure PHP-FPM, Nginx, TLS, upload limits and protected paths.
- [ ] Confirm `GET /?view=health` returns `200` without exposing secrets.
- [ ] Set DNS A/AAAA records for `gtournament.online` and `www.gtournament.online` to the VPS.

## Staging acceptance

- [ ] Anonymous user cannot access Account actions or Staff routes.
- [ ] Player can register and upload a private slip.
- [ ] Staff can inspect a signed slip URL.
- [ ] Player can submit one match result and evidence.
- [ ] A second player cannot overwrite the first player's result.
- [ ] Opponent confirmation requires Screenshot and required Video Link.
- [ ] Staff can resolve a dispute once and every decision creates an audit record.
- [ ] Player cannot create arbitrary audit actions.
- [ ] `anon` has no EXECUTE privilege on match/audit RPCs.
- [ ] `anon` and `authenticated` cannot INSERT/UPDATE/DELETE `match_results` directly; result writes use the RPC.
- [ ] Participant match SELECT policy and private evidence policies pass with player A, player B, Staff and anonymous test sessions.
- [ ] Backup and restore drill completes successfully.

## Release and rollback

- [ ] Take a database backup before migration.
- [ ] Deploy code and reload PHP-FPM.
- [ ] Apply migrations only after backup succeeds.
- [ ] Verify health, Auth, registration, upload and match flows.
- [ ] Monitor PHP/Nginx/Supabase errors and 5xx responses.
- [ ] Keep the previous release available for rollback.
