<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-09h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Magento\Orders;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedMagentoAddress;
use App\Actions\Retina\Dropshipping\Orders\PayOrderAsync;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class StoreOrderFromMagento extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedMagentoAddress;

    /**
     * @throws \Throwable
     */
    public function handle(MagentoUser $magentoUser, array $modelData): void
    {
        $shippingRawAddress = Arr::get($modelData, 'extension_attributes.shipping_assignments.0.shipping.address');

        $customerClient = $this->digestMagentoCustomerClient($magentoUser, $modelData);
        $orderedProducts = $this->digestMagentoProducts($magentoUser, $modelData);

        $order = StoreOrder::make()->action($customerClient, [
            'platform_id' => $magentoUser->platform_id,
            'customer_reference'        => Arr::get($modelData, 'ext_order_id', ''),
            'customer_sales_channel_id' => $magentoUser->customer_sales_channel_id,
            'date' => $modelData['created_at'],
            'delivery_address' => $this->digestMagentoAddress($shippingRawAddress),
            'platform_order_id'         => Arr::get($modelData, 'entity_id'),
            'data' => [
                'magento_order' => $modelData
            ]
        ]);

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
        } catch (Exception $e) {
            Sentry::captureException($e);
        }
        SubmitOrder::run($order);

    }

    /**
     * @throws \Throwable
     */
    public function digestMagentoCustomerClient(MagentoUser $magentoUser, array $magentoOrderData): CustomerClient
    {
        $reference = Arr::get($magentoOrderData, 'customer_id');
        if (!$reference || $reference == 0 || $reference == '0') {
            $reference = Arr::get($magentoOrderData, 'billing_address.email');
            if (!$reference) {
                $reference = Arr::get($magentoOrderData, 'billing_address.telephone');
            }
            if (!$reference) {
                $reference = trim(Arr::get($magentoOrderData, 'billing_address.firstname').' '.Arr::get($magentoOrderData, 'billing_address.lastname').' '.Arr::get($magentoOrderData, 'billing_address.company'));
            }
            if (!$reference) {
                $reference = Str::random();
            }
        }

        $reference = (string)$reference;

        $customerClientID = DB::table('customer_clients')
            ->select('id')
            ->where('customer_sales_channel_id', $magentoUser->customer_sales_channel_id)
            ->where('reference', $reference)
            ->first();

        if (!$customerClientID) {
            $customerClient = StoreCustomerClient::make()->action($magentoUser->customerSalesChannel, [
                'reference'    => $reference,
                'email'        => Arr::get($magentoOrderData, 'billing_address.email'),
                'contact_name' => trim(Arr::get($magentoOrderData, 'billing.firstname').' '.Arr::get($magentoOrderData, 'billing.lastname')),
                'company_name' => Arr::get($magentoOrderData, 'billing_address.company'),
                'phone'        => Arr::get($magentoOrderData, 'billing_address.telephone'),
                'address'      => $this->digestMagentoAddress(Arr::get($magentoOrderData, 'billing_address'))->toArray(),
            ]);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($magentoOrderData, 'billing_address.email'),
                'contact_name' => trim(Arr::get($magentoOrderData, 'billing.firstname').' '.Arr::get($magentoOrderData, 'billing.lastname')),
                'company_name' => Arr::get($magentoOrderData, 'billing_address.company'),
                'phone'        => Arr::get($magentoOrderData, 'billing_address.telephone'),
                'address'      => $this->digestMagentoAddress(Arr::get($magentoOrderData, 'billing_address'))->toArray(),

            ]);
        }

        return $customerClient;
    }

    public function digestMagentoAddress($magentoOrderData): Address
    {
        $country = Country::where('code', Arr::get($magentoOrderData, 'country_id'))->first();
        if (!$country) {
            $country = Country::where('code', 'GB')->first(); // ¯\_(ツ)_/¯
            Sentry::captureMessage('MagentoUserHasCountry >>'.Arr::get($magentoOrderData, 'country_id').'<< country not found, using GB as default when creating CustomerClient. Please check the country code in the order data.');
        }


        $address = [
            'address_line_1'      => Arr::get($magentoOrderData, 'street.0'),
            'address_line_2'      => Arr::get($magentoOrderData, 'street.1'),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($magentoOrderData, 'postcode'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($magentoOrderData, 'city'),
            'administrative_area' => Arr::get($magentoOrderData, 'state'),
            'country_id'          => $country->id
        ];

        return new Address($address);
    }

    public function digestMagentoProducts(MagentoUser $magentoUser, array $magentoOrderData): array
    {
        $orderedProducts = [];
        foreach (Arr::get($magentoOrderData, 'items', []) as $item) {
            $portfolioData = DB::table('portfolios')->select('item_id')
                ->where('item_type', 'Product')
                ->where('customer_sales_channel_id', $magentoUser->customer_sales_channel_id)
                ->where('platform_product_id', $item['product_id'])->first();
            if ($portfolioData && $portfolioData->item_id) {
                $product = Product::find($portfolioData->item_id);
                if ($product) {
                    $orderedProducts[] = [
                        'historicAsset'           => $product->currentHistoricProduct,
                        'quantity_ordered'        => $item['qty_ordered'],
                        'platform_transaction_id' => $item['item_id']
                    ];
                }
            }
        }

        return $orderedProducts;
    }
}
