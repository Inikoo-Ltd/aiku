<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ValidateIncomingAllegroOrder extends RetinaAction
{
    use WithActionUpdate;

    public function handle(AllegroUser $allegroUser, $order = []): void
    {
        $existingOrder = Order::where('customer_id', $allegroUser->customer_id)
            ->where('platform_order_id', Arr::get($order, 'id'))
            ->exists();

        if ($existingOrder) {
            return;
        }

        if (Arr::get($order, 'status') !== 'READY_FOR_PROCESSING') {
            return;
        }

        $lineItems = collect(Arr::get($order, 'lineItems', []))
            ->map(fn ($lineItem) => Arr::get($lineItem, 'offer.id'))
            ->toArray();

        $hasOutProducts = DB::table('portfolios')
            ->where('customer_sales_channel_id', $allegroUser->customer_sales_channel_id)
            ->whereIn('platform_product_id', $lineItems)
            ->exists();

        if ($hasOutProducts) {
            StoreAllegroOrder::run($allegroUser, $order);
        }
    }
}
