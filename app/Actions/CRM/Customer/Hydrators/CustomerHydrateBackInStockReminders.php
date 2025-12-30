<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-11h-37m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Comms\BackInStockReminderSnapshot;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateBackInStockReminders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $customerId): string
    {
        return $customerId ?? 'empty';
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $stats = [
            'number_reminders' => BackInStockReminderSnapshot::where('customer_id', $customerId)->whereNull('reminder_cancelled_at')->whereNotNull('reminder_sent_at')->count(),
            'number_reminders_cancelled' => BackInStockReminderSnapshot::where('customer_id', $customerId)->whereNotNull('reminder_cancelled_at')->whereNull('reminder_sent_at')->count(),
        ];

        $customer->stats()->update($stats);
    }
}
