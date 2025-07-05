<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-09h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedEbayAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\EbayUser;
use App\Models\Helpers\Address;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreOrderFromEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedEbayAddress;

    /**
     * @throws \Throwable
     */
    public function handle(EbayUser $ebayUser, array $ebayOrderData): void
    {
        $modelData = $this->digestEbayOrderData($ebayUser, $ebayOrderData);


        $order = StoreOrder::make()->action($ebayUser->customer, [
            'customer_reference'        => Arr::get($ebayOrderData, 'orderId'),
            'customer_client_id'        => $modelData['customer_client_id'],
            'platform_id'               => $ebayUser->platform_id,
            'customer_sales_channel_id' => $ebayUser->customer_sales_channel_id,
            'delivery_address'          => $modelData['delivery_address'],
            'billing_address'           => $modelData['billing_address'],
            'platform_order_id'         => Arr::get($ebayOrderData, 'orderId'),
            'data'                      => [
                'ebay_order' => $ebayOrderData
            ]
        ], false);

        foreach ($modelData['ordered_products'] as $orderedProduct) {
            StoreTransaction::make()->action(
                order: $order,
                historicAsset: $orderedProduct['historicAsset'],
                modelData: [
                    'quantity_ordered'        => $orderedProduct['quantity_ordered'],
                    'platform_transaction_id' => $orderedProduct['platform_transaction_id'],

                ]
            );
        }


        SubmitOrder::run($order);
    }

    /**
     * @throws \Throwable
     */
    public function digestEbayOrderData(EbayUser $ebayUser, array $ebayOrderData): array
    {
        $deliveryAttributes = $this->getContactAttributes(Arr::get($ebayOrderData, 'fulfillmentStartInstructions.0.shippingStep.shipTo'));
        $deliveryAddress    = Arr::get($deliveryAttributes, 'address');
        $billingAddress     = $ebayUser->customer->address->getFields();

        $customerEmail  = Arr::get($deliveryAttributes, 'email');
        $customerClient = $ebayUser->customer->clients()->where('email', $customerEmail)->first();
        if (!$customerClient) {
            $customerClient = StoreCustomerClient::make()->action($ebayUser->customerSalesChannel, $deliveryAttributes);
        }




        $orderedProducts = [];


        foreach (Arr::get($ebayOrderData, 'lineItems', []) as $lineItem) {


            $portfolioData = DB::table('portfolios')->select('item_id')->where('item_type', 'Product')->where('customer_sales_channel_id', $ebayUser->customer_sales_channel_id)
                ->where('platform_product_id', $lineItem['legacyItemId'])->first();
            if ($portfolioData && $portfolioData->item_id) {
                $product = Product::find($portfolioData->item_id);
                if ($product) {
                    $orderedProducts[] = [
                        'historicAsset'           => $product->currentHistoricProduct,
                        'quantity_ordered'        => $lineItem['quantity'],
                        'platform_transaction_id' => $lineItem['lineItemId']
                    ];
                }
            }
        }


        return [
            'delivery_address'   => new Address($deliveryAddress),
            'billing_address'    => new Address($billingAddress),
            'customer_client_id' => $customerClient->id,
            'ordered_products'   => $orderedProducts
        ];
    }

}
