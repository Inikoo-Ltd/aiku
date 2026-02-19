<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Retina\Dropshipping\Client\StoreRetinaClientFromPlatformUser;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedTiktokAddress;
use App\Actions\Retina\Dropshipping\Orders\PayOrderAsync;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\TiktokUserHasProduct;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreTiktokOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedTiktokAddress;

    public function handle(TiktokUser $tiktokUser, array $tiktokOrders): void
    {
        $customerClient = $this->digestTiktokCustomerClient($tiktokUser, $tiktokOrders);
        $orderedProducts = $this->digestTiktokProducts($tiktokUser, $tiktokOrders);

        $orderData = [
            'customer_client_id'        => $customerClient->id,
            'platform_id'               => $tiktokUser->platform_id,
            'customer_sales_channel_id' => $tiktokUser->customer_sales_channel_id,
            'customer_reference'        => Arr::get($tiktokOrders, 'user_id'),
            'platform_order_id'         => Arr::get($tiktokOrders, 'id'),
            'delivery_address'          => $this->digestTiktokAddress($tiktokOrders),
            'data'                      => ['tiktok_order' => $tiktokOrders]
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

        try {
            PayOrderAsync::run($order);
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }

        SubmitOrder::run($order);
    }

    /**
     * @throws \Throwable
     */
    public function digestTiktokCustomerClient(TiktokUser $tiktokUser, array $tiktokOrderData): CustomerClient
    {
        $tiktokOrderAddressData = Arr::get($tiktokOrderData, 'recipient_address');
        $reference = trim(Arr::get($tiktokOrderAddressData, 'first_name').' '.Arr::get($tiktokOrderAddressData, 'last_name'));

        $customerClientID = DB::table('customer_clients')
            ->select('id')
            ->where('customer_sales_channel_id', $tiktokUser->customer_sales_channel_id)
            ->where('reference', $reference)
            ->first();

        if (!$customerClientID) {
            $customerClient = StoreCustomerClient::make()->action($tiktokUser->customerSalesChannel, [
                'reference'    => $reference,
                'email'        => Arr::get($tiktokOrderData, 'buyer_email'),
                'contact_name' => trim(Arr::get($tiktokOrderAddressData, 'first_name').' '.Arr::get($tiktokOrderAddressData, 'last_name')),
                'company_name' => Arr::get($tiktokOrderAddressData, 'name'),
                'phone'        => null,
                'address'      => $this->digestTiktokAddress($tiktokOrderData)->toArray()
            ]);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($tiktokOrderData, 'buyer_email'),
                'contact_name' => trim(Arr::get($tiktokOrderAddressData, 'first_name').' '.Arr::get($tiktokOrderAddressData, 'last_name')),
                'company_name' => Arr::get($tiktokOrderAddressData, 'name'),
                'phone'        => null,
                'address'      => $this->digestTiktokAddress($tiktokOrderData)->toArray()
            ]);
        }

        return $customerClient;
    }

    public function digestTiktokAddress($tiktokOrderData): Address
    {
        $tiktokOrderAddressData = Arr::get($tiktokOrderData, 'recipient_address');
        $country = Country::where('code', Arr::get($tiktokOrderAddressData, 'region_code'))->first();
        if (!$country) {
            $country = Country::where('code', 'GB')->first(); // ¯\_(ツ)_/¯
            Sentry::captureMessage('TiktokUserHasCountry >>'.Arr::get($tiktokOrderData, 'country').'<< country not found, using GB as default when creating CustomerClient. Please check the country code in the order data.');
        }

        $address = [
            'address_line_1'      => Arr::get($tiktokOrderAddressData, 'address_line1'),
            'address_line_2'      => Arr::get($tiktokOrderAddressData, 'address_line2'),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($tiktokOrderAddressData, 'postal_code'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($tiktokOrderAddressData, 'post_town'),
            'administrative_area' => Arr::get($tiktokOrderAddressData, 'state'),
            'country_code'        => $country->code,
            'country_id'          => $country->id
        ];

        return new Address($address);
    }

    public function digestTiktokProducts(TiktokUser $tiktokUser, array $tiktokOrderData): array
    {
        $orderedProducts = [];
        foreach (Arr::get($tiktokOrderData, 'line_items', []) as $item) {
            $portfolioData = DB::table('portfolios')->select('item_id')->where('item_type', 'Product')
                ->where('customer_sales_channel_id', $tiktokUser->customer_sales_channel_id)
                ->where('platform_product_id', $item['product_id'])
                ->first();
            if ($portfolioData && $portfolioData->item_id) {
                $product = Product::find($portfolioData->item_id);
                if ($product) {
                    $orderedProducts[] = [
                        'historicAsset'           => $product->currentHistoricProduct,
                        'quantity_ordered'        => 1,
                        'platform_transaction_id' => $item['id']
                    ];
                }
            }
        }

        return $orderedProducts;
    }
}
