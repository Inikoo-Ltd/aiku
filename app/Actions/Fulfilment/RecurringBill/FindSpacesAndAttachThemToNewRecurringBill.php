<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Apr 2025 18:30:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill;

use App\Actions\Fulfilment\RecurringBill\Hydrators\RecurringBillHydrateTransactions;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\Space;

class FindSpacesAndAttachThemToNewRecurringBill extends OrgAction
{
    public function handle(RecurringBill $recurringBill): RecurringBill
    {
        $spaces = $recurringBill->fulfilmentCustomer->spaces()->where('state', SpaceStateEnum::RENTING)->get();


        /** @var Space $space */
        foreach ($spaces as $space) {
            if ($recurringBill->transactions()->where('item_type', 'Space')->where('item_id', $space->id)->exists()) {
                continue;
            }

            $startDate = $recurringBill->start_date;

            if ($space->start_at && $space->start_at->greaterThan($startDate)) {
                $startDate = $space->start_at;
            }


            StoreRecurringBillTransaction::make()->action(
                recurringBill: $recurringBill,
                item: $space,
                modelData: [
                    'start_date' => $startDate,
                    'end_date' => $recurringBill->end_date
                ],
                skipHydrators: true
            );
        }
        CalculateRecurringBillTotals::run($recurringBill);
        RecurringBillHydrateTransactions::run($recurringBill);

        $spaces = $recurringBill->fulfilmentCustomer->spaces()->where('state', SpaceStateEnum::FINISHED)->where('end_at', '>', $recurringBill->start_date)->where('end_at', '<', $recurringBill->end_date)->get();
        foreach ($spaces as $space) {

            if ($recurringBill->transactions()->where('item_type', 'Space')->where('item_id', $space->id)->exists()) {
                continue;
            }

            $startDate = $recurringBill->start_date;
            if ($space->start_at->greaterThan($startDate)) {
                $startDate = $space->start_at;
            }
            $endDate = $space->end_at;


            StoreRecurringBillTransaction::make()->action(
                recurringBill: $recurringBill,
                item: $space,
                modelData: [
                    'start_date' => $startDate,
                    'end_date'   => $endDate
                ],
                skipHydrators: true
            );
            CalculateRecurringBillTotals::run($recurringBill);
            RecurringBillHydrateTransactions::run($recurringBill);
        }


        return $recurringBill;
    }


    public function action(RecurringBill $recurringBill): RecurringBill
    {
        $this->asAction = true;

        $this->initialisationFromFulfilment($recurringBill->fulfilment, []);

        return $this->handle($recurringBill);
    }


}
