<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Zentrada;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Actions\Ordering\Order\UpdateOrderShippingEngineAsManual;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Retina\Dropshipping\Portfolio\ZentradaWebApi;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\Ordering\Order\OrderChargesEngineEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Models\Ordering\Order;
use App\Models\Ordering\SalesChannel;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * A TradeSafe "Delivery order transaction" email is sent once Zentrada holds the buyer's payment, so it
 * is the signal to dispatch. The order is billed to the shop's Zentrada customer (another one for
 * deliveries outside the EU), delivered to the buyer, priced as Zentrada priced it, with Zentrada's
 * freight. It goes straight to the warehouse when every line matches the feed and is in stock; anything
 * else leaves it unsubmitted with the reasons in its internal notes and a task for the person in charge.
 */
class ImportZentradaOrder
{
    use AsAction;

    public const string SALES_CHANNEL_CODE = 'zentrada';

    private const int HAND_KEYED_LOOKBACK_DAYS = 21;

    /**
     * @param array{transaction: string, currency: string, freight: float, vat_number: string, address: array<string, string>, lines: list<array{code: string, packs: float, pieces: ?float, piece_price: ?float, amount: float, ean: string}>} $transaction
     * @param array{delivery_date?: ?string, buyer?: ?string} $mail
     * @param array<int, array{name: string, contents: string}> $files
     */
    public function handle(Shop $shop, array $transaction, array $mail = [], array $files = []): ?Order
    {
        $reference = $transaction['transaction'];

        $lock = Cache::lock('zentrada-order:'.$shop->id.':'.$reference, 120);
        if (!$lock->get()) {
            return null;
        }

        try {
            $existing = $this->existingOrder($shop, $transaction);
            if ($existing) {
                return $existing;
            }

            try {
                [$order, $problems] = DB::transaction(fn () => $this->storeOrder($shop, $transaction, $mail));
            } catch (Throwable $exception) {
                $this->askForHelp($shop, null, $reference, [$exception->getMessage()]);

                return null;
            }

            $this->attachFiles($order, $files, __('Zentrada transaction :transaction', ['transaction' => $reference]));

            if ($problems) {
                $order->update(['internal_notes' => trim($order->internal_notes."\n\n".__('Zentrada order not sent to the warehouse:')."\n- ".implode("\n- ", $problems))]);
                $this->askForHelp($shop, $order, $reference, $problems);

                return $order->refresh();
            }

            try {
                $order = SubmitOrder::make()->action($order);
                SendOrderToWarehouse::make()->action($order, ['warehouse_id' => $shop->organisation->warehouses()->first()->id]);
                UpdateOrderPaymentsStatus::run($order->refresh());
            } catch (Throwable $exception) {
                \Sentry\captureException($exception);
                $this->askForHelp($shop, $order, $reference, [__('created, but could not be sent to the warehouse: :error', ['error' => $exception->getMessage()])]);
            }

            return $order->refresh();
        } finally {
            $lock->release();
        }
    }

    /**
     * Imported orders carry the transaction as the customer's reference. Orders keyed in by hand never
     * did, so one of the Zentrada customers' recent orders to the same postcode with the same products
     * is taken to be this transaction keyed in already.
     */
    public function existingOrder(Shop $shop, array $transaction): ?Order
    {
        $reference = $transaction['transaction'];

        $imported = Order::withTrashed()
            ->where('shop_id', $shop->id)
            ->where(fn ($query) => $query->where('customer_reference', $reference)->orWhere('external_id', $reference))
            ->first();
        if ($imported) {
            return $imported;
        }

        $postalCode = $this->normalisedPostalCode(Arr::get($transaction, 'address.postal'));
        $codes      = collect($transaction['lines'])->pluck('code')->map(fn ($code) => mb_strtolower($code))->unique()->sort()->values()->all();
        if (!$postalCode || !$codes) {
            return null;
        }

        return Order::where('shop_id', $shop->id)
            ->whereIn('customer_id', $this->zentradaCustomerIds($shop))
            ->where('state', '!=', OrderStateEnum::CANCELLED)
            ->where('created_at', '>=', now()->subDays(self::HAND_KEYED_LOOKBACK_DAYS))
            ->with(['deliveryAddress', 'transactions.model'])
            ->get()
            ->first(function (Order $order) use ($postalCode, $codes) {
                $orderCodes = $order->transactions
                    ->where('model_type', 'Product')
                    ->map(fn ($transaction) => mb_strtolower((string) $transaction->model?->code))
                    ->unique()->sort()->values()->all();

                return $this->normalisedPostalCode($order->deliveryAddress?->postal_code) === $postalCode && $orderCodes === $codes;
            });
    }

    /**
     * @return array{0: Order, 1: list<string>}
     */
    private function storeOrder(Shop $shop, array $transaction, array $mail): array
    {
        if ($transaction['currency'] !== $shop->currency->code) {
            throw new RuntimeException(__('Zentrada charged in :currency, the shop sells in :shop_currency', ['currency' => $transaction['currency'], 'shop_currency' => $shop->currency->code]));
        }

        $country  = $this->deliveryCountry($transaction);
        $customer = $this->customerFor($shop, $country);
        [$lines, $problems] = $this->lines($shop, $transaction['lines']);

        if (!$lines) {
            throw new RuntimeException(implode('; ', $problems));
        }

        $address      = Arr::get($transaction, 'address', []);
        $deliveryDate = Arr::get($mail, 'delivery_date');

        $order = StoreOrder::make()->action($customer, [
            'external_id'        => $transaction['transaction'],
            'customer_reference' => $transaction['transaction'],
            'sales_channel_id'   => SalesChannel::where('group_id', $shop->group_id)->where('code', self::SALES_CHANNEL_CODE)->firstOrFail()->id,
            'shipping_notes'     => $deliveryDate ? __('Zentrada announced delivery by :date', ['date' => $deliveryDate]) : null,
            'data'               => [
                'zentrada' => [
                    'transaction'   => $transaction['transaction'],
                    'buyer'         => Arr::get($mail, 'buyer'),
                    'delivery_date' => $deliveryDate,
                    'vat_number'    => $transaction['vat_number'],
                    'email'         => Arr::get($address, 'email'),
                ],
            ],
        ]);

        $order->update([
            'charges_engine' => OrderChargesEngineEnum::MANUAL,
            'company_name'   => Arr::get($address, 'company'),
            'contact_name'   => trim(Arr::get($address, 'firstName', '').' '.Arr::get($address, 'name', '')) ?: null,
            'phone'          => Arr::get($address, 'phone'),
        ]);

        $order = UpdateOrderDeliveryAddress::make()->action($order, ['address' => [
            'address_line_1'      => trim(Arr::get($address, 'street', '').' '.Arr::get($address, 'number', '')),
            'address_line_2'      => Arr::get($address, 'line2'),
            'sorting_code'        => null,
            'postal_code'         => Arr::get($address, 'postal'),
            'dependent_locality'  => null,
            'locality'            => Arr::get($address, 'city'),
            'administrative_area' => null,
            'country_code'        => $country->code,
            'country_id'          => $country->id,
        ]]);

        foreach ($lines as $line) {
            StoreTransaction::make()->action(
                order: $order,
                historicAsset: $line['product']->currentHistoricProduct,
                modelData: Arr::except($line, 'product'),
                strict: false,
                forceHydrators: true
            );
        }

        UpdateOrderShippingEngineAsManual::make()->handle($order->refresh(), ['shipping_amount' => round($transaction['freight'], 2)]);

        return [$order->refresh(), $problems];
    }

    /**
     * Zentrada sells our packs by the piece (HELP-4923): D_MENGE_VE is packs, E_MENGE_STUECK the pieces
     * in them, and the piece price has to be the one the feed publishes for that many packs.
     *
     * @param list<array{code: string, packs: float, pieces: ?float, piece_price: ?float, amount: float, ean: string}> $csvLines
     *
     * @return array{0: list<array{product: Product, quantity_ordered: float, net_amount: float, gross_amount: float}>, 1: list<string>}
     */
    private function lines(Shop $shop, array $csvLines): array
    {
        $problems = [];
        $lines    = [];

        foreach ($csvLines as $csvLine) {
            $code    = $csvLine['code'];
            $product = Product::where('shop_id', $shop->id)->whereRaw('lower(code) = ?', [mb_strtolower($code)])->first();
            $units   = $product ? (float) $product->units : 0;

            $problem = match (true) {
                !$product                                                                               => __(':code is not a product of the shop', ['code' => $code]),
                !$product->currentHistoricProduct                                                       => __(':code has no price history', ['code' => $code]),
                $csvLine['packs'] <= 0 || $csvLine['packs'] != floor($csvLine['packs'])                 => __(':code: :packs is not a whole number of packs', ['code' => $code, 'packs' => $csvLine['packs']]),
                isset($lines[$product->id])                                                             => __(':code is twice on the order', ['code' => $code]),
                $csvLine['pieces'] !== null && abs($csvLine['pieces'] - $csvLine['packs'] * $units) > 0.001 => __(':code: :packs packs of :units are not the :pieces pieces Zentrada sold', ['code' => $code, 'packs' => $csvLine['packs'], 'units' => $units, 'pieces' => $csvLine['pieces']]),
                default                                                                                 => null,
            };

            if ($problem) {
                $problems[] = $problem;
                continue;
            }

            if ($priceProblem = $this->priceProblem($shop, $product, $csvLine)) {
                $problems[] = $priceProblem;
            }

            if ((int) $product->available_quantity < $csvLine['packs']) {
                $problems[] = __(':code: :packs packs ordered, :stock in stock', ['code' => $code, 'packs' => $csvLine['packs'], 'stock' => (int) $product->available_quantity]);
            }

            $lines[$product->id] = [
                'product'          => $product,
                'quantity_ordered' => $csvLine['packs'],
                'net_amount'       => round($csvLine['amount'], 2),
                'gross_amount'     => round($csvLine['amount'], 2),
            ];
        }

        return [array_values($lines), $problems];
    }

    private function priceProblem(Shop $shop, Product $product, array $csvLine): ?string
    {
        if ($csvLine['piece_price'] === null) {
            return null;
        }

        $expected = $this->feedPiecePrice($shop, $product, (int) $csvLine['packs']);
        if (abs($csvLine['piece_price'] - $expected) <= 0.01) {
            return null;
        }

        return __(':code: Zentrada charged :price a piece, the feed sends :expected', [
            'code'     => $csvLine['code'],
            'price'    => number_format($csvLine['piece_price'], 2, '.', ''),
            'expected' => number_format($expected, 2, '.', ''),
        ]);
    }

    public function feedPiecePrice(Shop $shop, Product $product, int $packs): float
    {
        $feed       = ZentradaWebApi::make();
        $units      = max(1, (int) $product->units);
        $packCents  = (int) round((float) $product->price * 100);
        $pieceCents = $feed->piecePriceInCents($packCents, $units);

        foreach ($feed->getVolumeTiers($shop) as $tier) {
            if ($packs >= $tier['quantity']) {
                $denominator = 10000 * $units;
                $pieceCents  = intdiv($packCents * (10000 - (int) round($tier['discount'] * 100)) + $denominator - 1, $denominator);
            }
        }

        $promotion = (float) Arr::get($shop->settings, 'zentrada.promotion_discount', 0);

        return round($pieceCents * (100 - $promotion) / 10000, 2);
    }

    /**
     * The transaction's prefix ("EU-NL 958559") names the buyer's country when the address does not.
     */
    private function deliveryCountry(array $transaction): Country
    {
        $value = trim((string) Arr::get($transaction, 'address.country'));

        $country = match (true) {
            $value === ''      => null,
            strlen($value) === 2 => Country::where('code', strtoupper($value))->first(),
            strlen($value) === 3 => Country::where('iso3', strtoupper($value))->first(),
            default            => Country::whereRaw('lower(name) = ?', [mb_strtolower($value)])->first(),
        };

        if (!$country && preg_match('/^[A-Z]{2}-([A-Z]{2})\b/', $transaction['transaction'], $match)) {
            $country = Country::where('code', $match[1])->first();
        }

        if (!$country) {
            throw new RuntimeException(__('Could not tell the delivery country from ":country"', ['country' => $value]));
        }

        return $country;
    }

    private function customerFor(Shop $shop, Country $country): Customer
    {
        $isEu      = in_array($country->code, Country::getCountryCodesInEU(), true);
        $reference = $isEu
            ? Arr::get($shop->settings, 'zentrada.customer_reference')
            : (Arr::get($shop->settings, 'zentrada.non_eu_customer_reference') ?: Arr::get($shop->settings, 'zentrada.customer_reference'));

        $customer = $reference ? Customer::where('shop_id', $shop->id)->where('reference', $reference)->first() : null;
        if (!$customer) {
            throw new RuntimeException(__('No Zentrada customer :reference in the shop to bill this order to', ['reference' => $reference]));
        }

        return $customer;
    }

    /**
     * @return list<int>
     */
    private function zentradaCustomerIds(Shop $shop): array
    {
        $references = array_filter([
            Arr::get($shop->settings, 'zentrada.customer_reference'),
            Arr::get($shop->settings, 'zentrada.non_eu_customer_reference'),
        ]);

        return Customer::where('shop_id', $shop->id)->whereIn('reference', $references)->pluck('id')->all();
    }

    private function normalisedPostalCode(?string $postalCode): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $postalCode));
    }

    /**
     * @param array<int, array{name: string, contents: string}> $files
     */
    public function attachFiles(Order|Customer $model, array $files, string $caption): void
    {
        foreach ($files as $file) {
            $path = tempnam(sys_get_temp_dir(), 'zentrada-');
            file_put_contents($path, $file['contents']);

            try {
                SaveModelAttachment::make()->action($model, [
                    'path'         => $path,
                    'originalName' => $file['name'],
                    'extension'    => pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin',
                    'scope'        => 'zentrada',
                    'caption'      => $caption,
                ]);
            } catch (Throwable $exception) {
                \Sentry\captureException($exception);
            } finally {
                @unlink($path);
            }
        }
    }

    /**
     * @param list<string> $problems
     */
    public function askForHelp(Shop $shop, ?Order $order, string $reference, array $problems): void
    {
        $assignment = $this->assignment($shop);

        StoreStaffTask::run(GetAikuAssistant::run($shop->group_id), [
            'subject'     => $order
                ? __('Zentrada order :order (:transaction) needs checking', ['order' => $order->reference, 'transaction' => $reference])
                : __('Zentrada transaction :transaction could not be imported', ['transaction' => $reference]),
            'description' => ($order
                ? __('It was imported but not sent to the warehouse:')
                : __('No order was created. Key it in by hand with :transaction as the customer\'s reference:', ['transaction' => $reference]))
                ."\n- ".implode("\n- ", $problems),
            ...$assignment,
            ...($order ? ['model_type' => 'Order', 'model_id' => $order->id] : []),
            'data'        => ['organisation_id' => $shop->organisation_id, 'zentrada_transaction' => $reference],
        ]);
    }

    /**
     * @return array{assignee_id: int}|array{department: string}
     */
    public function assignment(Shop $shop): array
    {
        $user = User::where('id', Arr::get($shop->settings, 'zentrada.orders_user_id'))->where('status', true)->first();

        return $user && StaffTask::canBeAssigned($user) ? ['assignee_id' => $user->id] : ['department' => 'customer-services'];
    }
}
