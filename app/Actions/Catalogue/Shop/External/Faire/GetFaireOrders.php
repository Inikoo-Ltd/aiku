<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class GetFaireOrders extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop): void
    {
        $filters = [
            'created_at_min' => Carbon::parse('2026-02-01')->toIsoString(),
        ];

        $orders = $shop->getFaireOrders([
            'excluded_states' => 'PROCESSING,PRE_TRANSIT,IN_TRANSIT,DELIVERED,PENDING_RETAILER_CONFIRMATION,BACKORDERED,CANCELED',
            ...$filters
        ]);


        foreach (Arr::get($orders, 'orders', []) as $faireOrder) {
            $externalId = Arr::get($faireOrder, 'id');
            $retailerId = Arr::get($faireOrder, 'retailer_id');
            $retailer   = GetFaireRetailers::run($shop, $retailerId);

            $transactionCommissions = Arr::get($faireOrder, 'payout_costs.commission_bps', 0) / 10000;
            $orderCommission        = Arr::get($faireOrder, 'payout_costs.commission.amount_minor', 0) / 100;

            $orderExists = Order::where('shop_id', $shop->id)->where('external_id', $externalId)->exists();

            if ($orderExists) {
                continue;
            }

            if ($retailer) {
                $address = $this->getFormattedAddress(Arr::get($faireOrder, 'address'));

                $customer = Customer::where('shop_id', $shop->id)->where('external_id', $retailerId)->first();

                $contactName = trim(Arr::get($faireOrder, 'customer.first_name', '').' '.Arr::get($faireOrder, 'customer.last_name', ''));
                $phone       = Arr::get($faireOrder, 'address.phone_number');

                $customerData = [
                    'company_name'    => (Arr::get($retailer, 'name')),
                    'contact_name'    => $contactName,
                    'external_id'     => $retailerId,
                    'reference'       => $retailerId,
                    'contact_address' => $address,

                ];
                if ($contactName != '') {
                    $customerData['contact_name'] = $contactName;
                }
                if ($phone != '') {
                    $customerData['phone'] = $contactName;
                }

                if ($customer) {
                    $customer = UpdateCustomer::make()->action(customer: $customer, modelData: $customerData, strict: false);
                } else {
                    $customer = StoreCustomer::make()->action(shop: $shop, modelData: $customerData, strict: false);
                }


                $orderData = [
                    'is_shipping_by_external' => Arr::get($shop->settings, 'is_shipping_by_external'),
                    'external_id'             => $externalId,
                    'marketplace_id'          => $externalId,
                    'reference'               => $faireOrder['display_id'],
                    'created_at'              => Carbon::parse(Arr::get($faireOrder, 'created_at'))->toDateTimeString(),
                    'billing_address'         => $address,
                    'delivery_address'        => $address,
                    'commission_amount'       => $orderCommission,
                    'pay_status'              => OrderPayStatusEnum::UNPAID->value,
                    'pay_detailed_status'     => OrderPayDetailedStatusEnum::UNPAID->value
                ];

                $transactionsData = [];
                $errors           = [];
                foreach (Arr::get($faireOrder, 'items', []) as $item) {
                    $product = Product::where('shop_id', $shop->id)
                        ->where('marketplace_id', $item['variant_id'])
                        ->first();

                    if (!$product) {
                        $errors[] = [
                            'product_code'           => $item['sku'],
                            'product_name'           => $item['name'],
                            'product_marketplace_id' => $item['variant_id'],
                            'message'                => "Product not found in catalogue"
                        ];
                        continue;
                    }


                    $historicAsset = $product->asset->historicAsset;

                    if (!$historicAsset) {
                        $errors[] = [
                            'product_code'           => $item['sku'],
                            'product_name'           => $item['name'],
                            'product_marketplace_id' => $item['variant_id'],
                            'product_slug'           => $product->slug,
                            'message'                => "Product has no historic asset"
                        ];
                        continue;
                    }

                    $price        = Arr::get($item, 'price.amount_minor', 0) / 100;
                    $currencyCode = Arr::get($item, 'price.currency');
                    $currency     = Currency::where('code', $currencyCode)->first();
                    if (!$currency) {
                        $errors[] = [
                            'message' => 'Currency ('.$currencyCode.') not found'
                        ];
                    }

                    $price = $price * GetCurrencyExchange::run($currency, $shop->currency);


                    $quantity = $item['quantity'] / $product->units;

                    $transactionsData[] = [
                        'historical_asset'  => $historicAsset,
                        'quantity_ordered'  => $quantity,
                        'external_id'       => $item['id'],
                        'net_amount'        => $price * $quantity,
                        'gross_amount'      => $price * $quantity,
                        'commission_amount' => $price * $transactionCommissions * $quantity,
                        'marketplace_id'    => $item['id'],
                        'created_at'        => Carbon::parse(Arr::get($item, 'created_at'))->toDateTimeString(),
                    ];
                }

                if (empty($errors)) {
                    $order = StoreOrder::make()->action($customer, $orderData);

                    foreach ($transactionsData as $transactionData) {
                        StoreTransaction::make()->action(
                            order: $order,
                            historicAsset: $transactionData['historical_asset'],
                            modelData: Arr::except($transactionData, 'historical_asset')
                        );
                    }

                    $order = SubmitOrder::make()->action($order);
                    /** @var \App\Models\Inventory\Warehouse $warehouse */
                    $warehouse = $order->shop->organisation->warehouses()->first();

                    if(Arr::get($shop->settings, 'faire.send_orders_automatically_to_warehouse', true)) {
                        SendOrderToWarehouse::make()->action($order, [
                            'warehouse_id' => $warehouse->id
                        ]);
                    }

                    AcceptFaireOrder::run($order);
                }
            }
        }
        //  });
    }

    public function getFormattedAddress(array $address): array
    {
        $country = Country::where('iso3', Arr::get($address, 'country_code'))->first();

        return [
            'address_line_1'      => Arr::get($address, 'address1', ''),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($address, 'postal_code'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($address, 'city'),
            'administrative_area' => Arr::get($address, 'state'),
            'country_code'        => $country->code,
            'country_id'          => $country->id
        ];
    }

    public string $commandSignature = 'faire:orders {shop?}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('shop')) {
            $shop = Shop::where('slug', $command->argument('shop'))->first();
            $this->handle($shop);

            return 0;
        }

        $shops = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::FAIRE)
            ->get();

        /** @var Shop $shop */
        foreach ($shops as $shop) {
            if (Arr::has($shop->settings, 'faire.access_token')) {
                $this->handle($shop);
            }
        }

        return 0;
    }
}
