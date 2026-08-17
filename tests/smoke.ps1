param(
    [string]$BaseUrl = 'http://127.0.0.1:8087'
)

$ErrorActionPreference = 'Stop'
$routes = @(
    '/?view=home', '/?view=tournaments', '/?view=rules', '/?view=ranking',
    '/?view=ranking&scope=club', '/?view=ranking&scope=all-time',
    '/?view=register', '/?view=auth', '/?view=account', '/?view=staff',
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
