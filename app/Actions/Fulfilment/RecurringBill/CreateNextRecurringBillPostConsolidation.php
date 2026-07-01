<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 16:53:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateRecurringBills;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydrateRecurringBills;
use App\Actions\Fulfilment\RecurringBill\Hydrators\RecurringBillHydrateTransactions;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRecurringBills;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRecurringBills;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Fulfilment\RecurringBill;

class CreateNextRecurringBillPostConsolidation extends OrgAction
{
    use WithActionUpdate;


    public string $jobQueue = 'long-running';


    /**
     * @throws \Throwable
     */
    public function handle(RecurringBill $previousRecurringBill): ?RecurringBill
    {
        $newRecurringBill   = null;
        $fulfilmentCustomer = $previousRecurringBill->fulfilmentCustomer;

        if (!$fulfilmentCustomer->current_recurring_bill_id) {
            $hasStoringPallet = $fulfilmentCustomer->pallets()
                ->where('status', PalletStatusEnum::STORING)
                ->exists();

            $hasSpaces = $fulfilmentCustomer->spaces()
                ->where('state', SpaceStateEnum::RENTING)
                ->exists();

            if ($hasStoringPallet || $hasSpaces) {

                $newRecurringBill = StoreRecurringBill::make()->action(
                    rentalAgreement: $fulfilmentCustomer->rentalAgreement,
                    modelData: [
                        'start_date' => now()->tomorrow()->startOfDay()
                    ],
                    strict: true
                );

                $this->update($fulfilmentCustomer, ['current_recurring_bill_id' => $newRecurringBill->id]);
            }
        }


        if ($newRecurringBill) {
            RecurringBillHydrateTransactions::dispatch($newRecurringBill);
        }

        GroupHydrateRecurringBills::dispatch($previousRecurringBill->group);
        OrganisationHydrateRecurringBills::dispatch($previousRecurringBill->organisation);
        FulfilmentHydrateRecurringBills::dispatch($previousRecurringBill->fulfilment);
        FulfilmentCustomerHydrateRecurringBills::dispatch($fulfilmentCustomer);

        return $newRecurringBill;
    }

}
