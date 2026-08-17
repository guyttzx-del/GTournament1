<?php
declare(strict_types=1);

// Prototype data layer. Replace these arrays with Supabase queries in the next iteration.
$season = [
    'name' => 'Redline Season 01',
    'subtitle' => 'E-Football Community Tournament',
    'status' => 'เปิดรับสมัคร',
    'players' => 24,
    'capacity' => 32,
    'fee' => 25,
    'close' => 'ปิดรับสมัคร ศุกร์ 20:00 น.',
];

$rankings = [
    ['rank' => 1, 'name' => 'Brave Suppakorn', 'handle' => 'BRAVE', 'club' => 'RED PHOENIX', 'points' => '280.00', 'form' => 'W W W'],
    ['rank' => 2, 'name' => 'Kongphop Keawsanthia', 'handle' => 'WAVE', 'club' => 'DARK HORSE', 'points' => '224.50', 'form' => 'W W L'],
    ['rank' => 3, 'name' => 'Wo Woradon', 'handle' => 'BASS', 'club' => 'NOVA FC', 'points' => '198.25', 'form' => 'W D W'],
];

$rules = [
    ['icon' => '◉', 'title' => 'รูปแบบการแข่งขัน', 'text' => 'Group Stage เก็บคะแนน และ Knockout แบบนัดเดียวจบ'],
    ['icon' => '◷', 'title' => 'เวลาแข่งขัน', 'text' => '6 นาทีรอบทั่วไป / 8 นาทีรอบรองชนะเลิศและรอบชิงชนะเลิศ'],
    ['icon' => '✓', 'title' => 'ส่งผลการแข่งขัน', 'text' => 'ผู้ชนะส่งสกอร์ ทีมที่ใช้ และภาพสรุปผลหลังจบเกม'],
    ['icon' => '!', 'title' => 'ความยุติธรรม', 'text' => 'รอคู่แข่ง 15 นาที เก็บหลักฐาน และแจ้งทีมงานตามกำหนด'],
];

$view = $_GET['view'] ?? 'home';
$allowedViews = ['home', 'tournaments', 'rules', 'ranking', 'register'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'home';
}
function active(string $current, string $view): string { return $current === $view ? ' is-active' : ''; }
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>REDLINE HUB — E-Football Tournament</title>
    <meta name="description" content="ศูนย์กลางการแข่งขัน E-Football Community Tournament">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="site-shell">
    <header class="topbar">
        <a class="brand" href="?view=home" aria-label="Redline Hub home">
            <span class="brand-mark">R<span>/</span></span>
            <span><strong>REDLINE</strong><small>COMMUNITY HUB</small></span>
        </a>
        <nav class="main-nav" aria-label="เมนูหลัก">
            <a class="<?= active('home', $view) ?>" href="?view=home">Dashboard</a>
            <a class="<?= active('tournaments', $view) ?>" href="?view=tournaments">Tournaments</a>
            <a class="<?= active('ranking', $view) ?>" href="?view=ranking">Rankings</a>
            <a class="<?= active('rules', $view) ?>" href="?view=rules">Rules</a>
        </nav>
        <a class="staff-link" href="#staff">สำหรับทีมงาน <span>↗</span></a>
        <button class="menu-toggle" aria-label="เปิดเมนู" type="button">☰</button>
    </header>

    <main>
    <?php if ($view === 'home'): ?>
        <section class="hero section-pad">
            <div class="hero-copy">
                <p class="eyebrow"><span class="live-dot"></span> SEASON 01 / REGISTRATION OPEN</p>
                <h1>PLAY HARD.<br><em>LEAVE A MARK.</em></h1>
                <p class="hero-lead">สนามแข่งขัน E-Football สำหรับคนที่อยากวัดฝีมือ เก็บสถิติ และสร้างชื่อของตัวเองในชุมชน</p>
                <div class="hero-actions"><a class="btn btn-primary" href="?view=register">สมัครแข่งขัน <span>→</span></a><a class="btn btn-ghost" href="?view=tournaments">ดูรายการทั้งหมด</a></div>
                <div class="hero-meta"><span><strong><?= $season['players'] ?></strong> / <?= $season['capacity'] ?> PLAYERS</span><span><strong>฿<?= $season['fee'] ?></strong> ENTRY FEE</span><span><strong>32</strong> MAX SLOTS</span></div>
            </div>
            <div class="hero-art" aria-hidden="true"><div class="art-ring ring-one"></div><div class="art-ring ring-two"></div><div class="art-cross"></div><div class="art-label">R01<br><span>EST. 2026</span></div></div>
        </section>

        <section class="ticker"><div>● LATEST UPDATE</div><p>เปิดรับสมัคร Redline Season 01 แล้ววันนี้ — ปิดรับสมัครเมื่อครบ 32 คน</p><a href="?view=register">สมัครเลย →</a></section>

        <section class="section-pad section-grid">
            <div class="section-heading"><p class="eyebrow">01 / ACTIVE TOURNAMENT</p><h2>รายการที่กำลังเปิดรับสมัคร</h2><p>ทุกแมตช์มีระบบบันทึกผล ตรวจสอบได้ และเก็บเป็นสถิติของคุณ</p></div>
            <article class="season-card"><div class="season-card-top"><span class="status-pill"><span class="live-dot"></span><?= $season['status'] ?></span><span class="season-code">RL / 001</span></div><h3><?= $season['name'] ?></h3><p><?= $season['subtitle'] ?></p><div class="progress-label"><span>ที่นั่งคงเหลือ</span><strong><?= $season['players'] ?> / <?= $season['capacity'] ?></strong></div><div class="progress"><i style="width: <?= ($season['players'] / $season['capacity']) * 100 ?>%"></i></div><div class="card-foot"><span><?= $season['close'] ?></span><a href="?view=register">ดูรายละเอียด →</a></div></article>
        </section>

        <section class="section-pad dark-band"><div class="section-heading"><p class="eyebrow">02 / THE RULEBOOK</p><h2>กติกาที่ทำให้ทุกเกมแฟร์</h2><p>อ่านให้ครบก่อนสมัคร เพื่อให้การแข่งขันสนุก โปร่งใส และเคารพคู่แข่ง</p></div><div class="rule-grid"><?php foreach ($rules as $rule): ?><article class="rule-card"><span class="rule-icon"><?= $rule['icon'] ?></span><h3><?= $rule['title'] ?></h3><p><?= $rule['text'] ?></p></article><?php endforeach; ?></div><a class="text-link" href="?view=rules">อ่านกติกาฉบับเต็ม <span>→</span></a></section>

        <section class="section-pad ranking-preview"><div class="section-heading"><p class="eyebrow">03 / CURRENT RANKING</p><h2>ผู้เล่นที่กำลังร้อนแรง</h2></div><div class="ranking-list"><?php foreach ($rankings as $player): ?><article class="ranking-row"><span class="rank-number">0<?= $player['rank'] ?></span><div class="avatar avatar-<?= $player['rank'] ?>"><?= substr($player['handle'], 0, 1) ?></div><div class="player-id"><strong><?= $player['name'] ?></strong><span><?= $player['handle'] ?> · <?= $player['club'] ?></span></div><div class="form"><span><?= $player['form'] ?></span></div><strong class="points"><?= $player['points'] ?><small>BCL POINT</small></strong></article><?php endforeach; ?></div><a class="btn btn-outline" href="?view=ranking">ดูอันดับทั้งหมด →</a></section>
    <?php elseif ($view === 'tournaments'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">TOURNAMENTS / 2026</p><h1>สนามแข่งของคุณ<br><em>เริ่มที่นี่</em></h1><p>เลือกรายการที่ใช่ ตรวจสอบกติกา และลงชื่อให้ทันก่อนที่นั่งเต็ม</p></section>
        <section class="section-pad content-section"><div class="filter-tabs"><a class="active" href="#">ทั้งหมด</a><a href="#">เปิดรับสมัคร</a><a href="#">กำลังแข่งขัน</a><a href="#">จบแล้ว</a></div><article class="tournament-detail"><div><span class="status-pill"><span class="live-dot"></span> <?= $season['status'] ?></span><span class="season-code">RL / 001</span><h2><?= $season['name'] ?></h2><p><?= $season['subtitle'] ?> · จับสายหลังปิดรับสมัครทันที</p><div class="detail-stats"><span><b>32</b> ที่นั่ง</span><span><b>฿25</b> ค่าสมัคร</span><span><b>6 / 8</b> นาที</span><span><b>1</b> แมตช์จบ</span></div><a class="btn btn-primary" href="?view=register">สมัครรายการนี้ →</a></div><div class="tournament-poster"><span>RL</span><b>REDLINE<br>SEASON 01</b><small>NO EXCUSES / JUST PLAY</small></div></article></section>
    <?php elseif ($view === 'rules'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">RULEBOOK / VERSION 1.0</p><h1>กติกา<br><em>ที่ทุกคนต้องรู้</em></h1><p>สรุปจากเอกสารระเบียบและกติกาการแข่งขัน E-Football Tournament สำหรับ Season 01</p></section>
        <section class="section-pad content-section"><div class="rules-layout"><aside class="rules-index"><a class="active" href="#format">01 รูปแบบการแข่งขัน</a><a href="#match">02 การตั้งค่าแมตช์</a><a href="#report">03 การรายงานผล</a><a href="#fairplay">04 Fair Play & บทลงโทษ</a><a href="#prize">05 เงินรางวัล</a></aside><div class="rules-body"><article id="format" class="rule-block"><span class="rule-num">01</span><h2>รูปแบบการแข่งขัน</h2><div class="rule-table"><div><b>Group Stage</b><span>เก็บคะแนน · 6 นาที · ไม่มีต่อเวลา/จุดโทษ</span></div><div><b>Knockout Stage</b><span>นัดเดียวจบ · 6 นาที · มีต่อเวลา/จุดโทษ</span></div><div><b>Semi & Final</b><span>นัดเดียวจบ · 8 นาที · มีต่อเวลา/จุดโทษ</span></div></div></article><article id="match" class="rule-block"><span class="rule-num">02</span><h2>Match Settings</h2><div class="settings-grid"><span>Match Type<b>Standard</b></span><span>Injuries<b>On</b></span><span>Substitution<b>5 คน / 3 ครั้ง</b></span><span>Home/Away<b>Excellent</b></span><span>Smart Assist<b>เปิดหรือปิดได้</b></span></div></article><article id="report" class="rule-block"><span class="rule-num">03</span><h2>รายงานผลและหลักฐาน</h2><p>ผู้ชนะมีหน้าที่ส่งผลผ่านระบบ พร้อมชื่อผู้เล่น ทีม/สโมสรที่ใช้ สกอร์ และภาพหน้าจอสรุปผลหลังเกม หากเป็นรอบ Semi-Finals หรือ Finals ต้องส่ง Screen Recording ภายใน 2 ชั่วโมง</p></article><article id="fairplay" class="rule-block"><span class="rule-num">04</span><h2>Fair Play</h2><p>รอคู่แข่งได้ 15 นาที เก็บหลักฐานการติดต่อกรณีขอ Walkover ห้ามถ่วงเวลา Match-fixing หรือ Rage Quit การฝ่าฝืนอาจถูกปรับแพ้ ตัดสิทธิ์ ริบเงินรางวัล และพักการแข่งขัน Season ถัดไป</p></article><article id="prize" class="rule-block"><span class="rule-num">05</span><h2>ค่าสมัครและเงินรางวัล</h2><div class="prize-table"><div><b>Champion</b><span>16 คน ฿140</span><span>32 คน ฿280</span></div><div><b>Runner-up</b><span>16 คน ฿60</span><span>32 คน ฿160</span></div><div><b>Semi-finalist</b><span>คนละ ฿30</span><span>คนละ ฿50</span></div><div><b>รอบ 8 ทีม</b><span>คนละ ฿15</span><span>คนละ ฿25</span></div></div></article><a class="btn btn-primary" href="?view=register">ฉันอ่านและพร้อมสมัคร →</a></div></div></section>
    <?php elseif ($view === 'ranking'): ?>
        <section class="page-hero section-pad"><p class="eyebrow">RANKINGS / LIVE TABLE</p><h1>ชื่อของคุณ<br><em>อยู่ตรงไหน?</em></h1><p>คะแนนสะสมจากผลงานจริง ทุกแมตช์มีความหมาย</p></section><section class="section-pad content-section"><div class="filter-tabs"><a class="active" href="#">Player</a><a href="#">Club</a><a href="#">All-Time</a></div><div class="ranking-list full"><?php foreach ($rankings as $player): ?><article class="ranking-row"><span class="rank-number">0<?= $player['rank'] ?></span><div class="avatar avatar-<?= $player['rank'] ?>"><?= substr($player['handle'], 0, 1) ?></div><div class="player-id"><strong><?= $player['name'] ?></strong><span><?= $player['handle'] ?> · <?= $player['club'] ?></span></div><div class="record"><span>PLAYED <b><?= 12 - $player['rank'] ?></b></span><span>WIN RATE <b><?= 82 - ($player['rank'] * 7) ?>%</b></span></div><strong class="points"><?= $player['points'] ?><small>BCL POINT</small></strong></article><?php endforeach; ?><article class="ranking-row muted-row"><span class="rank-number">04</span><div class="avatar">?</div><div class="player-id"><strong>ผู้เล่นคนถัดไปคือคุณ</strong><span>ลงแข่ง Season 01 เพื่อขึ้นอันดับ</span></div><a class="btn btn-outline" href="?view=register">เริ่มไต่แรงก์</a></article></div></section>
    <?php else: ?>
        <section class="page-hero section-pad"><p class="eyebrow">REGISTRATION / REDLINE 01</p><h1>ลงชื่อเข้าสนาม<br><em>สร้างประวัติของคุณ</em></h1><p>กรอกข้อมูลให้ครบ อ่านกติกา แล้วเตรียมหลักฐานการชำระเงิน</p></section><section class="section-pad content-section"><div class="register-layout"><div class="steps"><div class="step active"><span>01</span><div><b>ข้อมูลผู้สมัคร</b><small>ชื่อแข่ง / ช่องทางติดต่อ / คลับ</small></div></div><div class="step"><span>02</span><div><b>ยอมรับกติกา</b><small>อ่าน Rulebook และยืนยันข้อตกลง</small></div></div><div class="step"><span>03</span><div><b>ชำระค่าสมัคร</b><small>แนบสลิปเพื่อให้ทีมงานตรวจสอบ</small></div></div></div><form class="register-card" action="#" method="post"><div class="form-head"><span class="status-pill"><span class="live-dot"></span> <?= $season['status'] ?></span><h2><?= $season['name'] ?></h2><p>เหลืออีก <?= $season['capacity'] - $season['players'] ?> ที่นั่ง · ค่าสมัคร ฿<?= $season['fee'] ?></p></div><label>ชื่อที่ใช้แข่งขัน<input type="text" name="competition_name" placeholder="เช่น REDLINE.BANK" required></label><label>ชื่อเล่น<input type="text" name="nickname" placeholder="ชื่อเล่นภาษาไทย" required></label><label>ลิงก์ Facebook หรือช่องทางติดต่อ<input type="url" name="contact_url" placeholder="https://facebook.com/..." required></label><label>คลับที่สังกัด <small>(ไม่บังคับ)</small><input type="text" name="club" placeholder="ค้นหาชื่อคลับ / ชื่อย่อคลับ"></label><label class="check-row"><input type="checkbox" required> ฉันอ่านและยอมรับ <a href="?view=rules">กติกาการแข่งขัน</a> และเงื่อนไขการใช้ข้อมูล</label><button class="btn btn-primary btn-wide" type="submit">ไปขั้นตอนชำระเงิน <span>→</span></button><p class="form-note">หลังส่งใบสมัคร ทีมงานจะตรวจสอบข้อมูลและสลิปก่อนยืนยันสิทธิ์เข้าร่วม</p></form></div></section>
    <?php endif; ?>
    </main>
    <footer class="footer"><div class="brand"><span class="brand-mark">R<span>/</span></span><span><strong>REDLINE</strong><small>COMMUNITY HUB</small></span></div><p>สนามนี้เป็นของทุกคนที่รักการแข่งขัน</p><div><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Contact</a></div></footer>
</div><script src="assets/app.js"></script>
</body>
</html>
