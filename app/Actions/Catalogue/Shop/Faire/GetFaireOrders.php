<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GetFaireOrders extends OrgAction
{
    public string $commandSignature = 'faire:orders';

    public function handle(Shop $shop): void
    {
        DB::transaction(function () use ($shop) {
            $orders = $shop->getFaireOrders([
                //'excluded_states' => 'DELIVERED,BACKORDERED,CANCELED,PROCESSING,PRE_TRANSIT,IN_TRANSIT,PENDING_RETAILER_CONFIRMATION'
            ]);

            foreach (Arr::get($orders, 'orders', []) as $faireOrder) {
                $retailerId = Arr::get($faireOrder, 'retailer_id');
                $retailer = GetFaireRetailers::run($shop, $retailerId);

                if ($retailer) {
                    data_set($retailer, 'contact_name', Arr::get($retailer, 'name'));
                    data_set($retailer, 'company_name', Arr::get($retailer, 'name'));
                    data_set($retailer, 'external_id', $retailerId);

                    $customer = Customer::where('shop_id', $shop->id)
                        ->where('external_id', $retailerId)
                        ->first();

                    if (!$customer) {
                        data_set($retailer, 'delivery_address', $this->getFormattedAddress(Arr::get($faireOrder, 'address')));
                        data_set($retailer, 'contact_address', $this->getFormattedAddress(Arr::get($faireOrder, 'address')));

                        $customer = StoreCustomer::make()->action($shop, $retailer);
                    }

                    data_set($faireOrder, 'external_id', Arr::get($faireOrder, 'id'));
                    $awOrder = StoreOrder::make()->action($customer, Arr::only($faireOrder, ['delivery_address', 'billing_address', 'external_id']));

                    foreach (Arr::get($faireOrder, 'items', []) as $item) {
                        $product = Product::where('shop_id', $shop->id)
                            ->where('sku', $item['sku'])
                            ->first();
                        $historicAsset = $product->asset?->historicAsset;

                        if(! $historicAsset) {
                            continue;
                        }

                        StoreTransaction::make()->action(
                            order: $awOrder,
                            historicAsset: $historicAsset,
                            modelData: [
                                'quantity_ordered'        => $item['quantity'],
                                'external_id' => $item['id'],
                            ]
                        );
                    }
                }
            }
        });
    }

    public function getFormattedAddress(array $address): array
    {
        $country = Country::where('iso3', Arr::get($address, 'country_code'))->first();

        return [
            'address_line_1' => Arr::get($address, 'address1', ''),
            'sorting_code' => null,
            'postal_code' => Arr::get($address, 'postal_code'),
            'dependent_locality' => null,
            'locality' => Arr::get($address, 'city'),
            'administrative_area' => Arr::get($address, 'state'),
            'country_code' => $country->code,
            'country_id' => $country->id
        ];
    }

    public function asCommand(): void
    {
        $shop = Shop::where('type', ShopTypeEnum::FAIRE)
            ->where('state', ShopStateEnum::OPEN)
            ->first();

        $this->handle($shop);
    }
}
