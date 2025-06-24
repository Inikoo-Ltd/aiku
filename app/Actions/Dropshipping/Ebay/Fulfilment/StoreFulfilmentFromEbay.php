<?php

/*
 * author Arya Permana - Kirin
 * created on 20-06-2025-09h-12m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Fulfilment;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\PalletReturn\SubmitAndConfirmPalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedEbayAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreFulfilmentFromEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedEbayAddress;

    /**
     * @throws \Throwable
     */
    public function handle(EbayUser $ebayUser, array $modelData): void
    {
        $deliveryAttributes = $this->getAttributes(Arr::get($modelData, 'fulfillmentStartInstructions.0.shippingStep.shipTo'));
        $deliveryAddress = Arr::get($deliveryAttributes, 'address');

        $billingAddress = $ebayUser->customer->address->getFields();

        $customerEmail = Arr::get($deliveryAttributes, 'email');
        $customerClient = $ebayUser->customer?->clients()->where('email', $customerEmail)->first();

        $ebayProducts = collect($modelData['line_items']);

        if (!$customerClient) {
            StoreCustomerClient::make()->action($ebayUser->customerSalesChannel, $deliveryAttributes);
        }

        $ebayUserHasProductExists = $ebayUser->customerSalesChannel->portfolios()
            ->whereIn('platform_product_id', $ebayProducts->pluck('legacyItemId'))->exists();

        if ($ebayUserHasProductExists) {
            $fulfilment = StorePalletReturn::make()->action($ebayUser->customer->fulfilmentCustomer, [
                'type'   => PalletReturnTypeEnum::DROPSHIPPING,
                'platform_id' => $ebayUser->platform_id,
                'delivery_address' => new Address($deliveryAddress),
                'data' => [
                        'destination' => $deliveryAddress->toArray(),
                        'orderId'     => Arr::get($modelData, 'orderId'),
                ],
                'is_collection' => false,
                'customer_sales_channel_id' => $ebayUser->customer_sales_channel_id,
            ], false);

            $storedItems = [];


            foreach ($ebayProducts as $ebayProduct) {
                /** @var Portfolio $ebayUserHasProduct */
                $ebayUserHasProduct = $ebayUser->customerSalesChannel->portfolios()
                    ->where('platform_product_id', $ebayProduct['legacyItemId'])->first(); //legacyItemId is listing id which we can get from publishing offer

                if (!$ebayUserHasProduct) {
                    continue;
                }

                $storedItems[$ebayUserHasProduct->item_id] = [
                    'quantity' => $ebayProduct['quantity']
                ];

                $itemQuantity = (int) $ebayUserHasProduct->item->total_quantity;
                $requiredQuantity = $ebayProduct['quantity'];



                $this->update($ebayUserHasProduct->item, [
                    'total_quantity' => $itemQuantity - $requiredQuantity
                ]);
            }

            if (blank($storedItems)) {
                return ;
            }

            StoreStoredItemsToReturn::make()->action(
                palletReturn: $fulfilment,
                modelData: [
                    'stored_items' => $storedItems,
                ]
            );
            SubmitAndConfirmPalletReturn::make()->action($fulfilment);
        } else {
            Sentry::captureMessage('Some products dont exist');
        }
    }
}
