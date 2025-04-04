<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\CreditTransaction;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateCreditTransactions implements ShouldBeUnique
{
    use AsAction;
    use WithActionUpdate;


    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id;
    }

    public function handle(Customer $customer): void
    {
        $stats          = [
            'number_credit_transactions' => $customer->creditTransactions()->count(),
        ];

        $balance            = 0;
        $creditTransactions = $customer->creditTransactions()
        ->orderBy('date')
        ->get();

        /** @var CreditTransaction $creditTransaction */
        foreach ($creditTransactions as $creditTransaction) {
            $balance += $creditTransaction->amount;
            $this->update($creditTransaction, [
                'running_amount' => $balance
            ]);
        }
        data_set($modelData, 'balance', $balance);

        $customer->stats()->update($stats);

        UpdateCustomer::make()->action($customer, $modelData);
    }


}
