<?php

namespace App\Services\Providers;

use App\Exceptions\ProviderException;
use App\Exceptions\ProviderRejectedException;
use App\Models\Order;

interface ProviderClient
{
    /**
     * @return list<array{service: string, name: string, type: string, category: string, rate: string, min: int, max: int, refill: bool, cancel: bool, dripfeed: bool}>
     *
     * @throws ProviderException
     */
    public function services(): array;

    /**
     * Gửi đơn, trả về order ID bên provider.
     *
     * @throws ProviderRejectedException provider từ chối rõ ràng
     * @throws ProviderException lỗi mạng / timeout / response lạ – không chắc đơn đã được tạo hay chưa
     */
    public function addOrder(Order $order): string;

    /**
     * @param  list<string>  $orderIds
     * @return array<string, array{status?: string, start_count?: mixed, remains?: mixed, charge?: mixed, error?: string}>
     *
     * @throws ProviderException
     */
    public function status(array $orderIds): array;

    /**
     * Yêu cầu refill, trả về refill ID bên provider.
     *
     * @throws ProviderException
     */
    public function refill(string $orderId): string;

    /**
     * @param  list<string>  $refillIds
     * @return array<string, string|array{error: string}> refill ID → status hoặc lỗi
     *
     * @throws ProviderException
     */
    public function refillStatus(array $refillIds): array;

    /**
     * @param  list<string>  $orderIds
     * @return array<string, true|string> order ID → true hoặc thông báo lỗi
     *
     * @throws ProviderException
     */
    public function cancel(array $orderIds): array;

    /**
     * @return array{balance: string, currency: string}
     *
     * @throws ProviderException
     */
    public function balance(): array;
}
