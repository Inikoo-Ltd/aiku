<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-09h-17m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\WooCommerce\Fulfilment;

use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitAndConfirmPalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedWooCommerceAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Enums\Dropshipping\ShopifyFulfilmentReasonEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreFulfilmentFromWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedWooCommerceAddress;

    public function handle(WooCommerceUser $wooCommerceUser, array $modelData): bool
    {
        // dd($modelData);
        return DB::transaction(function () use ($wooCommerceUser, $modelData) {
            $products = collect($modelData['line_items']);

            $deliveryAttributes = $this->getAttributes(Arr::get($modelData, 'shipping'));
            $deliveryAddress = Arr::get($deliveryAttributes, 'address');

            $billingAttributes = $this->getAttributes(Arr::get($modelData, 'billing'));
            $billingAddress = Arr::get($billingAttributes, 'address');

            $deliveryAddress = new Address($deliveryAddress);

            $palletReturn = StorePalletReturn::make()->actionWithDropshipping($wooCommerceUser->customer->fulfilmentCustomer, [
                'type' => PalletReturnTypeEnum::DROPSHIPPING,
                'platform_id' => Platform::where('type', PlatformTypeEnum::WOOCOMMERCE->value)->first()->id,
                'delivery_address' => $deliveryAddress,
                'data' => [
                    'wooCommerce_fulfilment_id' => Arr::get($modelData, 'id'),
                    'destination' => $deliveryAddress->toArray(),
                    'wooCommerce_user_id' => $wooCommerceUser->id,
                    'order_key' => Arr::get($modelData, 'order_key'),
                ],
                'is_collection' => false,
                'customer_sales_channel_id' => $wooCommerceUser->customerSalesChannel->id
            ]);

            $storedItems = [];
            $allComplete = true;
            $someComplete = false;

            foreach ($products as $product) {
                $wooCommerceUserHasProduct = $wooCommerceUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $product['product_id'])->first();

                if (!$wooCommerceUserHasProduct) {
                    continue;
                }

                $storedItems[$wooCommerceUserHasProduct->item_id] = [
                    'quantity' => $product['quantity']
                ];

                $itemQuantity = (int) $wooCommerceUserHasProduct->item->total_quantity;
                $requiredQuantity = $product['quantity'];

                if ($itemQuantity >= $requiredQuantity) {
                    $someComplete = true;
                } else {
                    $allComplete = false;
                }

                $this->update($wooCommerceUserHasProduct->item, [
                    'total_quantity' => $itemQuantity - $requiredQuantity
                ]);
            }

            if (blank($storedItems)) {
                return false;
            }

            // $reasons = [
            //     'no_fulfilment_reason' => ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK,
            //     'no_fulfilment_reason_notes' => ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->notes()[ShopifyFulfilmentReasonEnum::INVENTORY_OUT_OF_STOCK->value],
            // ];

            // if ($allComplete) {
            //     $status = ChannelFulfilmentStateEnum::OPEN;
            //     $reasons = [];
            // } elseif ($someComplete) {
            //     $status = ChannelFulfilmentStateEnum::HOLD;
            // } else {
            //     $status = ChannelFulfilmentStateEnum::INCOMPLETE;
            // }

            StoreStoredItemsToReturn::make()->action($palletReturn, [
                'stored_items' => $storedItems
            ]);


            SubmitAndConfirmPalletReturn::make()->action($palletReturn);

            return true;
        });
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request): void
    {
        $this->initialisation($wooCommerceUser->organisation, $request);

        $this->handle($wooCommerceUser, $request->all());
    }
}
