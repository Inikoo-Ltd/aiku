<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Models\CRM\Customer;
use App\Models\Web\WebsiteVisitor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SyncAllCustomersWebActivitiesDaily
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function handle(?Carbon $date = null): void
    {
        $date = $date ?? now()->subDay()->startOfDay();

        $customerIds = WebsiteVisitor::query()
            ->whereNotNull('web_user_id')
            ->whereDate('last_seen_at', $date->toDateString())
            ->join('web_users', 'website_visitors.web_user_id', '=', 'web_users.id')
            ->whereNotNull('web_users.customer_id')
            ->distinct()
            ->pluck('web_users.customer_id');

        Customer::whereIn('id', $customerIds)
            ->each(function (Customer $customer) use ($date) {
                SyncCustomerWebActivities::dispatch($customer, $date);
            });
    }

    public function getCommandSignature(): string
    {
        return 'sync:customer-web-activities-daily {--date= : Date to process (Y-m-d), defaults to yesterday}';
    }

    public function asCommand(Command $command): int
    {
        try {
            $date = $command->option('date')
                ? Carbon::parse($command->option('date'))->startOfDay()
                : null;

            $this->handle($date);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
