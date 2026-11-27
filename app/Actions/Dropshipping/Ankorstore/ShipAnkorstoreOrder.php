<?php

namespace App\Actions\Dropshipping\Ankorstore;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * We ship Ankorstore orders with our own couriers ("custom" on Ankorstore): the parcels are sent for a quote, and
 * confirming it with the tracking marks the order shipped there. Only an order Ankorstore still has as
 * brand_confirmed is shipped, so a second shipment, or one staff already shipped on Ankorstore, is left alone.
 */
class ShipAnkorstoreOrder
{
    use AsAction;

    /**
     * @return array{status: string, msg: string}
     */
    public function handle(DeliveryNote $deliveryNote): array
    {
        $order = $deliveryNote->orders()->first();
        if (!$order?->isFromAnkorstore()) {
            return ['status' => 'fail', 'msg' => 'Not an Ankorstore order'];
        }

        try {
            $shop     = $order->shop;
            $orderId  = Arr::get($order->data, 'ankorstore.id');
            $status   = Arr::get($shop->ankorstoreRequest('GET', 'orders/'.$orderId), 'data.attributes.status');
            if ($status !== 'brand_confirmed') {
                return ['status' => 'success', 'msg' => "Ankorstore has the order as $status, not shipped again"];
            }

            $parcels = $this->parcels($deliveryNote);
            $tracking = $this->tracking($deliveryNote);
            if (!$parcels || !$tracking) {
                return ['status' => 'fail', 'msg' => 'Ankorstore not told: the delivery note has no '.(!$parcels ? 'parcels' : 'tracking')];
            }

            $quote   = $shop->ankorstoreRequest('POST', 'orders/'.$orderId.'/ship/custom', ['shipping' => ['parcels' => $parcels]]);
            $quoteId = Arr::get($quote, 'data.attributes.shippingOverview.latestQuote.id');
            if (!$quoteId) {
                return ['status' => 'fail', 'msg' => 'Ankorstore gave no shipping quote'];
            }

            $shop->ankorstoreRequest('POST', 'shipping-quotes/'.$quoteId.'/confirm', ['tracking' => $tracking]);
        } catch (Throwable $exception) {
            \Sentry\captureException($exception);

            return ['status' => 'fail', 'msg' => $exception->getMessage()];
        }

        return ['status' => 'success', 'msg' => 'Ankorstore order shipped'];
    }

    /**
     * A parcel line packed as several boxes is that many parcels. Ankorstore takes whole grams up to 30 kg and whole
     * centimetres up to 274.
     *
     * @return list<array{length: int, width: int, height: int, distanceUnit: string, weight: int, massUnit: string}>
     */
    public function parcels(DeliveryNote $deliveryNote): array
    {
        $parcels = [];
        foreach (DeliveryNote::parcelsWithCarrierWeights($deliveryNote->parcels, $deliveryNote->estimated_weight) as $parcel) {
            $dimensions = array_values(array_map(fn ($size) => min(274, max(1, (int)ceil((float)$size))), (array)Arr::get($parcel, 'dimensions', [])));
            $parcelData = [
                'length'       => $dimensions[0] ?? 1,
                'width'        => $dimensions[1] ?? 1,
                'height'       => $dimensions[2] ?? 1,
                'distanceUnit' => 'cm',
                'weight'       => min(30000, max(1, (int)round((float)Arr::get($parcel, 'weight', 0) * 1000))),
                'massUnit'     => 'g',
            ];
            for ($box = 0; $box < max(1, (int)Arr::get($parcel, 'boxes', 1)); $box++) {
                $parcels[] = $parcelData;
            }
        }

        return $parcels;
    }

    /**
     * @return array{trackingProvider: string, trackingNumber: string}|array{trackingLink: string}|null
     */
    public function tracking(DeliveryNote $deliveryNote): ?array
    {
        /** @var Shipment $shipment */
        foreach ($deliveryNote->shipments->sortBy('id') as $shipment) {
            $link = Arr::first((array)$shipment->tracking_urls);
            if (is_string($link) && filter_var($link, FILTER_VALIDATE_URL)) {
                return ['trackingLink' => $link];
            }

            $number = trim((string)Arr::first(preg_split('/[,\/]+/', (string)$shipment->tracking, -1, PREG_SPLIT_NO_EMPTY)));
            if ($number !== '') {
                return ['trackingProvider' => (string)($shipment->shipper->trade_as ?: $shipment->shipper->code), 'trackingNumber' => $number];
            }
        }

        return null;
    }

    public string $commandSignature = 'ankorstore:ship {delivery_note : slug}';

    public function asCommand(Command $command): int
    {
        $result = $this->handle(DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail());
        $command->{$result['status'] === 'success' ? 'info' : 'error'}($result['msg']);

        return $result['status'] === 'success' ? 0 : 1;
    }
}
