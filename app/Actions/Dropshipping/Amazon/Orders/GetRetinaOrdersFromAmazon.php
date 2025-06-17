<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-09h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Amazon\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AmazonUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetRetinaOrdersFromAmazon extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(AmazonUser $amazonUser): void
    {
        DB::transaction(function () use ($amazonUser) {
            $existingOrderKeys = $amazonUser
                ->customerSalesChannel
                ->orders()
                ->pluck('data')
                ->map(fn ($data) => Arr::get($data, 'AmazonOrderId'))
                ->filter()
                ->toArray();

            $response = $amazonUser->getOrders();

            foreach (Arr::get($response, 'payload.Orders') as $order) {
                if (in_array($order['AmazonOrderId'], $existingOrderKeys, true)) {
                    continue;
                }

                StoreOrderFromAmazon::run($amazonUser, $order);
            }
        });
    }

    public function asController(AmazonUser $amazonUser, ActionRequest $request): void
    {
        $this->initialisation($amazonUser->organisation, $request);

        $this->handle($amazonUser);
    }
}
