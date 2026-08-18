<?php
declare(strict_types=1);

final class MockSupabaseClient extends SupabaseClient
{
    private string $file;

    public function __construct()
    {
        parent::__construct('http://mock.local', 'local-mock-key');
        $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mock';
        if (!is_dir($directory)) @mkdir($directory, 0700, true);
        $this->file = $directory . DIRECTORY_SEPARATOR . 'local.json';
        if (!is_file($this->file)) $this->write($this->seed());
    }

    public function auth(string $path, string $method = 'POST', $body = null): array
    {
        $state = $this->read();
        $body = is_array($body) ? $body : [];
        if ($path === 'signup') {
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            foreach ($state['users'] as $user) if ($user['email'] === $email) throw new RuntimeException('อีเมลนี้มีบัญชีอยู่แล้ว');
            $state['users'][] = ['id' => 'u-' . bin2hex(random_bytes(5)), 'email' => $email, 'password_hash' => password_hash((string) ($body['password'] ?? ''), PASSWORD_DEFAULT), 'email_confirmed_at' => gmdate('c')];
            $this->write($state);
            return [];
        }
        if (str_starts_with($path, 'token?grant_type=password')) {
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            foreach ($state['users'] as $user) {
                if ($user['email'] === $email && password_verify((string) ($body['password'] ?? ''), $user['password_hash'])) return $this->session($user);
            }
            throw new RuntimeException('อีเมลหรือรหัสผ่านไม่ถูกต้อง');
        }
        if (str_starts_with($path, 'token?grant_type=refresh_token')) return $this->session($state['users'][0]);
        return [];
    }

    public function rest(string $table, string $query = '', string $method = 'GET', $body = null): array
    {
        $state = $this->read(); $rows = $state['tables'][$table] ?? [];
        $bodyRows = is_array($body) && array_is_list($body) ? $body : (is_array($body) ? [$body] : []);
        if ($method === 'GET') {
            if ($table === 'match_player_access') {
                preg_match('/match_id=eq\.([^&]+)/', $query, $matchId); preg_match('/user_id=eq\.([^&]+)/', $query, $userId);
                $registrationIds = array_map(fn(array $r): string => (string) $r['id'], array_filter($state['tables']['registrations'], fn(array $r): bool => (string) ($r['user_id'] ?? '') === rawurldecode($userId[1] ?? '')));
                return array_values(array_filter($state['tables']['matches'], fn(array $m): bool => (string) ($m['id'] ?? '') === rawurldecode($matchId[1] ?? '') && (in_array((string) ($m['player_a_registration_id'] ?? ''), $registrationIds, true) || in_array((string) ($m['player_b_registration_id'] ?? ''), $registrationIds, true))));
            }
            return array_values(array_filter($rows, fn(array $row): bool => $this->matches($row, $query)));
        }
        if ($method === 'POST') {
            foreach ($bodyRows as $row) { $row['id'] ??= 'id-' . bin2hex(random_bytes(6)); $row['created_at'] ??= gmdate('c'); $rows[] = $row; }
            $state['tables'][$table] = $rows; $this->write($state); return $bodyRows;
        }
        if ($method === 'PATCH') {
            $updated = [];
            foreach ($rows as &$row) if ($this->matches($row, $query)) { $row = array_merge($row, $bodyRows[0] ?? []); $updated[] = $row; }
            unset($row); $state['tables'][$table] = $rows; $this->write($state); return $updated;
        }
        return [];
    }

    public function rpc(string $function, array $args = []): array
    {
        $state = $this->read();
        if ($function === 'append_audit_log') {
            $state['tables']['audit_logs'][] = ['id' => 'audit-' . bin2hex(random_bytes(4)), 'actor_id' => $args['p_actor_id'] ?? null, 'action' => $args['p_action'] ?? '', 'entity_type' => $args['p_entity_type'] ?? '', 'entity_id' => $args['p_entity_id'] ?? null, 'metadata' => $args['p_metadata'] ?? [], 'created_at' => gmdate('c')];
            $this->write($state); return [];
        }
        if ($function === 'submit_match_result') {
            $matchId = (string) ($args['p_match_id'] ?? ''); $userId = (string) ($args['p_user_id'] ?? '');
            $existing = array_values(array_filter($state['tables']['match_results'], fn(array $row): bool => (string) ($row['match_id'] ?? '') === $matchId));
            if ($existing && (string) ($existing[0]['submitted_by'] ?? '') !== $userId) throw new RuntimeException('result_already_submitted');
            $result = $existing[0] ?? ['id' => 'result-' . bin2hex(random_bytes(5)), 'match_id' => $matchId, 'submitted_by' => $userId];
            $result['score_a'] = (int) ($args['p_score_a'] ?? 0); $result['score_b'] = (int) ($args['p_score_b'] ?? 0); $result['submitted_at'] = gmdate('c');
            $state['tables']['match_results'] = array_values(array_filter($state['tables']['match_results'], fn(array $row): bool => (string) ($row['match_id'] ?? '') !== $matchId)); $state['tables']['match_results'][] = $result;
            foreach ($state['tables']['matches'] as &$match) if ((string) ($match['id'] ?? '') === $matchId) $match['status'] = 'awaiting_result';
            unset($match); $this->write($state); return [$result];
        }
        if ($function === 'reserve_registration') {
            $id = 'reg-' . bin2hex(random_bytes(5));
            $state['tables']['registrations'][] = ['id' => $id, 'season_id' => $args['p_season_id'], 'user_id' => $args['p_user_id'], 'competition_name' => $args['p_competition_name'], 'nickname' => $args['p_nickname'], 'contact_url' => $args['p_contact_url'], 'club' => $args['p_club'], 'status' => 'pending_payment', 'created_at' => gmdate('c')];
            $this->write($state); return [['id' => $id]];
        }
        if ($function === 'confirm_match_result') return $this->transitionMatch($state, (string) ($args['p_match_id'] ?? ''), 'confirmed', $args);
        if ($function === 'dispute_match_result') return $this->transitionMatch($state, (string) ($args['p_match_id'] ?? ''), 'disputed', $args);
        if ($function === 'resolve_match_dispute') return $this->transitionMatch($state, (string) ($args['p_match_id'] ?? ''), (string) ($args['p_outcome'] ?? 'void'), $args);
        return [];
    }

    public function storageUpload(string $bucket, string $path, array $file): array { return ['bucket' => $bucket, 'path' => $path]; }
    public function storageSignedUrl(string $bucket, string $path, int $expiresIn = 300): string { return 'https://mock.local/storage/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($path)); }

    private function transitionMatch(array $state, string $matchId, string $status, array $args): array
    {
        foreach ($state['tables']['matches'] as &$match) if ($match['id'] === $matchId) { $match['status'] = $status; $match['decision_note'] = $args['p_note'] ?? $args['p_reason'] ?? null; }
        unset($match); $this->write($state); return [];
    }
    private function matches(array $row, string $query): bool
    {
        if ($query === '') return true;
        foreach (preg_split('/&|,/', $query) ?: [] as $part) {
            if (!str_contains($part, '=eq.')) continue;
            [$field, $value] = explode('=eq.', $part, 2); $field = trim($field); $value = rawurldecode(trim($value));
            if ($field !== '' && isset($row[$field]) && (string) $row[$field] !== trim($value, '()')) return false;
        }
        if (preg_match('/or=\((.*)\)/', $query, $or)) {
            $ok = false; foreach (explode(',', $or[1]) as $condition) { if (preg_match('/([\w_]+)\.in\.\(([^)]*)\)/', $condition, $m) && in_array((string) ($row[$m[1]] ?? ''), array_map('rawurldecode', explode(',', $m[2])), true)) $ok = true; } if (!$ok) return false;
        }
        return true;
    }
    private function session(array $user): array { return ['access_token' => 'mock-' . bin2hex(random_bytes(8)), 'refresh_token' => 'mock-refresh', 'expires_in' => 3600, 'user' => ['id' => $user['id'], 'email' => $user['email'], 'email_confirmed_at' => $user['email_confirmed_at']]]; }
    private function read(): array { $raw = @file_get_contents($this->file); $data = is_string($raw) ? json_decode($raw, true) : null; return is_array($data) ? $data : $this->seed(); }
    private function write(array $state): void { file_put_contents($this->file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX); }
    private function seed(): array
    {
        return ['users' => [['id' => 'demo-player-a', 'email' => 'player@example.com', 'password_hash' => password_hash('password123', PASSWORD_DEFAULT), 'email_confirmed_at' => gmdate('c')], ['id' => 'demo-staff', 'email' => 'staff@example.com', 'password_hash' => password_hash('staff12345', PASSWORD_DEFAULT), 'email_confirmed_at' => gmdate('c')]], 'tables' => [
            'seasons' => [['id' => 'season-local-01', 'name' => 'GTournament1 Season 01', 'subtitle' => 'Local Mock Tournament', 'status' => 'open', 'capacity' => 32, 'entry_fee' => 25, 'expected_payment' => 25, 'registration_opens_at' => null, 'registration_closes_at' => null, 'created_at' => gmdate('c')]],
            'profiles' => [['id' => 'demo-player-a', 'display_name' => 'Demo Player A', 'competition_name' => 'Demo Player A', 'nickname' => 'PLAYER A', 'club' => 'GT1 Academy']],
            'registrations' => [['id' => 'reg-demo-a', 'season_id' => 'season-local-01', 'user_id' => 'demo-player-a', 'competition_name' => 'Demo Player A', 'nickname' => 'PLAYER A', 'club' => 'GT1 Academy', 'status' => 'approved', 'created_at' => gmdate('c')], ['id' => 'reg-demo-b', 'season_id' => 'season-local-01', 'user_id' => 'demo-player-b', 'competition_name' => 'Demo Player B', 'nickname' => 'PLAYER B', 'club' => 'LOCAL FC', 'status' => 'approved', 'created_at' => gmdate('c')]],
            'matches' => [['id' => 'match-demo-01', 'player_a_registration_id' => 'reg-demo-a', 'player_b_registration_id' => 'reg-demo-b', 'stage' => 'group', 'status' => 'scheduled', 'scheduled_at' => '2026-08-20 20:00', 'deadline_at' => '2026-08-21 22:00', 'created_at' => gmdate('c')]],
            'match_results' => [], 'match_evidence' => [], 'staff_roles' => [['user_id' => 'demo-staff', 'role' => 'staff']], 'payments' => [], 'audit_logs' => [],
        ]];
    }
}
