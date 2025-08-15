<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Feb 2025 10:56:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Fulfilment;

use App\Actions\Fulfilment\PalletReturn\CancelPalletReturn;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitAndConfirmPalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Enums\Dropshipping\ShopifyFulfilmentReasonEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePalletReturnFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(ShopifyUser $shopifyUser, array $modelData): bool
    {
        return DB::transaction(function () use ($shopifyUser, $modelData) {
            if ($shopifyUser->orders()->where('shopify_fulfilment_id', Arr::get($modelData, 'id'))->exists()) {
                return false;
            }

            $shopifyProducts = collect($modelData['line_items']);
            $deliveryAddress = Arr::get($modelData, 'shipping_address');
            $country = Country::where('code', Arr::get($deliveryAddress, 'country_code'))->first();

            $deliveryAddressData = [
                'address_line_1' => Arr::get($deliveryAddress, 'address1'),
                'address_line_2' => Arr::get($deliveryAddress, 'address2'),
                'sorting_code' => null,
                'postal_code' => Arr::get($deliveryAddress, 'zip'),
                'dependent_locality' => null,
                'locality' => Arr::get($deliveryAddress, 'city'),
                'administrative_area' => Arr::get($deliveryAddress, 'province'),
                'country_code'        => Arr::get($deliveryAddress, 'country_code'),
                'country_id'          => $country?->id
            ];

            if ($deliveryAddress) {
                $deliveryAddress = new Address($deliveryAddressData);
            } else {
                $deliveryAddress = new Address($shopifyUser->customer?->deliveryAddress?->toArray());
            }

            $palletReturn = StorePalletReturn::make()->actionWithDropshipping($shopifyUser->customer->fulfilmentCustomer, [
                'type' => PalletReturnTypeEnum::DROPSHIPPING,
                'platform_id' => Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first()->id,
                'delivery_address' => $deliveryAddress,
                'data' => [
                    'shopify_order_id' => Arr::get($modelData, 'order_id'),
                    'shopify_fulfilment_id' => Arr::get($modelData, 'id'),
                    'destination' => Arr::get($modelData, 'destination'),
                    'shopify_user_id' => $shopifyUser->id,
                ],
                'is_collection' => false,
                'shopify_user_id' => $shopifyUser->id,
                'customer_sales_channel_id' => $shopifyUser->customerSalesChannel->id
            ]);

            $storedItems = [];
            $allComplete = true;
            $someComplete = false;

            foreach ($shopifyProducts as $shopifyProduct) {
                $shopifyPortfolio = $shopifyUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $shopifyProduct['product_id'])
                    ->first();

                // todo i dont know what you have to do , but just do it
                $portfolio = null;//<-- this is wrong

                $storedItems[$portfolio->item_id] = [
                    'quantity' => $shopifyProduct['quantity']
                ];

                $itemQuantity = (int) $portfolio->item->total_quantity;
                $requiredQuantity = $shopifyProduct['quantity'];

                if ($itemQuantity >= $requiredQuantity) {
                    $someComplete = true;
                } else {
                    $allComplete = false;
                }
            }

            if (blank($storedItems)) {
                return false;
            }

            $reasons = [
                'no_fulfilment_reason' => ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK,
                'no_fulfilment_reason_notes' => ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->notes()[ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->value],
            ];

            if ($allComplete) {
                $status = ChannelFulfilmentStateEnum::OPEN;
                $reasons = [];
            } elseif ($someComplete) {
                $status = ChannelFulfilmentStateEnum::HOLD;
            } else {
                $status = ChannelFulfilmentStateEnum::INCOMPLETE;
            }

            StoreStoredItemsToReturn::make()->action($palletReturn, [
                'stored_items' => $storedItems
            ]);

            $shopifyOrder = StoreShopifyOrderFulfilment::run($shopifyUser, $palletReturn, [
                'shopify_order_id' => Arr::get($modelData, 'order_id'),
                'shopify_fulfilment_id' => Arr::get($modelData, 'id'),
                'state' => $status->value,
                'customer' => Arr::get($modelData, 'customer'),
                ...$reasons
            ]);

            if ($shopifyOrder && $status === ChannelFulfilmentStateEnum::HOLD) {
                HoldFulfilmentOrderShopify::run($shopifyOrder, $shopifyUser);
            } elseif ($shopifyOrder && $status === ChannelFulfilmentStateEnum::OPEN) {
                SubmitAndConfirmPalletReturn::make()->action($palletReturn);
            } else {
                CancelPalletReturn::make()->action($palletReturn->fulfilmentCustomer, $palletReturn);
            }

            return true;
        });
    }

    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        $this->initialisation($shopifyUser->organisation, $request);

        $this->handle($shopifyUser, $request->all());
    }
}
