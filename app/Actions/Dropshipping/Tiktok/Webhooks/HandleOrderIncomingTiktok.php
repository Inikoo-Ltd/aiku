<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Webhooks;

use App\Actions\Dropshipping\Tiktok\Order\ShowTiktokOrderApi;
use App\Actions\Dropshipping\Tiktok\Order\StoreTiktokOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
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
            $tiktokUsers = TiktokUser::where('data->authorized_shop->id', $shopId)->get();

            $payload = Arr::get($modelData, 'data');
            $orderId = Arr::get($payload, 'order_id');

            foreach ($tiktokUsers as $tiktokUser) {
                $orders = ShowTiktokOrderApi::run($tiktokUser, $orderId);
                foreach (Arr::get($orders, 'data.orders') as $order) {
                    if (Arr::get($order, 'status') === 'AWAITING_SHIPMENT') {
                        StoreTiktokOrder::run($tiktokUser, $order);
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
