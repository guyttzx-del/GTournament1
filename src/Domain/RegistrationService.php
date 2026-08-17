<?php
declare(strict_types=1);
final class RegistrationService
{
    private $db;
    public function __construct(SupabaseClient $db) { $this->db = $db; }
    public function submit(array $user, array $input, array $slip, string $seasonId, float $fee): string
    {
        foreach (['competition_name', 'nickname', 'contact_url'] as $field) if (trim((string) ($input[$field] ?? '')) === '') throw new InvalidArgumentException('กรุณากรอกข้อมูลให้ครบทุกช่อง');
        if (!filter_var($input['contact_url'], FILTER_VALIDATE_URL)) throw new InvalidArgumentException('ลิงก์ช่องทางติดต่อไม่ถูกต้อง');
        if (($slip['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('กรุณาแนบสลิปการชำระเงิน');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($slip['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true) || ($slip['size'] ?? 0) > 5 * 1024 * 1024) throw new InvalidArgumentException('สลิปต้องเป็น JPG, PNG หรือ PDF และมีขนาดไม่เกิน 5MB');
        $userId = (string) $user['id'];
        $profile = ['id' => $userId, 'display_name' => $input['nickname'], 'competition_name' => $input['competition_name'], 'contact_url' => $input['contact_url'], 'club' => $input['club'] ?? null];
        if (!$this->db->rest('profiles', 'id=eq.' . rawurlencode($userId), 'PATCH', $profile)) $this->db->rest('profiles', '', 'POST', [$profile]);
        $existing = $this->db->rest('registrations', 'season_id=eq.' . rawurlencode($seasonId) . '&user_id=eq.' . rawurlencode($userId) . '&limit=1');
        $existingStatus = (string) ($existing[0]['status'] ?? '');
        if ($existing && !in_array($existingStatus, ['rejected', 'pending_payment'], true)) throw new InvalidArgumentException('คุณสมัครรายการนี้ไว้แล้ว');
        if ($existing) { $registrationId = (string) $existing[0]['id']; $this->db->rest('registrations', 'id=eq.' . rawurlencode($registrationId), 'PATCH', ['competition_name' => $input['competition_name'], 'nickname' => $input['nickname'], 'contact_url' => $input['contact_url'], 'club' => $input['club'] ?? null, 'status' => 'pending_payment', 'rejection_reason' => null]); }
        else { $rows = $this->db->rpc('reserve_registration', ['p_season_id' => $seasonId, 'p_user_id' => $userId, 'p_competition_name' => $input['competition_name'], 'p_nickname' => $input['nickname'], 'p_contact_url' => $input['contact_url'], 'p_club' => $input['club'] ?? null]); $registrationId = (string) (($rows[0]['id'] ?? '') ?: ($rows['id'] ?? '')); }
        if ($registrationId === '') throw new RuntimeException('สร้างใบสมัครไม่สำเร็จ');
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo((string) $slip['name'], PATHINFO_EXTENSION))) ?: 'bin';
        $path = $userId . '/' . $registrationId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
        try { $this->db->storageUpload((string) env_value('SUPABASE_SLIP_BUCKET', 'slips'), $path, ['tmp_name' => $slip['tmp_name'], 'type' => $mime]); } catch (Throwable $e) { throw new RuntimeException('อัปโหลดสลิปไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 0, $e); }
        $this->db->rest('registrations', 'id=eq.' . rawurlencode($registrationId), 'PATCH', ['slip_path' => $path, 'status' => 'pending_review']);
        $this->db->rest('payments', 'registration_id=eq.' . rawurlencode($registrationId), 'PATCH', ['amount' => $fee, 'slip_path' => $path, 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
        if (!$existing) $this->db->rest('payments', '', 'POST', [['registration_id' => $registrationId, 'amount' => $fee, 'slip_path' => $path]]);
        $this->db->rest('audit_logs', '', 'POST', [['actor_id' => $userId, 'action' => 'registration.submitted', 'entity_type' => 'registration', 'entity_id' => $registrationId, 'metadata' => ['status' => 'pending_review']]]);
        return $registrationId;
    }
}
