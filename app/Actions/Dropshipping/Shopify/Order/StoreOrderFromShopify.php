<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Order;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\Shopify\WithShopifyPortfolioMatching;
use App\Actions\Dropshipping\WithSanitizedPhone;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\Ordering\Order\Traits\WithPayAndSubmitOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderCancellationReasonEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreOrderFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithGeneratedShopifyAddress;
    use WithPayAndSubmitOrder;
    use WithSanitizedPhone;
    use WithShopifyPortfolioMatching;

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData): void
    {
        $declinedReason = Arr::get($modelData, 'declined_reason');
        $bannedDelivery = Arr::get($modelData, 'banned_delivery');
        $modelData      = Arr::except($modelData, 'banned_delivery');

        $existOrder = Order::withTrashed()->where('platform_order_id', Arr::get($modelData, 'id'))->first();

        if ($existOrder) {
            if (!$existOrder->isDeclinedPlatformRequest()) {
                return;
            }

            if ($declinedReason) {
                $existOrder->update([
                    'public_notes' => $this->getDeclinedPublicNotes($declinedReason),
                    'data'         => array_merge(
                        Arr::except((array) $existOrder->data, 'banned_delivery'),
                        array_filter(['declined_reason' => $declinedReason, 'banned_delivery' => $bannedDelivery])
                    ),
                ]);

                return;
            }

            $existOrder->update(['platform_order_id' => null]);
        }

        $attributes      = $this->getShopifyAttributesFromWebhook(Arr::get($modelData, 'customer'), Arr::get($modelData, 'shipping_address', []));
        $deliveryAddress = Arr::get($attributes, 'address');

        if ($declinedReason && !filled(Arr::get($deliveryAddress, 'country_id'))) {
            $deliveryAddress = $this->getFallbackDeliveryAddress($shopifyUser);
        }

        $shopifyProducts = collect(Arr::get($modelData, 'line_items', []));

        $matchedShopifyProducts = [];
        $unmatchedShopifyProducts = [];

        foreach ($shopifyProducts as $shopifyProduct) {
            $portfolio = $this->matchShopifyLineItemToPortfolio(
                $shopifyUser->customerSalesChannel,
                Arr::get($shopifyProduct, 'product_id'),
                Arr::get($shopifyProduct, 'product_variant_id'),
                Arr::get($shopifyProduct, 'sku')
            );

            if ($portfolio) {
                $matchedShopifyProducts[] = [$portfolio, $shopifyProduct];
            } else {
                $unmatchedShopifyProducts[] = $shopifyProduct;
            }
        }

        if ($unmatchedShopifyProducts) {
            Log::info(
                'Shopify order '.Arr::get($modelData, 'id').' of customer sales channel '
                .$shopifyUser->customer_sales_channel_id.' has line items outside the portfolio: '
                .json_encode($unmatchedShopifyProducts)
            );
        }

        if (!$declinedReason && !$matchedShopifyProducts) {
            return;
        }

        if (!$declinedReason && $this->isHeldByAnotherOrder($shopifyUser, $modelData)) {
            return;
        }

        $customerClient = $this->digestShopifyCustomerClient($shopifyUser, $modelData, $deliveryAddress);

        if ($declinedReason) {
            StoreOrder::make()->action($customerClient, [
                'platform_id'               => $shopifyUser->platform_id,
                'customer_sales_channel_id' => $shopifyUser->customer_sales_channel_id,
                'date'                      => $modelData['created_at'],
                'delivery_address'          => new Address($deliveryAddress),
                'data'                      => array_filter([
                    'shopify_data'    => Arr::except($modelData, 'declined_reason'),
                    'declined_reason' => $declinedReason,
                    'banned_delivery' => $bannedDelivery,
                ]),
                'platform_order_id'         => Arr::get($modelData, 'id'),
                'public_notes'              => $this->getDeclinedPublicNotes($declinedReason),
                'state'                     => OrderStateEnum::CANCELLED,
                'cancelled_at'              => now(),
            ]);

            return;
        }

        if ($matchedShopifyProducts) {
            $order = DB::transaction(function () use ($shopifyUser, $customerClient, $modelData, $deliveryAddress, $matchedShopifyProducts) {
                $order = StoreOrder::make()->action($customerClient, [
                    'platform_id'               => $shopifyUser->platform_id,
                    'customer_sales_channel_id' => $shopifyUser->customer_sales_channel_id,
                    'date'                      => $modelData['created_at'],
                    'delivery_address'          => new Address($deliveryAddress),
                    'data'                      => [
                        'shopify_data' => $modelData,
                        'platform_milestones' => [
                            'draft_created_at' => Arr::get($modelData, 'created_at'),
                            'placed_at'        => Arr::get($modelData, 'placed_at'),
                        ]
                    ],
                    'platform_order_id'         => Arr::get($modelData, 'id'),
                    'shipping_notes'            => $this->getShippingNotes($modelData, $deliveryAddress),
                    'customer_notes'            => Str::limit(trim((string) Arr::get($modelData, 'order.note')), 4000, '') ?: null,
                ]);

                foreach ($matchedShopifyProducts as [$portfolio, $shopifyProduct]) {
                    /** @var Product $product */
                    $product = $portfolio->item;
                    if (!$product) {
                        \Sentry\captureMessage('Portfolio '.$portfolio->id.' does not have a product');
                        continue;
                    }

                    /** @var HistoricAsset $product */
                    $historicAsset = $product->asset?->historicAsset;
                    if (!$historicAsset) {
                        \Sentry\captureMessage('Portfolio '.$portfolio->id.' does not have a historic asset');
                        continue;
                    }

                    StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $historicAsset,
                        modelData: [
                            'quantity_ordered'        => $shopifyProduct['quantity'],
                            'platform_transaction_id' => $shopifyProduct['id'],
                        ]
                    );
                }

                return $order->refresh();
            });

            $order = $this->payAndSubmitOrder($order);
        }
    }

    /**
     * Shopify can hand the same order lines over again under a new fulfilment order id, and each id
     * used to become its own aiku order, so the dropshipper paid and the end customer got the parcel
     * twice (PRE-90). An order still waiting for payment whose lines all come again gives way to the
     * new request, which can add lines or change quantities; an order already paid and not yet
     * dispatched takes the new id only when the lines and quantities are the same, so nothing is
     * lost and the dispatch still reaches the live fulfilment order. Anything else is created as before.
     *
     * @throws \Throwable
     */
    protected function isHeldByAnotherOrder(ShopifyUser $shopifyUser, array $modelData): bool
    {
        $shopifyOrderId    = Arr::get($modelData, 'order.id');
        $incomingQuantities = collect(Arr::get($modelData, 'line_items', []))
            ->filter(fn ($lineItem) => filled(Arr::get($lineItem, 'id')))
            ->mapWithKeys(fn ($lineItem) => [$lineItem['id'] => (float) $lineItem['quantity']]);

        if (!$shopifyOrderId || $incomingQuantities->isEmpty()) {
            return false;
        }

        $heldBy = Order::where('customer_sales_channel_id', $shopifyUser->customer_sales_channel_id)
            ->whereNotIn('state', [OrderStateEnum::CANCELLED, OrderStateEnum::DISPATCHED])
            ->where('data->shopify_data->order->id', $shopifyOrderId)
            ->whereHas('transactions', fn ($query) => $query->whereIn('platform_transaction_id', $incomingQuantities->keys()))
            ->latest('id')
            ->first();

        if (!$heldBy) {
            return false;
        }

        $heldQuantities = $heldBy->transactions()
            ->whereNotNull('platform_transaction_id')
            ->get(['platform_transaction_id', 'quantity_ordered'])
            ->groupBy('platform_transaction_id')
            ->map(fn ($transactions) => (float) $transactions->sum('quantity_ordered'));

        if ($heldQuantities->keys()->diff($incomingQuantities->keys())->isNotEmpty()) {
            return false;
        }

        if (in_array($heldBy->state, [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED]) && $heldBy->pay_status !== OrderPayStatusEnum::PAID) {
            Cache::lock('shopify_superseded_order_'.$heldBy->id, 120)->block(15, function () use ($heldBy, $modelData) {
                if (!in_array($heldBy->refresh()->state, [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED])) {
                    return;
                }

                try {
                    CancelOrder::run($heldBy, [
                        'cancellation_reason' => OrderCancellationReasonEnum::DUPLICATE_ORDER,
                        'cancellation_notes'  => 'Shopify sent these lines again as fulfilment order '.Arr::get($modelData, 'id'),
                    ]);
                } catch (ValidationException $e) {
                    Log::info('Shopify fulfilment order of superseded order '.$heldBy->id.' not closed: '.$e->getMessage());
                }
            });

            return false;
        }

        if ($heldQuantities->sortKeys()->all() != $incomingQuantities->sortKeys()->all()) {
            return false;
        }

        $heldBy->update([
            'platform_order_id' => Arr::get($modelData, 'id'),
            'data'              => array_merge((array) $heldBy->data, ['shopify_data' => $modelData]),
        ]);

        return true;
    }

    /**
     * A fulfilment request Shopify was told we decline still lands in AW, as a cancelled order that
     * says why, so the customer sees it without going to Shopify (HELP-3151). When they fix the
     * problem and request fulfilment again the placeholder gives up the platform order id and the
     * real order takes it.
     *
     */
    protected function getDeclinedPublicNotes(string $declinedReason): string
    {
        return __('Fulfilment request declined: :reason', ['reason' => $declinedReason]).' '
            .__('Fix the order in Shopify and request fulfilment again.');
    }

    /**
     * Messages Shopify automation apps write on every request, read as instructions by the
     * warehouse though they say nothing about the parcel (HELP-4341).
     */
    private const string AUTOMATION_MESSAGE_PATTERN = "/^(Triggered by rule |We're unable to ship this order due to customer's default shipping template)/i";

    /**
     * TikTok hides the buyer's address on the orders it ships itself and passes them to Shopify
     * like that, so the parcel would go nowhere. AW never ships those (HELP-4568), and the warehouse
     * stops picking on the shipping note, as it does for direct TikTok orders (PRE-96).
     */
    protected function getShippingNotes(array $modelData, array $deliveryAddress): ?string
    {
        $notes = collect([
            $this->hasMaskedDeliveryAddress($deliveryAddress)
                ? __('TikTok hid the delivery address because this order is shipped by TikTok. Do not send it until the full delivery address is on the order.')
                : null,
            $this->getMerchantRequestMessages($modelData),
        ])->filter()->implode("\n");

        return Str::limit($notes, 4000, '') ?: null;
    }

    protected function hasMaskedDeliveryAddress(array $deliveryAddress): bool
    {
        return str_contains((string) Arr::get($deliveryAddress, 'address_line_1'), '**')
            || str_contains((string) Arr::get($deliveryAddress, 'postal_code'), '**');
    }

    /**
     * What the merchant wrote when requesting fulfilment (a pickup point, "dispatch asap") is an
     * instruction for the warehouse and the courier, so it lands in the shipping notes, where the
     * Packeta parcel locker lookup reads it (PRE-16). The end customer's checkout note goes to the
     * customer notes, as Wix and Allegro buyer notes do.
     */
    protected function getMerchantRequestMessages(array $modelData): ?string
    {
        $messages = collect(data_get($modelData, 'merchantRequests.edges', []))
            ->map(fn ($edge) => trim((string) data_get($edge, 'node.message')))
            ->filter(fn (string $message) => $message !== '' && !preg_match(self::AUTOMATION_MESSAGE_PATTERN, $message))
            ->unique()
            ->implode("\n");

        return Str::limit($messages, 4000, '') ?: null;
    }

    /**
     * A declined request may come with no delivery address at all, and both the client and the order
     * insist on one; the customer's own address stands in on the cancelled placeholder.
     */
    protected function getFallbackDeliveryAddress(ShopifyUser $shopifyUser): array
    {
        return Arr::only($shopifyUser->customer->address?->toArray() ?? [], [
            'address_line_1',
            'address_line_2',
            'sorting_code',
            'postal_code',
            'dependent_locality',
            'locality',
            'administrative_area',
            'country_code',
            'country_id',
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function digestShopifyCustomerClient(ShopifyUser $shopifyUser, array $shopifyOrderData, array $deliveryAddress): CustomerClient
    {
        $receiverDetail = Arr::get($shopifyOrderData, 'shipping_address');

        $reference = preg_replace('/\s+/', '', trim(Arr::get($receiverDetail, 'firstName') . Arr::get($receiverDetail, 'lastName') . $shopifyUser->customer_sales_channel_id));
        $customerClientID = DB::table('customer_clients')
            ->select('id')
            ->where('customer_sales_channel_id', $shopifyUser->customer_sales_channel_id)
            ->whereRaw('lower(reference) = lower(?)', [$reference])
            ->first();

        if (!$customerClientID) {
            $customerClient = StoreCustomerClient::make()->action($shopifyUser->customerSalesChannel, [
                'reference'    => $reference,
                'email'        => Arr::get($shopifyOrderData, 'customer.email'),
                'contact_name' => $this->getShopifyContactName($shopifyOrderData),
                'phone'        => $this->sanitizePhone(Arr::get($receiverDetail, 'phone')),
                'address'      => $deliveryAddress,
                'platform_customer_id' => Arr::get($shopifyOrderData, 'customer.id')
            ], strict: false);
        } else {
            $customerClient = CustomerClient::find($customerClientID->id);
            $customerClient = UpdateCustomerClient::make()->action($customerClient, [
                'email'        => Arr::get($shopifyOrderData, 'customer.email'),
                'contact_name' => $this->getShopifyContactName($shopifyOrderData),
                'phone'        => $this->sanitizePhone(Arr::get($receiverDetail, 'phone')),
                'address'      => $deliveryAddress,
                'platform_customer_id' => Arr::get($shopifyOrderData, 'customer.id')
            ], strict: false);
        }

        return $customerClient;
    }

    protected function getShopifyContactName(array $shopifyOrderData): string
    {
        $receiver = Arr::get($shopifyOrderData, 'shipping_address') ?: Arr::get($shopifyOrderData, 'customer', []);

        return trim(Arr::get($receiver, 'firstName').' '.Arr::get($receiver, 'lastName'));
    }

}
