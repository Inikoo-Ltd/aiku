<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Models\Ordering\Order;

trait WithChatMessageSearch
{
    public const int MESSAGE_SEARCH_HITS = 200;

    /**
     * @param  class-string  $messageModel
     * @param  array<int, int>  $shopIds
     */
    protected function messagesMatching(string $messageModel, string $search, array $shopIds): \Illuminate\Support\Collection
    {
        try {
            return $messageModel::search($search)
                ->when($shopIds !== [], fn ($messages) => $messages->whereIn('shop_id', $shopIds))
                ->take(self::MESSAGE_SEARCH_HITS)
                ->keys();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * An order number is matched exactly, the way it is printed on the order, and leads to the
     * customer who placed it: a chat names its customer, never its orders.
     *
     * @param  array<int, int>  $shopIds
     */
    protected function customersWithOrderReference(string $search, array $shopIds): \Illuminate\Support\Collection
    {
        $reference = ltrim(trim($search), '#');

        if (mb_strlen($reference) < 4 || !preg_match('/\d/', $reference)) {
            return collect();
        }

        return Order::query()
            ->where(fn ($orders) => $orders->where('reference', mb_strtoupper($reference))->orWhere('customer_reference', $reference))
            ->when($shopIds !== [], fn ($orders) => $orders->whereIn('shop_id', $shopIds))
            ->limit(self::MESSAGE_SEARCH_HITS)
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->values();
    }
}
