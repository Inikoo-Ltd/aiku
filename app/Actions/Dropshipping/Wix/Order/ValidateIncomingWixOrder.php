<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WixUser;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ValidateIncomingWixOrder extends RetinaAction
{
    use WithActionUpdate;

    private const int LOCK_SECONDS = 120;

    private const int LOCK_WAIT_SECONDS = 15;

    public function handle(WixUser $wixUser, array $order = []): void
    {
        $platformOrderId = Arr::get($order, 'id');

        if (blank($platformOrderId)) {
            return;
        }

        $lock = Cache::lock('wix_order_'.$wixUser->id.'_'.$platformOrderId, self::LOCK_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS, function () use ($wixUser, $order) {
                $this->processOrder($wixUser, $order);
            });
        } catch (LockTimeoutException) {
            return;
        }
    }

    private function processOrder(WixUser $wixUser, array $order): void
    {
        $wixUser->debugWebhooks()->create([
            'data' => $order
        ]);

        $existingOrder = Order::where('customer_id', $wixUser->customer_id)
            ->where('platform_order_id', Arr::get($order, 'id'))
            ->exists();

        if ($existingOrder) {
            return;
        }

        if (Arr::get($order, 'status') !== 'APPROVED') {
            return;
        }

        $catalogItemIds = collect(Arr::get($order, 'lineItems', []))
            ->map(fn ($lineItem) => Arr::get($lineItem, 'catalogReference.catalogItemId'))
            ->filter()
            ->toArray();

        $hasOurProducts = DB::table('portfolios')
            ->where('customer_sales_channel_id', $wixUser->customer_sales_channel_id)
            ->whereIn('platform_product_id', $catalogItemIds)
            ->exists();

        if ($hasOurProducts) {
            StoreWixOrder::run($wixUser, $order);
        }
    }
}
