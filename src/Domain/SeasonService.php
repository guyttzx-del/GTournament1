<?php
declare(strict_types=1);

final class SeasonService
{
    public function __construct(private SupabaseClient $db) {}
    public function findOpen(string $seasonId): array
    {
        $rows = $this->db->rest('seasons', 'id=eq.' . rawurlencode($seasonId) . '&status=eq.open&limit=1');
        if (!$rows) throw new InvalidArgumentException('รายการนี้ไม่เปิดรับสมัครแล้ว');
        $season = $rows[0]; $now = time();
        if (!empty($season['registration_opens_at']) && strtotime((string) $season['registration_opens_at']) > $now) throw new InvalidArgumentException('ยังไม่ถึงเวลาเปิดรับสมัคร');
        if (!empty($season['registration_closes_at']) && strtotime((string) $season['registration_closes_at']) < $now) throw new InvalidArgumentException('ปิดรับสมัครแล้ว');
        return $season;
    }
    public function counts(string $seasonId): array
    {
        $rows = $this->db->rest('registration_counts', 'season_id=eq.' . rawurlencode($seasonId) . '&limit=1');
        return $rows[0] ?? ['approved_count' => 0, 'pending_count' => 0];
    }
}
