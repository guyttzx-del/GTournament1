<?php
declare(strict_types=1);
require_once __DIR__ . '/src/Application.php';

$appConfigured = app_configured();
$isProduction = $appConfigured && env_value('APP_ENV', 'production') === 'production';
$user = current_user();
$staff = is_staff_user();
$error = null;
$success = flash('success');
$season = ['fee' => 25.0];
$view = $_GET['view'] ?? 'home';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = $_POST['action'] ?? '';
        if (!$appConfigured) throw new RuntimeException('ระบบยังไม่ได้ตั้งค่า Supabase กรุณาสร้างไฟล์ .env จาก .env.example ก่อน');
        if ($action === 'signup') {
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $password = (string) ($_POST['password'] ?? '');
            if (!$email || strlen($password) < 8) throw new InvalidArgumentException('อีเมลไม่ถูกต้องและรหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
            supabase()->auth('signup', 'POST', ['email' => $email, 'password' => $password]);
            flash('success', 'สมัครบัญชีแล้ว กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ'); header('Location: ?view=auth'); exit;
        }
        if ($action === 'login') {
            $response = supabase()->auth('token?grant_type=password', 'POST', ['email' => $_POST['email'] ?? '', 'password' => $_POST['password'] ?? '']);
            $_SESSION['access_token'] = $response['access_token'] ?? null; $_SESSION['user'] = $response['user'] ?? null;
            header('Location: ?view=account'); exit;
        }
        if ($action === 'logout') { session_destroy(); header('Location: ?view=home'); exit; }
        if ($action === 'review_registration') {
            require_staff();
            $registrationId = (string) ($_POST['registration_id'] ?? ''); $status = $_POST['status'] === 'approved' ? 'approved' : 'rejected';
            $currentRows = supabase()->rest('registrations', 'id=eq.' . rawurlencode($registrationId) . '&limit=1');
            $currentStatus = (string) ($currentRows[0]['status'] ?? '');
            if (!RegistrationStatus::canTransition($currentStatus, $status)) throw new InvalidArgumentException('สถานะใบสมัครนี้ไม่สามารถเปลี่ยนเป็นสถานะที่เลือกได้');
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($status === 'rejected' && $reason === '') throw new InvalidArgumentException('กรุณาระบุเหตุผลเมื่อปฏิเสธใบสมัคร');
            supabase()->rest('registrations', 'id=eq.' . rawurlencode($registrationId), 'PATCH', ['status' => $status, 'rejection_reason' => $status === 'rejected' ? $reason : null]);
            supabase()->rest('payments', 'registration_id=eq.' . rawurlencode($registrationId), 'PATCH', ['status' => $status === 'approved' ? 'approved' : 'rejected', 'reviewed_by' => $user['id'], 'reviewed_at' => gmdate('c')]);
            supabase()->rest('audit_logs', '', 'POST', [['actor_id' => $user['id'], 'action' => 'registration.' . $status, 'entity_type' => 'registration', 'entity_id' => $registrationId]]);
            flash('success', 'อัปเดตสถานะใบสมัครแล้ว'); header('Location: ?view=staff'); exit;
        }
        if ($action === 'registration') {
            require_auth();
            $seasonId = (string) ($_POST['season_id'] ?? '');
            $seasonRows = supabase()->rest('seasons', 'id=eq.' . rawurlencode($seasonId) . '&status=eq.open&limit=1');
            if (!$seasonRows) throw new InvalidArgumentException('รายการนี้ไม่เปิดรับสมัครแล้ว');
            $service = new RegistrationService(supabase());
            $service->submit($user, $_POST, $_FILES['slip'] ?? [], $seasonId, (float) $seasonRows[0]['entry_fee']);
            flash('success', 'ส่งใบสมัครและสลิปแล้ว ทีมงานกำลังตรวจสอบ'); header('Location: ?view=account'); exit;
        }
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
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
        if ($remoteSeasons) { $remote = $remoteSeasons[0]; $labels = ['open' => 'เปิดรับสมัคร', 'running' => 'กำลังแข่งขัน', 'closed' => 'ปิดรับสมัคร', 'completed' => 'จบแล้ว']; $season = ['id' => $remote['id'], 'name' => $remote['name'], 'subtitle' => $remote['subtitle'] ?? '', 'status' => $labels[$remote['status']] ?? $remote['status'], 'players' => 0, 'capacity' => (int) $remote['capacity'], 'fee' => (float) $remote['entry_fee'], 'open' => $remote['registration_opens_at'] ?? 'ตามประกาศ', 'close' => 'จับสายหลังปิดรับสมัคร', 'prize' => 'ตรวจสอบประกาศเงินรางวัล', 'promptpay_name' => $remote['promptpay_name'] ?? '', 'promptpay_number' => $remote['promptpay_number'] ?? '', 'expected_payment' => $remote['expected_payment'] ?? $remote['entry_fee']]; } elseif ($isProduction) { $dataUnavailable = true; $season = ['id' => '', 'name' => 'ยังไม่มีรายการแข่งขัน', 'subtitle' => 'ยังไม่มี Season ที่พร้อมแสดงในขณะนี้', 'status' => 'ยังไม่พร้อม', 'players' => 0, 'capacity' => 0, 'fee' => 0, 'open' => 'รอการตั้งค่า', 'close' => 'รอการตั้งค่า', 'prize' => 'รอการตั้งค่า', 'promptpay_name' => '', 'promptpay_number' => '', 'expected_payment' => 0]; }
    } catch (Throwable) { if ($isProduction) { $dataUnavailable = true; $season = ['id' => '', 'name' => 'ไม่สามารถโหลดข้อมูลการแข่งขัน', 'subtitle' => 'กรุณาลองใหม่ภายหลัง', 'status' => 'ระบบขัดข้องชั่วคราว', 'players' => 0, 'capacity' => 0, 'fee' => 0, 'open' => '-', 'close' => '-', 'prize' => '-']; } }
}
$pendingRegistrations = [];
if ($staff && $appConfigured) { try { $pendingRegistrations = supabase()->rest('registrations', 'status=eq.pending_review&order=created_at.asc'); } catch (Throwable) { $pendingRegistrations = []; } }
$slipLinks = [];
if ($staff && $appConfigured) { foreach ($pendingRegistrations as $registration) { if (!empty($registration['slip_path'])) { try { $slipLinks[$registration['id']] = supabase()->storageSignedUrl((string) env_value('SUPABASE_SLIP_BUCKET', 'slips'), (string) $registration['slip_path']); } catch (Throwable) { $slipLinks[$registration['id']] = null; } } } }
$myRegistrations = [];
if ($user && $appConfigured) { try { $myRegistrations = supabase()->rest('registrations', 'user_id=eq.' . rawurlencode((string) $user['id']) . '&order=created_at.desc'); } catch (Throwable) { $myRegistrations = []; } }

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

$allowedViews = ['home', 'tournaments', 'rules', 'ranking', 'register', 'auth', 'account', 'staff', 'privacy', 'terms', 'contact'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'home';
}
function active(string $current, string $view): string { return $current === $view ? ' is-active' : ''; }
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
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
    <link rel="stylesheet" href="assets/style.css">
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
        </nav>
        <a class="staff-link" href="?view=<?= $staff ? 'staff' : ($user ? 'account' : 'auth') ?>"><?= $staff ? 'ศูนย์ทีมงาน' : ($user ? 'บัญชีของฉัน' : 'เข้าสู่ระบบ') ?> <span>↗</span></a>
        <button class="menu-toggle" aria-label="เปิดเมนู" aria-controls="main-navigation" aria-expanded="false" type="button">☰</button>
    </header>

    <main>
    <?php if ($error): ?><div class="alert alert-error" role="alert">กรุณาตรวจสอบ: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($dataUnavailable): ?><div class="alert alert-error" role="status">ยังไม่สามารถโหลดข้อมูลจากระบบหลักได้ โปรดลองใหม่ภายหลัง</div><?php endif; ?>
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
        <section class="page-hero section-pad"><p class="eyebrow">STAFF / REVIEW QUEUE</p><h1>ตรวจใบสมัคร<br><em>อย่างโปร่งใส</em></h1><p>คิวนี้แสดงเฉพาะใบสมัครที่ส่งสลิปแล้วและรอการตรวจสอบ</p></section><section class="section-pad content-section"><div class="ranking-list"><?php if (!$staff): ?><div class="register-card"><h2>ไม่มีสิทธิ์เข้าถึง</h2></div><?php elseif (!$pendingRegistrations): ?><div class="register-card"><h2>ไม่มีรายการรอตรวจ</h2><p>คิวตรวจว่างในขณะนี้</p></div><?php else: foreach ($pendingRegistrations as $registration): ?><article class="ranking-row"><div class="player-id"><strong><?= htmlspecialchars((string) $registration['competition_name'], ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars((string) $registration['nickname'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($registration['club'] ?? 'ไม่ระบุคลับ'), ENT_QUOTES, 'UTF-8') ?></span></div><?php if (!empty($slipLinks[$registration['id']])): ?><a class="btn btn-outline" href="<?= htmlspecialchars($slipLinks[$registration['id']], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">เปิดสลิป</a><?php endif; ?><form method="post"><input type="hidden" name="action" value="review_registration"><input type="hidden" name="registration_id" value="<?= htmlspecialchars((string) $registration['id'], ENT_QUOTES, 'UTF-8') ?>"><?= csrf_field() ?><input type="text" name="reason" placeholder="เหตุผลเมื่อปฏิเสธ" aria-label="เหตุผลเมื่อปฏิเสธ"><button class="btn btn-primary" name="status" value="approved" type="submit">อนุมัติ</button><button class="btn btn-outline" name="status" value="rejected" type="submit">ปฏิเสธ</button></form></article><?php endforeach; endif; ?></div></section>
    <?php elseif ($view === 'privacy' || $view === 'terms' || $view === 'contact'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">GT1 / INFORMATION</p><h1><?= $view === 'privacy' ? 'ความเป็นส่วนตัว' : ($view === 'terms' ? 'ข้อกำหนดการใช้งาน' : 'ติดต่อทีมงาน') ?><br><em>GTournament1</em></h1><p><?= $view === 'privacy' ? 'เราใช้ข้อมูลเท่าที่จำเป็นต่อการสมัครแข่งขัน ตรวจสอบการชำระเงิน และดูแลความปลอดภัยของระบบ โดยไม่เปิดเผยสลิปหรือข้อมูลติดต่อเป็นสาธารณะ' : ($view === 'terms' ? 'ผู้สมัครต้องให้ข้อมูลที่ถูกต้อง ยอมรับกติกาการแข่งขัน และปฏิบัติตามคำตัดสินของทีมงานตามขั้นตอนที่ประกาศ' : 'ติดต่อทีมงานผ่านช่องทางที่ประกาศใน Season ที่เปิดอยู่ หากพบปัญหาใบสมัครหรือผลการแข่งขัน กรุณาแนบหมายเลขรายการเพื่อให้ตรวจสอบได้เร็วขึ้น') ?></p></section>
    <?php elseif ($view === 'auth'): ?>
        <section class="page-hero section-pad auth-hero"><div><p class="eyebrow"><span class="live-dot"></span> ACCOUNT / SECURE ACCESS</p><h1>เข้าสู่ระบบ<br><em>เพื่อสมัครแข่ง</em></h1><p>สร้างบัญชีเพื่อยืนยันอีเมล ติดตามใบสมัคร และเก็บประวัติการแข่งขันของคุณ</p></div><div class="auth-hero-mark" aria-hidden="true"><span>GT1</span><small>YOUR GAME<br>YOUR RECORD</small></div></section><section class="section-pad content-section auth-section"><div class="auth-layout"><div class="auth-intro"><p class="eyebrow">PLAYER HUB / 01</p><h2>สนามของคุณ<br><em>เริ่มตรงนี้</em></h2><p>บัญชีเดียวสำหรับสมัคร Season, ส่งหลักฐานการชำระเงิน และติดตามสถานะการตรวจสอบจากทีมงาน</p><ul><li><span>01</span><div><strong>สมัครได้อย่างปลอดภัย</strong><small>ยืนยันอีเมลก่อนเริ่มใช้งาน</small></div></li><li><span>02</span><div><strong>ติดตามใบสมัคร</strong><small>เห็นสถานะการตรวจสอบแบบชัดเจน</small></div></li><li><span>03</span><div><strong>เก็บประวัติการแข่งขัน</strong><small>พร้อมต่อยอดสถิติใน Season ถัดไป</small></div></li></ul></div><div class="auth-forms"><form class="auth-card auth-card-primary" method="post"><input type="hidden" name="action" value="login"><?= csrf_field() ?><div class="auth-card-head"><span class="auth-card-index">01 / RETURNING PLAYER</span><h2>ยินดีต้อนรับกลับ</h2><p>เข้าสู่บัญชีเพื่อไปต่อ</p></div><label>อีเมล<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><label>รหัสผ่าน<input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"></label><button class="btn btn-primary btn-wide" type="submit">เข้าสู่ระบบ <span>→</span></button><p class="auth-help">ยังไม่มีบัญชี? <a href="#create-account">สร้างบัญชีด้านล่าง</a></p></form><form class="auth-card" id="create-account" method="post"><input type="hidden" name="action" value="signup"><?= csrf_field() ?><div class="auth-card-head"><span class="auth-card-index">02 / NEW PLAYER</span><h2>สร้างบัญชีใหม่</h2><p>เริ่มเก็บสถิติของคุณตั้งแต่ Season แรก</p></div><label>อีเมล<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label><label>รหัสผ่าน<input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="อย่างน้อย 8 ตัวอักษร"></label><button class="btn btn-outline btn-wide" type="submit">สมัครบัญชี <span>→</span></button></form></div></div></section>
    <?php elseif ($view === 'account'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">ACCOUNT / PLAYER HUB</p><h1>สวัสดี<br><em><?= htmlspecialchars((string) ($user['email'] ?? 'ผู้เล่น'), ENT_QUOTES, 'UTF-8') ?></em></h1><p>ติดตามสถานะใบสมัครและการชำระเงินของคุณจากหน้านี้</p></section><section class="section-pad content-section"><div class="ranking-list"><?php if (!$myRegistrations): ?><div class="register-card"><h2>ยังไม่มีใบสมัคร</h2><a class="btn btn-primary" href="?view=register">สมัคร Season ที่เปิดอยู่ →</a></div><?php else: foreach ($myRegistrations as $registration): ?><article class="ranking-row"><div class="player-id"><strong><?= htmlspecialchars((string) $registration['competition_name'], ENT_QUOTES, 'UTF-8') ?></strong><span>สถานะ: <?= htmlspecialchars((string) $registration['status'], ENT_QUOTES, 'UTF-8') ?></span></div></article><?php endforeach; endif; ?></div><form method="post"><input type="hidden" name="action" value="logout"><?= csrf_field() ?><button class="btn btn-outline" type="submit">ออกจากระบบ</button></form></section>
    <?php else: ?>
        <section class="page-hero section-pad"><p class="eyebrow">REGISTRATION / REDLINE 01</p><h1>ลงชื่อเข้าสนาม<br><em>สร้างประวัติของคุณ</em></h1><p>กรอกข้อมูลให้ครบ อ่านกติกา แล้วแนบหลักฐานการชำระเงิน</p></section><section class="section-pad content-section"><div class="register-layout"><div class="steps"><div class="step active"><span>01</span><div><b>ข้อมูลผู้สมัคร</b><small>ชื่อแข่ง / ช่องทางติดต่อ / คลับ</small></div></div><div class="step"><span>02</span><div><b>ยอมรับกติกา</b><small>อ่าน Rulebook และยืนยันข้อตกลง</small></div></div><div class="step"><span>03</span><div><b>ชำระค่าสมัคร</b><small>แนบสลิปเพื่อให้ทีมงานตรวจสอบ</small></div></div></div><?php if (!$user): ?><div class="register-card"><h2>ต้องเข้าสู่ระบบก่อน</h2><p>สร้างบัญชีหรือเข้าสู่ระบบเพื่อสมัครแข่งขันและติดตามสถานะ</p><a class="btn btn-primary" href="?view=auth">เข้าสู่ระบบ / สมัครบัญชี →</a></div><?php else: ?><form class="register-card" action="?view=register" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="registration"><input type="hidden" name="season_id" value="<?= htmlspecialchars((string) $season['id'], ENT_QUOTES, 'UTF-8') ?>"><?= csrf_field() ?><div class="form-head"><span class="status-pill"><span class="live-dot"></span> <?= htmlspecialchars($season['status'], ENT_QUOTES, 'UTF-8') ?></span><h2><?= htmlspecialchars($season['name'], ENT_QUOTES, 'UTF-8') ?></h2><p>เหลืออีก <?= max(0, $season['capacity'] - $season['players']) ?> ที่นั่ง · ค่าสมัคร ฿<?= htmlspecialchars((string) $season['fee'], ENT_QUOTES, 'UTF-8') ?></p></div><label>ชื่อที่ใช้แข่งขัน<input type="text" name="competition_name" placeholder="เช่น REDLINE.BANK" required></label><label>ชื่อเล่น<input type="text" name="nickname" placeholder="ชื่อเล่นภาษาไทย" required></label><label>ลิงก์ Facebook หรือช่องทางติดต่อ<input type="url" name="contact_url" placeholder="https://facebook.com/..." required></label><label>คลับที่สังกัด <small>(ไม่บังคับ)</small><input type="text" name="club" placeholder="ค้นหาชื่อคลับ / ชื่อย่อคลับ"></label><label>สลิปการชำระเงิน <small>(JPG, PNG หรือ PDF ไม่เกิน 5MB)</small><input type="file" name="slip" accept="image/jpeg,image/png,application/pdf" required></label><label class="check-row"><input type="checkbox" required> ฉันอ่านและยอมรับ <a href="?view=rules">กติกาการแข่งขัน</a> และเงื่อนไขการใช้ข้อมูล</label><button class="btn btn-primary btn-wide" type="submit">ส่งใบสมัครให้ทีมงานตรวจ <span>→</span></button><p class="form-note">ไฟล์สลิปจะเก็บในพื้นที่ส่วนตัวและเปิดดูได้เฉพาะทีมงาน</p></form><?php endif; ?></div></section>
    <?php endif; ?>
    </main>
    <footer class="footer"><div class="brand"><img class="brand-logo" src="assets/logo-gtournament.png" alt="GTournament1 logo"><span><strong>GTournament1</strong><small>E-FOOTBALL TOURNAMENT</small></span></div><p>สนามนี้เป็นของทุกคนที่รักการแข่งขัน</p><div><a href="?view=privacy">Privacy</a><a href="?view=terms">Terms</a><a href="?view=contact">Contact</a></div></footer>
</div><script src="assets/app.js"></script>
</body>
</html>
