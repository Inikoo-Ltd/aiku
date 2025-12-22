<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;

class GetFaireOrders extends OrgAction
{
    public string $commandSignature = 'faire:orders';

    public function handle(Shop $shop): void
    {
        $orders = $shop->getFaireOrders([
            //'excluded_states' => 'DELIVERED,BACKORDERED,CANCELED,PROCESSING,PRE_TRANSIT,IN_TRANSIT,PENDING_RETAILER_CONFIRMATION'
        ]);

        foreach (Arr::get($orders, 'orders', []) as $order) {
            $retailerId = Arr::get($order, 'retailer_id');
            $retailer = GetFaireRetailers::run($shop, $retailerId);

            if($retailer) {
                data_set($retailer, 'contact_name', Arr::get($retailer, 'name'));
                data_set($retailer, 'external_id', $retailerId);

                $customer = Customer::where('shop_id', $shop->id)
                    ->where('external_id', $retailerId)
                    ->first();
                if(! $customer) {
                    $customer = StoreCustomer::make()->action($shop, $retailer);
                }

                StoreOrder::make()->action($customer, $order);
            }
        }
    }

    public function asCommand(): void
    {
        $shop = Shop::where('type', ShopTypeEnum::FAIRE)
            ->where('state', ShopStateEnum::OPEN)
            ->first();

        $this->handle($shop);
    }
}
