<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 19:22:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\RecurringBill;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydrateRecurringBills implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Fulfilment $fulfilment): string
    {
        return $fulfilment->id;
    }

    public function handle(Fulfilment $fulfilment): void
    {
        $stats = [
            'number_recurring_bills' => $fulfilment->recurringBills()->count(),
            'current_recurring_bills_amount' => $fulfilment->recurringBills->where('status', RecurringBillStatusEnum::CURRENT)->sum('net_amount'),
            'current_recurring_bills_amount_org_currency' => $fulfilment->recurringBills->where('status', RecurringBillStatusEnum::CURRENT)->sum('org_net_amount'),
            'current_recurring_bills_amount_grp_currency' => $fulfilment->recurringBills->where('status', RecurringBillStatusEnum::CURRENT)->sum('grp_net_amount'),
        ];


        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'recurring_bills',
                field: 'status',
                enum: RecurringBillStatusEnum::class,
                models: RecurringBill::class,
                where: function ($q) use ($fulfilment) {
                    $q->where('fulfilment_id', $fulfilment->id);
                }
            )
        );

        $fulfilment->stats()->update($stats);
    }

}
