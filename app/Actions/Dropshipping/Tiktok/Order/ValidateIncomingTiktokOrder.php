<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ValidateIncomingTiktokOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'tiktok:get-order {customerSalesChannel}';

    public function handle(TiktokUser $tiktokUser, array $order): void
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
}
