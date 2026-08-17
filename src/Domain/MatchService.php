<?php
declare(strict_types=1);

final class MatchService
{
    public function __construct(private SupabaseClient $db) {}
    public function submitResult(string $matchId, string $userId, int $scoreA, int $scoreB): array
    {
        if ($scoreA < 0 || $scoreB < 0) throw new InvalidArgumentException('สกอร์ต้องไม่ติดลบ');
        $matches = $this->db->rest('matches', 'id=eq.' . rawurlencode($matchId) . '&limit=1'); $match = $matches[0] ?? null;
        if (!$match || !in_array($match['status'], ['scheduled','awaiting_result','disputed'], true)) throw new InvalidArgumentException('แมตช์นี้ไม่พร้อมรับผล');
        $access = $this->db->rest('match_player_access', 'match_id=eq.' . rawurlencode($matchId) . '&user_id=eq.' . rawurlencode($userId) . '&limit=1');
        if (!$access) throw new RuntimeException('คุณไม่มีสิทธิ์ส่งผลแมตช์นี้');
        $rows = $this->db->rest('match_results', '', 'POST', [['match_id' => $matchId, 'score_a' => $scoreA, 'score_b' => $scoreB, 'submitted_by' => $userId]]);
        $this->db->rest('matches', 'id=eq.' . rawurlencode($matchId), 'PATCH', ['status' => 'awaiting_result']);
        return $rows[0] ?? [];
    }
    public function confirm(string $matchId, string $userId): void
    {
        $this->db->rpc('confirm_match_result', ['p_match_id' => $matchId, 'p_user_id' => $userId]);
    }
    public function dispute(string $matchId, string $userId, string $reason): void
    {
        if (trim($reason) === '') throw new InvalidArgumentException('กรุณาระบุเหตุผลการโต้แย้ง');
        $this->db->rpc('dispute_match_result', ['p_match_id' => $matchId, 'p_user_id' => $userId, 'p_reason' => trim($reason)]);
    }
}
