<?php
declare(strict_types=1);

final class AuthService
{
    public function __construct(private SupabaseClient $db) {}
    public function signIn(string $email, string $password): array
    {
        $response = $this->db->auth('token?grant_type=password', 'POST', ['email' => trim($email), 'password' => $password]);
        if (empty($response['access_token']) || empty($response['user'])) throw new RuntimeException('เข้าสู่ระบบไม่สำเร็จ');
        if (($response['user']['email_confirmed_at'] ?? null) === null) throw new RuntimeException('กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ');
        return $response;
    }
    public function signUp(string $email, string $password): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) throw new InvalidArgumentException('อีเมลไม่ถูกต้องและรหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
        $this->db->auth('signup', 'POST', ['email' => trim($email), 'password' => $password, 'options' => ['email_redirect_to' => env_value('SUPABASE_AUTH_REDIRECT_URL')]]);
    }
    public function requestPasswordReset(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('อีเมลไม่ถูกต้อง');
        $this->db->auth('recover', 'POST', ['email' => trim($email), 'options' => ['redirect_to' => env_value('SUPABASE_AUTH_REDIRECT_URL')]]);
    }
}
