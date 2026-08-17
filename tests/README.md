# Tests

Run the local server first, then execute:

```powershell
./tests/smoke.ps1
```

The smoke suite checks all public routes, assets, unknown-view fallback, CSRF rendering, and ranking scope navigation. It does not call Supabase and therefore does not replace authenticated end-to-end tests.

Run domain logic tests with:

```powershell
php tests/domain.php
```

Run service contract tests with:

```powershell
php tests/contracts.php
```
