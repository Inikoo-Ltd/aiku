<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-08h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\WooCommerce\Orders;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\WithSanitizedPhone;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\Traits\WithPayAndSubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedWooCommerceAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreOrderFromWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedWooCommerceAddress;
    use WithPayAndSubmitOrder;
    use WithSanitizedPhone;

    /**
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $wooOrderData): void
    {
        $customerClient = $this->digestWooCustomerClient($wooCommerceUser, $wooOrderData);

        $orderedProducts = $this->digestWooProducts($wooCommerceUser, $wooOrderData);


        $orderData = [
            'customer_client_id'        => $customerClient->id,
            'platform_id'               => $wooCommerceUser->platform_id,
            'customer_sales_channel_id' => $wooCommerceUser->customer_sales_channel_id,
            'customer_reference'        => Arr::get($wooOrderData, 'number'),
            'platform_order_id'         => Arr::get($wooOrderData, 'order_key'),
            'delivery_address'          => $this->digestWooAddress(Arr::get($wooOrderData, 'shipping')),
            'data'                      => ['woo_order' => $wooOrderData]
        ];


        $order = StoreOrder::make()->action($customerClient, $orderData);

        foreach ($orderedProducts as $orderedProduct) {


            $transactionData = [
                'quantity_ordered'        => $orderedProduct['quantity_ordered'],
                'platform_transaction_id' => $orderedProduct['platform_transaction_id'],

            ];


            StoreTransaction::make()->action(
                order: $order,
                historicAsset: $orderedProduct['historicAsset'],
                modelData: $transactionData
            );
        }

        $order = $this->payAndSubmitOrder($order);



    }

    /**
     * @throws \Throwable
     */
    public function digestWooCustomerClient(WooCommerceUser $wooCommerceUser, array $wooOrderData): CustomerClient
    {
        $reference = trim(Arr::get($wooOrderData, 'shipping.first_name').' '.Arr::get($wooOrderData, 'shipping.last_name').' '.Arr::get($wooOrderData, 'shipping.company')) . $wooCommerceUser->customer_sales_channel_id;

        $customerClientID = DB::table('customer_clients')
            ->select('id')
            ->where('customer_sales_channel_id', $wooCommerceUser->customer_sales_channel_id)
            ->where('reference', $reference)
            ->first();

        if (!$customerClientID) {
            $customerClient = StoreCustomerClient::make()->action($wooCommerceUser->customerSalesChannel, [
                'reference'    => $reference,
                'email'        => Arr::get($wooOrderData, 'billing.email'),
                'contact_name' => trim(Arr::get($wooOrderData, 'shipping.first_name').' '.Arr::get($wooOrderData, 'shipping.last_name')),
                'company_name' => Arr::get($wooOrderData, 'shipping.company'),
                'phone'        => $this->sanitizePhone(Arr::get($wooOrderData, 'shipping.phone')),
                'address'      => $this->digestWooAddress(Arr::get($wooOrderData, 'billing'))->toArray(),
            ], strict: false);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($wooOrderData, 'billing.email'),
                'contact_name' => trim(Arr::get($wooOrderData, 'shipping.first_name').' '.Arr::get($wooOrderData, 'shipping.last_name')),
                'company_name' => Arr::get($wooOrderData, 'shipping.company'),
                'phone'        => $this->sanitizePhone(Arr::get($wooOrderData, 'shipping.phone')),
                'address'      => $this->digestWooAddress(Arr::get($wooOrderData, 'billing'))->toArray(),

            ], strict: false);
        }


        return $customerClient;
    }


    public function digestWooAddress($wooOrderData): Address
    {
        $country = Country::where('code', Arr::get($wooOrderData, 'country'))->first();
        if (!$country) {
            $country = Country::where('code', 'GB')->first(); // ¯\_(ツ)_/¯
            Sentry::captureMessage('WooCommerceUserHasCountry >>'.Arr::get($wooOrderData, 'country').'<< country not found, using GB as default when creating CustomerClient. Please check the country code in the order data.');
        }


        $address = [
            'address_line_1'      => Arr::get($wooOrderData, 'address_1'),
            'address_line_2'      => Arr::get($wooOrderData, 'address_2'),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($wooOrderData, 'postcode'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($wooOrderData, 'city'),
            'administrative_area' => Arr::get($wooOrderData, 'state'),
            'country_code'        => $country->code,
            'country_id'          => $country->id
        ];

        return new Address($address);
    }


    /**
     * A line for a variation carries both the variation id and the parent product id. A portfolio
     * matched to the variation wins, one matched to the parent covers every variation of it.
     *
     * @return array<int, string>
     */
    public static function lineItemPlatformProductIds(array $item): array
    {
        return array_values(array_map(
            'strval',
            array_filter([Arr::get($item, 'variation_id'), Arr::get($item, 'product_id')])
        ));
    }

    public function digestWooProducts(WooCommerceUser $wooCommerceUser, array $wooOrderData): array
    {
        $orderedProducts = [];
        foreach (Arr::get($wooOrderData, 'line_items', []) as $item) {
            $platformProductIds = self::lineItemPlatformProductIds($item);

            $portfolioData = DB::table('portfolios')
                ->select('item_id', 'platform_product_id')
                ->where('item_type', 'Product')
                ->where('customer_sales_channel_id', $wooCommerceUser->customer_sales_channel_id)
                ->whereIn('platform_product_id', $platformProductIds)
                ->get()
                ->sortBy(fn ($portfolio) => array_search($portfolio->platform_product_id, $platformProductIds))
                ->first();

            if ($portfolioData && $portfolioData->item_id) {
                $product       = Product::find($portfolioData->item_id);
                $historicAsset = $product?->currentHistoricProduct;
                if ($historicAsset) {
                    $orderedProducts[] = [
                        'historicAsset'           => $historicAsset,
                        'quantity_ordered'        => $item['quantity'],
                        'platform_transaction_id' => $item['id']
                    ];
                } else {
                    Sentry::captureMessage('Woo order item '.$item['id'].' matched portfolio for product '.$portfolioData->item_id.' but could not resolve a '.($product ? 'historic asset' : 'product').', item dropped');
                }
            }
        }

        return $orderedProducts;
    }
}
