<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Mar 2025 20:42:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateDispatchedEmails implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(?int $shopId): string
    {
        return $shopId ?? 'empty';
    }

    public function handle(?int $shopID): void
    {
        if (!$shopID) {
            return;
        }
        $shop = Shop::find($shopID);
        if (!$shop) {
            return;
        }

        $stats = [
            'number_dispatched_emails' => DB::table('outboxes')->where('shop_id', $shop->id)
                ->leftJoin('outbox_stats', 'outboxes.id', '=', 'outbox_stats.outbox_id')
                ->sum('number_dispatched_emails'),
        ];

        $shop->commsStats()->update($stats);
    }


}
