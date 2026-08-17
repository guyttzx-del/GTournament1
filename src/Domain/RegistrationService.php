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
        if ($existing) throw new InvalidArgumentException('คุณสมัครรายการนี้ไว้แล้ว');
        $rows = $this->db->rest('registrations', '', 'POST', [['season_id' => $seasonId, 'user_id' => $userId, 'competition_name' => $input['competition_name'], 'nickname' => $input['nickname'], 'contact_url' => $input['contact_url'], 'club' => $input['club'] ?? null, 'status' => 'pending_payment']]);
        $registrationId = (string) ($rows[0]['id'] ?? '');
        if ($registrationId === '') throw new RuntimeException('สร้างใบสมัครไม่สำเร็จ');
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo((string) $slip['name'], PATHINFO_EXTENSION))) ?: 'bin';
        $path = $userId . '/' . $registrationId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
        $this->db->storageUpload((string) env_value('SUPABASE_SLIP_BUCKET', 'slips'), $path, ['tmp_name' => $slip['tmp_name'], 'type' => $mime]);
        $this->db->rest('registrations', 'id=eq.' . rawurlencode($registrationId), 'PATCH', ['slip_path' => $path, 'status' => 'pending_review']);
        $this->db->rest('payments', '', 'POST', [['registration_id' => $registrationId, 'amount' => $fee, 'slip_path' => $path]]);
        $this->db->rest('audit_logs', '', 'POST', [['actor_id' => $userId, 'action' => 'registration.submitted', 'entity_type' => 'registration', 'entity_id' => $registrationId, 'metadata' => ['status' => 'pending_review']]]);
        return $registrationId;
    }
}
