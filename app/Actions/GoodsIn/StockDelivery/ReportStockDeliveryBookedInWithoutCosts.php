<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\User;
use App\Notifications\StockDeliveryBookedInWithoutCostsNotification;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;
use Spatie\Permission\Models\Role;

/**
 * The warehouse started booking in a delivery whose costs procurement has not entered: written once in the
 * delivery's history, and procurement of the organisation and the group's supply chain managers are told
 * in the app and by email. The warehouse is never asked anything about costs.
 */
class ReportStockDeliveryBookedInWithoutCosts
{
    use AsAction;

    /**
     * @param array<int, string> $missing
     */
    public function handle(StockDelivery $stockDelivery, array $missing): int
    {
        if ($stockDelivery->audits()->where('event', 'booked_in_without_costs')->exists()) {
            return 0;
        }

        $roles = Role::whereIn('name', [
            "procurement-clerk-$stockDelivery->organisation_id",
            "procurement-supervisor-$stockDelivery->organisation_id",
            'supply-chain',
        ])->pluck('name')->all();

        $recipients = $roles ? User::role($roles)->where('status', true)->get() : collect();

        $recipients->each(fn (User $recipient) => rescue(fn () => $recipient->notify(new StockDeliveryBookedInWithoutCostsNotification($stockDelivery, implode(', ', $missing)))));

        $stockDelivery->auditEvent     = 'booked_in_without_costs';
        $stockDelivery->isCustomEvent  = true;
        $stockDelivery->auditCustomOld = [];
        $stockDelivery->auditCustomNew = ['missing_costs' => implode(', ', $missing)];
        Event::dispatch(new AuditCustom($stockDelivery));
        $stockDelivery->isCustomEvent  = false;
        $stockDelivery->auditCustomOld = $stockDelivery->auditCustomNew = [];

        return $recipients->count();
    }
}
