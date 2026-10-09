<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 9 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;

class BroadcastPartnerProductionChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public int $organisationId)
    {
    }

    public static function dispatchForJobOrder(?int $jobOrderId): void
    {
        if (!$jobOrderId) {
            return;
        }

        DB::table('partner_shopping_list_items')
            ->where('job_order_id', $jobOrderId)
            ->whereNotNull('partner_organisation_id')
            ->distinct()
            ->pluck('organisation_id')
            ->each(fn (int $buyerId) => rescue(fn () => static::dispatch($buyerId)));
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.org.'.$this->organisationId.'.partner-production')];
    }

    public function broadcastAs(): string
    {
        return 'partner-production-changed';
    }

    public function broadcastWith(): array
    {
        return ['organisation_id' => $this->organisationId];
    }
}
