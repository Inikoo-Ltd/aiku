<?php

namespace App\Actions\Dropshipping\Orderchamp;

use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Ordering\Order\OrderChargesEngineEnum;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Models\Helpers\TaxNumber;
use App\Models\Ordering\Order;
use App\Models\Ordering\SalesChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Orderchamp buys from us and resells to the retailer: the order is billed to the Orderchamp company that invoices
 * it (B.V. NL, GmbH DE, B.V. BE, named by the VAT number of the order's billing address, each already a customer of
 * the shop) and delivered to the retailer, whose name goes on the label. Lines are priced as Orderchamp priced them,
 * so shop discounts, shipping and charges are kept off the order. Anything that does not fit (an unknown SKU, a case
 * size that is not the product's units, a total that does not come out the same) is skipped and listed in the shop's
 * orderchamp.skipped_orders, for staff to key in by hand with the Orderchamp number as the customer's reference, so it
 * is never imported a second time.
 */
class GetOrderchampOrders
{
    use AsAction;

    private const string ORDER_FIELDS = 'id number createdAt isTest isCancelled currency totalPrice commissionPrice companyName companyPhone firstName lastName note productsCount
        shippingAddress { companyName firstName lastName street houseNumber addressLine2 postalCode city region country }
        billingAddress { companyName vatNumber country }
        products(first: 100) { edges { node { id sku title quantity caseQuantity subtotalPrice } } }';

    public function handle(Shop $shop, ?Command $command = null): void
    {
        $importFrom = Arr::get($shop->settings, 'orderchamp.import_from');
        if (!$shop->hasOrderchamp() || !$importFrom) {
            return;
        }

        $lock = Cache::lock('orderchamp-orders:'.$shop->id, 1800);
        if (!$lock->get()) {
            return;
        }

        try {
            $this->importAndCancel($shop, $command);
        } finally {
            $lock->release();
        }
    }

    private function importAndCancel(Shop $shop, ?Command $command): void
    {
        $importFrom    = Arr::get($shop->settings, 'orderchamp.import_from');
        $confirmOrders = (bool)Arr::get($shop->settings, 'orderchamp.confirm_orders', false);
        $filters       = $confirmOrders ? 'includeUnconfirmed: true' : 'isConfirmed: true';

        $this->eachOrder($shop, 'isFulfilled: false, '.$filters.', since: "'.Carbon::parse($importFrom)->toIso8601ZuluString().'", sort: CREATED_AT_ASC', function (array $orderchampOrder) use ($shop, $confirmOrders, $command) {
            $this->importOrder($shop, $orderchampOrder, $confirmOrders, $command);
        });

        $this->eachOrder($shop, 'includeCancelled: true, includeUnconfirmed: true, updatedSince: "'.now()->subDays(2)->toIso8601ZuluString().'", sort: UPDATED_AT_ASC', function (array $orderchampOrder) use ($shop, $command) {
            if (!Arr::get($orderchampOrder, 'isCancelled')) {
                return;
            }
            try {
                $this->cancelOrder($shop, $orderchampOrder, $command);
            } catch (Throwable $exception) {
                $this->recordSkippedOrder($shop, $orderchampOrder['number'], 'cancelled on Orderchamp, but could not be cancelled here: '.$exception->getMessage());
            }
        });
    }

    private function eachOrder(Shop $shop, string $arguments, callable $callback): void
    {
        $after = null;
        $pages = 0;
        do {
            $data = $shop->orderchampQuery('{ orders(first: 10'.($after ? ', after: "'.$after.'"' : '').', '.$arguments.') { pageInfo { hasNextPage endCursor } edges { node { '.self::ORDER_FIELDS.' } } } }');

            foreach (Arr::get($data, 'orders.edges', []) as $edge) {
                $callback($edge['node']);
            }

            $after = Arr::get($data, 'orders.pageInfo.hasNextPage') ? Arr::get($data, 'orders.pageInfo.endCursor') : null;
        } while ($after && ++$pages < 50);
    }

    public function importOrder(Shop $shop, array $orderchampOrder, bool $confirmOrders = false, ?Command $command = null): ?Order
    {
        $number = $orderchampOrder['number'];
        if (Arr::get($orderchampOrder, 'isTest') || Arr::get($orderchampOrder, 'isCancelled')) {
            return null;
        }

        $existing = $this->existingOrder($shop, $number);
        if ($existing) {
            if (!in_array($existing->state, [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED])) {
                $this->clearSkippedOrder($shop, $number);
            }

            return null;
        }

        try {
            $order = DB::transaction(fn () => $this->storeOrder($shop, $orderchampOrder));
        } catch (Throwable $exception) {
            $this->recordSkippedOrder($shop, $number, $exception->getMessage());
            $command?->warn("$number skipped: ".$exception->getMessage());

            return null;
        }

        try {
            $order = SubmitOrder::make()->action($order);
            SendOrderToWarehouse::make()->action($order, ['warehouse_id' => $shop->organisation->warehouses()->first()->id]);
            UpdateOrderPaymentsStatus::run($order->refresh());

            if ($confirmOrders) {
                $shop->orderchampQuery('mutation($input: OrderConfirmInput!) { orderConfirm(input: $input) { userErrors { field message } } }', ['input' => ['orderId' => $orderchampOrder['id']]]);
            }
        } catch (Throwable $exception) {
            $this->recordSkippedOrder($shop, $number, "created as $order->reference, but not sent on: ".$exception->getMessage());
            \Sentry\captureException($exception);

            return $order;
        }

        $this->clearSkippedOrder($shop, $number);
        $command?->info("$number imported as $order->reference");

        return $order;
    }

    /**
     * An order staff keyed in by hand carries the Orderchamp number as the customer's reference.
     */
    private function existingOrder(Shop $shop, string $number): ?Order
    {
        return Order::where('shop_id', $shop->id)
            ->where(fn ($query) => $query->where('external_id', $number)->orWhere('customer_reference', $number))
            ->first();
    }

    private function storeOrder(Shop $shop, array $orderchampOrder): Order
    {
        $customer = $this->orderchampCustomer($shop, (string)Arr::get($orderchampOrder, 'billingAddress.vatNumber'));
        $lines    = $this->lines($shop, $orderchampOrder);

        $salesChannel = SalesChannel::where('group_id', $shop->group_id)->where('code', 'orderchamp')->firstOrFail();
        $total        = round((float)$orderchampOrder['totalPrice'], 2);

        $order = StoreOrder::make()->action($customer, [
            'external_id'        => $orderchampOrder['number'],
            'marketplace_id'     => $orderchampOrder['number'],
            'customer_reference' => $orderchampOrder['number'],
            'sales_channel_id'   => $salesChannel->id,
            'commission_amount'  => round((float)Arr::get($orderchampOrder, 'commissionPrice', 0), 2),
            'customer_notes'     => Arr::get($orderchampOrder, 'note'),
            'data'               => ['orderchamp' => ['id' => $orderchampOrder['id'], 'number' => $orderchampOrder['number'], 'total' => $total]],
        ]);

        $order->update([
            'shipping_engine' => OrderShippingEngineEnum::MANUAL,
            'charges_engine'  => OrderChargesEngineEnum::MANUAL,
            'company_name'    => Arr::get($orderchampOrder, 'shippingAddress.companyName') ?: Arr::get($orderchampOrder, 'companyName'),
            'contact_name'    => trim(Arr::get($orderchampOrder, 'shippingAddress.firstName').' '.Arr::get($orderchampOrder, 'shippingAddress.lastName')) ?: null,
            'phone'           => Arr::get($orderchampOrder, 'companyPhone'),
        ]);
        $order = UpdateOrderDeliveryAddress::make()->action($order, ['address' => $this->deliveryAddress((array)$orderchampOrder['shippingAddress'])]);

        foreach ($lines as $line) {
            StoreTransaction::make()->action(
                order: $order,
                historicAsset: $line['product']->currentHistoricProduct,
                modelData: Arr::except($line, 'product'),
                strict: false,
                forceHydrators: true
            );
        }

        $order->refresh();
        if (abs(round((float)$order->total_amount - $total, 2)) >= 0.01) {
            throw new RuntimeException("the order would total $order->total_amount, Orderchamp says $total");
        }

        return $order;
    }

    /**
     * @return list<array{product: Product, quantity_ordered: float, net_amount: float, gross_amount: float, marketplace_id: string}>
     */
    private function lines(Shop $shop, array $orderchampOrder): array
    {
        $orderchampLines = collect(Arr::get($orderchampOrder, 'products.edges', []))->pluck('node');
        if ($orderchampLines->count() !== (int)$orderchampOrder['productsCount']) {
            throw new RuntimeException('more than 100 lines');
        }

        $problems = [];
        $lines    = [];
        foreach ($orderchampLines as $orderchampLine) {
            $sku     = trim($orderchampLine['sku']);
            $product = Product::where('shop_id', $shop->id)->whereRaw('lower(code) = ?', [mb_strtolower($sku)])->first();
            $case    = (int)$orderchampLine['caseQuantity'];

            $problem = match (true) {
                !$product => "$sku is not a product of the shop",
                !$product->currentHistoricProduct => "$sku has no price history",
                (int)round((float)$product->units) !== $case => "$sku comes in packs of ".(float)$product->units.", Orderchamp sells cases of $case",
                $case < 1 || $orderchampLine['quantity'] % $case !== 0 => "$sku: ".$orderchampLine['quantity']." is not a whole number of cases of $case",
                isset($lines[$product->id]) => "$sku is twice on the order",
                default => null,
            };
            if ($problem) {
                $problems[] = $problem;
                continue;
            }

            $amount             = round((float)$orderchampLine['subtotalPrice'], 2);
            $lines[$product->id] = [
                'product'          => $product,
                'quantity_ordered' => $orderchampLine['quantity'] / $case,
                'net_amount'       => $amount,
                'gross_amount'     => $amount,
                'marketplace_id'   => $orderchampLine['id'],
            ];
        }

        if ($problems) {
            throw new RuntimeException(implode('; ', $problems));
        }

        return array_values($lines);
    }

    private function orderchampCustomer(Shop $shop, string $vatNumber): Customer
    {
        $vatNumber = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vatNumber));
        $withoutCountry = preg_match('/^[A-Z]{2}(\d.*)$/', $vatNumber, $matches) ? $matches[1] : $vatNumber;

        $customerId = TaxNumber::where('owner_type', 'Customer')
            ->whereIn('owner_id', Customer::where('shop_id', $shop->id)->select('id'))
            ->whereIn(DB::raw("upper(regexp_replace(number, '[^A-Za-z0-9]', '', 'g'))"), array_unique([$vatNumber, $withoutCountry]))
            ->value('owner_id');

        if (!$customerId) {
            throw new RuntimeException("no customer of the shop has the VAT number $vatNumber Orderchamp bills this order from");
        }

        return Customer::find($customerId);
    }

    private function deliveryAddress(array $address): array
    {
        $country = Country::where('code', strtoupper((string)Arr::get($address, 'country')))->first();
        if (!$country) {
            throw new RuntimeException('unknown delivery country '.Arr::get($address, 'country'));
        }

        return [
            'address_line_1'      => trim(Arr::get($address, 'street').' '.Arr::get($address, 'houseNumber')),
            'address_line_2'      => Arr::get($address, 'addressLine2'),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($address, 'postalCode'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($address, 'city'),
            'administrative_area' => Arr::get($address, 'region'),
            'country_code'        => $country->code,
            'country_id'          => $country->id,
        ];
    }

    private function cancelOrder(Shop $shop, array $orderchampOrder, ?Command $command): void
    {
        $order = Order::where('shop_id', $shop->id)->where('external_id', $orderchampOrder['number'])->first();
        if (!$order || in_array($order->state, [OrderStateEnum::CANCELLED, OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])) {
            $this->clearSkippedOrder($shop, $orderchampOrder['number']);

            return;
        }

        CancelOrder::make()->action($order);
        $command?->info($orderchampOrder['number']." cancelled on Orderchamp, $order->reference cancelled");
    }

    public function recordSkippedOrder(Shop $shop, string $number, string $reason): void
    {
        $shop->refresh();
        $settings = $shop->settings;
        $reason   = mb_substr($reason, 0, 1000);
        if (Arr::get($settings, "orderchamp.skipped_orders.$number.reason") === $reason) {
            return;
        }
        \Sentry\captureMessage("Orderchamp order $number skipped ($shop->slug): $reason");
        data_set($settings, "orderchamp.skipped_orders.$number", [
            'reason'     => $reason,
            'first_seen' => Arr::get($settings, "orderchamp.skipped_orders.$number.first_seen", now()->toIso8601String()),
        ]);
        $shop->settings = $settings;
        $shop->save();
    }

    private function clearSkippedOrder(Shop $shop, string $number): void
    {
        $shop->refresh();
        if (!Arr::has($shop->settings, "orderchamp.skipped_orders.$number")) {
            return;
        }
        $settings = $shop->settings;
        Arr::forget($settings, "orderchamp.skipped_orders.$number");
        $shop->settings = $settings;
        $shop->save();
    }

    public string $commandSignature = 'orderchamp:orders {shop? : slug; every shop connected to Orderchamp when omitted}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Shop::whereNotNull('settings->orderchamp->access_token')
            ->when($command->argument('shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get()
            ->each(fn (Shop $shop) => $this->handle($shop, $command));

        return 0;
    }

    public function asJob(): void
    {
        Shop::whereNotNull('settings->orderchamp->access_token')->get()->each(function (Shop $shop) {
            try {
                $this->handle($shop);
            } catch (Throwable $exception) {
                \Sentry\captureException($exception);
            }
        });
    }
}
