<?php

/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-16h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\WooCommerce\Orders\Webhooks;

use App\Actions\Dropshipping\WooCommerce\Fulfilment\StoreFulfilmentFromWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Orders\StoreOrderFromWooCommerce;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CatchRetinaOrdersFromWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        DB::transaction(function () use ($wooCommerceUser) {
            $existingOrderKeys = $wooCommerceUser
                ->customerSalesChannel
                ?->orders()
                ->pluck('data')
                ->map(fn ($data) => $data['order_key'] ?? null)
                ->filter()
                ->toArray();

            $response = $wooCommerceUser->getWooCommerceOrders();
            foreach ($response as $order) {

                if (in_array($order['order_key'], $existingOrderKeys, true)) {
                    continue;
                }

                if (!empty(array_filter($order['billing'])) && !empty(array_filter($order['shipping']))) {
                    if ($wooCommerceUser->customer?->shop?->type === ShopTypeEnum::FULFILMENT) {
                        StoreFulfilmentFromWooCommerce::run($wooCommerceUser, $order);
                    } elseif ($wooCommerceUser->customer?->shop?->type === ShopTypeEnum::DROPSHIPPING) {
                        StoreOrderFromWooCommerce::run($wooCommerceUser, $order);
                    }
                }
            }
        });
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request): void
    {
        $this->initialisation($wooCommerceUser->organisation, $request);

        $this->handle($wooCommerceUser, $request->all());
    }
}
