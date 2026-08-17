<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Support/Csrf.php';
require_once __DIR__ . '/Support/Flash.php';
require_once __DIR__ . '/Infrastructure/SupabaseClient.php';
require_once __DIR__ . '/Domain/RegistrationService.php';
require_once __DIR__ . '/Domain/RankingService.php';
require_once __DIR__ . '/Domain/RegistrationStatus.php';
require_once __DIR__ . '/Domain/CompetitionService.php';
function supabase(): ?SupabaseClient { static $client; if ($client instanceof SupabaseClient) return $client; if (!app_configured()) return null; return $client = new SupabaseClient((string) env_value('SUPABASE_URL'), (string) env_value('SUPABASE_ANON_KEY'), $_SESSION['access_token'] ?? null); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_auth(): void { if (!current_user()) { flash('error', 'กรุณาเข้าสู่ระบบก่อนดำเนินการต่อ'); header('Location: ?view=auth'); exit; } }
function is_staff_user(): bool { static $staff; if ($staff !== null) return $staff; if (!current_user() || !supabase()) return $staff = false; try { $rows = supabase()->rest('staff_roles', 'user_id=eq.' . rawurlencode((string) current_user()['id']) . '&limit=1'); return $staff = (bool) $rows; } catch (Throwable) { return $staff = false; } }
function require_staff(): void { require_auth(); if (!is_staff_user()) { http_response_code(403); exit('Forbidden'); } }
