<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Application.php';

$_ENV['APP_ENV'] = 'local';
$_ENV['LOCAL_MOCK'] = 'true';
if (!app_uses_mock()) throw new RuntimeException('local mock mode should be enabled by default');
$db = new MockSupabaseClient();
$auth = new AuthService($db);
$session = $auth->signIn('player@example.com', 'password123');
if (($session['user']['id'] ?? '') !== 'demo-player-a') throw new RuntimeException('demo player login failed');
$matches = $db->rest('matches', 'or=(player_a_registration_id.in.(reg-demo-a),player_b_registration_id.in.(reg-demo-a))');
if (count($matches) !== 1) throw new RuntimeException('demo match fixture missing');
$access = $db->rest('match_player_access', 'match_id=eq.match-demo-01&user_id=eq.demo-player-a&limit=1');
if (!$access) throw new RuntimeException('mock player access failed');
$db->rpc('dispute_match_result', ['p_match_id' => 'match-demo-01', 'p_user_id' => 'demo-player-a', 'p_reason' => 'ตรวจสอบหลักฐาน']);
$disputed = $db->rest('matches', 'id=eq.match-demo-01&limit=1');
if (($disputed[0]['status'] ?? '') !== 'disputed') throw new RuntimeException('mock dispute transition failed');
echo "Mock tests passed\n";
