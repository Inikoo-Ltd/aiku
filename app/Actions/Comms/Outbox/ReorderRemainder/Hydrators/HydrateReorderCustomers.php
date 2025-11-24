<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\ReorderRemainder\Hydrators;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\EmailBulkRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Log;
use App\Actions\Comms\Email\SendReOrderRemainderToCustomerEmail;

class HydrateReorderCustomers
// implements ShouldBeUnique
{
    use AsAction;
    public string $commandSignature = 'hydrate-reorder-customers';
    // public string $jobQueue = 'low-priority';

    // public function getJobUniqueId(EmailBulkRun $emailBulkRun): string
    // {
    //     return $emailBulkRun->id;
    // }

    /**
     * @return void
     * flow explanation:
     * 1. get the customers need to sending ReOrder Notification default 20 days after last invoice
     * 2. create email bulk run for current day
     * 3. day can be configurable
     * 4. get the customers email address
     * 5. send email
     * 6. update dispatched email
     * 7. specify the email bulk run state to sent
     */
    public function handle(): void
    {
        $defaultDays = 20;

        $customers = Customer::where('last_invoiced_at', '<', now()->subDays($defaultDays))
            ->get();

        $customers->each(function ($customer) {
            SendReOrderRemainderToCustomerEmail::run($customer);
        });

        // Log the IDs
        Log::info($customers->pluck('id'));


        //Create dispatched email
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
