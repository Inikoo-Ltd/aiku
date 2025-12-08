<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateInvoiceTransactions implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_invoice_transactions' => $group->invoiceTransactions()->count(),
            'number_positive_invoice_transactions' => $group->invoiceTransactions()->where('net_amount', '>', 0)->count(),
            'number_negative_invoice_transactions' => $group->invoiceTransactions()->where('net_amount', '<', 0)->count(),
            'number_zero_invoice_transactions' => $group->invoiceTransactions()->where('net_amount', 0)->count(),

            'number_current_invoice_transactions' => $group->invoiceTransactions()->where('date', '>=', now()->startOfMonth())->whereDoesntHave('transaction', function ($query) {
                $query->where('state', TransactionStateEnum::CANCELLED);
            })->count(),
            'number_positive_current_invoice_transactions' => $group->invoiceTransactions()->where('date', '>=', now()->startOfMonth())->where('net_amount', '>', 0)->whereDoesntHave('transaction', function ($query) {
                $query->where('state', TransactionStateEnum::CANCELLED);
            })->count(),
            'number_negative_current_invoice_transactions' => $group->invoiceTransactions()->where('date', '>=', now()->startOfMonth())->where('net_amount', '<', 0)->whereDoesntHave('transaction', function ($query) {
                $query->where('state', TransactionStateEnum::CANCELLED);
            })->count(),
            'number_zero_current_invoice_transactions' => $group->invoiceTransactions()->where('date', '>=', now()->startOfMonth())->where('net_amount', 0)->whereDoesntHave('transaction', function ($query) {
                $query->where('state', TransactionStateEnum::CANCELLED);
            })->count(),
        ];

        $group->orderingStats()->update($stats);
    }

    public string $commandSignature = 'hydrate:group_invoice_transactions';

    public function asCommand($command): void
    {
        $group = Group::first();
        $this->handle($group);
    }
}
