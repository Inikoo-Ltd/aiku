<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-09h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Orders\Webhooks;

use App\Actions\Dropshipping\Ebay\Orders\StoreOrderFromEbay;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CatchRetinaOrdersFromEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser): void
    {
        DB::transaction(function () use ($ebayUser) {
            $existingOrderKeys = $ebayUser
                ->customerSalesChannel
                ->orders()
                ->pluck('data')
                ->map(fn ($data) => Arr::get($data, 'orderId'))
                ->filter()
                ->toArray();

            $response = $ebayUser->getOrders();

            foreach ($response['orders'] as $order) {
                if (in_array(Arr::get($order, 'orderId'), $existingOrderKeys, true)) {
                    continue;
                }
                
                if (!empty(array_filter(Arr::get($order, 'buyer'))) && !empty(array_filter(Arr::get($order, 'buyer.buyerRegistrationAddress')))) {
                    StoreOrderFromEbay::run($ebayUser, $order);
                } else {
                    \Sentry::captureMessage('The order doesnt have shipping, order: id ' . Arr::get($order, 'orderId'));
                }
            }
        });
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): void
    {
        $this->initialisation($ebayUser->organisation, $request);

        $this->handle($ebayUser);
    }
}
