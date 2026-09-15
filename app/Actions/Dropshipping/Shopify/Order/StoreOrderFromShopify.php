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
        $existOrder = Order::where('platform_order_id', Arr::get($modelData, 'id'))->first();

        if ($existOrder) {
            return;
        }

        $attributes = $this->getShopifyAttributesFromWebhook(
            Arr::get($modelData, 'customer'),
            Arr::get($modelData, 'shipping_address', []) // AIKU-Z20: fallback empty array
        );

        $shopifyDeliveryAddress = Arr::get($attributes, 'address');
        $hasDeliveryAddress     = filled(Arr::get($shopifyDeliveryAddress, 'country_id'));
        $deliveryAddress        = $hasDeliveryAddress
            ? $shopifyDeliveryAddress
            : $this->getFallbackDeliveryAddress($shopifyUser);

        $customerClient  = $this->digestShopifyCustomerClient($shopifyUser, $modelData, $deliveryAddress);
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

        $notes = $this->getImportProblemNotes($matchedShopifyProducts, $unmatchedShopifyProducts, $hasDeliveryAddress);

        $order = DB::transaction(function () use ($shopifyUser, $customerClient, $modelData, $deliveryAddress, $matchedShopifyProducts, $notes) {
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
                ...$notes,
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

        $this->payAndSubmitOrder($order);
    }

    /**
     * Shopify used to be told to keep an order we could not fulfil, which left the customer with no
     * order in AW and nothing to explain the silence (HELP-3151). The order is imported regardless
     * now, so what is wrong with it has to be written down: the detail privately for the office,
     * a plain sentence publicly for the customer.
     *
     * @param  array<int, array<string, mixed>>  $matchedShopifyProducts
     * @param  array<int, array<string, mixed>>  $unmatchedShopifyProducts
     * @return array{internal_notes?: string, public_notes?: string}
     */
    protected function getImportProblemNotes(array $matchedShopifyProducts, array $unmatchedShopifyProducts, bool $hasDeliveryAddress): array
    {
        $internal = [];
        $public   = [];

        if (!$matchedShopifyProducts && !$unmatchedShopifyProducts) {
            $internal[] = __('Shopify sent no line item with anything left to fulfil.');
            $public[]   = __('This order has nothing left to fulfil.');
        }

        if ($unmatchedShopifyProducts) {
            $internal[] = __('Not in the portfolio of this sales channel, so left off the order:').' '
                .collect($unmatchedShopifyProducts)->map(fn (array $product) => $this->describeShopifyLineItem($product))->join('; ');

            $public[] = trans_choice(
                '{1} :count item on this order is not in your portfolio, so we cannot fulfil it.'
                .'|[2,*] :count items on this order are not in your portfolio, so we cannot fulfil them.',
                count($unmatchedShopifyProducts)
            );
        }

        if (!$hasDeliveryAddress) {
            $internal[] = __('Shopify sent no delivery address, the address of the customer is used instead.');
            $public[]   = __('This order arrived without a delivery address, so your own address is being used. Please change if necessary');
        }

        if (!$internal) {
            return [];
        }

        array_unshift($internal, __('Imported from Shopify with problems:'));

        return [
            'internal_notes' => implode("\n", $internal),
            'public_notes'   => implode(' ', $public).' '.__('Please check it before it is dispatched.'),
        ];
    }

    protected function describeShopifyLineItem(array $shopifyProduct): string
    {
        $description = Arr::get($shopifyProduct, 'title') ?: __('untitled item');

        foreach (['sku' => 'SKU', 'product_id' => 'product', 'product_variant_id' => 'variant'] as $key => $label) {
            if ($value = Arr::get($shopifyProduct, $key)) {
                $description .= ' ('.$label.' '.$value.')';
            }
        }

        return $description;
    }

    /**
     * An order with no delivery address cannot be stored at all, because both the client and the
     * order insist on a country. The address of the customer stands in so the order still lands in
     * AW, and the note beside it says the address is not the one Shopify sent.
     */
    protected function getFallbackDeliveryAddress(ShopifyUser $shopifyUser): array
    {
        $address = $shopifyUser->customer->address;

        return Arr::only($address ? $address->toArray() : [], [
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
                'contact_name' => Arr::get($receiverDetail, 'firstName').' '.Arr::get($receiverDetail, 'lastName'),
                'phone'        => $this->sanitizePhone(Arr::get($receiverDetail, 'phone')),
                'address'      => $deliveryAddress,
                'platform_customer_id' => Arr::get($shopifyOrderData, 'customer.id')
            ], strict: false);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($shopifyOrderData, 'customer.email'),
                'contact_name' => Arr::get($receiverDetail, 'firstName').' '.Arr::get($receiverDetail, 'lastName'),
                'phone'        => $this->sanitizePhone(Arr::get($receiverDetail, 'phone')),
                'address'      => $deliveryAddress,
                'platform_customer_id' => Arr::get($shopifyOrderData, 'customer.id')
            ], strict: false);
        }

        return $customerClient;
    }

}
