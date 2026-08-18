<?php
declare(strict_types=1);

final class MatchService
{
    private const EVIDENCE_BUCKET = 'match-evidence';
    private const IMAGE_MIMES = ['image/jpeg', 'image/png'];
    private const VIDEO_STAGES = ['semi_final', 'final'];

    public function __construct(private SupabaseClient $db) {}

    public function submitResult(string $matchId, string $userId, int $scoreA, int $scoreB): array
    {
        if ($scoreA < 0 || $scoreB < 0) throw new InvalidArgumentException('สกอร์ต้องไม่ติดลบ');
        $match = $this->matchForPlayer($matchId, $userId);
        if (!in_array($match['status'] ?? '', ['scheduled', 'awaiting_result', 'disputed'], true)) throw new InvalidArgumentException('แมตช์นี้ไม่พร้อมรับผล');
        return $this->db->rpc('submit_match_result', ['p_match_id' => $matchId, 'p_user_id' => $userId, 'p_score_a' => $scoreA, 'p_score_b' => $scoreB])[0] ?? [];
    }

    public function submitEvidence(string $matchId, string $userId, array $screenshot, ?string $videoUrl): void
    {
        $match = $this->matchForPlayer($matchId, $userId);
        $stage = (string) ($match['stage'] ?? 'group');
        if (($screenshot['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('กรุณาแนบภาพ Screenshot ผลการแข่งขัน');
        if (!is_uploaded_file($screenshot['tmp_name']) && PHP_SAPI !== 'cli') throw new InvalidArgumentException('ไฟล์หลักฐานไม่ถูกต้อง');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($screenshot['tmp_name']);
        if (!in_array($mime, self::IMAGE_MIMES, true) || ($screenshot['size'] ?? 0) > 5 * 1024 * 1024) throw new InvalidArgumentException('Screenshot ต้องเป็น JPG หรือ PNG และมีขนาดไม่เกิน 5MB');
        $videoUrl = trim((string) ($videoUrl ?? ''));
        if (in_array($stage, self::VIDEO_STAGES, true) && !$this->isVideoUrl($videoUrl)) throw new InvalidArgumentException('รอบ Semi-Final และ Final ต้องใส่ลิงก์ Screen Recording');
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $path = $userId . '/' . $matchId . '/' . bin2hex(random_bytes(10)) . '.' . $extension;
        $bucket = (string) env_value('SUPABASE_MATCH_EVIDENCE_BUCKET', self::EVIDENCE_BUCKET);
        $this->db->storageUpload($bucket, $path, ['tmp_name' => $screenshot['tmp_name'], 'type' => $mime]);
        $this->db->rest('match_evidence', '', 'POST', [[
            'match_id' => $matchId, 'storage_path' => $path, 'evidence_type' => 'screenshot', 'source_url' => null, 'uploaded_by' => $userId,
        ]]);
        if ($videoUrl !== '') {
            $this->db->rest('match_evidence', '', 'POST', [[
                'match_id' => $matchId, 'storage_path' => null, 'evidence_type' => 'video_link', 'source_url' => $videoUrl, 'uploaded_by' => $userId,
            ]]);
        }
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

    public function resolveDispute(string $matchId, string $staffId, string $outcome, string $note): void
    {
        if (!in_array($outcome, ['confirmed', 'void'], true) || trim($note) === '') throw new InvalidArgumentException('กรุณาเลือกผลการตัดสินและระบุหมายเหตุ');
        $this->db->rpc('resolve_match_dispute', ['p_match_id' => $matchId, 'p_user_id' => $staffId, 'p_outcome' => $outcome, 'p_note' => trim($note)]);
    }

    public function matchForPlayer(string $matchId, string $userId): array
    {
        $access = $this->db->rest('match_player_access', 'match_id=eq.' . rawurlencode($matchId) . '&user_id=eq.' . rawurlencode($userId) . '&limit=1');
        if (!$access) throw new RuntimeException('คุณไม่มีสิทธิ์เข้าถึงแมตช์นี้');
        $matches = $this->db->rest('matches', 'id=eq.' . rawurlencode($matchId) . '&limit=1');
        if (!$matches) throw new InvalidArgumentException('ไม่พบแมตช์ที่ต้องการ');
        return $matches[0];
    }

    public static function isVideoUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') return false;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return in_array($host, ['youtube.com', 'www.youtube.com', 'youtu.be', 'drive.google.com', 'www.drive.google.com'], true);
    }
}
