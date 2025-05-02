<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-16h-16m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\Purge;

use App\Actions\Ordering\PurgedOrder\StorePurgedOrder;
use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Purge;
use Carbon\Carbon;

class PurgeCreatePurgeOrders extends OrgAction
{
    public function handle(Purge $purge): Purge
    {
        $dateThreshold = Carbon::now()->subDays($purge->inactive_days);
        $orders        = $purge->shop->orders()
            ->where('updated_at', '<', $dateThreshold)
            ->where('state', OrderStateEnum::CREATING)
            ->get();

        foreach ($orders as $order) {
            StorePurgedOrder::make()->action($purge, $order, []);
        }

        $purge->refresh();

        return $purge;
    }
}
