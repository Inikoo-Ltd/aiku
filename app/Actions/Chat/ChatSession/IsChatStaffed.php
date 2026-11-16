<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whether a person can answer the shop's chat now: within its chat hours, or outside them while an
 * agent of the shop is online and available (an early start or a late finish). Outside both the
 * website chat is in Merlin mode. Reports keep using IsWithinWorkingHours, as they measure hours.
 */
class IsChatStaffed
{
    use AsAction;

    public function handle(Shop $shop): bool
    {
        return IsWithinWorkingHours::make()->chatHours()->handle($shop, now())
            || ChatAgent::online()->where('is_available', true)->where('last_heartbeat_at', '<=', now())->forShop($shop->id)->exists();
    }
}
