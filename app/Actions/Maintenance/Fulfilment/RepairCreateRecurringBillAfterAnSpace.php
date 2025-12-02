<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jan 2025 18:10:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Fulfilment;

use App\Actions\Fulfilment\RecurringBill\StoreRecurringBill;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Console\Command;

class RepairCreateRecurringBillAfterAnSpace extends OrgAction
{
    use WithActionUpdate;

    public function handle(FulfilmentCustomer $fulfilmentCustomer): void
    {


        $currentRecurringBill = $fulfilmentCustomer->currentRecurringBill;
        if (!$currentRecurringBill) {
            $currentRecurringBill = StoreRecurringBill::make()->action(
                rentalAgreement: $fulfilmentCustomer->rentalAgreement,
                modelData: [
                    'start_date' => now(),
                ],
                strict: true
            );
            $fulfilmentCustomer->update(
                [
                    'current_recurring_bill_id' => $currentRecurringBill->id
                ]
            );
        }

        foreach ($fulfilmentCustomer->spaces as $space) {
            $this->update($space, [
                'current_recurring_bill_id' => $currentRecurringBill->id,
                'state' => SpaceStateEnum::RENTING
            ]);

            StoreRecurringBillTransaction::make()->action(
                $currentRecurringBill,
                $space,
                [
                    'start_date' => $space->start_at,
                    'end_date'   => $currentRecurringBill->end_date,
                    'quantity'   => 1,
                ]
            );
        }



    }

    public function getCommandSignature(): string
    {
        return 'repair:create_recurring_bill_after_an_space {customerFulfilment}';
    }

    public function asCommand(Command $command): void
    {
        $customerFulfilment = FulfilmentCustomer::where('slug', $command->argument('customerFulfilment'))->firstOrFail();
        $this->handle($customerFulfilment);
    }


}
