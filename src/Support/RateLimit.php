<?php
declare(strict_types=1);

function enforce_rate_limit(string $key, int $limit = 10, int $window = 300): void
{
    $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate-limit';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('ระบบ rate limit ยังไม่พร้อมใช้งาน');
    $file = $directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    $now = time();
    $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
    if (!is_array($data) || (int) ($data['started_at'] ?? 0) + $window <= $now) $data = ['started_at' => $now, 'count' => 0];
    $data['count'] = (int) $data['count'] + 1;
    if ($data['count'] > $limit) throw new RuntimeException('ทำรายการบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่');
    @file_put_contents($file, json_encode($data), LOCK_EX);
}
