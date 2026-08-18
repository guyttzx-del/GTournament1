<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Infrastructure/SupabaseClient.php';
require_once __DIR__ . '/../src/Domain/AuthService.php';
require_once __DIR__ . '/../src/Domain/MatchService.php';

final class MockSupabaseClient extends SupabaseClient
{
    public array $calls = [];
    public function __construct() { parent::__construct('https://mock.invalid', 'test-key'); }
    public function auth(string $path, string $method = 'POST', $body = null): array { $this->calls[] = ['auth',$path,$body]; return $path === 'signup' ? [] : ['access_token'=>'token','refresh_token'=>'refresh','expires_in'=>3600,'user'=>['id'=>'u1','email'=>'player@example.com','email_confirmed_at'=>'2026-01-01T00:00:00Z']]; }
    public function rest(string $table, string $query = '', string $method = 'GET', $body = null): array { $this->calls[] = [$table,$query,$method,$body]; if ($table === 'match_player_access') return str_contains($query, 'u1') ? [['match_id'=>'m1','user_id'=>'u1']] : []; if ($table === 'matches') return [['id'=>'m1','status'=>'scheduled','stage'=>'group']]; return []; }
    public function rpc(string $function, array $args = []): array { $this->calls[] = ['rpc',$function,$args]; return []; }
    public function storageUpload(string $bucket, string $path, array $file): array { $this->calls[] = ['storageUpload',$bucket,$path,$file['type']]; return []; }
}
$db = new MockSupabaseClient();
$auth = new AuthService($db); $session = $auth->signIn('player@example.com','password');
if (empty($session['access_token'])) throw new RuntimeException('auth contract failed');
$match = new MatchService($db); $match->submitResult('m1','u1',2,1);
if (!array_filter($db->calls, static fn(array $call): bool => $call[0] === 'match_player_access')) throw new RuntimeException('player access contract failed');
if (!array_filter($db->calls, static fn(array $call): bool => $call[0] === 'rpc' && $call[1] === 'submit_match_result')) throw new RuntimeException('atomic result contract failed');
$temp = tempnam(sys_get_temp_dir(), 'gt-match-'); file_put_contents($temp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
$match->submitEvidence('m1', 'u1', ['error' => UPLOAD_ERR_OK, 'tmp_name' => $temp, 'size' => 10, 'name' => 'result.png'], 'https://youtu.be/example');
unlink($temp);
if (!array_filter($db->calls, static fn(array $call): bool => $call[0] === 'storageUpload')) throw new RuntimeException('evidence upload contract failed');
if (!MatchService::isVideoUrl('https://youtu.be/example') || MatchService::isVideoUrl('http://youtu.be/example') || MatchService::isVideoUrl('https://example.com/video')) throw new RuntimeException('video URL validation failed');
echo "Contract tests passed\n";
