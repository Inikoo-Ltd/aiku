<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 12:24:17 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\RecurringBill\Hydrators\RecurringBillHydrateTransactions;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Models\Fulfilment\RecurringBill;

class FindStoredPalletsAndAttachThemToNewRecurringBill extends OrgAction
{
    public function handle(RecurringBill $recurringBill, RecurringBill $previousRecurringBill = null): RecurringBill
    {
        //todo this is probably wrong, do same as the fetch
        $palletsInStoringState = $recurringBill->fulfilmentCustomer->pallets->where('state', PalletStateEnum::STORING);
        foreach ($palletsInStoringState as $pallet) {
            if (!$pallet->storing_at) {
                UpdatePallet::make()->action($pallet, [
                    'storing_at'  => now(),
                ]);
            }
            $startDate = $pallet->storing_at;
            if ($previousRecurringBill) {
                $startDate = $recurringBill->start_date;
            }
            StoreRecurringBillTransaction::make()->action(
                recurringBill:  $recurringBill,
                item:$pallet,
                modelData:[
                    'start_date' => $startDate
                ],
                skipHydrators: true
            );
            CalculateRecurringBillTotals::run($recurringBill);
            RecurringBillHydrateTransactions::run($recurringBill);

        }

        return $recurringBill;
    }


    public function action(RecurringBill $recurringBill, RecurringBill $previousRecurringBill = null): RecurringBill
    {
        $this->asAction = true;

        $this->initialisationFromFulfilment($recurringBill->fulfilment, []);

        return $this->handle($recurringBill, $previousRecurringBill);
    }


}
