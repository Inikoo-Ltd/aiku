<?php
/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-16h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\WooCommerce\Orders\Webhooks;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\WooCommerce\Fulfilment\StoreFulfilmentFromWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Orders\StoreOrderFromWooCommerce;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedWooCommerceAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Models\Dropshipping\ShopifyUserHasProduct;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
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
            $response = $wooCommerceUser->getWooCommerceOrders();
            dd($response);
            foreach ($response as $order) {
                if ($wooCommerceUser->customer?->shop?->type === ShopTypeEnum::FULFILMENT) {
                    StoreFulfilmentFromWooCommerce::run($wooCommerceUser, $order);
                } elseif ($wooCommerceUser->customer?->shop?->type === ShopTypeEnum::DROPSHIPPING) {
                    StoreOrderFromWooCommerce::run($wooCommerceUser, $order);
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