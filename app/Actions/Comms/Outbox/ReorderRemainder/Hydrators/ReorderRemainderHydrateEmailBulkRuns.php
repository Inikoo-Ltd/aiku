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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Log;
use App\Actions\Comms\Email\SendReOrderRemainderToCustomerEmail;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRun;
use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\DispatchedEmail\App\Enums\Comms\DispatchedEmailEvent\DispatchedEmailEventTypeEnum;
use App\Actions\Comms\Outbox\ReorderRemainder\WithGenerateEmailBulkRuns;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;

class ReorderRemainderHydrateEmailBulkRuns implements ShouldQueue
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    public string $commandSignature = 'hydrate:reorder-reminder-email-bulk-runs';
    public string $jobQueue = 'low-priority';

    /**
     * @return void
     *
     *
     * flow explanation:
     * 1. get the customers need to sending ReOrder Notification default 20 days after last invoice
     * 2. create email bulk run for current day
     * 3. day can be configurable
     * 4. get the customers email address
     * 5. send email
     * 6. update dispatched email
     * 7. specify the email bulk run state to sent
     *
     *
     * Check if EmailBulkRun already exists for today
     * Find eligible customers (20+ days since last invoice)
     * Create EmailBulkRun if not exists
     * Process recipients in bulk
     * Trigger email delivery channels
     */
    public function handle(): void
    {
        // TODO: update from setting
        $defaultDays = 20;

        // get the customers
        // TODO: confirm to raul more conditions for the customers like last_invoiced_at, status, state
        $customers = Customer::where('last_invoiced_at', '<', now()->subDays($defaultDays))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('shop_id')
            ->get();

            // New Function to generate and make sure EmailBulkRun for each customer
            $customers->each(function ($customer) {
                $generateEmailBulkRun = $this->generateEmailBulkRuns($customer, OutboxCodeEnum::REORDER_REMINDER, now()->toDateString());
            // next Step make sure  Dispatched_emails
            SendReOrderRemainderToCustomerEmail::run($customer, $generateEmailBulkRun);
            // update email bulk run state to sent
            EmailBulkRunHydrateDispatchedEmails::dispatch($generateEmailBulkRun);
            });

    }

    public function asCommand(): void
    {
        $this->run();
    }
}
