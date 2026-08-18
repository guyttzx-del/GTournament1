<?php
declare(strict_types=1);
function load_env(string $path): void { if (!is_readable($path)) return; foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) { $line = trim($line); if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue; list($key, $value) = explode('=', $line, 2); $_ENV[trim($key)] = trim($value, " \t\"'"); } }
load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    $httpsRequest = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    if (env_value('APP_ENV', 'production') !== 'local' && $httpsRequest) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
function env_value(string $key, ?string $default = null): ?string {
    if (array_key_exists($key, $_ENV)) return (string) $_ENV[$key];
    $value = getenv($key);
    return $value === false ? $default : (string) $value;
}
function app_uses_mock(): bool { return env_value('APP_ENV', 'production') === 'local' && filter_var(env_value('LOCAL_MOCK', 'false'), FILTER_VALIDATE_BOOLEAN); }
function production_config_errors(): array
{
    if (app_uses_mock()) return [];
    $errors = [];
    $appEnv = env_value('APP_ENV', '');
    $appUrl = (string) env_value('APP_URL', '');
    $supabaseUrl = (string) env_value('SUPABASE_URL', '');
    $anonKey = (string) env_value('SUPABASE_ANON_KEY', '');
    $redirect = (string) env_value('SUPABASE_AUTH_REDIRECT_URL', '');
    if (!in_array($appEnv, ['local', 'staging', 'production'], true)) $errors[] = 'APP_ENV must be local, staging or production';
    if (!filter_var($appUrl, FILTER_VALIDATE_URL) || ($appEnv !== 'local' && !str_starts_with(strtolower($appUrl), 'https://'))) $errors[] = 'APP_URL is invalid or must use HTTPS outside local';
    if (!filter_var($supabaseUrl, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($supabaseUrl), 'https://')) $errors[] = 'SUPABASE_URL must use HTTPS';
    if ($anonKey === '' || str_contains($anonKey, 'replace-with')) $errors[] = 'SUPABASE_ANON_KEY is missing or still a placeholder';
    if (!filter_var($redirect, FILTER_VALIDATE_URL) || ($appEnv !== 'local' && !str_starts_with(strtolower($redirect), 'https://'))) $errors[] = 'SUPABASE_AUTH_REDIRECT_URL is invalid or must use HTTPS outside local';
    if ($appEnv !== 'local' && !filter_var(env_value('SESSION_SECURE_COOKIE', 'false'), FILTER_VALIDATE_BOOLEAN)) $errors[] = 'SESSION_SECURE_COOKIE must be true';
    $sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionPath) || !is_writable($sessionPath)) $errors[] = 'session storage is not writable';
    return $errors;
}
function app_configured(): bool { return app_uses_mock() || production_config_errors() === []; }
if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionPath)) @mkdir($sessionPath, 0700, true);
    session_save_path($sessionPath);
    session_name(env_value('SESSION_COOKIE_NAME', 'gtournament_session'));
    $secureDefault = env_value('APP_ENV', 'local') === 'production';
    $secureCookie = filter_var(env_value('SESSION_SECURE_COOKIE', $secureDefault ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    session_set_cookie_params(['httponly' => true, 'secure' => $secureCookie, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
if (env_value('APP_ENV', 'production') !== 'local') { ini_set('display_errors', '0'); ini_set('log_errors', '1'); }
