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
use App\Enums\Dropshipping\ShopifyFulfilmentReasonEnum;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Country;
use App\Models\ShopifyUserHasProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreFulfilmentFromShopify extends OrgAction
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

            $deliveryAddress = [
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

            $palletReturn = StorePalletReturn::make()->actionWithDropshipping($shopifyUser->customer->fulfilmentCustomer, [
                'type' => PalletReturnTypeEnum::DROPSHIPPING,
                'platform_id' => Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first()->id
            ]);

            $storedItems = [];
            $allComplete = true;
            $someComplete = false;

            foreach ($shopifyProducts as $shopifyProduct) {
                $shopifyUserHasProduct = ShopifyUserHasProduct::where('shopify_user_id', $shopifyUser->id)
                    ->where('shopify_product_id', $shopifyProduct['product_id'])
                    ->first();

                if (!$shopifyUserHasProduct) {
                    continue;
                }

                $storedItems[$shopifyUserHasProduct->portfolio->item_id] = [
                    'quantity' => $shopifyProduct['quantity']
                ];

                $itemQuantity = (int) $shopifyUserHasProduct->portfolio->item->total_quantity;
                $requiredQuantity = $shopifyProduct['quantity'];

                if ($itemQuantity >= $requiredQuantity) {
                    $someComplete = true;
                } else {
                    $allComplete = false;
                }

                $this->update($shopifyUserHasProduct->portfolio->item, [
                    'total_quantity' => $itemQuantity - $requiredQuantity
                ]);
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
