<?php

namespace App\Modules\MerchantPortal\Services;

use App\Modules\MerchantPortal\Models\MerchantNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MerchantPortalNotificationService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function queryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return MerchantNotification::query()
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(($filters['unread_only'] ?? false) === true, fn (Builder $query) => $query->whereNull('read_at'))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest();
    }

    public function unreadCount(int $merchantId): int
    {
        return MerchantNotification::query()
            ->where('merchant_id', $merchantId)
            ->whereNull('read_at')
            ->count();
    }

    public function findOwned(int $merchantId, int $notificationId): MerchantNotification
    {
        return $this->queryForMerchant($merchantId)
            ->whereKey($notificationId)
            ->firstOrFail();
    }

    public function markRead(MerchantNotification $notification): MerchantNotification
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification->fresh();
    }

    /**
     * @return Collection<int, MerchantNotification>
     */
    public function markAllRead(int $merchantId): Collection
    {
        MerchantNotification::query()
            ->where('merchant_id', $merchantId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->queryForMerchant($merchantId)->get();
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function create(int $merchantId, ?int $merchantUserId, string $type, string $title, string $message, ?array $data = null): MerchantNotification
    {
        return MerchantNotification::query()->create([
            'merchant_id' => $merchantId,
            'merchant_user_id' => $merchantUserId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
