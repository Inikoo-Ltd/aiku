<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Webhooks;

use App\Actions\Dropshipping\Tiktok\Order\ShowTiktokOrderApi;
use App\Actions\Dropshipping\Tiktok\Order\ValidateIncomingTiktokOrder;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleOrderIncomingTiktok
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(array $modelData)
    {
        DB::transaction(function () use ($modelData) {
            $shopId = Arr::get($modelData, 'shop_id');
            $tiktokUser = TiktokUser::where('tiktok_shop_id', $shopId)->firstOrFail();

            $payload = Arr::get($modelData, 'data');
            $orderId = Arr::get($payload, 'order_id');

            $orders = ShowTiktokOrderApi::run($tiktokUser, $orderId);

            foreach (Arr::get($orders, 'data.orders', []) as $order) {
                if (Arr::get($order, 'status') === 'AWAITING_SHIPMENT') {
                    ValidateIncomingTiktokOrder::run($tiktokUser, $order);
                } elseif (Arr::get($order, 'status') === 'CANCELLED') {
                    $orderToBeCancel = Order::where('customer_id', $tiktokUser->customer_id)
                        ->whereNot('state', OrderStateEnum::CANCELLED)
                        ->where('platform_order_id', Arr::get($order, 'id'))
                        ->first();

                    if ($orderToBeCancel) {
                        CancelOrder::run($orderToBeCancel);
                    }
                }
            }
        });
    }

    public function asController(ActionRequest $request): void
    {
        $this->handle($request->all());
    }
}
