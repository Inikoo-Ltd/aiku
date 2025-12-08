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
use App\Actions\Comms\Outbox\ReorderRemainder\WithGenerateEmailBulkRuns;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Comms\Outbox;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;

class CustomersHydrateReorderRemainderEmails implements ShouldQueue
{
    // this job only running in the midnight (00:00)
    use AsAction;
    use WithGenerateEmailBulkRuns;
    public string $commandSignature = 'hydrate:reorder-reminder-customers';
    public string $jobQueue = 'low-priority';


    public function handle(): void
    {
        // NOTE: Get the all outbox related to reorder reminder
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->where('code', OutboxCodeEnum::REORDER_REMINDER);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->leftJoin('outbox_settings', 'outboxes.id', '=', 'outbox_settings.outbox_id');
        $queryOutbox->where('outbox_settings.outbox_id', 843);// for testing bulgaria outbox
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outbox_settings.days_after', 'outbox_settings.send_time');
        $outboxes = $queryOutbox->get();

        $currentDateTime = Carbon::now()->utc();

        // NOTE: get all user related each outbox
        foreach ($outboxes as $outbox) {

            \Log::info("original outbox setting: ".$outbox->setting?->send_time);
            // convert to utc
            $outboxSendTimeUTC = Carbon::parse($outbox->setting?->send_time)->utc()->format('H:i');
            \Log::info("Outbox Send Time UTC: ".$outboxSendTimeUTC);


            \Log::info("current date time: ".$currentDateTime);
            $currentTime = $currentDateTime->copy()->format('H:i');
            \Log::info("current time utc : ".$currentTime);

            // now compare  $outboxSendTimeUTC and $currentTime
            if($outboxSendTimeUTC == $currentTime){
                \Log::info(" currentSendTimeUtc same with the currentTime");


                // $startDate = Carbon::now()->subDays($outbox->days_after)->startOfDay();
            // $endDate = $startDate->copy()->endOfDay();
            // $currentDate = Carbon::now()->utc();

            // \Log::info("startDate ".$startDate);
            // \Log::info("endDate ".$endDate);

            // $queryUser = QueryBuilder::for(Customer::class);
            // $queryUser->leftJoin('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
            // $queryUser->where('customer_comms.is_subscribed_to_reorder_reminder', true); // check if customer subscribed to reorder reminder
            // $queryUser->where('customers.shop_id', $outbox->shop_id);
            // $queryUser->where('customers.state', CustomerStateEnum::ACTIVE->value);
            // $queryUser->where('customers.status', CustomerStatusEnum::APPROVED->value);
            // $queryUser->where('customers.shop_id', 42); // test for bulgaria
            // $queryUser->whereBetween('customers.last_invoiced_at', [
            //     $startDate,
            //     $endDate
            // ]);
            // $queryUser->whereNotNull('customers.email');
            // $queryUser->where('customers.email', '!=', '');
            // $queryUser->select('customers.id', 'customers.shop_id');
            // $queryUser->orderBy('customers.shop_id');
            // $queryUser->orderBy('customers.id');


            // $LastBulkRun = null;
            // $lastSentTime = null;
            // foreach ($queryUser->cursor() as $customer) {
            //     $bulkRun = $this->generateEmailBulkRuns(
            //         $customer,
            //         OutboxCodeEnum::REORDER_REMINDER,
            //         Carbon::now()->toDateString() // carbon date string harus sesuai date kirim email
            //     );


            //     //Make sure if TimeZone - like -2,-1, etc
            //     $timeInUTC = Carbon::parse($outbox->send_time)->utc();

            //     \Log::info("time in UTC : ".  $timeInUTC);

            //     // merge current date with sending time
            //     $sendTime = $currentDate->copy()->setTimeFrom($timeInUTC);
            //     \Log::info("full send time : ".$sendTime);

            //     $LastBulkRun = $bulkRun;
            //     $lastSentTime = $sendTime;

            //     // Delay SendReOrderRemainderToCustomerEmail to specific date and time for scheduled sending
            //     SendReOrderRemainderToCustomerEmail::dispatch($customer, $bulkRun)->delay($sendTime);
            // }

            // if ($LastBulkRun && $lastSentTime) {
            //     EmailBulkRunHydrateDispatchedEmails::dispatch($LastBulkRun)->delay($lastSentTime);
            // }


            }
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
