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
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Comms\Outbox;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;

class CustomersHydrateReorderRemainderEmails implements ShouldQueue
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    public string $commandSignature = 'hydrate:reorder-reminder-customers';
    public string $jobQueue = 'low-priority';


    public function handle(): void
    {

        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->where('code', OutboxCodeEnum::REORDER_REMINDER);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->leftJoin('outbox_settings', 'outboxes.id', '=', 'outbox_settings.outbox_id');
        // $queryOutbox->where('outbox_settings.outbox_id', 843);// for testing bulgaria outbox
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outbox_settings.days_after', 'outbox_settings.send_time');
        $outboxes = $queryOutbox->get();

        $currentDateTime = Carbon::now()->utc();

        // NOTE: get all user related each outbox
        foreach ($outboxes as $outbox) {
            // convert to utc
            $outboxSendTimeUTC = Carbon::parse($outbox->setting?->send_time)->utc()->format('H:i');

            $currentTime = $currentDateTime->copy()->format('H:i');

            //compare  $outboxSendTimeUTC and $currentTime
            if ($outboxSendTimeUTC == $currentTime) {

                $startDate = Carbon::now()->subDays($outbox->days_after)->startOfDay();
                $endDate = $startDate->copy()->endOfDay();

                $queryUser = QueryBuilder::for(Customer::class);
                $queryUser->leftJoin('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
                $queryUser->where('customer_comms.is_subscribed_to_reorder_reminder', true); // check if customer subscribed to reorder reminder
                $queryUser->where('customers.shop_id', $outbox->shop_id);
                $queryUser->where('customers.state', CustomerStateEnum::ACTIVE->value);
                $queryUser->where('customers.status', CustomerStatusEnum::APPROVED->value);
                // $queryUser->where('customers.shop_id', 42); // test for bulgaria
                $queryUser->whereBetween('customers.last_invoiced_at', [
                    $startDate,
                    $endDate
                ]);
                $queryUser->whereNotNull('customers.email');
                $queryUser->where('customers.email', '!=', '');
                $queryUser->select('customers.id', 'customers.shop_id');
                $queryUser->orderBy('customers.shop_id');
                $queryUser->orderBy('customers.id');


                $LastBulkRun = null;
                foreach ($queryUser->cursor() as $customer) {
                    $bulkRun = $this->generateEmailBulkRuns(
                        $customer,
                        OutboxCodeEnum::REORDER_REMINDER,
                        $currentDateTime->copy()->toDateString()
                    );

                    $LastBulkRun = $bulkRun;

                    // Dispatch SendReOrderRemainderToCustomerEmail immediately
                    SendReOrderRemainderToCustomerEmail::dispatch($customer, $bulkRun);
                }

                if ($LastBulkRun) {
                    // No delay needed since we're dispatching immediately
                    EmailBulkRunHydrateDispatchedEmails::dispatch($LastBulkRun);
                }


            }
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
