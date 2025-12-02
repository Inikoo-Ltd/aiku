<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\ReorderRemainder\Hydrators;

use App\Models\Comms\EmailBulkRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Customer;
use App\Actions\Comms\Email\SendReOrderRemainderToCustomerEmail;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Actions\Comms\Outbox\ReorderRemainder\WithGenerateEmailBulkRuns;
use App\Actions\Comms\EmailBulkRun\Hydrators\EmailBulkRunHydrateDispatchedEmails;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;

class CustomersHydrateReorderRemainderEmails implements ShouldQueue
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    public string $commandSignature = 'hydrate:reorder-reminder-customers';
    public string $jobQueue = 'low-priority';


    public function handle(): void
    {
        // TODO: update from setting
        $defaultDays =  env('REORDER_REMINDER_DAYS', 20);

        // get the customers
        // TODO: confirm to raul more conditions for the customers like last_invoiced_at, status, state and etc.
        $baseCustomersQuery = Customer::where('last_invoiced_at', '<', now()->subDays($defaultDays))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('shop_id')
            ->where('state', CustomerStateEnum::ACTIVE->value)
            ->where('status', CustomerStatusEnum::APPROVED->value)
            ->select('id', 'shop_id')
            ->orderBy('shop_id')
            ->orderBy('id');

        $currentShopId = null;
        $currentBulkRun = null;
        $buffer = [];
        $today = now()->toDateString();

        foreach ($baseCustomersQuery->cursor() as $row) {
            $customer = (object) $row;

            // New shop → finalize previous bulk run and start new one
            if ($currentShopId !== $customer->shop_id) {
                // Process remaining customers from previous shop
                if ($currentBulkRun && !empty($buffer)) {
                    $this->dispatchEmailsForBulkRun($currentBulkRun, $buffer);
                    EmailBulkRunHydrateDispatchedEmails::dispatch($currentBulkRun);
                    $buffer = [];
                }

                $currentShopId = $customer->shop_id;
                $currentBulkRun = $this->generateEmailBulkRuns(
                    $customer,
                    OutboxCodeEnum::REORDER_REMINDER,
                    $today
                );
            }

            $buffer[] = $customer;

            // Flush buffer every 1000 customers to prevent memory creep
            if (count($buffer) >= 1000) {
                $this->dispatchEmailsForBulkRun($currentBulkRun, $buffer);
                $buffer = [];
            }


        }

        // Don't forget the last shop's customers
        if ($currentBulkRun && !empty($buffer)) {
            $this->dispatchEmailsForBulkRun($currentBulkRun, $buffer);
            EmailBulkRunHydrateDispatchedEmails::dispatch($currentBulkRun);
        }
    }

    private function dispatchEmailsForBulkRun(EmailBulkRun $bulkRun, array $customers): void
    {
        foreach ($customers as $customer) {
            SendReOrderRemainderToCustomerEmail::dispatch($customer, $bulkRun);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
