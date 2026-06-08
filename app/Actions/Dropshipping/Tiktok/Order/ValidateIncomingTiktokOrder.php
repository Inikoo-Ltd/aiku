<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\Dropshipping\Tiktok\Fulfilment\StoreTiktokFulfilmentOrder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\StoredItem;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ValidateIncomingTiktokOrder extends RetinaAction
{
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser, $order = []): void
    {
        $tiktokUser->debugWebhooks()->create([
            'data' => $order
        ]);

        if ($tiktokUser->customer->is_fulfilment) {
            $this->forFulfilment($tiktokUser, $order);
        } elseif ($tiktokUser->customer->is_dropshipping) {
            $this->forDropshipping($tiktokUser, $order);
        }
    }

    public function forDropshipping(TiktokUser $tiktokUser, $order = []): void
    {
        $existingOrder = Order::where('customer_id', $tiktokUser->customer_id)
            ->where('platform_order_id', Arr::get($order, 'id'))
            ->exists();

        if ($existingOrder) {
            return;
        }

        if (Arr::get($order, 'status') !== 'AWAITING_SHIPMENT') {
            return;
        }

        $lineItems = collect(Arr::get($order, 'line_items', []))
            ->pluck('product_id')
            ->filter()
            ->toArray();

        $hasOutProducts = DB::table('portfolios')
            ->where('customer_sales_channel_id', $tiktokUser->customer_sales_channel_id)
            ->whereIn('platform_product_id', $lineItems)
            ->exists();

        if ($hasOutProducts) {
            StoreTiktokOrder::run($tiktokUser, $order);
        }
    }

    public function forFulfilment(TiktokUser $tiktokUser, $order = []): void
    {
        $existingReturn = PalletReturn::where('fulfilment_customer_id', $tiktokUser->customer->fulfilmentCustomer->id)
            ->where('platform_order_id', Arr::get($order, 'id'))
            ->exists();

        if ($existingReturn) {
            return;
        }

        if (Arr::get($order, 'status') !== 'AWAITING_SHIPMENT') {
            return;
        }

        $lineItems = collect(Arr::get($order, 'line_items', []))
            ->pluck('sku_id')
            ->filter()
            ->toArray();

        $portfolios = DB::table('portfolios')
            ->where('customer_sales_channel_id', $tiktokUser->customer_sales_channel_id)
            ->whereIn('platform_product_variant_id', $lineItems)
            ->where('item_type', class_basename(StoredItem::class))
            ->get();

        $hasPallet = false;
        foreach ($portfolios as $portfolio) {
            /** @var StoredItem $storedItem */
            $storedItem = $portfolio->item;

            if($storedItem->pallets->count() > 0) {
                $hasPallet = true;
            }
        }

        if (!blank($portfolios) && $hasPallet) {
            StoreTiktokFulfilmentOrder::run($tiktokUser, $order);
        }
    }
}
