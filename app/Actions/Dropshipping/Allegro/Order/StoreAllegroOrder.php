<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\Traits\WithPayAndSubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedTiktokAddress;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreAllegroOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedTiktokAddress;
    use WithPayAndSubmitOrder;

    /**
     * @throws \Throwable
     */
    public function handle(AllegroUser $allegroUser, array $allegroOrders): void
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $allegroUser->customerSalesChannel;

        $customerClient = $this->digestAllegroCustomerClient($allegroUser, $allegroOrders);
        $orderedProducts = $this->digestAllegroProducts($allegroUser, $allegroOrders);

        $orderData = [
            'customer_client_id'        => $customerClient->id,
            'is_shipping_by_external'   => true,
            'platform_id'               => $customerSalesChannel->platform_id,
            'customer_sales_channel_id' => $allegroUser->customer_sales_channel_id,
            'platform_order_id'         => Arr::get($allegroOrders, 'id'),
            'customer_notes'            => Arr::get($allegroOrders, 'note.text'),
            'delivery_address'          => $this->digestAllegroAddress($allegroOrders),
            'data'                      => ['allegro_order' => $allegroOrders]
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

        $this->payAndSubmitOrder($order);
    }

    /**
     * @throws \Throwable
     */
    public function digestAllegroCustomerClient(AllegroUser $allegroUser, array $allegroOrderData): CustomerClient
    {
        $allegroOrderAddressData = Arr::get($allegroOrderData, 'delivery.address');
        $reference = trim(Arr::get($allegroOrderAddressData, 'firstName').' '.Arr::get($allegroOrderAddressData, 'lastName') . ' ' . Arr::get($allegroOrderData, 'buyer.login'));

        $customerClientID = DB::table('customer_clients')
            ->select('id')
            ->where('customer_sales_channel_id', $allegroUser->customer_sales_channel_id)
            ->where('reference', $reference)
            ->first();

        if (!$customerClientID) {
            $customerClient = StoreCustomerClient::make()->action($allegroUser->customerSalesChannel, [
                'reference'    => $reference,
                'email'        => Arr::get($allegroOrderData, 'buyer.email'),
                'contact_name' => trim(Arr::get($allegroOrderAddressData, 'firstName').' '.Arr::get($allegroOrderAddressData, 'lastName')),
                'company_name' => Arr::get($allegroOrderAddressData, 'companyName'),
                'phone'        => Arr::get($allegroOrderAddressData, 'phoneNumber'),
                'address'      => $this->digestAllegroAddress($allegroOrderData)->toArray()
            ]);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($allegroOrderData, 'buyer.email'),
                'contact_name' => trim(Arr::get($allegroOrderAddressData, 'firstName').' '.Arr::get($allegroOrderAddressData, 'lastName')),
                'company_name' => Arr::get($allegroOrderAddressData, 'companyName'),
                'phone'        => Arr::get($allegroOrderAddressData, 'phoneNumber'),
                'address'      => $this->digestAllegroAddress($allegroOrderData)->toArray()
            ]);
        }

        return $customerClient;
    }

    public function digestAllegroAddress($allegroOrderData): Address
    {
        $allegroOrderAddressData = Arr::get($allegroOrderData, 'delivery.address');
        $country = Country::where('code', Arr::get($allegroOrderAddressData, 'countryCode'))->first();
        if (!$country) {
            $country = Country::where('code', 'GB')->first();
        }

        $address = [
            'address_line_1'      => Arr::get($allegroOrderAddressData, 'street'),
            'address_line_2'      => null,
            'sorting_code'        => null,
            'postal_code'         => Arr::get($allegroOrderAddressData, 'zipCode'),
            'dependent_locality'  => null,
            'locality'            => null,
            'administrative_area' => Arr::get($allegroOrderAddressData, 'city'),
            'country_code'        => $country->code,
            'country_id'          => $country->id
        ];

        return new Address($address);
    }

    public function digestAllegroProducts(AllegroUser $allegroUser, array $allegroOrderData): array
    {
        $orderedProducts = [];
        foreach (Arr::get($allegroOrderData, 'lineItems', []) as $item) {
            $portfolioData = DB::table('portfolios')->select('item_id')->where('item_type', 'Product')
                ->where('customer_sales_channel_id', $allegroUser->customer_sales_channel_id)
                ->where('platform_product_id', Arr::get($item, 'offer.id'))
                ->first();
            if ($portfolioData && $portfolioData->item_id) {
                $product = Product::find($portfolioData->item_id);
                if ($product) {
                    $orderedProducts[] = [
                        'historicAsset'           => $product->currentHistoricProduct,
                        'quantity_ordered'        => Arr::get($item, 'quantity'),
                        'platform_transaction_id' => $item['id']
                    ];
                }
            }
        }

        return $orderedProducts;
    }
}
