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
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRun;
use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\DispatchedEmail\App\Enums\Comms\DispatchedEmailEvent\DispatchedEmailEventTypeEnum;

class ReorderRemainderHydrateEmailBulkRuns
// implements ShouldBeUnique
{
    use AsAction;
    public string $commandSignature = 'hydrate:reorder-reminder-bulk-runs';
    // public string $jobQueue = 'low-priority';

    // public function getJobUniqueId(EmailBulkRun $emailBulkRun): string
    // {
    //     return $emailBulkRun->id;
    // }

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
        $defaultDays = 20;

        // get the customers
        // TODO: confirm more conditions for the customers like
        $customers = Customer::where('last_invoiced_at', '<', now()->subDays($defaultDays))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('shop_id')
            ->get();


            //  TODO: check if has group, organitation , and shop difference

                // Find or create outbox (use first customer's shop as reference)
                //  TODO: need to update query for grouping the customer
                $firstCustomer = $customers->first();

                $outbox = $firstCustomer->shop->outboxes()->where('code', OutboxCodeEnum::REORDER_REMINDER)->first();

                // TODO: get the Email Ongoing Run
                $emailOngoingRun = $outbox->emailOngoingRun;
                // Log::info('outbox', ['outbox' => $outbox]);
                // Log::info('emailOngoingRun', ['emailOngoingRun' => $emailOngoingRun]);

                // TODO: create EmailBulkRun
                // the data need to adding to modelData
              $storeEmailBulkRun =  StoreEmailBulkRun::make()->action(
                    emailOngoingRun: $emailOngoingRun,
                    modelData: [
                        'subject' => now()->format('Y-m-d'),
                        'state' => EmailBulkRunStateEnum::SENDING,
                        'scheduled_at' => now(),
                        'data' => [
                            'customer_count' => $customers->count(),
                            'reminder_days' => config('aiku.reorder_reminder_days', 20),
                            'created_by' => 'ReorderRemainderHydrateEmailBulkRunsEnhanced'
                        ]
                        ],
                        hydratorsDelay: true,
                        strict: false,
                );
                Log::info('storeEmailBulkRun', ['storeEmailBulkRun' => $storeEmailBulkRun]);

        // TODO: Create bulk for the recipients using dispatched email
        $customers->each(function ($customer) use ($storeEmailBulkRun) {
            // Example can be found here : app/Actions/Transfers/Aurora/FetchAuroraDispatchedEmails.php
            $dispatchedEmail = StoreDispatchedEmail::make()->action(
                parent: $storeEmailBulkRun,
                recipient: $customer,
                modelData: [
                    'is_test'       => true,
                    'provider'      => DispatchedEmailProviderEnum::SES,
                    'email_address' => $customer->email,
                    'state' => DispatchedEmailStateEnum::READY,
                ],
                strict: false,
            );
            Log::info('dispatchedEmail', ['dispatchedEmail' => $dispatchedEmail]);
        });


        // $customers->each(function ($customer) {
        //     SendReOrderRemainderToCustomerEmail::run($customer);
        // });
    }

    public function asCommand(): void
    {
        $this->run();
    }

    //  TODO: Trigger email delivery
    // private function triggerEmailDelivery(EmailBulkRun $emailBulkRun): void
    // {
    //     try {
    //         // Create delivery channel
    //         $channelData = [
    //             'code' => 'ses-main',
    //             'data' => [
    //                 'auto_created' => true,
    //                 'provider' => 'ses'
    //             ]
    //         ];

    //         $emailDeliveryChannel = StoreEmailDeliveryChannel::make()->action($emailBulkRun, $channelData);

    //         // Dispatch SendEmailDeliveryChannel job
    //         \App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);

    //         Log::info('Triggered email delivery for EmailBulkRun', [
    //             'bulk_run_id' => $emailBulkRun->id,
    //             'channel_id' => $emailDeliveryChannel->id
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Failed to trigger email delivery', [
    //             'bulk_run_id' => $emailBulkRun->id,
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }
}
