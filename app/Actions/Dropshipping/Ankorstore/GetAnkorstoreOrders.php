<?php

namespace App\Actions\Dropshipping\Ankorstore;

use App\Actions\Billables\Charge\StoreDiscretionaryChargeTransaction;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Catalogue\Product\ProductStateEnum;
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
 * Ankorstore collects from the retailer and pays us the order later, so the order is the retailer's: billed to the
 * customer of the shop with the retailer's email (created from the Ankorstore retailer when there is none), priced
 * at the shop's own prices with its shipping and charges, as staff key them in by hand, and left to be paid by the
 * marketplace. It is sent to the warehouse and accepted on Ankorstore. Anything that does not fit (an unknown SKU, a
 * pack size that is not the product's units) is skipped and listed in the shop's ankorstore.skipped_orders, for staff
 * to key in by hand with the Ankorstore number as the customer's reference, so it is never imported a second time.
 */
class GetAnkorstoreOrders
{
    use AsAction;

    private const string INCLUDE = 'retailer,orderItems.productVariant.product';

    public function handle(Shop $shop, ?Command $command = null): void
    {
        if (!$shop->hasAnkorstore() || !Arr::get($shop->settings, 'ankorstore.import_from')) {
            return;
        }

        $lock = Cache::lock('ankorstore-orders:'.$shop->id, 1800);
        if (!$lock->get()) {
            return;
        }

        try {
            $importFrom = Carbon::parse(Arr::get($shop->settings, 'ankorstore.import_from'));

            foreach (['ankor_confirmed', 'brand_confirmed'] as $status) {
                $this->eachOrder($shop, $status, $importFrom, fn (array $ankorstoreOrder) => $this->importOrder($shop, $ankorstoreOrder, $command));
            }

            $this->eachOrder($shop, 'cancelled', $importFrom, function (array $ankorstoreOrder) use ($shop, $command) {
                try {
                    $this->cancelOrder($shop, $ankorstoreOrder, $command);
                } catch (Throwable $exception) {
                    $this->recordSkippedOrder($shop, (string)$ankorstoreOrder['attributes']['reference'], 'cancelled on Ankorstore, but could not be cancelled here: '.$exception->getMessage());
                }
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * ponytail: Ankorstore lists newest first with no sort or date filter, so paging stops at the first order
     * submitted before import_from and never goes past 20 pages of 50.
     */
    private function eachOrder(Shop $shop, string $status, Carbon $importFrom, callable $callback): void
    {
        $after = null;
        $pages = 0;
        do {
            $response = $shop->ankorstoreRequest('GET', 'orders?'.http_build_query(array_filter([
                'filter' => ['status' => $status],
                'page'   => array_filter(['limit' => 50, 'after' => $after]),
            ])));

            foreach (Arr::get($response, 'data', []) as $ankorstoreOrder) {
                $submittedAt = Arr::get($ankorstoreOrder, 'attributes.submittedAt') ?? Arr::get($ankorstoreOrder, 'attributes.createdAt');
                if ($submittedAt && Carbon::parse($submittedAt)->lt($importFrom)) {
                    return;
                }
                $callback($ankorstoreOrder);
            }

            $after = Arr::get($response, 'meta.page.hasMore') ? Arr::get($response, 'meta.page.to') : null;
        } while ($after && ++$pages < 20);
    }

    /**
     * The shipping address is only on an order read on its own, so the listed order is read again before import. An
     * order that does not fit is handed to staff for good: their hand-keyed copy carries the Ankorstore number only
     * once its payment is recorded, days later, so importing it once the cause cleared would duplicate it.
     */
    public function importOrder(Shop $shop, array $ankorstoreOrder, ?Command $command = null): ?Order
    {
        $number = (string)$ankorstoreOrder['attributes']['reference'];
        if (Arr::get($shop->settings, "ankorstore.skipped_orders.$number.handed_to_staff")) {
            return null;
        }

        $existing = $this->existingOrder($shop, $number);
        if ($existing) {
            if ($existing->isFromAnkorstore() && !$existing->trashed()) {
                $this->sendOn($shop, $existing, $ankorstoreOrder, $command);
            }

            return null;
        }

        try {
            $single = $shop->ankorstoreRequest('GET', 'orders/'.$ankorstoreOrder['id'].'?include='.self::INCLUDE);
        } catch (Throwable $exception) {
            $this->recordSkippedOrder($shop, $number, $exception->getMessage());

            return null;
        }

        try {
            $ankorstoreOrder = $single['data'];
            $order           = DB::transaction(fn () => $this->storeOrder($shop, $ankorstoreOrder, $this->keyIncluded($single)));
        } catch (Throwable $exception) {
            $this->recordSkippedOrder($shop, $number, $exception->getMessage().'. Key it in by hand.', handedToStaff: true);
            $command?->warn("$number handed to staff: ".$exception->getMessage());

            return null;
        }

        $command?->info("$number imported as $order->reference");

        return $this->sendOn($shop, $order, $ankorstoreOrder, $command);
    }

    /**
     * Takes an imported order as far as it should go: submitted, in the warehouse, accepted on Ankorstore. A step
     * that failed is picked up again on the next run, while Ankorstore still lists the order.
     */
    private function sendOn(Shop $shop, Order $order, array $ankorstoreOrder, ?Command $command): Order
    {
        $number = (string)$ankorstoreOrder['attributes']['reference'];
        try {
            if ($order->state === OrderStateEnum::CREATING) {
                $order = SubmitOrder::make()->action($order);
            }
            if ($order->refresh()->state === OrderStateEnum::SUBMITTED) {
                SendOrderToWarehouse::make()->action($order, ['warehouse_id' => $shop->organisation->warehouses()->first()->id]);
            }
            UpdateOrderPaymentsStatus::run($order->refresh());

            if ($ankorstoreOrder['attributes']['status'] === 'ankor_confirmed') {
                $shop->ankorstoreRequest('POST', 'orders/'.$ankorstoreOrder['id'].'/-actions/transition', ['data' => ['type' => 'brand-validates']]);
            }
        } catch (Throwable $exception) {
            $this->recordSkippedOrder($shop, $number, "imported as $order->reference, but not sent on: ".$exception->getMessage());
            $command?->warn("$number imported as $order->reference, not sent on: ".$exception->getMessage());

            return $order;
        }

        $this->clearSkippedOrder($shop, $number);

        return $order;
    }

    private function keyIncluded(array $response): array
    {
        return collect(Arr::get($response, 'included', []))->keyBy(fn ($resource) => $resource['type'].':'.$resource['id'])->all();
    }

    /**
     * Staff key Ankorstore orders in by hand with the Ankorstore number as the reference of the payment they record,
     * or as the customer's reference.
     */
    private function existingOrder(Shop $shop, string $number): ?Order
    {
        return Order::withTrashed()
            ->where('shop_id', $shop->id)
            ->where(function ($query) use ($number) {
                $query->where('external_id', $number)
                    ->orWhere('customer_reference', $number)
                    ->orWhereIn('id', DB::table('model_has_payments')
                        ->join('payments', 'payments.id', 'model_has_payments.payment_id')
                        ->where('model_has_payments.model_type', 'Order')
                        ->where('payments.reference', $number)
                        ->select('model_has_payments.model_id'));
            })
            ->first();
    }

    private function storeOrder(Shop $shop, array $ankorstoreOrder, array $included): Order
    {
        $attributes   = $ankorstoreOrder['attributes'];
        $retailer     = Arr::get($included, 'retailers:'.Arr::get($ankorstoreOrder, 'relationships.retailer.data.id'), []);
        $lines        = $this->lines($shop, $ankorstoreOrder, $included);
        $customer     = $this->retailerCustomer($shop, $attributes, (array)Arr::get($retailer, 'attributes', []));
        $salesChannel = SalesChannel::where('group_id', $shop->group_id)->where('code', 'ankorstore')->firstOrFail();
        $number       = (string)$attributes['reference'];

        $order = StoreOrder::make()->action($customer, [
            'external_id'        => $number,
            'marketplace_id'     => $ankorstoreOrder['id'],
            'customer_reference' => $number,
            'sales_channel_id'   => $salesChannel->id,
            'data'               => ['ankorstore' => ['id' => $ankorstoreOrder['id'], 'master_order_id' => Arr::get($attributes, 'masterOrderId'), 'reference' => $number]],
        ]);

        $shipTo = Arr::get($attributes, 'shippingOverview.shipToAddress');
        if ($shipTo) {
            $order = UpdateOrderDeliveryAddress::make()->action($order, ['address' => $this->address(
                Arr::get($shipTo, 'street'),
                Arr::get($shipTo, 'postalCode'),
                Arr::get($shipTo, 'city'),
                Arr::get($shipTo, 'countryCode')
            )]);
        }

        foreach ($lines as $line) {
            StoreTransaction::make()->action(
                order: $order,
                historicAsset: $line['product']->currentHistoricProduct,
                modelData: Arr::except($line, 'product'),
                strict: false,
                forceHydrators: true
            );
        }

        return $this->topUpToAnkorstoreTotal($order->refresh(), $attributes);
    }

    /**
     * Ankorstore pays its own prices, not the shop's, and staff key the difference in as a discretionary charge so the
     * goods come to what Ankorstore pays (brandTotalAmount, before VAT). Less than the shop's prices goes to staff.
     */
    private function topUpToAnkorstoreTotal(Order $order, array $attributes): Order
    {
        if (strtoupper((string)Arr::get($attributes, 'brandCurrency')) !== $order->currency->code) {
            throw new RuntimeException('Ankorstore pays in '.Arr::get($attributes, 'brandCurrency').', the shop sells in '.$order->currency->code);
        }

        $ankorstoreTotal = round((int)Arr::get($attributes, 'brandTotalAmount') / 100, 2);
        $difference      = round($ankorstoreTotal - (float)$order->goods_amount, 2);
        if ($difference <= -0.01) {
            throw new RuntimeException("Ankorstore pays $ankorstoreTotal, less than the shop's prices $order->goods_amount");
        }
        if ($difference >= 0.01) {
            $order = StoreDiscretionaryChargeTransaction::make()->handle($order, ['amount' => $difference, 'label' => 'Ankorstore']);
        }

        return $order;
    }

    /**
     * A variant sells either one of our packs (unitMultiplier 1) or one of its pieces, in packs of unitMultiplier,
     * which must then be the product's units. Either way the Ankorstore quantity is the number of our packs. A product
     * no longer for sale here goes to staff, who decide whether to ship what is left or lower the order.
     *
     * @return list<array{product: Product, quantity_ordered: float, marketplace_id: string}>
     */
    private function lines(Shop $shop, array $ankorstoreOrder, array $included): array
    {
        $problems = [];
        $lines    = [];
        foreach (Arr::get($ankorstoreOrder, 'relationships.orderItems.data', []) as $itemReference) {
            $item       = Arr::get($included, 'order-items:'.$itemReference['id'], []);
            $variant    = Arr::get($included, 'productVariants:'.Arr::get($item, 'relationships.productVariant.data.id'), []);
            $multiplier = (int)Arr::get($included, 'products:'.Arr::get($variant, 'relationships.product.data.id').'.attributes.unitMultiplier', 0);
            $sku        = trim((string)Arr::get($variant, 'attributes.sku'));
            $quantity   = (int)Arr::get($item, 'attributes.quantity');

            $product = $sku === '' ? null : Product::where('shop_id', $shop->id)
                ->whereRaw('lower(code) = ?', [mb_strtolower($sku)])
                ->orderByRaw("state = 'discontinued'")
                ->orderByDesc('id')
                ->first();

            $problem = match (true) {
                $sku === '' => 'a line has no SKU',
                !$product => "$sku is not a product of the shop",
                !$product->currentHistoricProduct => "$sku has no price history",
                !in_array($product->state, [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING]) => "$sku is ".$product->state->value.' in the shop',
                $multiplier < 1 => "$sku: Ankorstore did not say its pack size",
                $multiplier > 1 && (int)round((float)$product->units) !== $multiplier => "$sku comes in packs of ".(float)$product->units.", Ankorstore sells packs of $multiplier",
                $quantity < 1 => "$sku has no quantity",
                isset($lines[$product->id]) => "$sku is twice on the order",
                default => null,
            };
            if ($problem) {
                $problems[] = $problem;
                continue;
            }

            $lines[$product->id] = [
                'product'          => $product,
                'quantity_ordered' => $quantity,
                'marketplace_id'   => $itemReference['id'],
            ];
        }

        if (!$lines && !$problems) {
            $problems[] = 'no lines';
        }
        if ($problems) {
            throw new RuntimeException(implode('; ', $problems));
        }

        return array_values($lines);
    }

    private function retailerCustomer(Shop $shop, array $attributes, array $retailer): Customer
    {
        $email = mb_strtolower(trim((string)Arr::get($retailer, 'email')));
        if ($email === '') {
            throw new RuntimeException('the retailer has no email');
        }

        $vatNumber = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)Arr::get($retailer, 'vatNumber')));
        $customer  = Customer::where('shop_id', $shop->id)->whereRaw('lower(email) = ?', [$email])->orderBy('id')->first()
            ?? $this->customerWithVatNumber($shop, $vatNumber);
        if ($customer) {
            return $customer;
        }

        $contactName = trim(Arr::get($retailer, 'firstName').' '.Arr::get($retailer, 'lastName'));
        $address     = $this->address(
            Arr::get($attributes, 'billingStreet'),
            Arr::get($attributes, 'billingPostalCode'),
            Arr::get($attributes, 'billingCity'),
            Arr::get($attributes, 'billingCountryCode')
        );

        return StoreCustomer::make()->action($shop, array_filter([
            'company_name'    => Arr::get($attributes, 'shippingOverview.shipToAddress.organisationName') ?: Arr::get($attributes, 'billingOrganisationName') ?: Arr::get($retailer, 'storeName'),
            'contact_name'    => $contactName ?: Arr::get($attributes, 'billingName'),
            'email'           => $email,
            'phone'           => Arr::get($retailer, 'phoneNumberE164'),
            'contact_address' => $address,
            'tax_number'      => $vatNumber ? ['number' => $vatNumber, 'country_id' => $address['country_id']] : null,
        ]), strict: false);
    }

    /**
     * Staff often keyed a retailer in under another email (about a third of the orders keyed by hand to Nov 2026), but
     * with its VAT number, stored with or without the country prefix.
     */
    private function customerWithVatNumber(Shop $shop, string $vatNumber): ?Customer
    {
        if ($vatNumber === '') {
            return null;
        }

        $customerId = TaxNumber::where('owner_type', 'Customer')
            ->whereIn('owner_id', Customer::where('shop_id', $shop->id)->select('id'))
            ->whereIn(DB::raw("upper(regexp_replace(number, '[^A-Za-z0-9]', '', 'g'))"), array_unique([$vatNumber, substr($vatNumber, 2)]))
            ->min('owner_id');

        return $customerId ? Customer::find($customerId) : null;
    }

    private function address(?string $street, ?string $postalCode, ?string $city, ?string $countryCode): array
    {
        $country = Country::where('code', strtoupper((string)$countryCode))->first();
        if (!$country) {
            throw new RuntimeException('unknown country '.$countryCode);
        }

        return [
            'address_line_1'      => $street,
            'address_line_2'      => null,
            'sorting_code'        => null,
            'postal_code'         => $postalCode,
            'dependent_locality'  => null,
            'locality'            => $city,
            'administrative_area' => null,
            'country_code'        => $country->code,
            'country_id'          => $country->id,
        ];
    }

    private function cancelOrder(Shop $shop, array $ankorstoreOrder, ?Command $command): void
    {
        $number = (string)$ankorstoreOrder['attributes']['reference'];
        $order  = Order::where('shop_id', $shop->id)->where('external_id', $number)->first();
        if (!$order || in_array($order->state, [OrderStateEnum::CANCELLED, OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])) {
            $this->clearSkippedOrder($shop, $number);

            return;
        }

        CancelOrder::make()->action($order);
        $command?->info("$number cancelled on Ankorstore, $order->reference cancelled");
    }

    public function recordSkippedOrder(Shop $shop, string $number, string $reason, bool $handedToStaff = false): void
    {
        $shop->refresh();
        $settings = $shop->settings;
        $reason   = mb_substr($reason, 0, 1000);
        if (Arr::get($settings, "ankorstore.skipped_orders.$number.reason") === $reason) {
            return;
        }
        \Sentry\captureMessage("Ankorstore order $number skipped ($shop->slug): $reason");
        data_set($settings, "ankorstore.skipped_orders.$number", array_filter([
            'reason'          => $reason,
            'handed_to_staff' => $handedToStaff,
            'first_seen'      => Arr::get($settings, "ankorstore.skipped_orders.$number.first_seen", now()->toIso8601String()),
        ]));
        $shop->settings = $settings;
        $shop->save();
    }

    private function clearSkippedOrder(Shop $shop, string $number): void
    {
        $shop->refresh();
        if (!Arr::has($shop->settings, "ankorstore.skipped_orders.$number")) {
            return;
        }
        $settings = $shop->settings;
        Arr::forget($settings, "ankorstore.skipped_orders.$number");
        $shop->settings = $settings;
        $shop->save();
    }

    public string $commandSignature = 'ankorstore:orders {shop? : slug; every shop connected to Ankorstore when omitted}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Shop::whereNotNull('settings->ankorstore->client_secret')
            ->when($command->argument('shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get()
            ->each(fn (Shop $shop) => $this->handle($shop, $command));

        return 0;
    }

    public function asJob(): void
    {
        Shop::whereNotNull('settings->ankorstore->client_secret')->get()->each(function (Shop $shop) {
            try {
                $this->handle($shop);
            } catch (Throwable $exception) {
                \Sentry\captureException($exception);
            }
        });
    }
}
