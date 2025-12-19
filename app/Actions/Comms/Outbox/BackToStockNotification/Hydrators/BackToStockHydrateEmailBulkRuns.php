<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2025 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\BackToStockNotification\Hydrators;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Customer;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Actions\Comms\Outbox\ReorderRemainder\WithGenerateEmailBulkRuns;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Comms\Outbox;
use App\Models\CRM\BackInStockReminder;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;

class BackToStockHydrateEmailBulkRuns implements ShouldQueue
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    use WithActionUpdate;
    public string $commandSignature = 'hydrate:out-of-stock-notification';
    public string $jobQueue = 'low-priority';


    public function handle(): void
    {

        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::OOS_NOTIFICATION]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        // $queryOutbox->whereNotNull('send_time');
        // $queryOutbox->whereIn('id', [863,862,843]); //test for bulgaria
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        $currentDateTime = Carbon::now()->utc();

        foreach ($outboxes as $outbox) {
            $lastOutBoxSent = $outbox->last_sent_at;

            $compareDate = $currentDateTime->copy()->subDays($outbox->days_after)->endOfDay();


            //change to back_in_stock_reminders
            $baseQuery = QueryBuilder::for(BackInStockReminder::class);
            $baseQuery->join('customers', 'back_in_stock_reminders.customer_id', '=', 'customers.id');






            // $queryCustomer = QueryBuilder::for(Customer::class);
            // $queryCustomer->leftJoin('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
            // $queryCustomer->where('customer_comms.is_subscribed_to_reorder_reminder', true); // check if customer subscribed to reorder reminder
            // $queryCustomer->where('customers.shop_id', $outbox->shop_id);
            // $queryCustomer->where('customers.state', CustomerStateEnum::ACTIVE->value);
            // $queryCustomer->where('customers.status', CustomerStatusEnum::APPROVED->value);
            // // $queryCustomer->where('customers.shop_id', 42); // test for bulgaria

            // $queryCustomer->where('customers.last_invoiced_at', '<=', $compareDate);
            // if ($lastOutBoxSent) {
            //     $queryCustomer->where('customers.last_invoiced_at', '>', $lastOutBoxSent);
            // }
            // $queryCustomer->whereNotNull('customers.email');
            // $queryCustomer->where('customers.email', '!=', '');
            // $queryCustomer->select('customers.id', 'customers.shop_id');
            // $queryCustomer->orderBy('customers.shop_id');
            // $queryCustomer->orderBy('customers.id');

            // $LastBulkRun = null;
            // $updateLastOutBoxSent = null;
            // foreach ($queryCustomer->cursor() as $customer) {

            //     $bulkRun = $this->generateEmailBulkRuns(
            //         $customer,
            //         $outbox->code,
            //         $currentDateTime->copy()->toDateString()
            //     );

            //     $LastBulkRun = $bulkRun;

            //     // Dispatch SendReOrderRemainderToCustomerEmail immediately
            //     SendReOrderRemainderToCustomerEmail::dispatch($customer, $outbox->code, $bulkRun);

            //     $updateLastOutBoxSent = $currentDateTime;
            // }

            // if ($LastBulkRun) {
            //     // No delay needed since we're dispatching immediately
            //     EmailBulkRunHydrateDispatchedEmails::dispatch($LastBulkRun);
            // }

            // if ($updateLastOutBoxSent) {
            //     // update last_sent_at for this outbox
            //     $this->update($outbox, ['last_sent_at' => $updateLastOutBoxSent]);
            // }
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
