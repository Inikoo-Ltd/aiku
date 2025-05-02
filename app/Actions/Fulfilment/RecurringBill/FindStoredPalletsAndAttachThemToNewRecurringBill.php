<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 12:24:17 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill;

use App\Actions\Fulfilment\RecurringBill\Hydrators\RecurringBillHydrateTransactions;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Models\Fulfilment\RecurringBill;

class FindStoredPalletsAndAttachThemToNewRecurringBill extends OrgAction
{
    public function handle(RecurringBill $recurringBill): RecurringBill
    {
        $palletsInStoringState = $recurringBill->fulfilmentCustomer->pallets
            ->whereIn('state', [
                PalletStateEnum::RECEIVED,
                PalletStateEnum::BOOKING_IN,
                PalletStateEnum::BOOKED_IN,
                PalletStateEnum::STORING,
                PalletStateEnum::REQUEST_RETURN_IN_PROCESS,
                PalletStateEnum::REQUEST_RETURN_SUBMITTED,
                PalletStateEnum::REQUEST_RETURN_CONFIRMED,
                PalletStateEnum::PICKING,
                PalletStateEnum::PICKED,

            ]);
        foreach ($palletsInStoringState as $pallet) {
            $startDate = $recurringBill->start_date;

            if ($pallet->delivered_at && $pallet->delivered_at->greaterThan($startDate)) {
                $startDate = $pallet->delivered_at;
            }



            StoreRecurringBillTransaction::make()->action(
                recurringBill: $recurringBill,
                item: $pallet,
                modelData: [
                    'start_date' => $startDate,
                    'end_date'   => $recurringBill->end_date
                ],
                skipHydrators: true
            );

        }
        CalculateRecurringBillTotals::run($recurringBill);
        RecurringBillHydrateTransactions::run($recurringBill);

        $palletsInStoringState = $recurringBill->fulfilmentCustomer->pallets
            ->where('state', PalletStateEnum::LOST)
            ->where('set_as_incident_at', '>', $recurringBill->start_date)
            ->where('set_as_incident_at', '<', $recurringBill->end_date);
        foreach ($palletsInStoringState as $pallet) {
            $startDate = $recurringBill->start_date;
            if ($pallet->delivered_at->greaterThan($startDate)) {
                $startDate = $pallet->delivered_at;
            }
            $endDate = $pallet->set_as_incident_at;


            StoreRecurringBillTransaction::make()->action(
                recurringBill: $recurringBill,
                item: $pallet,
                modelData: [
                    'start_date' => $startDate,
                    'end_date'   => $endDate
                ],
                skipHydrators: true
            );
            CalculateRecurringBillTotals::run($recurringBill);
            RecurringBillHydrateTransactions::run($recurringBill);
        }

        $palletsInStoringState = $recurringBill->fulfilmentCustomer->pallets
            ->where('state', PalletStateEnum::DISPATCHED)
            ->where('dispatched_at', '>', $recurringBill->start_date)
            ->where('dispatched_at', '<', $recurringBill->end_date);
        foreach ($palletsInStoringState as $pallet) {
            $startDate = $recurringBill->start_date;
            if ($pallet->delivered_at->greaterThan($startDate)) {
                $startDate = $pallet->delivered_at;
            }
            $endDate = $pallet->dispatched_at;


            StoreRecurringBillTransaction::make()->action(
                recurringBill: $recurringBill,
                item: $pallet,
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
