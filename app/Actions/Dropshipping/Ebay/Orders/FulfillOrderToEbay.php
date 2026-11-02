<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-12h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\OrgAction;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dropshipping\EbayUser;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FulfillOrderToEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * eBay ShippingCarrierCodeType values for the carriers our warehouses hand parcels to, keyed by shipment trade_as.
     */
    private const array EBAY_CARRIER_CODES = [
        'royal mail'    => 'RoyalMail',
        'apc overnight' => 'APC',
        'gls'           => 'GLS',
    ];

    private const int MAX_ATTEMPTS = 4;

    public string $jobQueue = 'ebay';

    public function handle(Order $order, int $attempt = 1): array|null
    {
        $fulfillOrderId = Arr::get($order->data, 'ebay_order.orderId');

        if (! $fulfillOrderId) {
            $fulfillOrderId = $order->platform_order_id;
        }

        if (! $order->customerSalesChannel->platform_status) {
            return [];
        }

        /** @var EbayUser $ebayUser */
        $ebayUser = $order->customerSalesChannel->user;

        /** @var DeliveryNote|null $deliveryNote */
        $deliveryNote = $order->currentDeliveryNote();

        $shipment  = $deliveryNote?->shipments()->first();

        if ($this->isShippedWithEbayLabel($order)) {
            $this->noteEbayLabelOrder($order, $shipment);

            return [];
        }

        $lineItems = [];

        foreach ($order->transactions()->where('model_type', 'Product')->get() as $transaction) {
            if ((int) $transaction->quantity_dispatched > 0) {
                $lineItems[] = [
                    'lineItemId' => $transaction->platform_transaction_id,
                    'quantity' => (int) $transaction->quantity_dispatched,
                ];
            }
        }

        $result = $ebayUser->fulfillOrder($fulfillOrderId, [
            'line_items'      => $lineItems,
            'tracking_number' => $shipment?->tracking,
            'carrier_code'    => $this->carrierCode($ebayUser, $shipment)
        ]);

        if (Arr::get($result, 'errors')) {
            if ($attempt < self::MAX_ATTEMPTS) {
                self::dispatch($order, $attempt + 1)->delay(now()->addMinutes(15 * $attempt))->afterCommit();
            } else {
                \Sentry::captureMessage('eBay rejected fulfilment of order '.$order->slug.' '.$attempt.' times: '.json_encode(Arr::get($result, 'errors')));
            }
        }

        return $result;
    }

    /**
     * The buyer paid eBay for postage and eBay issues the label (program.ebayShipping.shippingLabelProvidedBy = EBAY),
     * so eBay refuses our tracking with 30500 "System error." on every attempt (PRE-57). The seller is told instead.
     */
    public function isShippedWithEbayLabel(Order $order): bool
    {
        return Arr::get($order->data, 'ebay_order.program.ebayShipping.shippingLabelProvidedBy') === 'EBAY';
    }

    private function noteEbayLabelOrder(Order $order, ?Shipment $shipment): void
    {
        if (Arr::get($order->data, 'ebay_fulfilment_skipped') || Arr::get($order->data, 'ebay_order.orderFulfillmentStatus') === 'FULFILLED') {
            return;
        }

        $note = __('This eBay order uses postage bought through eBay (eBay label), so eBay does not accept our tracking and the order is not marked as shipped on eBay. Check the order in your eBay account. We sent it with :carrier, tracking :tracking.', [
            'carrier'  => $shipment?->trade_as ?: $shipment?->shipper?->name ?: '-',
            'tracking' => $shipment?->tracking ?: '-',
        ]);

        UpdateOrder::run($order, [
            'shipping_notes' => trim($order->shipping_notes."\n\n".$note),
            'data'           => ['ebay_fulfilment_skipped' => 'ebay_label'],
        ]);
    }

    /**
     * eBay links tracking to a carrier only when it gets its own carrier code. The shipper name is our integration
     * ("ITD (api)", "APC (API)"), the carrier the parcel travels with is the shipment's trade_as, so that is what is
     * translated; a carrier eBay has no code for is passed through by name.
     */
    public function carrierCode(EbayUser $ebayUser, ?Shipment $shipment): ?string
    {
        $carrierName = $shipment?->trade_as ?: $shipment?->shipper?->name;

        if (blank($carrierName)) {
            return null;
        }

        $carrier = collect($ebayUser->getServicesWithCarrierInfo())->first(
            fn ($service) => strcasecmp($service['carrier_name'], $carrierName) === 0 || strcasecmp($service['carrier_code'], $carrierName) === 0
        );

        return $carrier['carrier_code'] ?? self::EBAY_CARRIER_CODES[Str::lower(trim($carrierName))] ?? $carrierName;
    }

    public string $commandSignature = 'ebay-order-fulfill {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->first();

        $result = $this->handle($order);

        $command->table(['Field', 'Value'], [
            ['Order ID', $order->id],
            ['Status', Arr::get($result, 'error', 'Success')],
        ]);
    }
}
