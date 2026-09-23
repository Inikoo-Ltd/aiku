<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\Shopify\WithShopifyPortfolioMatching;
use App\Actions\Dropshipping\WithSanitizedPhone;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\Traits\WithPayAndSubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreOrderFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedShopifyAddress;
    use WithPayAndSubmitOrder;
    use WithSanitizedPhone;
    use WithShopifyPortfolioMatching;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData): void
    {
        $declinedReason = Arr::get($modelData, 'declined_reason');

        $existOrder = Order::where('platform_order_id', Arr::get($modelData, 'id'))->first();

        if ($existOrder) {
            if (!$existOrder->isDeclinedPlatformRequest()) {
                return;
            }

            if ($declinedReason) {
                $existOrder->update([
                    'public_notes' => $this->getDeclinedPublicNotes($declinedReason),
                    'data'         => array_merge((array) $existOrder->data, ['declined_reason' => $declinedReason]),
                ]);

                return;
            }

            $existOrder->update(['platform_order_id' => null]);
        }

        $attributes      = $this->getShopifyAttributesFromWebhook(Arr::get($modelData, 'customer'), Arr::get($modelData, 'shipping_address', []));
        $deliveryAddress = Arr::get($attributes, 'address');

        if ($declinedReason && !filled(Arr::get($deliveryAddress, 'country_id'))) {
            $deliveryAddress = $this->getFallbackDeliveryAddress($shopifyUser);
        }

        $shopifyProducts = collect(Arr::get($modelData, 'line_items', []));

        $matchedShopifyProducts = [];
        $unmatchedShopifyProducts = [];

        foreach ($shopifyProducts as $shopifyProduct) {
            $portfolio = $this->matchShopifyLineItemToPortfolio(
                $shopifyUser->customerSalesChannel,
                Arr::get($shopifyProduct, 'product_id'),
                Arr::get($shopifyProduct, 'product_variant_id'),
                Arr::get($shopifyProduct, 'sku')
            );

            if ($portfolio) {
                $matchedShopifyProducts[] = [$portfolio, $shopifyProduct];
            } else {
                $unmatchedShopifyProducts[] = $shopifyProduct;
            }
        }

        if ($unmatchedShopifyProducts) {
            Log::info(
                'Shopify order '.Arr::get($modelData, 'id').' of customer sales channel '
                .$shopifyUser->customer_sales_channel_id.' has line items outside the portfolio: '
                .json_encode($unmatchedShopifyProducts)
            );
        }

        if (!$declinedReason && !$matchedShopifyProducts) {
            return;
        }

        $customerClient = $this->digestShopifyCustomerClient($shopifyUser, $modelData, $deliveryAddress);

        if ($declinedReason) {
            StoreOrder::make()->action($customerClient, [
                'platform_id'               => $shopifyUser->platform_id,
                'customer_sales_channel_id' => $shopifyUser->customer_sales_channel_id,
                'date'                      => $modelData['created_at'],
                'delivery_address'          => new Address($deliveryAddress),
                'data'                      => [
                    'shopify_data'    => Arr::except($modelData, 'declined_reason'),
                    'declined_reason' => $declinedReason,
                ],
                'platform_order_id'         => Arr::get($modelData, 'id'),
                'public_notes'              => $this->getDeclinedPublicNotes($declinedReason),
                'state'                     => OrderStateEnum::CANCELLED,
                'cancelled_at'              => now(),
            ]);

            return;
        }

        if ($matchedShopifyProducts) {
            $order = DB::transaction(function () use ($shopifyUser, $customerClient, $modelData, $deliveryAddress, $matchedShopifyProducts) {
                $order = StoreOrder::make()->action($customerClient, [
                    'platform_id'               => $shopifyUser->platform_id,
                    'customer_sales_channel_id' => $shopifyUser->customer_sales_channel_id,
                    'date'                      => $modelData['created_at'],
                    'delivery_address'          => new Address($deliveryAddress),
                    'data'                      => [
                        'shopify_data' => $modelData,
                        'platform_milestones' => [
                            'draft_created_at' => Arr::get($modelData, 'created_at'),
                            'placed_at'        => Arr::get($modelData, 'placed_at'),
                        ]
                    ],
                    'platform_order_id'         => Arr::get($modelData, 'id'),
                ]);

                foreach ($matchedShopifyProducts as [$portfolio, $shopifyProduct]) {
                    /** @var Product $product */
                    $product = $portfolio->item;
                    if (!$product) {
                        \Sentry\captureMessage('Portfolio '.$portfolio->id.' does not have a product');
                        continue;
                    }

                    /** @var HistoricAsset $product */
                    $historicAsset = $product->asset?->historicAsset;
                    if (!$historicAsset) {
                        \Sentry\captureMessage('Portfolio '.$portfolio->id.' does not have a historic asset');
                        continue;
                    }

                    StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $historicAsset,
                        modelData: [
                            'quantity_ordered'        => $shopifyProduct['quantity'],
                            'platform_transaction_id' => $shopifyProduct['id'],
                        ]
                    );
                }

                return $order->refresh();
            });

            $order = $this->payAndSubmitOrder($order);
        }
    }

    /**
     * A fulfilment request Shopify was told we decline still lands in AW, as a cancelled order that
     * says why, so the customer sees it without going to Shopify (HELP-3151). When they fix the
     * problem and request fulfilment again the placeholder gives up the platform order id and the
     * real order takes it.
     *
     */
    protected function getDeclinedPublicNotes(string $declinedReason): string
    {
        return __('Fulfilment request declined: :reason', ['reason' => $declinedReason]).' '
            .__('Fix the order in Shopify and request fulfilment again.');
    }

    /**
     * A declined request may come with no delivery address at all, and both the client and the order
     * insist on one; the customer's own address stands in on the cancelled placeholder.
     */
    protected function getFallbackDeliveryAddress(ShopifyUser $shopifyUser): array
    {
        return Arr::only($shopifyUser->customer->address?->toArray() ?? [], [
            'address_line_1',
            'address_line_2',
            'sorting_code',
            'postal_code',
            'dependent_locality',
            'locality',
            'administrative_area',
            'country_code',
            'country_id',
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function digestShopifyCustomerClient(ShopifyUser $shopifyUser, array $shopifyOrderData, array $deliveryAddress): CustomerClient
    {
        $receiverDetail = Arr::get($shopifyOrderData, 'shipping_address');

        $reference = preg_replace('/\s+/', '', trim(Arr::get($receiverDetail, 'firstName') . Arr::get($receiverDetail, 'lastName') . $shopifyUser->customer_sales_channel_id));
        $customerClientID = DB::table('customer_clients')
            ->select('id')
            ->where('customer_sales_channel_id', $shopifyUser->customer_sales_channel_id)
            ->where('reference', $reference)
            ->first();

        if (!$customerClientID) {
            $customerClient = StoreCustomerClient::make()->action($shopifyUser->customerSalesChannel, [
                'reference'    => $reference,
                'email'        => Arr::get($shopifyOrderData, 'customer.email'),
                'contact_name' => $this->getShopifyContactName($shopifyOrderData),
                'phone'        => $this->sanitizePhone(Arr::get($receiverDetail, 'phone')),
                'address'      => $deliveryAddress,
                'platform_customer_id' => Arr::get($shopifyOrderData, 'customer.id')
            ], strict: false);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($shopifyOrderData, 'customer.email'),
                'contact_name' => $this->getShopifyContactName($shopifyOrderData),
                'phone'        => $this->sanitizePhone(Arr::get($receiverDetail, 'phone')),
                'address'      => $deliveryAddress,
                'platform_customer_id' => Arr::get($shopifyOrderData, 'customer.id')
            ], strict: false);
        }

        return $customerClient;
    }

    protected function getShopifyContactName(array $shopifyOrderData): string
    {
        $receiver = Arr::get($shopifyOrderData, 'shipping_address') ?: Arr::get($shopifyOrderData, 'customer', []);

        return trim(Arr::get($receiver, 'firstName').' '.Arr::get($receiver, 'lastName'));
    }

}
