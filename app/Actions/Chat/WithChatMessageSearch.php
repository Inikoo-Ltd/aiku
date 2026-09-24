<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

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
}
