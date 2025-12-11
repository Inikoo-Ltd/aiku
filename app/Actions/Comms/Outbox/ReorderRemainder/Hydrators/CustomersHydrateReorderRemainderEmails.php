<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\ReorderRemainder\Hydrators;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Customer;
use App\Actions\Comms\Email\SendReOrderRemainderToCustomerEmail;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Actions\Comms\Outbox\ReorderRemainder\WithGenerateEmailBulkRuns;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Comms\Outbox;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;

class CustomersHydrateReorderRemainderEmails implements ShouldQueue
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    use WithActionUpdate;
    public string $commandSignature = 'hydrate:reorder-reminder-customers';
    public string $jobQueue = 'low-priority';


    public function handle(): void
    {

        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::REORDER_REMINDER,OutboxCodeEnum::REORDER_REMINDER_2ND, OutboxCodeEnum::REORDER_REMINDER_3RD]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->whereNotNull('days_after');
        // $queryOutbox->whereNotNull('send_time');
        // $queryOutbox->whereIn('id', [863,862,843]); //test for bulgaria
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.days_after', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        $currentDateTime = Carbon::now()->utc();

        foreach ($outboxes as $outbox) {
            $lastOutBoxSent = $outbox->last_sent_at;

            $compareDate = $currentDateTime->copy()->subDays($outbox->days_after)->endOfDay();

            $queryCustomer = QueryBuilder::for(Customer::class);
            $queryCustomer->leftJoin('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
            $queryCustomer->where('customer_comms.is_subscribed_to_reorder_reminder', true); // check if customer subscribed to reorder reminder
            $queryCustomer->where('customers.shop_id', $outbox->shop_id);
            $queryCustomer->where('customers.state', CustomerStateEnum::ACTIVE->value);
            $queryCustomer->where('customers.status', CustomerStatusEnum::APPROVED->value);
            // $queryCustomer->where('customers.shop_id', 42); // test for bulgaria

            $queryCustomer->where('customers.last_invoiced_at', '<=', $compareDate);
            if ($lastOutBoxSent) {
                $queryCustomer->where('customers.last_invoiced_at', '>', $lastOutBoxSent);
            }
            $queryCustomer->whereNotNull('customers.email');
            $queryCustomer->where('customers.email', '!=', '');
            $queryCustomer->select('customers.id', 'customers.shop_id');
            $queryCustomer->orderBy('customers.shop_id');
            $queryCustomer->orderBy('customers.id');

            $LastBulkRun = null;
            foreach ($queryCustomer->cursor() as $customer) {

                $bulkRun = $this->generateEmailBulkRuns(
                    $customer,
                    $outbox->code,
                    $currentDateTime->copy()->toDateString()
                );

                $LastBulkRun = $bulkRun;

                // Dispatch SendReOrderRemainderToCustomerEmail immediately
                SendReOrderRemainderToCustomerEmail::dispatch($customer, $outbox->code, $bulkRun);
            }

            if ($LastBulkRun) {
                // No delay needed since we're dispatching immediately
                EmailBulkRunHydrateDispatchedEmails::dispatch($LastBulkRun);
            }


            // update last_sent_at for this outbox
            $this->update($outbox, ['last_sent_at' => $currentDateTime]);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
