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
use Illuminate\Support\Facades\Log;

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
        // A deleted basket counts too: the hourly sweep sees the Wix order again and must not charge it twice.
        $existingOrder = Order::withTrashed()->where('customer_id', $wixUser->customer_id)
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

        if (!$hasOurProducts) {
            // Usually the merchant's own products or a booking. Logged once, as the hourly sweep sees it again,
            // so an order of ours that should have matched leaves a trail (PRE-43).
            if ($catalogItemIds && Cache::add('wix_order_skipped_'.$wixUser->id.'_'.Arr::get($order, 'id'), true, now()->addDays(GetWixOrdersFromApi::FETCH_DAYS + 1))) {
                Log::info('Wix order skipped: none of its products is in the channel', [
                    'wix_user_id'               => $wixUser->id,
                    'customer_sales_channel_id' => $wixUser->customer_sales_channel_id,
                    'wix_order_id'              => Arr::get($order, 'id'),
                    'catalog_item_ids'          => array_values($catalogItemIds),
                ]);
            }

            return;
        }

        $wixUser->debugWebhooks()->create([
            'data' => $order
        ]);

        StoreWixOrder::run($wixUser, $order);
    }
}
