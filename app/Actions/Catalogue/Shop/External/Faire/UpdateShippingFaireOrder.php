<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;

class UpdateShippingFaireOrder extends OrgAction
{
    public const FUNDED_SHIPPING_RATE_LINE = '/^([A-Za-z]{2})\s+(\d+(?:[.,]\d{1,2})?)\s+(\d+(?:[.,]\d{1,2})?)$/';

    public function handle(DeliveryNote $deliveryNote, Command|null $command = null): array
    {

        if (!app()->isProduction()) {
            return [
                'status' => 'success',
                'msg'    => __('Order not updated in local environment')
            ];
        }

        $order = $deliveryNote->orders()->first();
        if (
            $order && $order->shop->type == ShopTypeEnum::EXTERNAL && $order->external_id && !$order->is_shipping_by_external
            && app()->isProduction()
        ) {
            try {
                $this->setFaireFundedShipping($deliveryNote, $order);
                $shipments = $this->getFaireShipments($deliveryNote, $order);

                $result = $order->shop->updateShippingFaireOrder($order->external_id, [
                    'shipments' => $shipments,
                ]);


                $hasErr = Arr::has($result, 'error');

                if (!Arr::get($result, 'success') && $hasErr) {
                    Sentry::captureMessage(json_encode(Arr::get($result, 'error.message', [])));
                    return [
                        'status' => 'fail',
                        'msg'    => Arr::get($result, 'error.message')
                    ];
                } else {
                    try {
                        UpdateFaireOrder::make()->syncFaireTax($order);
                    } catch (\Throwable $e) {
                        Sentry::captureException($e);
                    }

                    return [
                        'status' => 'success',
                        'msg'    => __('Faire order updated successfully')
                    ];
                }
            } catch (\Exception $e) {
                $command?->error('Order '.$order->external_id.' not updated '.$e->getMessage());
                Sentry::captureException($e);

                return [
                    'status' => 'fail',
                    'msg'    => $e->getMessage()
                ];
            }
        } else {
            return [
                'status' => 'fail',
                'msg'    => __('Unprocessable order')
            ];
        }
    }

    /**
     * Faire reimburses the maker_cost sent with the tracking. A shipment rarely carries its own
     * cost, so the shipping staff typed on the order is sent instead (HELP-3906): that is what
     * saves them entering it again on Faire. It all goes on the first tracking, never spread:
     * every new shipment re-sends the whole list, and a share that moved between trackings could
     * be counted twice. A label Faire itself issued is never given a cost, Faire pays none on it.
     *
     * @return array<int, array{carrier: string, tracking_code: string, maker_cost: array{amount_minor: int, currency: string}}>
     */
    public function getFaireShipments(DeliveryNote $deliveryNote, Order $order): array
    {
        $shipments = [];

        $hasFaireLabel = false;

        foreach ($deliveryNote->shipments->sortBy('id') as $shipment) {
            $hasFaireLabel = $hasFaireLabel || str_ends_with((string)$shipment->shipper->code, '-faire');
            $trackings     = preg_split('/[,\/]+/', (string)$shipment->tracking, -1, PREG_SPLIT_NO_EMPTY);
            if (!$trackings) {
                continue;
            }

            $cost = (int)((int)round($shipment->cost * 100) / count($trackings));

            foreach ($trackings as $tracking) {
                $shipments[] = [
                    'carrier'       => $shipment->shipper->trade_as,
                    'tracking_code' => trim($tracking),
                    'maker_cost'    => [
                        'amount_minor' => $cost,
                        'currency'     => $order->currency->code,
                    ],
                ];
            }
        }

        if ($shipments && !$hasFaireLabel && array_sum(array_column(array_column($shipments, 'maker_cost'), 'amount_minor')) == 0) {
            $shipments[0]['maker_cost']['amount_minor'] = max(0, (int)round($order->shipping_amount * 100));
        }

        return $shipments;
    }

    /**
     * A Faire order whose shipping Faire funds is charged the shop's rate for the delivery country,
     * per parcel by its weight band, before the tracking sends it to Faire as maker_cost (HELP-4635).
     * Typed over whatever was on the order, unless the order is already invoiced or a shipment carries
     * its own carrier cost, which is then what Faire is sent. Never in the way of the tracking.
     */
    public function setFaireFundedShipping(DeliveryNote $deliveryNote, Order $order): void
    {
        try {
            $rate = $this->getFaireFundedShippingRate($order);
            if (!$rate || $deliveryNote->shipments->sum('cost') > 0 || $order->invoices()->exists()) {
                return;
            }

            $orderFaireData = $order->shop->getFaireOrder($order->external_id);
            if (Arr::get($orderFaireData, 'success') === false) {
                Sentry::captureMessage('Faire order '.$order->external_id.' not read for its funded shipping: '.json_encode(Arr::get($orderFaireData, 'error')));

                return;
            }

            $fundedShipping = $this->getFaireFundedShipping($deliveryNote, $order, $orderFaireData);
            if ($fundedShipping === null || abs($fundedShipping - (float)$order->shipping_amount) < 0.01) {
                return;
            }

            UpdateFaireOrder::make()->setFaireShipping($order, $fundedShipping);
            $order->refresh();
        } catch (\Throwable $e) {
            Sentry::captureException($e);
        }
    }

    /**
     * Only INSIDER_FREE_SHIPPING is shipping Faire pays for. Orders the retailer pays through the
     * shipping zones set up on Faire, and countries the shop has no rate for, are left to staff.
     * A parcel line packed as several boxes is that many parcels.
     */
    public function getFaireFundedShipping(DeliveryNote $deliveryNote, Order $order, array $orderFaireData): ?float
    {
        if (Arr::get($orderFaireData, 'free_shipping_reason') != 'INSIDER_FREE_SHIPPING') {
            return null;
        }

        $rate    = $this->getFaireFundedShippingRate($order);
        $parcels = $deliveryNote->parcels;
        if (!$rate || !is_array($parcels) || !$parcels || !array_is_list($parcels)) {
            return null;
        }

        return round(collect($parcels)->sum(
            fn ($parcel) => max(1, (int)Arr::get($parcel, 'boxes', 1)) * ((float)Arr::get($parcel, 'weight', 0) > 3 ? $rate[1] : $rate[0])
        ), 2);
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public function getFaireFundedShippingRate(Order $order): ?array
    {
        return Arr::get(self::parseFundedShippingRates(Arr::get($order->shop->settings, 'faire.funded_shipping_rates')), (string)$order->deliveryAddress?->country_code);
    }

    /**
     * One line per country: its code, the price per parcel up to 3 kg and the price per parcel over 3 kg.
     *
     * @return array<string, array{0: float, 1: float}>
     */
    public static function parseFundedShippingRates(?string $text): array
    {
        $rates = [];
        foreach (preg_split('/\R/', (string)$text) as $line) {
            if (preg_match(self::FUNDED_SHIPPING_RATE_LINE, trim($line), $matches)) {
                $rates[strtoupper($matches[1])] = [(float)str_replace(',', '.', $matches[2]), (float)str_replace(',', '.', $matches[3])];
            }
        }

        return $rates;
    }

    public function getCommandSignature(): string
    {
        return 'shop:update_shipping_faire_order {delivery_note}';
    }

    public function asCommand(Command $command): void
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail();
        $result       = $this->handle($deliveryNote, $command);
        if ($result['status'] == 'fail') {
            $command->error($result['msg']);
        } else {
            $command->info($result['msg']);
        }
    }
}
