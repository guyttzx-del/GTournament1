<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Support/Csrf.php';
require_once __DIR__ . '/Support/Flash.php';
require_once __DIR__ . '/Support/RateLimit.php';
require_once __DIR__ . '/Infrastructure/SupabaseClient.php';
require_once __DIR__ . '/Infrastructure/MockSupabaseClient.php';
require_once __DIR__ . '/Domain/RegistrationService.php';
require_once __DIR__ . '/Domain/RankingService.php';
require_once __DIR__ . '/Domain/RegistrationStatus.php';
require_once __DIR__ . '/Domain/CompetitionService.php';
require_once __DIR__ . '/Domain/AuthService.php';
require_once __DIR__ . '/Domain/SeasonService.php';
require_once __DIR__ . '/Domain/MatchService.php';
function supabase(): ?SupabaseClient { static $client; if ($client instanceof SupabaseClient) return $client; if (!app_configured()) return null; if (app_uses_mock()) return $client = new MockSupabaseClient(); return $client = new SupabaseClient((string) env_value('SUPABASE_URL'), (string) env_value('SUPABASE_ANON_KEY'), $_SESSION['access_token'] ?? null); }
function refresh_session_if_needed(): void { if (!empty($_SESSION['refresh_token']) && !empty($_SESSION['expires_at']) && (int) $_SESSION['expires_at'] < time() + 60 && app_configured()) { try { establish_session((new AuthService(supabase()))->refresh((string) $_SESSION['refresh_token'])); } catch (Throwable) { $_SESSION = []; } } }
function current_user(): ?array { refresh_session_if_needed(); return $_SESSION['user'] ?? null; }
function session_expired(): bool { $timeout = max(300, (int) env_value('SESSION_IDLE_TIMEOUT', '3600')); return isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $timeout; }
if (session_expired()) { $_SESSION = []; session_regenerate_id(true); }
$_SESSION['last_activity'] = time();
function establish_session(array $response): void { session_regenerate_id(true); $_SESSION['access_token'] = $response['access_token'] ?? null; $_SESSION['refresh_token'] = $response['refresh_token'] ?? null; $_SESSION['expires_at'] = time() + (int) ($response['expires_in'] ?? 3600); $_SESSION['user'] = $response['user'] ?? null; }
function require_auth(): void { if (!current_user()) { flash('error', 'กรุณาเข้าสู่ระบบก่อนดำเนินการต่อ'); header('Location: ?view=auth'); exit; } }
function is_staff_user(): bool { static $staff; if ($staff !== null) return $staff; if (!current_user() || !supabase()) return $staff = false; try { $rows = supabase()->rest('staff_roles', 'user_id=eq.' . rawurlencode((string) current_user()['id']) . '&disabled_at=is.null&limit=1'); return $staff = (bool) $rows; } catch (Throwable) { return $staff = false; } }
function is_admin_user(): bool { static $admin; if ($admin !== null) return $admin; if (!current_user() || !supabase()) return $admin = false; try { $rows = supabase()->rest('staff_roles', 'user_id=eq.' . rawurlencode((string) current_user()['id']) . '&role=eq.admin&disabled_at=is.null&limit=1'); return $admin = (bool) $rows; } catch (Throwable) { return $admin = false; } }
function require_staff(): void { require_auth(); if (!is_staff_user()) { http_response_code(403); exit('Forbidden'); } }
function require_admin(): void { require_auth(); if (!is_admin_user()) { http_response_code(403); exit('Forbidden'); } }
