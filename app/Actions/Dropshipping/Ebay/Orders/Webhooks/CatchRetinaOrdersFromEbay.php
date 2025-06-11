<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-09h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Orders\Webhooks;

use App\Actions\Dropshipping\WooCommerce\Fulfilment\StoreFulfilmentFromWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Orders\StoreOrderFromWooCommerce;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
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
        $response = $ebayUser->getOrders();
        dd($response);
        // DB::transaction(function () use ($ebayUser) {
        // $existingOrderKeys = $ebayUser
        //     ->customerSalesChannel
        //     ->orders()
        //     ->pluck('data')
        //     ->map(fn ($data) => $data['order_key'] ?? null)
        //     ->filter()
        //     ->toArray();

        // $response = $ebayUser->getOrders();

        // foreach ($response as $order) {
        //     if (in_array($order['order_key'], $existingOrderKeys, true)) {
        //         continue;
        //     }

        //     if (!empty(array_filter($order['billing'])) && !empty(array_filter($order['shipping'])) && !blank(Arr::get($order, 'shipping.country')) && !blank(Arr::get($order, 'billing.country'))) {
        //         if ($wooCommerceUser->customer?->shop?->type === ShopTypeEnum::FULFILMENT) {
        //             StoreFulfilmentFromWooCommerce::run($wooCommerceUser, $order);
        //         } elseif ($wooCommerceUser->customer?->shop?->type === ShopTypeEnum::DROPSHIPPING) {
        //             StoreOrderFromWooCommerce::run($wooCommerceUser, $order);
        //         }
        //     } else {
        //         \Sentry::captureMessage('The order doesnt have billing or shipping, order: id ' . $order['order_key']);
        //     }
        // }
        // });
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): void
    {
        $this->initialisation($ebayUser->organisation, $request);

        $this->handle($ebayUser, $request->all());
    }
}
