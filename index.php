<?php
declare(strict_types=1);
require_once __DIR__ . '/src/Application.php';

$appConfigured = app_configured();
$isProduction = env_value('APP_ENV', 'production') === 'production';
$user = current_user();
$staff = is_staff_user();
$admin = is_admin_user();
$error = null;
$success = flash('success');
$season = ['fee' => 25.0];
$view = $_GET['view'] ?? 'home';
$configErrors = production_config_errors();

if ($view === 'player-search') {
    header('Content-Type: application/json; charset=utf-8');
    $query = trim((string) ($_GET['q'] ?? ''));
    if (!$appConfigured || mb_strlen($query) < 2) { echo json_encode([]); exit; }
    try { echo json_encode(supabase()->rpc('search_public_players', ['p_query' => $query]), JSON_UNESCAPED_UNICODE); } catch (Throwable $searchException) { error_log('player_search ' . $searchException->getMessage()); echo json_encode([]); }
    exit;
}

if ($view === 'health') {
    $checks = ['environment' => $configErrors === [], 'session_storage' => is_writable(__DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions'), 'supabase' => false];
    if ($checks['environment'] && !app_uses_mock()) {
        try { supabase()->rest('seasons', 'limit=1'); $checks['supabase'] = true; } catch (Throwable $healthException) { error_log('health.supabase ' . $healthException->getMessage()); }
    } else { $checks['supabase'] = app_uses_mock(); }
    $healthy = !in_array(false, $checks, true);
    http_response_code($healthy ? 200 : 503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = $_POST['action'] ?? '';
        enforce_rate_limit('post:' . $action . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), in_array($action, ['login', 'signup', 'forgot-password'], true) ? 8 : 30, 300);
        if (!$appConfigured) throw new RuntimeException('ระบบยังไม่ได้ตั้งค่า Supabase กรุณาสร้างไฟล์ .env จาก .env.example ก่อน');
        if ($action === 'public_applicant_registration') {
            $seasonId = trim((string) ($_POST['season_id'] ?? ''));
            $applicantType = $_POST['applicant_type'] === 'new' ? 'new' : 'returning';
            $competitionName = trim((string) ($_POST['competition_name'] ?? ''));
            $nickname = trim((string) ($_POST['nickname'] ?? ''));
            $facebookName = trim((string) ($_POST['facebook_name'] ?? ''));
            $facebookUrl = trim((string) ($_POST['facebook_url'] ?? ''));
            $club = trim((string) ($_POST['club'] ?? ''));
            $existingPlayerId = trim((string) ($_POST['existing_player_id'] ?? ''));
            if ($seasonId === '' || $competitionName === '' || $nickname === '') throw new InvalidArgumentException('กรุณาเลือก Season และกรอกชื่อที่ใช้แข่งกับชื่อเล่น');
            if (mb_strlen($competitionName) > 80 || mb_strlen($nickname) > 40) throw new InvalidArgumentException('ชื่อผู้สมัครยาวเกินกำหนด');
            if ($facebookUrl !== '' && (!filter_var($facebookUrl, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($facebookUrl), 'https://'))) throw new InvalidArgumentException('ลิงก์ Facebook ต้องเป็น HTTPS URL');
            $profileImagePath = null;
            $profile = $_FILES['profile_image'] ?? [];
            if (!empty($profile['name'])) {
                if (($profile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($profile['size'] ?? 0) > 5 * 1024 * 1024) throw new InvalidArgumentException('รูปโปรไฟล์ต้องมีขนาดไม่เกิน 5MB');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) ($profile['tmp_name'] ?? ''));
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                if (!isset($extensions[$mime])) throw new InvalidArgumentException('รูปโปรไฟล์ต้องเป็น JPG หรือ PNG เท่านั้น');
                $profileImagePath = 'public/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                supabase()->storageUpload('profile-images', $profileImagePath, ['tmp_name' => $profile['tmp_name'], 'type' => $mime]);
            }
            $seasonRows = supabase()->rest('seasons', 'id=eq.' . rawurlencode($seasonId) . '&status=eq.open&limit=1');
            if (!$seasonRows) throw new InvalidArgumentException('Season นี้ยังไม่เปิดรับสมัคร');
            supabase()->rest('applicant_submissions', '', 'POST', [[
                'season_id' => $seasonId, 'applicant_type' => $applicantType,
                'competition_name' => $competitionName, 'nickname' => $nickname,
                'facebook_name' => $facebookName ?: null, 'facebook_url' => $facebookUrl ?: null,
                'club' => $club ?: null, 'existing_player_id' => $existingPlayerId !== '' ? $existingPlayerId : null, 'profile_image_path' => $profileImagePath,
            ]]);
            flash('success', 'ส่งข้อมูลผู้สมัครแล้ว ทีมงาน GTournament1 จะตรวจสอบให้'); header('Location: ?view=register'); exit;
        }
        if ($action === 'staff_login') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') throw new InvalidArgumentException('กรุณากรอกอีเมลและรหัสผ่านของทีมงาน');
            $session = (new AuthService(supabase()))->signIn($email, $password);
            establish_session($session);
            $sessionDb = new SupabaseClient((string) env_value('SUPABASE_URL'), (string) env_value('SUPABASE_ANON_KEY'), (string) ($session['access_token'] ?? ''));
            $roleRows = $sessionDb->rest('staff_roles', 'user_id=eq.' . rawurlencode((string) ($session['user']['id'] ?? '')) . '&limit=1');
            if (!$roleRows) { $_SESSION = []; session_regenerate_id(true); throw new InvalidArgumentException('บัญชีนี้ไม่มีสิทธิ์ทีมงาน'); }
            $role = (string) ($roleRows[0]['role'] ?? 'staff');
            flash('success', 'เข้าสู่ระบบทีมงานสำเร็จ');
            header('Location: ?view=' . ($role === 'admin' ? 'admin' : 'staff'));
            exit;
        }
        if (in_array($action, ['signup', 'login', 'forgot-password'], true)) {
            throw new InvalidArgumentException('ระบบบัญชีถูกปิดใช้งานชั่วคราว กรุณาติดต่อทีมงาน');
        }
        if ($action === 'logout') { session_destroy(); header('Location: ?view=home'); exit; }
        if ($action === 'review_registration') {
            require_staff();
            $registrationId = (string) ($_POST['registration_id'] ?? ''); $sourceTable = (string) ($_POST['source_table'] ?? 'registrations'); $status = $_POST['status'] === 'approved' ? 'approved' : 'rejected';
            if (!in_array($sourceTable, ['registrations', 'applicant_submissions'], true)) throw new InvalidArgumentException('แหล่งข้อมูลใบสมัครไม่ถูกต้อง');
            $currentRows = supabase()->rest($sourceTable, 'id=eq.' . rawurlencode($registrationId) . '&limit=1');
            if (!$currentRows && $sourceTable === 'registrations') { $sourceTable = 'applicant_submissions'; $currentRows = supabase()->rest($sourceTable, 'id=eq.' . rawurlencode($registrationId) . '&limit=1'); }
            $currentStatus = (string) ($currentRows[0]['status'] ?? '');
            if (!RegistrationStatus::canTransition($currentStatus, $status)) throw new InvalidArgumentException('สถานะใบสมัครนี้ไม่สามารถเปลี่ยนเป็นสถานะที่เลือกได้');
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($status === 'rejected' && $reason === '') throw new InvalidArgumentException('กรุณาระบุเหตุผลเมื่อปฏิเสธใบสมัคร');
            supabase()->rest($sourceTable, 'id=eq.' . rawurlencode($registrationId), 'PATCH', ['status' => $status, 'rejection_reason' => $status === 'rejected' ? $reason : null]);
            if ($sourceTable === 'registrations') supabase()->rest('payments', 'registration_id=eq.' . rawurlencode($registrationId), 'PATCH', ['status' => $status === 'approved' ? 'approved' : 'rejected', 'reviewed_by' => $user['id'], 'reviewed_at' => gmdate('c')]);
            supabase()->rpc('append_audit_log', ['p_actor_id' => $user['id'], 'p_action' => 'registration.' . $status, 'p_entity_type' => 'registration', 'p_entity_id' => $registrationId]);
            flash('success', 'อัปเดตสถานะใบสมัครแล้ว'); header('Location: ?view=staff'); exit;
        }
        if ($action === 'save_season') {
            require_admin();
            $seasonId = trim((string) ($_POST['season_id'] ?? ''));
            $payload = ['name' => trim((string) ($_POST['name'] ?? '')), 'subtitle' => trim((string) ($_POST['subtitle'] ?? '')), 'status' => (string) ($_POST['status'] ?? 'draft'), 'capacity' => (int) ($_POST['capacity'] ?? 0), 'entry_fee' => (float) ($_POST['entry_fee'] ?? 0), 'registration_opens_at' => $_POST['registration_opens_at'] ?: null, 'registration_closes_at' => $_POST['registration_closes_at'] ?: null, 'promptpay_name' => trim((string) ($_POST['promptpay_name'] ?? '')), 'promptpay_number' => trim((string) ($_POST['promptpay_number'] ?? '')), 'expected_payment' => (float) ($_POST['expected_payment'] ?? 0)];
            validate_season_payload($payload);
            $existing = null;
            if ($seasonId !== '') {
                $rows = supabase()->rest('seasons', 'id=eq.' . rawurlencode($seasonId) . '&limit=1');
                $existing = $rows[0] ?? null;
                if (!$existing) throw new InvalidArgumentException('ไม่พบ Season ที่ต้องการแก้ไข');
                validate_season_transition((string) ($existing['status'] ?? 'draft'), $payload['status']);
                if (in_array((string) ($existing['status'] ?? ''), ['completed', 'archived'], true)) throw new InvalidArgumentException('Season นี้ปิดการแก้ไขแล้ว');
                $registrationRows = supabase()->rest('registrations', 'season_id=eq.' . rawurlencode($seasonId) . '&limit=1000');
                $activeRegistrations = count(array_filter($registrationRows, static fn(array $row): bool => !in_array((string) ($row['status'] ?? ''), ['rejected', 'cancelled'], true)));
                if ($payload['capacity'] < $activeRegistrations) throw new InvalidArgumentException('Capacity ใหม่ต่ำกว่าจำนวนผู้สมัครปัจจุบัน');
                supabase()->rest('seasons', 'id=eq.' . rawurlencode($seasonId), 'PATCH', $payload + ['updated_at' => gmdate('c'), 'updated_by' => $user['id']]);
                $event = 'season.updated';
            } else {
                $created = supabase()->rest('seasons', '', 'POST', [['code' => strtoupper('GT-' . bin2hex(random_bytes(3)))] + $payload + ['updated_by' => $user['id']]]);
                $seasonId = (string) ($created[0]['id'] ?? '');
                $event = 'season.created';
            }
            supabase()->rpc('append_audit_log', ['p_actor_id' => $user['id'], 'p_action' => $event, 'p_entity_type' => 'season', 'p_entity_id' => $seasonId ?: null, 'p_metadata' => $payload]);
            flash('success', 'บันทึกข้อมูล Season แล้ว'); header('Location: ?view=admin'); exit;
        }
        if ($action === 'duplicate_season') {
            require_admin();
            $seasonId = trim((string) ($_POST['season_id'] ?? ''));
            $rows = supabase()->rest('seasons', 'id=eq.' . rawurlencode($seasonId) . '&limit=1');
            if (!$rows) throw new InvalidArgumentException('ไม่พบ Season ที่ต้องการทำสำเนา');
            $source = $rows[0];
            $copy = ['code' => strtoupper('GT-' . bin2hex(random_bytes(3))), 'name' => trim((string) ($source['name'] ?? '')) . ' Copy', 'subtitle' => $source['subtitle'] ?? null, 'status' => 'draft', 'capacity' => (int) ($source['capacity'] ?? 0), 'entry_fee' => (float) ($source['entry_fee'] ?? 0), 'registration_opens_at' => null, 'registration_closes_at' => null, 'promptpay_name' => $source['promptpay_name'] ?? null, 'promptpay_number' => $source['promptpay_number'] ?? null, 'expected_payment' => (float) ($source['expected_payment'] ?? $source['entry_fee'] ?? 0), 'updated_by' => $user['id']];
            validate_season_payload($copy);
            $created = supabase()->rest('seasons', '', 'POST', [$copy]);
            $newId = (string) ($created[0]['id'] ?? '');
            supabase()->rpc('append_audit_log', ['p_actor_id' => $user['id'], 'p_action' => 'season.duplicated', 'p_entity_type' => 'season', 'p_entity_id' => $newId ?: null, 'p_metadata' => ['source_season_id' => $seasonId]]);
            flash('success', 'ทำสำเนา Season เป็นฉบับร่างแล้ว'); header('Location: ?view=admin'); exit;
        }
        if ($action === 'archive_season') {
            require_admin();
            $seasonId = trim((string) ($_POST['season_id'] ?? ''));
            $rows = supabase()->rest('seasons', 'id=eq.' . rawurlencode($seasonId) . '&limit=1');
            if (!$rows) throw new InvalidArgumentException('ไม่พบ Season ที่ต้องการ Archive');
            if (in_array((string) ($rows[0]['status'] ?? ''), ['archived'], true)) throw new InvalidArgumentException('Season นี้ Archive แล้ว');
            $registrations = supabase()->rest('registrations', 'season_id=eq.' . rawurlencode($seasonId) . '&limit=1000');
            $activeRegistrations = array_filter($registrations, static fn(array $row): bool => !in_array((string) ($row['status'] ?? ''), ['rejected', 'cancelled'], true));
            $matches = supabase()->rest('matches', 'season_id=eq.' . rawurlencode($seasonId) . '&status=not.in.(confirmed,void)&limit=1');
            if ($activeRegistrations || $matches) throw new InvalidArgumentException('ยังมีใบสมัครหรือ Match ที่ active ไม่สามารถ Archive ได้');
            supabase()->rest('seasons', 'id=eq.' . rawurlencode($seasonId), 'PATCH', ['status' => 'archived', 'archived_at' => gmdate('c'), 'archived_by' => $user['id'], 'updated_at' => gmdate('c'), 'updated_by' => $user['id']]);
            supabase()->rpc('append_audit_log', ['p_actor_id' => $user['id'], 'p_action' => 'season.archived', 'p_entity_type' => 'season', 'p_entity_id' => $seasonId]);
            flash('success', 'Archive Season แล้ว'); header('Location: ?view=admin'); exit;
        }
        if ($action === 'delete_season') {
            require_admin();
            $seasonId = trim((string) ($_POST['season_id'] ?? ''));
            if (!preg_match('/^[0-9a-f-]{36}$/i', $seasonId)) throw new InvalidArgumentException('Season ID ไม่ถูกต้อง');
            supabase()->rpc('admin_delete_season', ['p_season_id' => $seasonId]);
            flash('success', 'ลบ Season ที่ไม่มีข้อมูลผูกอยู่แล้ว'); header('Location: ?view=admin'); exit;
        }
        if ($action === 'change_staff_role') {
            require_admin();
            $staffUserId = trim((string) ($_POST['staff_user_id'] ?? ''));
            $role = (string) ($_POST['role'] ?? 'staff');
            if (!preg_match('/^[0-9a-f-]{36}$/i', $staffUserId) || !in_array($role, ['staff', 'admin'], true)) throw new InvalidArgumentException('กรุณาระบุ User ID และ Role ที่ถูกต้อง');
            supabase()->rpc('admin_set_staff_role', ['p_target_user_id' => $staffUserId, 'p_role' => $role]);
            flash('success', 'อัปเดตสิทธิ์ทีมงานแล้ว'); header('Location: ?view=admin-staff'); exit;
        }
        if ($action === 'disable_staff') {
            require_admin();
            $staffUserId = trim((string) ($_POST['staff_user_id'] ?? ''));
            if (!preg_match('/^[0-9a-f-]{36}$/i', $staffUserId)) throw new InvalidArgumentException('User ID ไม่ถูกต้อง');
            supabase()->rpc('admin_disable_staff', ['p_target_user_id' => $staffUserId]);
            flash('success', 'ปิดสิทธิ์ทีมงานแล้ว'); header('Location: ?view=admin-staff'); exit;
        }
        if ($action === 'registration' || $action === 'submit' || $action === 'resubmit') {
            require_auth();
            $seasonId = (string) ($_POST['season_id'] ?? '');
            $remoteSeason = (new SeasonService(supabase()))->findOpen($seasonId);
            if (empty($_POST['accept_rules'])) throw new InvalidArgumentException('กรุณายอมรับกติกาการแข่งขันก่อนส่งใบสมัคร');
            $service = new RegistrationService(supabase());
            $service->submit($user, $_POST, $_FILES['slip'] ?? [], $seasonId, (float) ($remoteSeason['expected_payment'] ?? $remoteSeason['entry_fee']));
            flash('success', 'ส่งใบสมัครและสลิปแล้ว ทีมงานกำลังตรวจสอบ'); header('Location: ?view=account'); exit;
        }
        if ($action === 'submit_match_result') {
            require_auth();
            (new MatchService(supabase()))->submitResult((string) ($_POST['match_id'] ?? ''), (string) $user['id'], (int) ($_POST['score_a'] ?? -1), (int) ($_POST['score_b'] ?? -1));
            flash('success', 'ส่งผลการแข่งขันแล้ว รอคู่แข่งยืนยัน'); header('Location: ?view=account'); exit;
        }
        if ($action === 'confirm_match') {
            require_auth();
            (new MatchService(supabase()))->confirm((string) ($_POST['match_id'] ?? ''), (string) $user['id']);
            flash('success', 'ยืนยันผลการแข่งขันแล้ว'); header('Location: ?view=account'); exit;
        }
        if ($action === 'dispute_match') {
            require_auth();
            (new MatchService(supabase()))->dispute((string) ($_POST['match_id'] ?? ''), (string) $user['id'], (string) ($_POST['reason'] ?? ''));
            flash('success', 'ส่งคำโต้แย้งให้ทีมงานตรวจสอบแล้ว'); header('Location: ?view=account'); exit;
        }
        if ($action === 'submit_match_evidence') {
            require_auth();
            (new MatchService(supabase()))->submitEvidence((string) ($_POST['match_id'] ?? ''), (string) $user['id'], $_FILES['screenshot'] ?? [], (string) ($_POST['video_url'] ?? ''));
            flash('success', 'ส่งหลักฐานการแข่งขันแล้ว'); header('Location: ?view=account'); exit;
        }
        if ($action === 'resolve_match_dispute') {
            require_staff();
            (new MatchService(supabase()))->resolveDispute((string) ($_POST['match_id'] ?? ''), (string) $user['id'], (string) ($_POST['outcome'] ?? ''), (string) ($_POST['note'] ?? ''));
            flash('success', 'บันทึกผลการตัดสินข้อพิพาทแล้ว'); header('Location: ?view=staff'); exit;
        }
    } catch (Throwable $exception) {
        if ($isProduction) error_log((string) $exception);
        $exceptionText = strtolower($exception->getMessage());
        if (str_contains($exceptionText, 'email not confirmed') || str_contains($exceptionText, 'กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ')) {
            $error = 'กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ';
        } elseif (str_contains($exceptionText, 'invalid login credentials')) {
            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
        } elseif (str_contains($exceptionText, 'weak_password') || str_contains($exceptionText, 'password should') || str_contains($exceptionText, 'รหัสผ่านต้องมีอย่างน้อย')) {
            $error = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษรและผ่านนโยบายความปลอดภัย';
        } elseif (str_contains($exceptionText, 'user already registered') || str_contains($exceptionText, 'already registered')) {
            $error = 'อีเมลนี้มีบัญชีอยู่แล้ว กรุณาเข้าสู่ระบบหรือใช้ลืมรหัสผ่าน';
        } elseif (str_contains($exceptionText, 'rate limit') || str_contains($exceptionText, 'too many requests') || str_contains($exceptionText, 'over_email_send_rate_limit')) {
            $error = 'ส่งอีเมลยืนยันบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่';
        } elseif (str_contains($exceptionText, 'email provider') || str_contains($exceptionText, 'smtp')) {
            $error = 'ระบบอีเมลยืนยันยังไม่พร้อม กรุณาติดต่อทีมงาน';
        } elseif (str_contains($exceptionText, 'requested path is invalid')) {
            $error = 'ลิงก์ยืนยันอีเมลยังตั้งค่าไม่ถูกต้อง กรุณาติดต่อทีมงาน';
        } else {
            $error = $isProduction && !($exception instanceof InvalidArgumentException) ? 'ระบบขัดข้องชั่วคราว กรุณาลองใหม่ภายหลัง' : $exception->getMessage();
        }
    }
}

$season = [
    'name' => 'GTournament1 Season 01',
    'subtitle' => 'E-Football Community Tournament',
    'status' => 'เปิดรับสมัคร',
    'players' => 24,
    'capacity' => 32,
    'fee' => 25,
    'open' => 'ทุกวันศุกร์ประมาณ 16:00 น.',
    'close' => 'จับสายทันทีหลังปิดรับสมัคร',
    'prize' => 'เงินรางวัลอ้างอิง ฿800', 'id' => '', 'promptpay_name' => '', 'promptpay_number' => '', 'expected_payment' => 25,
];
$dataUnavailable = false;

$statusFilter = $_GET['status'] ?? 'open';
$allowedStatuses = ['all', 'open', 'running', 'closed', 'completed'];
if (!in_array($statusFilter, $allowedStatuses, true)) $statusFilter = 'open';
if ($appConfigured) {
    try {
        $statusQuery = $statusFilter === 'all' ? 'status=in.(open,running,closed,completed)' : 'status=eq.' . rawurlencode($statusFilter);
        $remoteSeasons = supabase()->rest('seasons', $statusQuery . '&order=created_at.desc&limit=1');
        if ($remoteSeasons) { $remote = $remoteSeasons[0]; $labels = ['open' => 'เปิดรับสมัคร', 'running' => 'กำลังแข่งขัน', 'closed' => 'ปิดรับสมัคร', 'completed' => 'จบแล้ว']; $season = ['id' => $remote['id'], 'name' => $remote['name'], 'subtitle' => $remote['subtitle'] ?? '', 'status' => $labels[$remote['status']] ?? $remote['status'], 'players' => 0, 'capacity' => (int) $remote['capacity'], 'fee' => (float) $remote['entry_fee'], 'open' => $remote['registration_opens_at'] ?? 'ตามประกาศ', 'close' => 'จับสายหลังปิดรับสมัคร', 'prize' => 'ตรวจสอบประกาศเงินรางวัล', 'promptpay_name' => $remote['promptpay_name'] ?? '', 'promptpay_number' => $remote['promptpay_number'] ?? '', 'expected_payment' => $remote['expected_payment'] ?? $remote['entry_fee']]; } elseif ($isProduction) { $season = ['id' => '', 'name' => 'ยังไม่มีรายการแข่งขัน', 'subtitle' => 'ยังไม่มี Season ที่พร้อมแสดงในขณะนี้', 'status' => 'ยังไม่พร้อม', 'players' => 0, 'capacity' => 0, 'fee' => 0, 'open' => 'รอการตั้งค่า', 'close' => 'รอการตั้งค่า', 'prize' => 'รอการตั้งค่า', 'promptpay_name' => '', 'promptpay_number' => '', 'expected_payment' => 0]; }
    } catch (Throwable) { if ($isProduction) { $dataUnavailable = true; $season = ['id' => '', 'name' => 'ไม่สามารถโหลดข้อมูลการแข่งขัน', 'subtitle' => 'กรุณาลองใหม่ภายหลัง', 'status' => 'ระบบขัดข้องชั่วคราว', 'players' => 0, 'capacity' => 0, 'fee' => 0, 'open' => '-', 'close' => '-', 'prize' => '-']; } }
}
$adminSeasons = [];
$editingSeason = null;
$adminSeasonSearch = trim((string) ($_GET['season_search'] ?? ''));
$adminSeasonStatus = (string) ($_GET['season_status'] ?? 'all');
$adminSeasonPage = max(1, (int) ($_GET['season_page'] ?? 1));
$adminSeasonTotal = 0;
$adminStats = ['seasons' => [], 'pending_registrations' => 0, 'disputes' => 0, 'matches' => [], 'registrations' => 0, 'payments' => 0, 'audit' => []];
$adminStaffRoles = [];
$adminAuditLogs = [];
if ($admin && $appConfigured) {
    try {
        $adminSeasons = supabase()->rest('seasons', 'order=created_at.desc&limit=50');
        $adminSeasons = array_values(array_filter($adminSeasons, static function (array $row) use ($adminSeasonSearch, $adminSeasonStatus): bool {
            $matchesSearch = $adminSeasonSearch === '' || stripos((string) ($row['name'] ?? '') . ' ' . (string) ($row['code'] ?? ''), $adminSeasonSearch) !== false;
            $matchesStatus = $adminSeasonStatus === 'all' || (string) ($row['status'] ?? '') === $adminSeasonStatus;
            return $matchesSearch && $matchesStatus;
        }));
        $adminSeasonTotal = count($adminSeasons);
        $adminSeasons = array_slice($adminSeasons, ($adminSeasonPage - 1) * 12, 12);
        $editSeasonId = trim((string) ($_GET['edit_season'] ?? ''));
        if ($editSeasonId !== '') {
            foreach ($adminSeasons as $candidate) {
                if ((string) ($candidate['id'] ?? '') === $editSeasonId) {
                    $editingSeason = $candidate;
                    break;
                }
            }
        }
        $seasonAll = supabase()->rest('seasons', 'limit=1000');
        foreach ($seasonAll as $row) $adminStats['seasons'][(string) ($row['status'] ?? 'unknown')] = ($adminStats['seasons'][(string) ($row['status'] ?? 'unknown')] ?? 0) + 1;
        $adminStats['pending_registrations'] = count(supabase()->rest('registrations', 'status=eq.pending_review&limit=1000')) + count(supabase()->rest('applicant_submissions', 'status=eq.pending_review&limit=1000'));
        $adminStats['registrations'] = count(supabase()->rest('registrations', 'limit=1000')) + count(supabase()->rest('applicant_submissions', 'limit=1000'));
        $adminStats['disputes'] = count(supabase()->rest('matches', 'status=eq.disputed&limit=1000'));
        $adminStats['audit'] = supabase()->rest('audit_logs', 'order=created_at.desc&limit=8');
        $adminStaffRoles = supabase()->rest('staff_roles', 'order=created_at.asc&limit=100');
    } catch (Throwable) {
        $adminSeasons = [];
    }
}
$pendingRegistrations = [];
if ($staff && $appConfigured) { try { $staffStatus = $_GET['status'] ?? 'pending_review'; $staffStatus = in_array($staffStatus, ['pending_review','approved','rejected','all'], true) ? $staffStatus : 'pending_review'; $staffSearch = trim((string) ($_GET['search'] ?? '')); $staffQuery = $staffStatus === 'all' ? 'order=created_at.asc&limit=50' : 'status=eq.' . rawurlencode($staffStatus) . '&order=created_at.asc&limit=50'; $registrationQueue = supabase()->rest('registrations', $staffQuery); $publicQueue = supabase()->rest('applicant_submissions', $staffQuery); foreach ($registrationQueue as &$row) $row['source_table'] = 'registrations'; foreach ($publicQueue as &$row) $row['source_table'] = 'applicant_submissions'; unset($row); $pendingRegistrations = array_merge($registrationQueue, $publicQueue); usort($pendingRegistrations, static fn(array $a, array $b): int => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''))); if ($staffSearch !== '') $pendingRegistrations = array_values(array_filter($pendingRegistrations, static fn(array $r): bool => stripos((string) ($r['nickname'] ?? '') . ' ' . (string) ($r['competition_name'] ?? '') . ' ' . (string) ($r['facebook_name'] ?? ''), $staffSearch) !== false)); } catch (Throwable) { $pendingRegistrations = []; } }
$slipLinks = [];
if ($staff && $appConfigured) { foreach ($pendingRegistrations as $registration) { if (!empty($registration['slip_path'])) { try { $slipLinks[$registration['id']] = supabase()->storageSignedUrl((string) env_value('SUPABASE_SLIP_BUCKET', 'slips'), (string) $registration['slip_path']); } catch (Throwable) { $slipLinks[$registration['id']] = null; } } } }
$myRegistrations = [];
if ($user && $appConfigured) { try { $myRegistrations = supabase()->rest('registrations', 'user_id=eq.' . rawurlencode((string) $user['id']) . '&order=created_at.desc'); } catch (Throwable) { $myRegistrations = []; } }
$publicPlayerSearch = [];
$publicPlayerQuery = trim((string) ($_GET['player_search'] ?? ''));
if ($view === 'register' && $appConfigured && mb_strlen($publicPlayerQuery) >= 2) { try { $publicPlayerSearch = supabase()->rpc('search_public_players', ['p_query' => $publicPlayerQuery]); } catch (Throwable) { $publicPlayerSearch = []; } }

function match_stage_label(string $stage): string { return ['group' => 'Group Stage', 'round_of_16' => 'Round of 16', 'quarter_final' => 'Quarter-final', 'semi_final' => 'Semi-final', 'final' => 'Final', 'third_place' => 'ชิงอันดับ 3'][$stage] ?? $stage; }
function match_status_label(string $status): string { return ['scheduled' => 'รอแข่งขัน', 'awaiting_result' => 'รอยืนยันผล', 'disputed' => 'อยู่ระหว่างข้อพิพาท', 'confirmed' => 'ยืนยันผลแล้ว', 'void' => 'ยกเลิกผล'][$status] ?? $status; }
function hydrate_matches(array $matches, ?SupabaseClient $db, string $bucket): array
{
    if (!$db) return $matches;
    $registrations = [];
    foreach ($db->rest('registrations') as $registration) $registrations[(string) ($registration['id'] ?? '')] = $registration;
    foreach ($matches as &$match) {
        $id = (string) ($match['id'] ?? '');
        try {
            $results = $id !== '' ? $db->rest('match_results', 'match_id=eq.' . rawurlencode($id) . '&order=submitted_at.desc&limit=1') : [];
            $match['result'] = $results[0] ?? null;
            $evidence = $id !== '' ? $db->rest('match_evidence', 'match_id=eq.' . rawurlencode($id) . '&order=created_at.asc') : [];
            foreach ($evidence as &$item) {
                if (($item['evidence_type'] ?? '') === 'screenshot' && !empty($item['storage_path'])) {
                    try { $item['signed_url'] = $db->storageSignedUrl($bucket, (string) $item['storage_path']); } catch (Throwable) { $item['signed_url'] = null; }
                }
            }
            unset($item);
            $match['evidence'] = $evidence;
        } catch (Throwable $exception) {
            $match['load_error'] = 'ไม่สามารถโหลดหลักฐานของแมตช์นี้ได้';
            $match['result'] = null;
            $match['evidence'] = [];
        }
        $a = $registrations[(string) ($match['player_a_registration_id'] ?? '')] ?? [];
        $b = $registrations[(string) ($match['player_b_registration_id'] ?? '')] ?? [];
        $match['player_a_name'] = $a['nickname'] ?? $a['competition_name'] ?? 'ผู้เล่น A';
        $match['player_b_name'] = $b['nickname'] ?? $b['competition_name'] ?? 'ผู้เล่น B';
    }
    unset($match);
    return $matches;
}
$myMatches = [];
$disputedMatches = [];
if ($user && $appConfigured && $myRegistrations) {
    try {
        $ids = implode(',', array_map(static fn(array $row): string => rawurlencode((string) ($row['id'] ?? '')), $myRegistrations));
        $myMatches = hydrate_matches(supabase()->rest('matches', 'or=(player_a_registration_id.in.(' . $ids . '),player_b_registration_id.in.(' . $ids . '))&order=scheduled_at.asc'), supabase(), (string) env_value('SUPABASE_MATCH_EVIDENCE_BUCKET', 'match-evidence'));
    } catch (Throwable) { $myMatches = []; }
}
if ($staff && $appConfigured) {
    try { $disputedMatches = hydrate_matches(supabase()->rest('matches', 'status=eq.disputed&order=created_at.asc&limit=50'), supabase(), (string) env_value('SUPABASE_MATCH_EVIDENCE_BUCKET', 'match-evidence')); } catch (Throwable) { $disputedMatches = []; }
}

$rankings = [
    ['rank' => 1, 'name' => 'Brave Suppakorn', 'handle' => 'BRAVE', 'club' => 'RED PHOENIX', 'points' => '280.00', 'form' => 'W W W'],
    ['rank' => 2, 'name' => 'Kongphop Keawsanthia', 'handle' => 'WAVE', 'club' => 'DARK HORSE', 'points' => '224.50', 'form' => 'W W L'],
    ['rank' => 3, 'name' => 'Wo Woradon', 'handle' => 'BASS', 'club' => 'NOVA FC', 'points' => '198.25', 'form' => 'W D W'],
];
$rankingScope = $_GET['scope'] ?? 'player';
if (!in_array($rankingScope, ['player', 'club', 'all-time'], true)) $rankingScope = 'player';
if ($isProduction) $rankings = [];

$rules = [
    ['icon' => '◉', 'title' => 'รูปแบบการแข่งขัน', 'text' => 'Group Stage เก็บคะแนน ต่อด้วย Knockout แบบนัดเดียวจบ'],
    ['icon' => '◷', 'title' => 'เวลาแข่งขัน', 'text' => '6 นาทีรอบทั่วไป / 8 นาทีรอบ Semi-Finals และ Finals'],
    ['icon' => '✓', 'title' => 'ส่งผลการแข่งขัน', 'text' => 'ผู้ชนะส่งสกอร์ ทีมที่ใช้ และภาพหน้าจอสรุปผล'],
    ['icon' => '!', 'title' => 'Fair Play', 'text' => 'รอคู่แข่ง 15 นาที เก็บหลักฐาน และแจ้งทีมงานตามกำหนด'],
];

$allowedViews = ['home', 'tournaments', 'rules', 'ranking', 'register', 'auth', 'account', 'account-overview', 'staff', 'staff-matches', 'admin', 'admin-dashboard', 'admin-seasons', 'admin-staff', 'admin-audit', 'privacy', 'terms', 'contact'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'home';
}
$protectedViewDenied = ($view === 'staff' && !$staff)
    || ($view === 'staff-matches' && !$staff)
    || (in_array($view, ['admin', 'admin-dashboard', 'admin-seasons', 'admin-staff', 'admin-audit'], true) && !$admin);
if ($protectedViewDenied) {
    http_response_code(403);
    exit('Forbidden');
}
if ($view === 'admin-seasons') $view = 'admin';
function active(string $current, string $view): string { return $current === $view ? ' is-active' : ''; }
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function datetime_local_value($value): string { if (!$value) return ''; try { return (new DateTime((string) $value))->format('Y-m-d\\TH:i'); } catch (Throwable) { return ''; } }
function validate_season_payload(array $payload): void
{
    if (trim((string) ($payload['name'] ?? '')) === '') throw new InvalidArgumentException('กรุณาระบุชื่อ Season');
    if (mb_strlen((string) $payload['name']) > 120) throw new InvalidArgumentException('ชื่อ Season ยาวเกินกำหนด');
    if (!in_array((string) ($payload['status'] ?? ''), ['draft', 'open', 'closed', 'running', 'completed'], true)) throw new InvalidArgumentException('สถานะ Season ไม่ถูกต้อง');
    if ((int) ($payload['capacity'] ?? 0) < 1 || (int) ($payload['capacity'] ?? 0) > 10000) throw new InvalidArgumentException('Capacity ต้องอยู่ระหว่าง 1 ถึง 10000');
    if ((float) ($payload['entry_fee'] ?? -1) < 0 || (float) ($payload['expected_payment'] ?? -1) < 0) throw new InvalidArgumentException('ค่าสมัครและยอดชำระต้องไม่ติดลบ');
    $opens = $payload['registration_opens_at'] ?? null;
    $closes = $payload['registration_closes_at'] ?? null;
    if ($opens && $closes && strtotime((string) $closes) <= strtotime((string) $opens)) throw new InvalidArgumentException('วันปิดรับสมัครต้องหลังวันเปิดรับสมัคร');
}
function validate_season_transition(string $from, string $to): void
{
    $allowed = ['draft' => ['draft', 'open'], 'open' => ['open', 'closed'], 'closed' => ['closed', 'running'], 'running' => ['running', 'completed'], 'completed' => ['completed']];
    if (!in_array($to, $allowed[$from] ?? [], true)) throw new InvalidArgumentException('ไม่สามารถเปลี่ยนสถานะ Season จาก ' . $from . ' เป็น ' . $to . ' ได้');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GTournament1 — E-Football Tournament</title>
    <meta name="description" content="ศูนย์กลางการแข่งขัน E-Football Community Tournament">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/style.css')) ?>">
</head>
<body>
<div class="site-shell">
    <header class="topbar">
        <a class="brand" href="?view=home" aria-label="GTournament1 home">
            <img class="brand-logo" src="assets/logo-gtournament.png" alt="GTournament1 logo">
            <span><strong>GTournament1</strong><small>E-FOOTBALL TOURNAMENT</small></span>
        </a>
        <nav class="main-nav" id="main-navigation" aria-label="เมนูหลัก">
            <a class="<?= active('home', $view) ?>" href="?view=home">Dashboard</a>
            <a class="<?= active('tournaments', $view) ?>" href="?view=tournaments">Tournaments</a>
            <a class="<?= active('ranking', $view) ?>" href="?view=ranking">Rankings</a>
            <a class="<?= active('rules', $view) ?>" href="?view=rules">Rules</a>
            <?php if ($staff): ?><a class="<?= active('staff-matches', $view) ?>" href="?view=staff-matches">Match Queue</a><?php endif; ?>
            <?php if ($admin): ?><a class="<?= active('admin-dashboard', $view) ?>" href="?view=admin-dashboard">ภาพรวม Admin</a><a class="<?= active('admin', $view) ?>" href="?view=admin">จัดการ Season</a><?php endif; ?>
        </nav>
        <a class="staff-link" href="?view=<?= $admin ? 'admin' : ($staff ? 'staff' : 'auth') ?>">เฉพาะทีมงาน <span>↗</span></a>
        <button class="menu-toggle" aria-label="เปิดเมนู" aria-controls="main-navigation" aria-expanded="false" type="button">☰</button>
    </header>

    <main>
    <?php if ($error): ?><div class="alert alert-error" role="alert">กรุณาตรวจสอบ: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($dataUnavailable): ?><div class="alert alert-error" role="status">ยังไม่สามารถโหลดข้อมูลจากระบบหลักได้ โปรดลองใหม่ภายหลัง</div><?php endif; ?>
    <?php if ($view === 'account' && $user): ?>
        <section class="section-pad content-section account-summary"><div class="section-heading"><div><p class="eyebrow">PLAYER HUB / REGISTRATION</p><h2>สถานะการสมัครและชำระเงิน</h2></div><a class="btn btn-outline" href="?view=register">สมัคร Season</a></div><div class="ranking-list"><?php if (!$myRegistrations): ?><div class="register-card"><p>ยังไม่มีใบสมัครในระบบ</p><a class="btn btn-primary" href="?view=register">เริ่มสมัครการแข่งขัน →</a></div><?php else: foreach ($myRegistrations as $registration): ?><article class="ranking-row"><div class="player-id"><strong><?= e($registration['competition_name'] ?? 'ใบสมัคร') ?></strong><span><?= e($registration['nickname'] ?? '') ?> · สถานะ <?= e(RegistrationStatus::label((string) ($registration['status'] ?? ''))) ?><?php if (!empty($registration['rejection_reason'])): ?> · <?= e($registration['rejection_reason']) ?><?php endif; ?></span></div><?php if (in_array(($registration['status'] ?? ''), ['rejected', 'pending_payment'], true)): ?><a class="btn btn-outline" href="?view=register&resubmit=<?= e($registration['id']) ?>">ส่งข้อมูลใหม่</a><?php endif; ?></article><?php endforeach; endif; ?></div></section>
    <?php endif; ?>
    <?php if ($view === 'account' && $user && $myMatches): ?><section class="section-pad content-section"><div class="section-heading"><div><p class="eyebrow">MATCHUP / OPPONENTS</p><h2>คู่แข่งและกำหนดการ</h2></div></div><div class="ranking-list"><?php foreach ($myMatches as $match): ?><article class="ranking-row"><div class="player-id"><strong><?= e($match['player_a_name'] ?? 'ผู้เล่น A') ?> <span>vs</span> <?= e($match['player_b_name'] ?? 'ผู้เล่น B') ?></strong><span><?= e(match_stage_label((string) ($match['stage'] ?? ''))) ?> · <?= e($match['scheduled_at'] ?? 'รอประกาศเวลา') ?> · <?= e(match_status_label((string) ($match['status'] ?? ''))) ?></span></div></article><?php endforeach; ?></div></section><?php endif; ?>
    <?php if ($view === 'home'): ?>
        <section class="hero section-pad">
            <div class="hero-copy">
                <p class="eyebrow"><span class="live-dot"></span> SEASON 01 / REGISTRATION OPEN</p>
                <h1>PLAY HARD.<br><em>LEAVE A MARK.</em></h1>
                <p class="hero-lead">สนามแข่งขัน E-Football สำหรับคนที่อยากวัดฝีมือ เก็บสถิติ และสร้างชื่อของตัวเองในชุมชน</p>
                <div class="hero-actions"><a class="btn btn-primary" href="?view=register">สมัครแข่งขัน <span>→</span></a><a class="btn btn-ghost" href="?view=tournaments">ดูรายการทั้งหมด</a></div>
                <div class="hero-meta"><span><strong><?= e($season['players']) ?></strong> / <?= e($season['capacity']) ?> PLAYERS</span><span><strong>฿<?= e($season['fee']) ?></strong> ENTRY FEE</span><span><strong>32</strong> MAX SLOTS</span></div>
            </div>
            <div class="hero-art" aria-hidden="true"><div class="art-ring ring-one"></div><div class="art-ring ring-two"></div><div class="art-cross"></div><div class="art-label">GT1<br><span>EST. 2026</span></div></div>
        </section>

        <section class="ticker"><div>● LATEST UPDATE</div><p>GTournament1 เปิดรับสมัครทุกวันศุกร์ประมาณ 16:00 น. — จับสายทันทีหลังปิดรับสมัคร</p><a href="?view=register">สมัครเลย →</a></section>

        <section class="section-pad section-grid">
            <div class="section-heading"><p class="eyebrow">01 / ACTIVE TOURNAMENT</p><h2>รายการที่กำลังเปิดรับสมัคร</h2><p>ทุกแมตช์มีระบบบันทึกผล ตรวจสอบได้ และเก็บเป็นสถิติของคุณ</p></div>
            <article class="season-card"><div class="season-card-top"><span class="status-pill"><span class="live-dot"></span><?= e($season['status']) ?></span><span class="season-code">RL / 001</span></div><h3><?= e($season['name']) ?></h3><p><?= e($season['subtitle']) ?></p><div class="progress-label"><span>ที่นั่งคงเหลือ</span><strong><?= e($season['players']) ?> / <?= e($season['capacity']) ?></strong></div><div class="progress"><i style="width: <?= min(100, max(0, ($season['players'] / max(1, $season['capacity'])) * 100)) ?>%"></i></div><div class="season-facts"><span>เปิดรับ <b><?= e($season['open']) ?></b></span><span><?= e($season['prize']) ?></span></div><div class="card-foot"><span><?= e($season['close']) ?></span><a href="?view=register">ดูรายละเอียด →</a></div></article>
        </section>

        <section class="section-pad dark-band"><div class="section-heading"><p class="eyebrow">02 / THE RULEBOOK</p><h2>กติกาที่ทำให้ทุกเกมแฟร์</h2><p>อ่านให้ครบก่อนสมัคร เพื่อให้การแข่งขันสนุก โปร่งใส และเคารพคู่แข่ง</p></div><div class="rule-grid"><?php foreach ($rules as $rule): ?><article class="rule-card"><span class="rule-icon"><?= $rule['icon'] ?></span><h3><?= $rule['title'] ?></h3><p><?= $rule['text'] ?></p></article><?php endforeach; ?></div><a class="text-link" href="?view=rules">อ่านกติกาฉบับเต็ม <span>→</span></a></section>

        <section class="section-pad ranking-preview"><div class="section-heading"><p class="eyebrow">03 / CURRENT RANKING</p><h2>ผู้เล่นที่กำลังร้อนแรง</h2></div><div class="ranking-list"><?php foreach ($rankings as $player): ?><article class="ranking-row"><span class="rank-number">0<?= $player['rank'] ?></span><div class="avatar avatar-<?= $player['rank'] ?>"><?= substr($player['handle'], 0, 1) ?></div><div class="player-id"><strong><?= $player['name'] ?></strong><span><?= $player['handle'] ?> · <?= $player['club'] ?></span></div><div class="form"><span><?= $player['form'] ?></span></div><strong class="points"><?= $player['points'] ?><small>BCL POINT</small></strong></article><?php endforeach; ?></div><a class="btn btn-outline" href="?view=ranking">ดูอันดับทั้งหมด →</a></section>
    <?php elseif ($view === 'tournaments'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">TOURNAMENTS / 2026</p><h1>สนามแข่งของคุณ<br><em>เริ่มที่นี่</em></h1><p>เลือกรายการที่ใช่ ตรวจสอบกติกา และลงชื่อให้ทันก่อนที่นั่งเต็ม</p></section>
        <section class="section-pad content-section"><div class="filter-tabs"><a class="<?= $statusFilter === 'all' ? 'active' : '' ?>" href="?view=tournaments&status=all">ทั้งหมด</a><a class="<?= $statusFilter === 'open' ? 'active' : '' ?>" href="?view=tournaments&status=open">เปิดรับสมัคร</a><a class="<?= $statusFilter === 'running' ? 'active' : '' ?>" href="?view=tournaments&status=running">กำลังแข่งขัน</a><a class="<?= $statusFilter === 'completed' ? 'active' : '' ?>" href="?view=tournaments&status=completed">จบแล้ว</a></div><article class="tournament-detail"><div><span class="status-pill"><span class="live-dot"></span> <?= e($season['status']) ?></span><span class="season-code">RL / 001</span><h2><?= e($season['name']) ?></h2><p><?= e($season['subtitle']) ?> · จับสายหลังปิดรับสมัครทันที</p><div class="detail-stats"><span><b><?= e($season['capacity']) ?></b> ที่นั่ง</span><span><b>฿<?= e($season['fee']) ?></b> ค่าสมัคร</span><span><b>6 / 8</b> นาที</span><span><b>1</b> แมตช์จบ</span></div><a class="btn btn-primary" href="?view=register">สมัครรายการนี้ →</a></div><div class="tournament-poster"><span>RL</span><b>REDLINE<br>SEASON 01</b><small>NO EXCUSES / JUST PLAY</small></div></article></section>
    <?php elseif ($view === 'rules'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">RULEBOOK / VERSION 1.0</p><h1>กติกา<br><em>ที่ทุกคนต้องรู้</em></h1><p>สรุปจากเอกสารระเบียบและกติกาการแข่งขัน E-Football Tournament สำหรับ Season 01</p></section>
        <section class="section-pad content-section"><div class="rules-layout"><aside class="rules-index"><a class="active" href="#format">01 รูปแบบการแข่งขัน</a><a href="#match">02 การตั้งค่าแมตช์</a><a href="#report">03 การรายงานผล</a><a href="#fairplay">04 Fair Play & บทลงโทษ</a><a href="#disconnect">05 Disconnect & Recording</a><a href="#prize">06 เงินรางวัล</a></aside><div class="rules-body"><article id="format" class="rule-block"><span class="rule-num">01</span><h2>รูปแบบการแข่งขัน</h2><div class="rule-table"><div><b>Group Stage</b><span>เก็บคะแนน · 6 นาที · ไม่มีต่อเวลา/จุดโทษ</span></div><div><b>Knockout Stage</b><span>นัดเดียวจบ · 6 นาที · มีต่อเวลา/จุดโทษ</span></div><div><b>Semi & Final</b><span>นัดเดียวจบ · 8 นาที · มีต่อเวลา/จุดโทษ</span></div></div></article><article id="match" class="rule-block"><span class="rule-num">02</span><h2>Match Settings</h2><div class="settings-grid"><span>Match Type<b>Standard</b></span><span>Injuries<b>On</b></span><span>Substitution<b>5 คน / 3 ครั้ง</b></span><span>Home/Away<b>Excellent</b></span><span>Smart Assist<b>เปิดหรือปิดได้</b></span></div></article><article id="report" class="rule-block"><span class="rule-num">03</span><h2>รายงานผลและหลักฐาน</h2><p>ผู้ชนะมีหน้าที่ส่งผลผ่านระบบ พร้อมชื่อผู้เล่น ทีม/สโมสรที่ใช้ สกอร์ และภาพหน้าจอสรุปผลหลังเกม หากเป็นรอบ Semi-Finals หรือ Finals ต้องส่ง Screen Recording ของทั้งแมตช์ ภายใน 2 ชั่วโมง</p></article><article id="fairplay" class="rule-block"><span class="rule-num">04</span><h2>Fair Play</h2><p>ติดต่อคู่แข่งและแข่งขันให้เสร็จตามกรอบเวลา หากอีกฝ่ายช้าเกิน 15 นาที ให้เก็บหลักฐานเพื่อขอ Walkover ห้ามถ่วงเวลา Match-fixing หรือ Rage Quit การฝ่าฝืนอาจถูกปรับแพ้ ตัดสิทธิ์ ริบเงินรางวัล และพักการแข่งขัน Season ถัดไป</p></article><article id="disconnect" class="rule-block"><span class="rule-num">05</span><h2>Disconnect & Screen Recording</h2><p>เหตุหลุดสุดวิสัยอนุญาตไม่เกิน 1 ครั้งต่อผู้เล่นต่อแมตช์ ให้สร้างห้องใหม่และแข่งต่อเฉพาะเวลาที่เหลือพร้อมนับสกอร์ต่อเนื่อง ส่วนรอบ Semi-Finals และ Finals ต้องเปิด Do Not Disturb เปิดเสียงเกม ไม่ข้าม Replay สำคัญ และส่งไฟล์หรือ Link ให้ทีมงานภายใน 2 ชั่วโมง</p></article><article id="prize" class="rule-block"><span class="rule-num">06</span><h2>ค่าสมัครและเงินรางวัล</h2><div class="prize-table"><div><b>Champion</b><span>16 คน ฿140</span><span>32 คน ฿280</span></div><div><b>Runner-up</b><span>16 คน ฿60</span><span>32 คน ฿160</span></div><div><b>Semi-finalist</b><span>คนละ ฿30</span><span>คนละ ฿50</span></div><div><b>รอบ 8 ทีม</b><span>คนละ ฿15</span><span>คนละ ฿25</span></div></div><p class="rule-note">ทีมงานโอนเงินรางวัลผ่าน PromptPay ภายใน 24–48 ชั่วโมงหลังจบรอบชิงชนะเลิศ</p></article><a class="btn btn-primary" href="?view=register">ฉันอ่านและพร้อมสมัคร →</a></div></div></section>
    <?php elseif ($view === 'ranking'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">RANKINGS / LIVE TABLE</p><h1>ชื่อของคุณ<br><em>อยู่ตรงไหน?</em></h1><p>คะแนนสะสมจากผลงานจริง ทุกแมตช์มีความหมาย</p></section><section class="section-pad content-section"><div class="filter-tabs"><a class="<?= $rankingScope === 'player' ? 'active' : '' ?>" href="?view=ranking&scope=player">Player</a><a class="<?= $rankingScope === 'club' ? 'active' : '' ?>" href="?view=ranking&scope=club">Club</a><a class="<?= $rankingScope === 'all-time' ? 'active' : '' ?>" href="?view=ranking&scope=all-time">All-Time</a></div><?php if ($rankingScope !== 'player'): ?><div class="register-card"><h2><?= $rankingScope === 'club' ? 'Club Ranking' : 'All-Time Ranking' ?></h2><p>ตารางนี้จะเปิดใช้เมื่อมีการยืนยันผลการแข่งขันและสูตรคะแนนใน Supabase แล้ว</p></div><?php elseif (!$rankings): ?><div class="register-card"><h2>ยังไม่มีข้อมูลอันดับ</h2><p>อันดับจะแสดงเมื่อมีผลการแข่งขันที่ยืนยันแล้ว</p></div><?php else: ?><div class="ranking-list full"><?php foreach ($rankings as $player): ?><article class="ranking-row"><span class="rank-number">0<?= e($player['rank']) ?></span><div class="avatar avatar-<?= e($player['rank']) ?>"><?= e(substr($player['handle'], 0, 1)) ?></div><div class="player-id"><strong><?= e($player['name']) ?></strong><span><?= e($player['handle']) ?> · <?= e($player['club']) ?></span></div><div class="record"><span>PLAYED <b><?= e(12 - $player['rank']) ?></b></span><span>WIN RATE <b><?= e(82 - ($player['rank'] * 7)) ?>%</b></span></div><strong class="points"><?= e($player['points']) ?><small>BCL POINT</small></strong></article><?php endforeach; ?><article class="ranking-row muted-row"><span class="rank-number">04</span><div class="avatar">?</div><div class="player-id"><strong>ผู้เล่นคนถัดไปคือคุณ</strong><span>ลงแข่ง Season 01 เพื่อขึ้นอันดับ</span></div><a class="btn btn-outline" href="?view=register">เริ่มไต่แรงก์</a></article></div><?php endif; ?></section>
    <?php elseif ($view === 'staff'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">STAFF / REVIEW QUEUE</p><h1>ตรวจใบสมัคร<br><em>อย่างโปร่งใส</em></h1><p>คิวนี้แสดงเฉพาะใบสมัครที่ส่งสลิปแล้วและรอการตรวจสอบ</p></section><section class="section-pad content-section"><div class="filter-tabs"><a href="?view=staff&status=pending_review">รอตรวจ</a><a href="?view=staff&status=approved">อนุมัติแล้ว</a><a href="?view=staff&status=rejected">ไม่ผ่าน</a><a href="?view=staff&status=all">ทั้งหมด</a></div><form class="staff-search" method="get"><input type="hidden" name="view" value="staff"><input type="search" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="ค้นหาชื่อผู้สมัคร"><button class="btn btn-outline" type="submit">ค้นหา</button></form><div class="ranking-list"><?php if (!$staff): ?><div class="register-card"><h2>ไม่มีสิทธิ์เข้าถึง</h2><p>บัญชีนี้ไม่มีสิทธิ์ Staff หรือ Admin</p></div><?php elseif (!$pendingRegistrations): ?><div class="register-card"><h2>ไม่มีรายการ</h2><p>ไม่พบใบสมัครตามตัวกรองนี้</p></div><?php else: foreach ($pendingRegistrations as $registration): ?><article class="ranking-row"><div class="player-id"><strong><?= e($registration['competition_name'] ?? '') ?></strong><span><?= e($registration['nickname'] ?? '') ?> · <?= e($registration['club'] ?? 'ไม่ระบุคลับ') ?> · <?= e(RegistrationStatus::label((string) ($registration['status'] ?? ''))) ?></span></div><?php if (!empty($slipLinks[$registration['id']])): ?><a class="btn btn-outline" href="<?= e($slipLinks[$registration['id']]) ?>" target="_blank" rel="noopener">เปิดสลิป</a><?php endif; ?><?php if (($registration['status'] ?? '') === 'pending_review'): ?><form method="post"><input type="hidden" name="action" value="review_registration"><input type="hidden" name="registration_id" value="<?= e($registration['id']) ?>"><?= csrf_field() ?><input type="text" name="reason" placeholder="เหตุผลเมื่อปฏิเสธ" aria-label="เหตุผลเมื่อปฏิเสธ"><button class="btn btn-primary" name="status" value="approved" type="submit">อนุมัติ</button><button class="btn btn-outline" name="status" value="rejected" type="submit">ปฏิเสธ</button></form><?php endif; ?></article><?php endforeach; endif; ?></div></section>
    <?php elseif ($view === 'staff-matches'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">STAFF / MATCH DISPUTES</p><h1>ตรวจผลแข่ง<br><em>ให้แฟร์กับทุกคน</em></h1><p>ตรวจสอบสกอร์ หลักฐาน และเหตุผลการโต้แย้งก่อนตัดสินผลแมตช์</p></section><section class="section-pad content-section"><div class="match-stack"><?php if (!$staff): ?><div class="register-card"><h2>ไม่มีสิทธิ์เข้าถึง</h2><p>หน้านี้สำหรับ Staff และ Admin เท่านั้น</p></div><?php elseif (!$disputedMatches): ?><div class="register-card"><h2>ไม่มีข้อพิพาทค้างอยู่</h2><p>ทุกแมตช์ที่รอการตรวจจะปรากฏที่นี่</p></div><?php else: foreach ($disputedMatches as $match): ?><article class="match-card"><div class="match-card-head"><div><span class="status-pill">ข้อพิพาท</span><h2><?= e(match_stage_label((string) ($match['stage'] ?? ''))) ?></h2><p>Match ID: <?= e($match['id'] ?? '') ?></p></div><strong><?= e(match_status_label((string) ($match['status'] ?? ''))) ?></strong></div><div class="match-score"><?php if (!empty($match['result'])): ?><strong><?= e($match['result']['score_a']) ?> - <?= e($match['result']['score_b']) ?></strong><span>ส่งโดย <?= e($match['result']['submitted_by']) ?></span><?php else: ?><span>ยังไม่มีสกอร์</span><?php endif; ?></div><div class="evidence-list"><?php foreach (($match['evidence'] ?? []) as $evidence): ?><?php if (($evidence['evidence_type'] ?? '') === 'screenshot' && !empty($evidence['signed_url'])): ?><a class="btn btn-outline" href="<?= e($evidence['signed_url']) ?>" target="_blank" rel="noopener">เปิด Screenshot</a><?php elseif (($evidence['evidence_type'] ?? '') === 'video_link' && !empty($evidence['source_url'])): ?><a class="btn btn-outline" href="<?= e($evidence['source_url']) ?>" target="_blank" rel="noopener">เปิด Screen Recording</a><?php endif; ?><?php endforeach; ?></div><form class="resolve-form" method="post"><input type="hidden" name="action" value="resolve_match_dispute"><input type="hidden" name="match_id" value="<?= e($match['id'] ?? '') ?>"><?= csrf_field() ?><textarea name="note" rows="2" required placeholder="หมายเหตุการตัดสิน"></textarea><button class="btn btn-primary" name="outcome" value="confirmed" type="submit">ยืนยันผลตามหลักฐาน</button><button class="btn btn-outline" name="outcome" value="void" type="submit">ยกเลิกผลแมตช์</button></form></article><?php endforeach; endif; ?></div></section>
     <?php elseif ($view === 'admin-dashboard'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">ADMIN / OVERVIEW</p><h1>ภาพรวมระบบ<br><em>GTournament1</em></h1><p>ติดตาม Season ใบสมัคร ข้อพิพาท และกิจกรรมสำคัญจากศูนย์กลางทีมงาน</p></section>
        <section class="section-pad content-section admin-dashboard-grid">
            <article class="register-card"><span class="eyebrow">SEASONS</span><h2><?= e(array_sum($adminStats['seasons'])) ?></h2><p>Season ทั้งหมด</p><a class="text-link" href="?view=admin">จัดการ Season →</a></article>
            <article class="register-card"><span class="eyebrow">REGISTRATION</span><h2><?= e($adminStats['pending_registrations']) ?></h2><p>ใบสมัครรอตรวจ</p><a class="text-link" href="?view=staff">เปิดคิวตรวจ →</a></article>
            <article class="register-card"><span class="eyebrow">DISPUTES</span><h2><?= e($adminStats['disputes']) ?></h2><p>ข้อพิพาทค้างอยู่</p><a class="text-link" href="?view=staff-matches">เปิด Match Queue →</a></article>
            <article class="register-card"><span class="eyebrow">PLAYERS</span><h2><?= e($adminStats['registrations']) ?></h2><p>ใบสมัครทั้งหมด</p><a class="text-link" href="?view=staff&status=all">ดูรายการ →</a></article>
            <div class="register-card admin-quick-links"><h2>ทางลัดทีมงาน</h2><div class="button-row"><a class="btn btn-primary" href="?view=admin">สร้าง Season</a><a class="btn btn-outline" href="?view=admin-staff">บัญชีทีมงาน</a><a class="btn btn-outline" href="?view=admin-audit">Audit Log</a></div></div>
            <div class="register-card"><div class="section-heading"><div><p class="eyebrow">RECENT ACTIVITY</p><h2>กิจกรรมล่าสุด</h2></div></div><?php if (!$adminStats['audit']): ?><p>ยังไม่มีกิจกรรมที่บันทึก</p><?php else: ?><div class="admin-audit-list"><?php foreach ($adminStats['audit'] as $audit): ?><div><strong><?= e($audit['action'] ?? '-') ?></strong><span><?= e($audit['created_at'] ?? '-') ?> · <?= e($audit['entity_type'] ?? '-') ?></span></div><?php endforeach; ?></div><?php endif; ?></div>
        </section>
     <?php elseif ($view === 'admin-audit'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">ADMIN / AUDIT LOG</p><h1>ประวัติการทำงาน<br><em>ตรวจสอบย้อนหลัง</em></h1><p>Audit Log เป็นข้อมูลสำหรับอ่านอย่างเดียว ไม่สามารถแก้ไขหรือลบผ่านเว็บไซต์</p></section>
        <section class="section-pad content-section"><div class="register-card admin-audit-table"><div class="table-scroll"><table><thead><tr><th>เวลา</th><th>ผู้ดำเนินการ</th><th>Action</th><th>Entity</th><th>รายละเอียด</th></tr></thead><tbody><?php foreach ($adminStats['audit'] as $audit): ?><tr><td><?= e($audit['created_at'] ?? '-') ?></td><td><?= e($audit['actor_id'] ?? '-') ?></td><td><?= e($audit['action'] ?? '-') ?></td><td><?= e(($audit['entity_type'] ?? '-') . ' ' . ($audit['entity_id'] ?? '')) ?></td><td><?= e(isset($audit['metadata']) ? json_encode($audit['metadata'], JSON_UNESCAPED_UNICODE) : '-') ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$adminStats['audit']): ?><p>ยังไม่มี Audit Log</p><?php endif; ?></div></section>
     <?php elseif ($view === 'admin-staff'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">ADMIN / STAFF ACCESS</p><h1>บัญชีทีมงาน<br><em>สิทธิ์และสถานะ</em></h1><p>จัดการ Role ผ่าน RPC ที่ตรวจสิทธิ์ฝั่งฐานข้อมูล ห้ามใส่หรือแสดงรหัสผ่านในหน้านี้</p></section>
        <section class="section-pad content-section"><div class="register-card"><h2>เพิ่มหรือเปลี่ยน Role</h2><p>ใช้ User ID จาก Supabase Auth เท่านั้น ระบบจะบันทึก Audit Log ทุกครั้ง</p><form class="form-grid" method="post"><input type="hidden" name="action" value="change_staff_role"><?= csrf_field() ?><label>User ID<input type="text" name="staff_user_id" required placeholder="UUID ของผู้ใช้ใน Auth"></label><label>Role<select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></label><button class="btn btn-primary" type="submit">บันทึก Role</button></form></div><div class="register-card"><h2>บัญชีทีมงานปัจจุบัน</h2><div class="admin-staff-list"><?php if (!$adminStaffRoles): ?><p>ยังไม่มีรายการ หรือยังไม่ได้เชื่อมต่อข้อมูล</p><?php else: foreach ($adminStaffRoles as $staffRole): ?><article><div><strong><?= e($staffRole['user_id'] ?? '-') ?></strong><span><?= e(strtoupper((string) ($staffRole['role'] ?? 'staff'))) ?> · <?= !empty($staffRole['disabled_at']) ? 'ปิดสิทธิ์' : 'ใช้งานอยู่' ?></span></div><?php if (empty($staffRole['disabled_at'])): ?><form method="post"><input type="hidden" name="action" value="disable_staff"><input type="hidden" name="staff_user_id" value="<?= e($staffRole['user_id'] ?? '') ?>"><?= csrf_field() ?><button class="btn btn-outline" type="submit">ปิดสิทธิ์</button></form><?php endif; ?></article><?php endforeach; endif; ?></div></div></section>
     <?php elseif ($view === 'admin'): ?>
        <?php $edit = $editingSeason ?? []; ?>
        <section class="page-hero section-pad"><p class="eyebrow">ADMIN / SEASON CONTROL</p><h1>จัดการ Season<br><em>และการรับสมัคร</em></h1><p>ตั้งค่าช่วงเวลา จำนวนผู้สมัคร ค่าสมัคร และ PromptPay จากศูนย์กลางทีมงาน</p></section>
        <section class="section-pad content-section admin-season-section">
            <div class="admin-season-list">
                <div class="section-heading"><div><p class="eyebrow">SEASON LIST</p><h2>รายการ Season</h2></div><a class="btn btn-outline" href="?view=admin">+ สร้าง Season ใหม่</a></div>
                <form class="staff-search admin-season-filters" method="get"><input type="hidden" name="view" value="admin"><input type="search" name="season_search" value="<?= e($adminSeasonSearch) ?>" placeholder="ค้นหาชื่อหรือรหัส Season"><select name="season_status"><option value="all">ทุกสถานะ</option><?php foreach (['draft'=>'ร่าง','open'=>'เปิดรับสมัคร','closed'=>'ปิดรับสมัคร','running'=>'กำลังแข่งขัน','completed'=>'จบแล้ว','archived'=>'เก็บเข้าคลัง'] as $statusKey => $statusLabel): ?><option value="<?= e($statusKey) ?>" <?= $adminSeasonStatus === $statusKey ? 'selected' : '' ?>><?= e($statusLabel) ?></option><?php endforeach; ?></select><button class="btn btn-outline" type="submit">ค้นหา</button></form>
                <?php if (!$adminSeasons): ?><div class="register-card admin-empty"><p>ยังไม่มี Season ตามตัวกรองนี้</p></div><?php else: ?><div class="admin-season-grid"><?php foreach ($adminSeasons as $adminSeason): ?><article class="admin-season-item"><div><span class="status-pill"><?= e($adminSeason['status'] ?? 'draft') ?></span><h3><?= e($adminSeason['name'] ?? 'ไม่มีชื่อ Season') ?></h3><p><?= e($adminSeason['subtitle'] ?? 'ไม่มีคำอธิบาย') ?></p><small>Capacity <?= e($adminSeason['capacity'] ?? '-') ?> · ค่าสมัคร <?= e($adminSeason['entry_fee'] ?? '-') ?></small></div><div class="button-row"><a class="btn btn-outline" href="?view=admin&amp;edit_season=<?= e($adminSeason['id'] ?? '') ?>">แก้ไข</a><form method="post"><input type="hidden" name="action" value="duplicate_season"><input type="hidden" name="season_id" value="<?= e($adminSeason['id'] ?? '') ?>"><?= csrf_field() ?><button class="btn btn-outline" type="submit">ทำสำเนา</button></form><?php if (($adminSeason['status'] ?? '') !== 'archived'): ?><form method="post"><input type="hidden" name="action" value="archive_season"><input type="hidden" name="season_id" value="<?= e($adminSeason['id'] ?? '') ?>"><?= csrf_field() ?><button class="btn btn-outline" type="submit">Archive</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php if ($adminSeasonTotal > 12): ?><nav class="pagination" aria-label="หน้ารายการ Season"><?php for ($page = 1; $page <= (int) ceil($adminSeasonTotal / 12); $page++): ?><a class="<?= $page === $adminSeasonPage ? 'active' : '' ?>" href="?view=admin&amp;season_page=<?= $page ?>&amp;season_status=<?= e($adminSeasonStatus) ?>&amp;season_search=<?= urlencode($adminSeasonSearch) ?>"><?= $page ?></a><?php endfor; ?></nav><?php endif; ?><?php endif; ?>
            </div>
            <div class="register-layout admin-season-form-layout"><form class="register-card" method="post"><input type="hidden" name="action" value="save_season"><?= csrf_field() ?><label>Season ID (เว้นว่างเมื่อสร้างใหม่)<input type="text" name="season_id" value="<?= e($edit['id'] ?? '') ?>" placeholder="เว้นว่างเมื่อสร้าง Season ใหม่"></label><label>ชื่อ Season<input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required placeholder="เช่น GTournament1 Season 01"></label><label>คำอธิบาย<input type="text" name="subtitle" value="<?= e($edit['subtitle'] ?? '') ?>" placeholder="รายละเอียดการแข่งขัน"></label><label>สถานะ<select name="status"><option value="draft" <?= (($edit['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>ร่าง</option><option value="open" <?= (($edit['status'] ?? '') === 'open') ? 'selected' : '' ?>>เปิดรับสมัคร</option><option value="closed" <?= (($edit['status'] ?? '') === 'closed') ? 'selected' : '' ?>>ปิดรับสมัคร</option><option value="running" <?= (($edit['status'] ?? '') === 'running') ? 'selected' : '' ?>>กำลังแข่งขัน</option><option value="completed" <?= (($edit['status'] ?? '') === 'completed') ? 'selected' : '' ?>>จบแล้ว</option></select></label><label>Capacity<input type="number" name="capacity" min="1" max="32" value="<?= e($edit['capacity'] ?? '') ?>" placeholder="กรอกจำนวนผู้สมัคร เช่น 32"></label><label>ค่าสมัคร<input type="number" name="entry_fee" min="0" step="0.01" value="<?= e($edit['entry_fee'] ?? '') ?>" placeholder="กรอกจำนวนเงิน เช่น 25"></label><label>เปิดรับสมัคร<input type="datetime-local" name="registration_opens_at" value="<?= e(datetime_local_value($edit['registration_opens_at'] ?? '')) ?>"></label><label>ปิดรับสมัคร<input type="datetime-local" name="registration_closes_at" value="<?= e(datetime_local_value($edit['registration_closes_at'] ?? '')) ?>"></label><label>ชื่อบัญชี PromptPay<input type="text" name="promptpay_name" value="<?= e($edit['promptpay_name'] ?? '') ?>" placeholder="ชื่อบัญชีรับชำระเงิน"></label><label>หมายเลข PromptPay<input type="text" name="promptpay_number" value="<?= e($edit['promptpay_number'] ?? '') ?>" placeholder="หมายเลข PromptPay"></label><label>ยอดที่ต้องชำระ<input type="number" name="expected_payment" min="0" step="0.01" value="<?= e($edit['expected_payment'] ?? '') ?>" placeholder="กรอกยอดที่ต้องชำระ"></label><button class="btn btn-primary btn-wide" type="submit"><?= $editingSeason ? 'อัปเดต Season' : 'บันทึก Season' ?></button><?php if ($editingSeason): ?><a class="btn btn-ghost btn-wide" href="?view=admin">ยกเลิกการแก้ไข</a><?php endif; ?></form></div>
        </section>
    <?php elseif ($view === 'privacy' || $view === 'terms' || $view === 'contact'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">GT1 / INFORMATION</p><h1><?= $view === 'privacy' ? 'ความเป็นส่วนตัว' : ($view === 'terms' ? 'ข้อกำหนดการใช้งาน' : 'ติดต่อทีมงาน') ?><br><em>GTournament1</em></h1><p><?= $view === 'privacy' ? 'เราใช้ข้อมูลเท่าที่จำเป็นต่อการสมัครแข่งขัน ตรวจสอบการชำระเงิน และดูแลความปลอดภัยของระบบ โดยไม่เปิดเผยสลิปหรือข้อมูลติดต่อเป็นสาธารณะ' : ($view === 'terms' ? 'ผู้สมัครต้องให้ข้อมูลที่ถูกต้อง ยอมรับกติกาการแข่งขัน และปฏิบัติตามคำตัดสินของทีมงานตามขั้นตอนที่ประกาศ' : 'ติดต่อทีมงานผ่านช่องทางที่ประกาศใน Season ที่เปิดอยู่ หากพบปัญหาใบสมัครหรือผลการแข่งขัน กรุณาแนบหมายเลขรายการเพื่อให้ตรวจสอบได้เร็วขึ้น') ?></p></section>
    <?php elseif ($view === 'auth'): ?>
        <?php if (false): ?>
        <section class="page-hero section-pad auth-hero"><div><p class="eyebrow"><span class="live-dot"></span> ACCOUNT / SECURE ACCESS</p><h1>เข้าสู่ระบบ<br><em>เพื่อสมัครแข่ง</em></h1><p>สร้างบัญชีเพื่อยืนยันอีเมล ติดตามใบสมัคร และเก็บประวัติการแข่งขันของคุณ</p></div><div class="auth-hero-mark" aria-hidden="true"><span>GT1</span><small>YOUR GAME<br>YOUR RECORD</small></div></section><section class="section-pad content-section auth-section"><div class="auth-layout"><div class="auth-intro"><p class="eyebrow">PLAYER HUB / 01</p><h2>สนามของคุณ<br><em>เริ่มตรงนี้</em></h2><p>บัญชีเดียวสำหรับสมัคร Season, ส่งหลักฐานการชำระเงิน และติดตามสถานะการตรวจสอบจากทีมงาน</p><ul><li><span>01</span><div><strong>สมัครได้อย่างปลอดภัย</strong><small>ยืนยันอีเมลก่อนเริ่มใช้งาน</small></div></li><li><span>02</span><div><strong>ติดตามใบสมัคร</strong><small>เห็นสถานะการตรวจสอบแบบชัดเจน</small></div></li><li><span>03</span><div><strong>เก็บประวัติการแข่งขัน</strong><small>พร้อมต่อยอดสถิติใน Season ถัดไป</small></div></li></ul></div><div class="auth-forms"><form class="auth-card auth-card-primary" method="post"><input type="hidden" name="action" value="login"><?= csrf_field() ?><div class="auth-card-head"><span class="auth-card-index">01 / RETURNING PLAYER</span><h2>ยินดีต้อนรับกลับ</h2><p>เข้าสู่บัญชีเพื่อไปต่อ</p></div><label>อีเมล<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><label>รหัสผ่าน<input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"></label><button class="btn btn-primary btn-wide" type="submit">เข้าสู่ระบบ <span>→</span></button><p class="auth-help">ยังไม่มีบัญชี? <a href="#create-account">สร้างบัญชีด้านล่าง</a></p></form><form class="auth-card" method="post"><input type="hidden" name="action" value="forgot-password"><?= csrf_field() ?><label>ลืมรหัสผ่าน? <input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><button class="btn btn-ghost btn-wide" type="submit">ส่งลิงก์ตั้งรหัสผ่านใหม่</button></form><form class="auth-card" id="create-account" method="post"><input type="hidden" name="action" value="signup"><?= csrf_field() ?><div class="auth-card-head"><span class="auth-card-index">02 / NEW PLAYER</span><h2>สร้างบัญชีใหม่</h2><p>เริ่มเก็บสถิติของคุณตั้งแต่ Season แรก</p></div><label>อีเมล<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><label>รหัสผ่าน<input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="อย่างน้อย 8 ตัวอักษร"></label><button class="btn btn-outline btn-wide" type="submit">สมัครบัญชี <span>→</span></button></form></div></div></section>
        <?php endif; ?><section class="page-hero section-pad auth-hero"><div><p class="eyebrow"><span class="live-dot"></span> STAFF / SECURE ACCESS</p><h1>เข้าสู่ระบบ<br><em>สำหรับทีมงาน</em></h1><p>พื้นที่นี้สำหรับ Staff และ Admin เท่านั้น ระบบจะตรวจสิทธิ์จากบัญชีทีมงานก่อนเปิดหน้าจัดการ</p></div></section><section class="section-pad content-section"><div class="auth-layout staff-login-layout"><div class="register-card"><h2>เข้าสู่ระบบทีมงาน</h2><p>ใช้บัญชีที่ทีมงานสร้างและกำหนด role ใน Supabase เท่านั้น</p><ul><li>Staff: ตรวจใบสมัครและข้อพิพาท</li><li>Admin: จัดการ Season และข้อมูลการแข่งขัน</li></ul></div><form class="auth-card auth-card-primary" method="post"><input type="hidden" name="action" value="staff_login"><?= csrf_field() ?><div class="auth-card-head"><span class="auth-card-index">STAFF ACCESS</span><h2>Team Login</h2><p>เข้าสู่ระบบด้วยบัญชีทีมงาน</p></div><label>อีเมลทีมงาน<input type="email" name="email" required autocomplete="username" placeholder="staff@example.com"></label><label>รหัสผ่าน<input type="password" name="password" required autocomplete="current-password" placeholder="รหัสผ่านทีมงาน"></label><button class="btn btn-primary btn-wide" type="submit">เข้าสู่ระบบทีมงาน <span>→</span></button><p class="auth-help">ไม่มีบัญชีหรือเข้าไม่ได้? ติดต่อผู้ดูแลระบบ</p></form></div></section>
    <?php elseif ($view === 'account-overview'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">ACCOUNT / PLAYER HUB</p><h1>สวัสดี<br><em><?= htmlspecialchars((string) ($user['email'] ?? 'ผู้เล่น'), ENT_QUOTES, 'UTF-8') ?></em></h1><p>ติดตามสถานะใบสมัครและการชำระเงินของคุณจากหน้านี้</p></section><section class="section-pad content-section"><div class="ranking-list"><?php if (!$myRegistrations): ?><div class="register-card"><h2>ยังไม่มีใบสมัคร</h2><a class="btn btn-primary" href="?view=register">สมัคร Season ที่เปิดอยู่ →</a></div><?php else: foreach ($myRegistrations as $registration): ?><article class="ranking-row"><div class="player-id"><strong><?= e($registration['competition_name'] ?? '') ?></strong><span>สถานะ: <?= e(RegistrationStatus::label((string) ($registration['status'] ?? ''))) ?><?php if (!empty($registration['rejection_reason'])): ?> · <?= e($registration['rejection_reason']) ?><?php endif; ?></span></div><?php if (in_array(($registration['status'] ?? ''), ['rejected','pending_payment'], true)): ?><a class="btn btn-outline" href="?view=register&resubmit=<?= e($registration['id']) ?>">ส่งข้อมูลใหม่</a><?php endif; ?></article><?php endforeach; endif; ?></div><form method="post"><input type="hidden" name="action" value="logout"><?= csrf_field() ?><button class="btn btn-outline" type="submit">ออกจากระบบ</button></form></section>
    <?php elseif ($view === 'account'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">ACCOUNT / MATCH CENTER</p><h1>แมตช์ของคุณ<br><em>เล่นให้จบ เก็บให้ครบ</em></h1><p>ส่งผลการแข่งขัน แนบหลักฐาน และติดตามสถานะการยืนยันจากคู่แข่ง</p></section><section class="section-pad content-section"><div class="match-stack"><?php if (!$user): ?><div class="register-card"><h2>กรุณาเข้าสู่ระบบ</h2><a class="btn btn-primary" href="?view=auth">เข้าสู่ระบบ →</a></div><?php elseif (!$myMatches): ?><div class="register-card"><h2>ยังไม่มีแมตช์</h2><p>ตารางการแข่งขันจะแสดงหลังทีมงานจัดสายและสร้างแมตช์แล้ว</p></div><?php else: foreach ($myMatches as $match): ?><?php $result = $match['result'] ?? null; $isVideoStage = in_array((string) ($match['stage'] ?? ''), ['semi_final', 'final'], true); ?><article class="match-card"><div class="match-card-head"><div><span class="status-pill">รอบ <?= e(match_stage_label((string) ($match['stage'] ?? ''))) ?></span><h2>ผู้เล่น A <span>vs</span> ผู้เล่น B</h2><p><?= e($match['scheduled_at'] ?? 'รอประกาศเวลา') ?><?php if (!empty($match['deadline_at'])): ?> · Deadline <?= e($match['deadline_at']) ?><?php endif; ?></p></div><strong><?= e(match_status_label((string) ($match['status'] ?? ''))) ?></strong></div><?php if ($result): ?><div class="match-score"><strong><?= e($result['score_a']) ?> - <?= e($result['score_b']) ?></strong><span>ส่งโดย <?= e($result['submitted_by']) ?></span></div><?php endif; ?><div class="evidence-list"><?php foreach (($match['evidence'] ?? []) as $evidence): ?><?php if (($evidence['evidence_type'] ?? '') === 'screenshot' && !empty($evidence['signed_url'])): ?><a class="btn btn-outline" href="<?= e($evidence['signed_url']) ?>" target="_blank" rel="noopener">ดู Screenshot</a><?php elseif (($evidence['evidence_type'] ?? '') === 'video_link' && !empty($evidence['source_url'])): ?><a class="btn btn-outline" href="<?= e($evidence['source_url']) ?>" target="_blank" rel="noopener">ดู Screen Recording</a><?php endif; ?><?php endforeach; ?></div><?php if (!in_array(($match['status'] ?? ''), ['confirmed', 'void'], true)): ?><div class="match-actions"><form class="match-form" method="post"><input type="hidden" name="action" value="submit_match_result"><input type="hidden" name="match_id" value="<?= e($match['id'] ?? '') ?>"><?= csrf_field() ?><label>สกอร์ผู้เล่น A<input type="number" name="score_a" min="0" required></label><label>สกอร์ผู้เล่น B<input type="number" name="score_b" min="0" required></label><button class="btn btn-primary" type="submit">ส่งผลการแข่งขัน</button></form><form class="match-form evidence-form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="submit_match_evidence"><input type="hidden" name="match_id" value="<?= e($match['id'] ?? '') ?>"><?= csrf_field() ?><label>Screenshot ผลการแข่งขัน<input type="file" name="screenshot" accept="image/jpeg,image/png" required></label><label>ลิงก์ Screen Recording <?php if ($isVideoStage): ?><small>(จำเป็นสำหรับรอบนี้)</small><?php else: ?><small>(ถ้ามี)</small><?php endif; ?><input type="url" name="video_url" <?= $isVideoStage ? 'required' : '' ?> placeholder="https://youtube.com/... หรือ https://drive.google.com/..."></label><button class="btn btn-outline" type="submit">ส่งหลักฐาน</button></form></div><?php if ($result && (string) ($result['submitted_by'] ?? '') !== (string) ($user['id'] ?? '')): ?><div class="match-actions"><form method="post"><input type="hidden" name="action" value="confirm_match"><input type="hidden" name="match_id" value="<?= e($match['id'] ?? '') ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit">ยืนยันผลนี้</button></form><form class="match-form" method="post"><input type="hidden" name="action" value="dispute_match"><input type="hidden" name="match_id" value="<?= e($match['id'] ?? '') ?>"><?= csrf_field() ?><input type="text" name="reason" required placeholder="เหตุผลที่โต้แย้งผล"><button class="btn btn-outline" type="submit">โต้แย้งผล</button></form></div><?php endif; ?><?php endif; ?></article><?php endforeach; endif; ?></div></section>
    <?php else: ?>
        <section class="page-hero section-pad"><p class="eyebrow">REGISTRATION / REDLINE 01</p><h1>ลงชื่อเข้าสนาม<br><em>สร้างประวัติของคุณ</em></h1><p>กรอกข้อมูลให้ครบ อ่านกติกา แล้วแนบหลักฐานการชำระเงิน</p></section><section class="section-pad content-section"><div class="register-layout"><div class="steps"><div class="step active"><span>01</span><div><b>ข้อมูลผู้สมัคร</b><small>ชื่อแข่ง / ช่องทางติดต่อ / คลับ</small></div></div><div class="step"><span>02</span><div><b>ยอมรับกติกา</b><small>อ่าน Rulebook และยืนยันข้อตกลง</small></div></div><div class="step"><span>03</span><div><b>ชำระค่าสมัคร</b><small>แนบสลิปเพื่อให้ทีมงานตรวจสอบ</small></div></div></div><?php if (!$user): ?><form class="applicant-panel" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="public_applicant_registration"><input type="hidden" name="season_id" value="<?= e($season['id']) ?>"><input type="hidden" name="applicant_type" value="returning"><?= csrf_field() ?><h2>ข้อมูลผู้สมัคร</h2><div class="applicant-choice-grid"><button class="applicant-choice is-selected" type="button"><strong>เคยลงแข่ง GTournament1 มาก่อน</strong><span>ใช้ข้อมูลผู้เล่นเดิมของ GTournament1 เพื่อสมัครต่อเนื่อง</span><small>เลือกเพื่อค้นหาผู้เล่นเดิม</small></button><button class="applicant-choice" type="button"><strong>ไม่เคย นี่เป็นการลงแข่งครั้งแรก</strong><span>สร้างข้อมูลผู้เล่นใหม่สำหรับการแข่งขันใน GTournament1</span><small>เลือกเพื่อกรอกข้อมูลผู้เล่นใหม่</small></button></div><div class="applicant-search-card"><h3>ค้นหาผู้เล่นเดิม</h3><p>ค้นหาจากชื่อ Facebook / ชื่อเล่น / ชื่อแข่งในระบบ GTournament1</p><label>ค้นหาผู้เล่นเดิม<input type="search" name="existing_player_query" placeholder="ชื่อ Facebook / ชื่อเล่น / ชื่อแข่ง"></label><button class="btn btn-outline" type="button">ค้นหา</button><small>เลือกข้อมูลผู้เล่นเดิมก่อนส่งใบสมัคร</small></div><div class="applicant-new-card"><h3>กรณีลงแข่งครั้งแรก</h3><label class="profile-upload"><span>รูปโปรไฟล์ผู้สมัคร</span><input type="file" name="profile_image" accept="image/jpeg,image/png"><small>หากไม่แนบ ระบบจะใช้รูปพื้นฐาน</small></label><div class="form-grid"><label>ชื่อที่ใช้แข่ง<input type="text" name="competition_name" maxlength="80" required placeholder="ชื่อในสนามแข่งขัน"></label><label>ชื่อเล่น <small>(ภาษาไทยได้ตามต้องการ)</small><input type="text" name="nickname" maxlength="40" required placeholder="ชื่อเล่น"></label><label>ชื่อ Facebook<input type="text" name="facebook_name" maxlength="120" placeholder="ชื่อ Facebook"></label><label>ลิงก์ Facebook<input type="url" name="facebook_url" placeholder="https://www.facebook.com/..."></label></div><label>คลับที่สังกัด<input type="text" name="club" maxlength="120" placeholder="ชื่อคลับ / ชื่อย่อคลับ"></label></div><button class="btn btn-primary btn-wide" type="submit">ส่งข้อมูลผู้สมัคร <span>→</span></button><p class="form-note">ข้อมูลจะถูกส่งให้ทีมงาน GTournament1 ตรวจสอบ</p></form><?php else: ?><form class="register-card" action="?view=register" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="<?= isset($_GET['resubmit']) ? 'resubmit' : 'registration' ?>"><input type="hidden" name="season_id" value="<?= e($season['id']) ?>"><?= csrf_field() ?><div class="form-head"><span class="status-pill"><span class="live-dot"></span> <?= e($season['status']) ?></span><h2><?= e($season['name']) ?></h2><p>เหลืออีก <?= max(0, $season['capacity'] - $season['players']) ?> ที่นั่ง · ค่าสมัคร ฿<?= e($season['fee']) ?></p><?php if ($season['promptpay_number']): ?><p>PromptPay: <?= e($season['promptpay_name']) ?> · <?= e($season['promptpay_number']) ?> · ฿<?= e($season['expected_payment']) ?></p><?php endif; ?></div><label>ชื่อที่ใช้แข่งขัน<input type="text" name="competition_name" placeholder="เช่น REDLINE.BANK" required></label><label>ชื่อเล่น<input type="text" name="nickname" placeholder="ชื่อเล่นภาษาไทย" required></label><label>ลิงก์ Facebook หรือช่องทางติดต่อ<input type="url" name="contact_url" placeholder="https://facebook.com/..." required></label><label>คลับที่สังกัด <small>(ไม่บังคับ)</small><input type="text" name="club" placeholder="ค้นหาชื่อคลับ / ชื่อย่อคลับ"></label><label>สลิปการชำระเงิน <small>(JPG, PNG หรือ PDF ไม่เกิน 5MB)</small><input type="file" name="slip" accept="image/jpeg,image/png,application/pdf" required></label><label class="check-row"><input type="checkbox" name="accept_rules" value="1" required> ฉันอ่านและยอมรับ <a href="?view=rules">กติกาการแข่งขัน</a> และเงื่อนไขการใช้ข้อมูล</label><button class="btn btn-primary btn-wide" type="submit">ส่งใบสมัครให้ทีมงานตรวจ <span>→</span></button><p class="form-note">ไฟล์สลิปจะเก็บในพื้นที่ส่วนตัวและเปิดดูได้เฉพาะทีมงาน</p></form><?php endif; ?></div></section>
    <?php endif; ?>
    </main>
    <footer class="footer"><div class="brand"><img class="brand-logo" src="assets/logo-gtournament.png" alt="GTournament1 logo"><span><strong>GTournament1</strong><small>E-FOOTBALL TOURNAMENT</small></span></div><p>สนามนี้เป็นของทุกคนที่รักการแข่งขัน</p><div><a href="?view=privacy">Privacy</a><a href="?view=terms">Terms</a><a href="?view=contact">Contact</a></div></footer>
</div><script src="assets/app.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/app.js')) ?>"></script>
</body>
</html>
