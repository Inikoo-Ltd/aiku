<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\Traits\WithPayAndSubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WixUser;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreWixOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithPayAndSubmitOrder;

    /**
     * Where an order is going, in the order the sources are worth trusting.
     *
     * `recipientInfo` is who ultimately receives the order, which is what we deliver to.
     * `shippingInfo.logistics.shippingDestination` is the address the shipping method points
     * at, so it is the pickup point rather than the buyer whenever a pickup method was chosen,
     * and Wix leaves it out entirely on orders carrying no shipping method. Billing is the last
     * resort, being the payer rather than the recipient.
     */
    private const array DESTINATION_PATHS = [
        'recipientInfo',
        'shippingInfo.logistics.shippingDestination',
        'billingInfo',
    ];

    /**
     * @throws \Throwable
     */
    public function handle(WixUser $wixUser, array $wixOrder): void
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $wixUser->customerSalesChannel;

        $customerClient  = $this->digestWixCustomerClient($wixUser, $wixOrder);
        $orderedProducts = $this->digestWixProducts($wixUser, $wixOrder);

        $orderData = [
            'customer_client_id'        => $customerClient->id,
            'is_shipping_by_external'   => false,
            'platform_id'               => $customerSalesChannel->platform_id,
            'customer_sales_channel_id' => $wixUser->customer_sales_channel_id,
            'platform_order_id'         => Arr::get($wixOrder, 'id'),
            'customer_notes'            => Arr::get($wixOrder, 'buyerNote'),
            'delivery_address'          => $this->digestWixAddress($wixOrder),
            'data'                      => ['wix_order' => $wixOrder]
        ];

        $order = StoreOrder::make()->action($customerClient, $orderData);

        foreach ($orderedProducts as $orderedProduct) {
            StoreTransaction::make()->action(
                order: $order,
                historicAsset: $orderedProduct['historicAsset'],
                modelData: [
                    'quantity_ordered'        => $orderedProduct['quantity_ordered'],
                    'platform_transaction_id' => $orderedProduct['platform_transaction_id'],
                ]
            );
        }

        $this->payAndSubmitOrder($order);
    }

    /**
     * @throws \Throwable
     */
    public function digestWixCustomerClient(WixUser $wixUser, array $wixOrder): CustomerClient
    {
        $contact   = $this->digestWixContactDetails($wixOrder);
        $email     = Arr::get($wixOrder, 'buyerInfo.email');
        $name      = trim(Arr::get($contact, 'firstName', '').' '.Arr::get($contact, 'lastName', ''));
        $reference = trim($name.' '.$email);

        $clientData = [
            'email'        => $email,
            'contact_name' => $name,
            'company_name' => Arr::get($contact, 'company'),
            'phone'        => Arr::get($contact, 'phone'),
            'address'      => $this->digestWixAddress($wixOrder)->toArray()
        ];

        $customerClientID = DB::table('customer_clients')
            ->select('id')
            ->where('customer_sales_channel_id', $wixUser->customer_sales_channel_id)
            ->where('reference', $reference)
            ->first();

        if (!$customerClientID) {
            return StoreCustomerClient::make()->action(
                $wixUser->customerSalesChannel,
                array_merge(['reference' => $reference], $clientData)
            );
        }

        return UpdateCustomerClient::make()->action(CustomerClient::find($customerClientID->id), $clientData);
    }

    public function digestWixAddress(array $wixOrder): Address
    {
        $wixAddress = $this->digestWixDestination($wixOrder, 'address');

        $country = Country::where('code', Arr::get($wixAddress, 'country'))->first();

        if (!$country) {
            $country = Country::where('code', 'GB')->first();
        }

        return new Address([
            'address_line_1'      => Arr::get($wixAddress, 'addressLine') ?: $this->digestWixStreetAddress($wixAddress),
            'address_line_2'      => Arr::get($wixAddress, 'addressLine2') ?: Arr::get($wixAddress, 'streetAddress.apt'),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($wixAddress, 'postalCode'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($wixAddress, 'city'),
            'administrative_area' => Arr::get($wixAddress, 'subdivision'),
            'country_code'        => $country->code,
            'country_id'          => $country->id
        ]);
    }

    public function digestWixContactDetails(array $wixOrder): array
    {
        return $this->digestWixDestination($wixOrder, 'contactDetails');
    }

    private function digestWixDestination(array $wixOrder, string $key): array
    {
        foreach (self::DESTINATION_PATHS as $path) {
            $value = Arr::get($wixOrder, $path.'.'.$key);

            if (is_array($value) && filled($value)) {
                return $value;
            }
        }

        return [];
    }

    /**
     * Wix splits a street into its parts when the buyer picked the address from its autocomplete
     * rather than typing it, and a delivery without the house number is a delivery that fails.
     */
    private function digestWixStreetAddress(array $wixAddress): ?string
    {
        $street = trim(
            Arr::get($wixAddress, 'streetAddress.number', '').' '.Arr::get($wixAddress, 'streetAddress.name', '')
        );

        return $street ?: null;
    }

    public function digestWixProducts(WixUser $wixUser, array $wixOrder): array
    {
        $orderedProducts = [];

        foreach (Arr::get($wixOrder, 'lineItems', []) as $item) {
            $catalogItemId = Arr::get($item, 'catalogReference.catalogItemId');

            if (!$catalogItemId) {
                continue;
            }

            $portfolioData = DB::table('portfolios')
                ->select('item_id')
                ->where('item_type', 'Product')
                ->where('customer_sales_channel_id', $wixUser->customer_sales_channel_id)
                ->where('platform_product_id', $catalogItemId)
                ->first();

            if (!$portfolioData || !$portfolioData->item_id) {
                continue;
            }

            $product = Product::find($portfolioData->item_id);

            if (!$product) {
                continue;
            }

            $orderedProducts[] = [
                'historicAsset'           => $product->currentHistoricProduct,
                'quantity_ordered'        => Arr::get($item, 'quantity'),
                'platform_transaction_id' => Arr::get($item, 'id')
            ];
        }

        return $orderedProducts;
    }
}
