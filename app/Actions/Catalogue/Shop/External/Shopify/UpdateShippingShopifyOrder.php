<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Sentry;
use Throwable;

class UpdateShippingShopifyOrder extends OrgAction
{
    use WithShopifyExternalShopApi;

    public const array FULFILLABLE_STATUSES = ['OPEN', 'IN_PROGRESS'];

    public const string CREATE_FULFILLMENT_ACTION = 'CREATE_FULFILLMENT';

    /**
     * @return array{status: string, msg: string}
     */
    public function handle(DeliveryNote $deliveryNote, ?Command $command = null): array
    {
        if (!$this->isShopifyPushAllowed()) {
            return $this->feedback('success', __('Order not updated in local environment'));
        }

        $order = $deliveryNote->orders()->first();

        if (!$order || $order->shop->type != ShopTypeEnum::EXTERNAL || $order->shop->engine != ShopEngineEnum::SHOPIFY || !$order->marketplace_id || $order->is_shipping_by_external) {
            return $this->feedback('fail', __('Unprocessable order'));
        }

        $shopifyUser = $this->getShopifyExternalShopUser($order->shop);

        if ($blockedReason = $this->getShopifyExternalShopBlockedReason($shopifyUser)) {
            return $this->feedback('fail', $blockedReason);
        }

        try {
            $shopifyOrder = $this->getShopifyExternalShopOrderFulfillmentOrders($shopifyUser, $order->marketplace_id);

            if ($message = Arr::get($shopifyOrder, 'message')) {
                return $this->fail($order, $message, $command);
            }

            if (!Arr::get($shopifyOrder, 'order')) {
                return $this->fail($order, __('Order not found in Shopify'), $command);
            }

            $trackingInfo = $this->getTrackingInfo($deliveryNote);
            $allocations  = $this->getAllocations($order, Arr::get($shopifyOrder, 'order.fulfillmentOrders.nodes', []));

            if ($allocations->isEmpty()) {
                return $this->whenNothingLeftToFulfil($order, $shopifyUser, $shopifyOrder, $trackingInfo, $command);
            }

            $fulfillmentIds      = [];
            $fulfilledQuantities = [];
            $errors              = [];

            foreach ($allocations->groupBy('location_id') as $locationAllocations) {
                $fulfillment = [
                    'notifyCustomer'              => true,
                    'lineItemsByFulfillmentOrder' => $locationAllocations
                        ->groupBy('fulfillment_order_id')
                        ->map(fn (Collection $lines, string $fulfillmentOrderId) => [
                            'fulfillmentOrderId'        => $fulfillmentOrderId,
                            'fulfillmentOrderLineItems' => $lines->map(fn (array $line) => [
                                'id'       => $line['fulfillment_order_line_item_id'],
                                'quantity' => $line['quantity'],
                            ])->values()->all(),
                        ])
                        ->values()
                        ->all(),
                ];

                if ($trackingInfo) {
                    $fulfillment['trackingInfo'] = $trackingInfo;
                }

                $result = $this->createShopifyExternalShopFulfillment($shopifyUser, $fulfillment);

                if ($message = Arr::get($result, 'message')) {
                    $errors[] = $message;
                } elseif ($userErrors = Arr::get($result, 'fulfillmentCreate.userErrors')) {
                    $errors[] = $this->getShopifyExternalShopUserErrorMessage($userErrors);
                } elseif ($fulfillmentId = Arr::get($result, 'fulfillmentCreate.fulfillment.id')) {
                    $fulfillmentIds[] = $fulfillmentId;

                    foreach ($locationAllocations as $allocation) {
                        $fulfilledQuantities[$allocation['line_item_id']] = ($fulfilledQuantities[$allocation['line_item_id']] ?? 0) + $allocation['quantity'];
                    }
                }
            }

            if ($fulfillmentIds) {
                $this->markOrderAsFulfilled($order, $fulfillmentIds, $fulfilledQuantities, complete: !$errors);
            }

            if ($errors) {
                return $this->fail($order, implode('; ', $errors), $command);
            }

            return $this->feedback('success', __('Shopify order updated successfully'));
        } catch (Throwable $e) {
            $command?->error('Order '.$order->reference.' not updated '.$e->getMessage());
            Sentry::captureException($e);

            return $this->feedback('fail', $e->getMessage());
        }
    }

    public function isShopifyPushAllowed(): bool
    {
        return $this->isShopifyExternalShopWriteAllowed();
    }

    /**
     * What the warehouse packed for each Shopify line, spread over the fulfilment order lines that still wait for it.
     *
     * @return Collection<int, array{line_item_id: string, location_id: ?string, fulfillment_order_id: string, fulfillment_order_line_item_id: string, quantity: int}>
     */
    public function getAllocations(Order $order, array $fulfillmentOrders): Collection
    {
        $quantitiesToFulfil = $order->transactions()
            ->where('model_type', 'Product')
            ->whereNotNull('marketplace_id')
            ->get()
            ->mapWithKeys(fn (Transaction $transaction) => [$transaction->marketplace_id => $this->getShippedQuantity($transaction)])
            ->all();

        $alreadyFulfilled = Arr::get($order->data ?? [], 'shopify_fulfilled_quantities', []);

        foreach ($quantitiesToFulfil as $lineItemId => $quantity) {
            $quantitiesToFulfil[$lineItemId] = $quantity - (int) ($alreadyFulfilled[$lineItemId] ?? 0);
        }

        return $this->allocate($quantitiesToFulfil, $fulfillmentOrders);
    }

    /**
     * @param array<string, int> $quantitiesToFulfil quantity keyed by Shopify line item id
     * @return Collection<int, array{line_item_id: string, location_id: ?string, fulfillment_order_id: string, fulfillment_order_line_item_id: string, quantity: int}>
     */
    public function allocate(array $quantitiesToFulfil, array $fulfillmentOrders): Collection
    {
        $allocations = collect();

        foreach ($fulfillmentOrders as $fulfillmentOrder) {
            if (!$this->canBeFulfilled($fulfillmentOrder)) {
                continue;
            }

            foreach (Arr::get($fulfillmentOrder, 'lineItems.nodes', []) as $fulfillmentOrderLineItem) {
                $lineItemId = Arr::get($fulfillmentOrderLineItem, 'lineItem.id');
                $remaining  = (int) Arr::get($fulfillmentOrderLineItem, 'remainingQuantity', 0);
                $quantity   = min($quantitiesToFulfil[$lineItemId] ?? 0, $remaining);

                if ($quantity <= 0) {
                    continue;
                }

                $quantitiesToFulfil[$lineItemId] -= $quantity;

                $allocations->push([
                    'line_item_id'                   => $lineItemId,
                    'location_id'                    => Arr::get($fulfillmentOrder, 'assignedLocation.location.id'),
                    'fulfillment_order_id'           => Arr::get($fulfillmentOrder, 'id'),
                    'fulfillment_order_line_item_id' => Arr::get($fulfillmentOrderLineItem, 'id'),
                    'quantity'                       => $quantity,
                ]);
            }
        }

        return $allocations;
    }

    public function getShippedQuantity(Transaction $transaction): int
    {
        $quantity = $transaction->quantity_dispatched ?? $transaction->quantity_picked ?? $transaction->quantity_ordered;

        return max((int) floor((float) $quantity), 0);
    }

    private function canBeFulfilled(array $fulfillmentOrder): bool
    {
        if (!in_array(Arr::get($fulfillmentOrder, 'status'), self::FULFILLABLE_STATUSES)) {
            return false;
        }

        return collect(Arr::get($fulfillmentOrder, 'supportedActions', []))->contains('action', self::CREATE_FULFILLMENT_ACTION);
    }

    /**
     * @return array{status: string, msg: string}
     */
    private function whenNothingLeftToFulfil(Order $order, ShopifyUser $shopifyUser, array $shopifyOrder, ?array $trackingInfo, ?Command $command): array
    {
        $lastFulfillmentId = Arr::last(Arr::get($order->data, 'shopify_fulfillment_ids', []));

        if ($lastFulfillmentId && $trackingInfo) {
            $result = $this->updateShopifyExternalShopFulfillmentTracking($shopifyUser, $lastFulfillmentId, $trackingInfo);

            $message = Arr::get($result, 'message') ?: $this->getShopifyExternalShopUserErrorMessage(Arr::get($result, 'fulfillmentTrackingInfoUpdate.userErrors', []));

            if ($message) {
                return $this->fail($order, $message, $command);
            }

            return $this->feedback('success', __('Shopify tracking updated'));
        }

        if (Arr::get($shopifyOrder, 'order.displayFulfillmentStatus') === 'FULFILLED') {
            $this->markOrderAsFulfilled($order, [], [], complete: true);

            return $this->feedback('success', __('Order already fulfilled in Shopify'));
        }

        $blocked = collect(Arr::get($shopifyOrder, 'order.fulfillmentOrders.nodes', []))
            ->reject(fn (array $fulfillmentOrder) => in_array(Arr::get($fulfillmentOrder, 'status'), ['CLOSED', 'CANCELLED']))
            ->map(fn (array $fulfillmentOrder) => Arr::get($fulfillmentOrder, 'status').($this->canBeFulfilled($fulfillmentOrder) ? '' : ' (cannot be fulfilled by us)'))
            ->unique()
            ->join(', ');

        return $this->fail($order, __('Nothing to fulfil in Shopify').($blocked ? ': '.$blocked : ''), $command);
    }

    /**
     * @return array{company?: string, numbers: array<int, string>, urls?: array<int, string>}|null
     */
    public function getTrackingInfo(DeliveryNote $deliveryNote): ?array
    {
        $shipments = $deliveryNote->shipments()->with('shipper')->get();

        $numbers = $shipments
            ->flatMap(fn (Shipment $shipment) => $shipment->trackings ?: preg_split('/[,\/]+/', (string) $shipment->tracking, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($number) => trim((string) $number))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($numbers)) {
            return null;
        }

        $trackingInfo = ['numbers' => $numbers];

        $shipper = $shipments->first(fn (Shipment $shipment) => $shipment->shipper)?->shipper;

        if ($company = $shipper?->trade_as ?: $shipper?->name) {
            $trackingInfo['company'] = $company;
        }

        $urls = $shipments->flatMap(fn (Shipment $shipment) => $shipment->tracking_urls ?? [])->filter()->unique()->values()->all();

        if ($urls) {
            $trackingInfo['urls'] = $urls;
        }

        return $trackingInfo;
    }

    /**
     * What Aiku already fulfilled is remembered per Shopify line, so a retry after a partly failed push
     * never sends the same units twice; the order only counts as done when every push went through.
     *
     * @param array<int, string> $fulfillmentIds
     * @param array<string, int> $fulfilledQuantities quantity keyed by Shopify line item id
     */
    private function markOrderAsFulfilled(Order $order, array $fulfillmentIds, array $fulfilledQuantities, bool $complete): void
    {
        $data = $order->data ?? [];

        $quantities = Arr::get($data, 'shopify_fulfilled_quantities', []);
        foreach ($fulfilledQuantities as $lineItemId => $quantity) {
            $quantities[$lineItemId] = (int) ($quantities[$lineItemId] ?? 0) + $quantity;
        }

        $data['shopify_fulfillment_ids']      = array_values(array_unique([...Arr::get($data, 'shopify_fulfillment_ids', []), ...$fulfillmentIds]));
        $data['shopify_fulfilled_quantities'] = $quantities;

        if ($complete) {
            $data['shopify_fulfilled_at'] = now()->toIso8601String();
        }

        $order->updateQuietly(['data' => $data]);
    }

    /**
     * @return array{status: string, msg: string}
     */
    private function fail(Order $order, string $message, ?Command $command): array
    {
        $command?->error('Order '.$order->reference.': '.$message);
        Sentry::captureMessage('Shopify fulfillment failed for order '.$order->reference.': '.$message);

        return $this->feedback('fail', $message);
    }

    /**
     * @return array{status: string, msg: string}
     */
    private function feedback(string $status, string $message): array
    {
        return [
            'status' => $status,
            'msg'    => $message,
        ];
    }

    public function getCommandSignature(): string
    {
        return 'external_shop:shopify_update_shipping {delivery_note}';
    }

    public function asCommand(Command $command): int
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail();
        $result       = $this->handle($deliveryNote, $command);

        if ($result['status'] == 'fail') {
            $command->error($result['msg']);

            return 1;
        }

        $command->info($result['msg']);

        return 0;
    }
}
