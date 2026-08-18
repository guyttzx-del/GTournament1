<?php
declare(strict_types=1);

final class RegistrationStatus
{
    private const TRANSITIONS = [
        'draft' => ['pending_payment', 'cancelled'],
        'pending_payment' => ['pending_review', 'cancelled'],
        'pending_review' => ['approved', 'rejected', 'cancelled'],
        'rejected' => ['pending_payment', 'cancelled'],
        'approved' => ['cancelled'],
        'cancelled' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function label(string $status): string
    {
        return ['draft' => 'ร่าง', 'pending_payment' => 'รอชำระเงิน', 'pending_review' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่ผ่านการตรวจ', 'cancelled' => 'ยกเลิกแล้ว'][$status] ?? $status;
    }
}
