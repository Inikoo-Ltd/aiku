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

    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id;
    }

    public function handle(Customer $customer): void
    {
        $stats = [
            'number_reminders' => $customer->backInStockReminder()->whereNull('un_reminded_at')->count(),
            'number_reminders_cancelled' => $customer->backInStockReminder()->whereNotNull('un_reminded_at')->count(),
        ];

        $customer->stats()->update($stats);
    }

}
