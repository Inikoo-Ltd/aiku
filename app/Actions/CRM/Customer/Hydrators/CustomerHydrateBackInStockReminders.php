<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-11h-37m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
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
            'number_reminders' => $customer->backInStockReminder()->whereNull('un_reminded_at')->count(),
            'number_reminders_cancelled' => $customer->backInStockReminder()->whereNotNull('un_reminded_at')->count(),
        ];

        $customer->stats()->update($stats);
    }

}
