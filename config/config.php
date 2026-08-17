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
}
function env_value(string $key, ?string $default = null): ?string { return $_ENV[$key] ?? getenv($key) ?: $default; }
function app_configured(): bool { return (bool) (env_value('SUPABASE_URL') && env_value('SUPABASE_ANON_KEY')); }
if (session_status() !== PHP_SESSION_ACTIVE) { $sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions'; if (!is_dir($sessionPath)) @mkdir($sessionPath, 0700, true); session_save_path($sessionPath); session_name(env_value('SESSION_COOKIE_NAME', 'gtournament_session')); session_set_cookie_params(['httponly' => true, 'secure' => filter_var(env_value('SESSION_SECURE_COOKIE', 'true'), FILTER_VALIDATE_BOOLEAN), 'samesite' => 'Lax', 'path' => '/']); session_start(); }
