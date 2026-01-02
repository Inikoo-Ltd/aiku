<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\ReorderRemainder;

use App\Actions\Comms\Email\SendReOrderRemainderToCustomerEmail;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class SendReorderRemainderEmails implements ShouldQueue
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    use WithActionUpdate;

    public string $commandSignature = 'hydrate:reorder-reminder-customers';
    public string $jobQueue = 'low-priority';


    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::REORDER_REMINDER, OutboxCodeEnum::REORDER_REMINDER_2ND, OutboxCodeEnum::REORDER_REMINDER_3RD]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->whereNotNull('days_after');
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.days_after', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        $currentDateTime = Carbon::now()->utc();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {

            $compareDate = $currentDateTime->copy()->subDays($outbox->days_after)->endOfDay();

            $queryCustomer = QueryBuilder::for(Customer::class);
            $queryCustomer->leftJoin('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
            $queryCustomer->where('customer_comms.is_subscribed_to_reorder_reminder', true); // check if the customer subscribed to a reorder reminder
            $queryCustomer->where('customers.shop_id', $outbox->shop_id);
            $queryCustomer->whereDate('customers.last_invoiced_at', '=', $compareDate->toDateString());

            $queryCustomer->whereNotNull('customers.email');
            $queryCustomer->where('customers.email', '!=', '');
            $queryCustomer->select('customers.id', 'customers.shop_id');
            $queryCustomer->orderBy('customers.shop_id');
            $queryCustomer->orderBy('customers.id');

            $lastBulkRun          = null;
            $updateLastOutBoxSent = null;
            foreach ($queryCustomer->cursor() as $customer) {

                $bulkRun = $this->upsertEmailBulkRuns(
                    $customer,
                    $outbox->code,
                    $currentDateTime->copy()->toDateString()
                );

                $lastBulkRun = $bulkRun;

                // Dispatch SendReOrderRemainderToCustomerEmail immediately
                SendReOrderRemainderToCustomerEmail::dispatch($customer, $outbox->code, $bulkRun);

                $updateLastOutBoxSent = $currentDateTime;
            }

            if ($lastBulkRun) {
                // No delay needed since we're dispatching immediately
                EmailBulkRunHydrateDispatchedEmails::dispatch($lastBulkRun);
            }

            if ($updateLastOutBoxSent) {
                // update last_sent_at for this outbox
                $this->update($outbox, [
                    'last_sent_at' => $updateLastOutBoxSent
                ]);
            }
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
