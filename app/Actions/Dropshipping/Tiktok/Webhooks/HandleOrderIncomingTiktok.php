<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Webhooks;

use App\Actions\Dropshipping\Tiktok\Order\ShowTiktokOrderApi;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleOrderIncomingTiktok extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser, array $modelData)
    {
        $orderId = Arr::get($modelData, 'order_id');
        $orderStatus = Arr::get($modelData, 'order_status');

        $order = ShowTiktokOrderApi::run($tiktokUser, $orderId);

        if ($orderStatus === 'AWAITING_SHIPMENT') {
            //
        }
    }

    public function asController(TiktokUser $tiktokUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($tiktokUser, $request->all());
    }
}
