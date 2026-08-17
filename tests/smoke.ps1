param(
    [string]$BaseUrl = 'http://127.0.0.1:8087'
)

$ErrorActionPreference = 'Stop'
$routes = @(
    '/?view=home', '/?view=tournaments', '/?view=rules', '/?view=ranking',
    '/?view=ranking&scope=club', '/?view=ranking&scope=all-time',
    '/?view=register', '/?view=auth', '/?view=account',
    '/?view=privacy', '/?view=terms', '/?view=contact', '/?view=unknown'
)
$assets = @('/assets/style.css', '/assets/app.js', '/assets/logo-gtournament.png')
$failures = [System.Collections.Generic.List[string]]::new()

function Assert-Status([string]$path) {
    try {
        $response = Invoke-WebRequest -Uri ($BaseUrl + $path) -UseBasicParsing
        if ($response.StatusCode -ne 200) { $failures.Add("$path returned $($response.StatusCode)") }
    } catch { $failures.Add("$path failed: $($_.Exception.Message)") }
}

foreach ($route in $routes) { Assert-Status $route }
try { Invoke-WebRequest -Uri ($BaseUrl + '/?view=staff') -UseBasicParsing | Out-Null; $failures.Add('/?view=staff should reject unauthenticated access') } catch { if ($_.Exception.Response.StatusCode.value__ -ne 403) { $failures.Add('/?view=staff did not return 403') } }
try { Invoke-WebRequest -Uri ($BaseUrl + '/?view=admin') -UseBasicParsing | Out-Null; $failures.Add('/?view=admin should reject unauthenticated access') } catch { if ($_.Exception.Response.StatusCode.value__ -ne 403) { $failures.Add('/?view=admin did not return 403') } }
foreach ($asset in $assets) { Assert-Status $asset }

$homeResponse = Invoke-WebRequest -Uri ($BaseUrl + '/?view=home') -UseBasicParsing
if ($homeResponse.Content -notmatch 'GTournament1') { $failures.Add('home page does not contain the brand marker') }

$auth = Invoke-WebRequest -Uri ($BaseUrl + '/?view=auth') -UseBasicParsing
if ($auth.Content -notmatch 'name="_csrf"') { $failures.Add('auth page does not contain a CSRF field') }

$ranking = Invoke-WebRequest -Uri ($BaseUrl + '/?view=ranking&scope=club') -UseBasicParsing
if ($ranking.Content -notmatch 'Club Ranking') { $failures.Add('club ranking scope is not rendered') }

if ($failures.Count -gt 0) {
    $failures | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Output "Smoke tests passed: $($routes.Count) routes + $($assets.Count) assets"
