<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-10h-28m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\Purge\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\PurgedOrder\PurgedOrderStatusEnum;
use App\Models\Ordering\Purge;
use App\Models\Ordering\PurgedOrder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PurgeHydratePurgedOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Purge $purge): string
    {
        return $purge->id;
    }

    public function handle(Purge $purge): void
    {
        $purgedOrders = $purge->purgedOrders()
            ->where('status', PurgedOrderStatusEnum::PURGED)
            ->count();

        $stats = [
            'estimated_number_orders'          => $purge->purgedOrders()->count(),
            'number_purged_orders'             => $purgedOrders,
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'purged_orders',
            field: 'status',
            enum: PurgedOrderStatusEnum::class,
            models: PurgedOrder::class,
            where: function ($q) use ($purge) {
                $q->where('purge_id', $purge->id);
            }
        ));
        $purge->stats()->update($stats);

        // Excluding amountStats & transactionsStats for aurora migrated purges
        if (!$purge->source_id) {
            $this->amountStats($purge);
            $this->transactionsStats($purge);
        }

    }

    private function transactionsStats(Purge $purge): void
    {
        $estimatedNumberTransactions = $purge->purgedOrders()->sum('number_transactions');

        $purgedTransactions = $purge->purgedOrders()
            ->where('status', PurgedOrderStatusEnum::PURGED)
            ->sum('number_transactions');

        $stats = [
            'estimated_number_transactions'    => $estimatedNumberTransactions,
            'number_purged_transactions'       => $purgedTransactions,
        ];
        $purge->stats()->update($stats);

    }


    private function amountStats(Purge $purge): void
    {


        $estimatedPurgedOrderAmounts = $purge->purgedOrders()
            ->get();

        $estimatedPurgedAmount = $estimatedPurgedOrderAmounts->sum(function ($purgedOrder) {
            return $purgedOrder->order->net_amount ?? 0;
        });

        $estimatedPurgedOrgAmount = $estimatedPurgedOrderAmounts->sum(function ($purgedOrder) {
            return $purgedOrder->order->org_net_amount ?? 0;
        });

        $estimatedPurgedGrpAmount = $estimatedPurgedOrderAmounts->sum(function ($purgedOrder) {
            return $purgedOrder->order->grp_net_amount ?? 0;
        });

        $purgedOrderAmounts = $purge->purgedOrders()
            ->where('status', PurgedOrderStatusEnum::PURGED)
            ->get();

        $purgedAmount = $purgedOrderAmounts->sum(function ($purgedOrder) {
            return $purgedOrder->order->net_amount ?? 0;
        });

        $purgedOrgAmount = $purgedOrderAmounts->sum(function ($purgedOrder) {
            return $purgedOrder->order->org_net_amount ?? 0;
        });

        $purgedGrpAmount = $purgedOrderAmounts->sum(function ($purgedOrder) {
            return $purgedOrder->order->grp_net_amount ?? 0;
        });

        $stats = [

            'estimated_net_amount'                 => $estimatedPurgedAmount,
            'estimated_org_net_amount'             => $estimatedPurgedOrgAmount,
            'estimated_grp_net_amount'             => $estimatedPurgedGrpAmount,
            'purged_net_amount'                    => $purgedAmount,
            'purged_org_net_amount'                => $purgedOrgAmount,
            'purged_grp_net_amount'                => $purgedGrpAmount
        ];
        $purge->stats()->update($stats);

    }
}
