<?php

namespace App\Sandbox;

/**
 * Dịch vụ của provider giả lập. Mỗi dịch vụ có 1 kịch bản (outcome) để test đủ các nhánh:
 * - completed: Pending → In progress → Completed
 * - partial:   Pending → In progress → Partial (giao 70%)
 * - canceled:  Pending → provider tự huỷ
 * - reject:    provider từ chối nhận đơn (test gửi lại + tự hoàn tiền)
 */
final class SandboxCatalog
{
    public const PARTIAL_DELIVERED = 0.7;

    public const SERVICES = [
        '101' => ['name' => 'Sandbox Followers – completes normally', 'type' => 'Default', 'rate' => '1.00', 'min' => 10, 'max' => 100000, 'refill' => true, 'cancel' => true, 'dripfeed' => true, 'outcome' => 'completed'],
        '102' => ['name' => 'Sandbox Likes – ends Partial (70% delivered)', 'type' => 'Default', 'rate' => '0.50', 'min' => 10, 'max' => 100000, 'refill' => false, 'cancel' => true, 'dripfeed' => false, 'outcome' => 'partial'],
        '103' => ['name' => 'Sandbox Views – canceled by provider', 'type' => 'Default', 'rate' => '0.10', 'min' => 100, 'max' => 1000000, 'refill' => false, 'cancel' => false, 'dripfeed' => false, 'outcome' => 'canceled'],
        '104' => ['name' => 'Sandbox Shares – provider rejects orders', 'type' => 'Default', 'rate' => '0.80', 'min' => 10, 'max' => 100000, 'refill' => false, 'cancel' => false, 'dripfeed' => false, 'outcome' => 'reject'],
        '105' => ['name' => 'Sandbox Custom Comments', 'type' => 'Custom Comments', 'rate' => '5.00', 'min' => 1, 'max' => 1000, 'refill' => false, 'cancel' => true, 'dripfeed' => false, 'outcome' => 'completed'],
        '106' => ['name' => 'Sandbox Package – 1000 Followers', 'type' => 'Package', 'rate' => '2.50', 'min' => 1, 'max' => 1, 'refill' => true, 'cancel' => false, 'dripfeed' => false, 'outcome' => 'completed'],
    ];

    /** @return array<string, mixed>|null */
    public static function find(string $serviceId): ?array
    {
        return self::SERVICES[$serviceId] ?? null;
    }
}
