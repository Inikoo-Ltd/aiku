<?php

namespace App\Actions\Dispatching\Shipment;

use App\Actions\Catalogue\Shop\External\Faire\GetSpecificFaireOrder;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Dispatching\Shipper;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreShipmentFromFaire extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): Shipment|null
    {
        $order = $deliveryNote->orders()->first();

        $faireOrder = GetSpecificFaireOrder::run($order);


        if (count($faireOrder['shipments']) == 0) {
            throw ValidationException::withMessages(['message' => __('No shipment found for this order')]);
        }

        $shipment = null;
        foreach (Arr::get($faireOrder, 'shipments') as $faireShipment) {
            $shipperCode = Arr::get($faireShipment, 'carrier').'-faire';
            $shipper     = Shipper::where('code', $shipperCode)->where('organisation_id', $deliveryNote->organisation_id)->first();


            if (!$shipper) {
                $shipper = StoreShipper::make()->action($order->organisation, [
                    'code'     => Arr::get($faireShipment, 'carrier').'-faire',
                    'name'     => Arr::get($faireShipment, 'carrier')." (Faire)",
                    'trade_as' => Arr::get($faireShipment, 'carrier')
                ]);
            }

            $shipment = StoreShipment::make()->action($deliveryNote, $shipper, [
                'reference'          => Arr::get($faireShipment, 'id'),
                'tracking'           => Arr::get($faireShipment, 'tracking_code'),
                'combined_label_url' => Arr::get($faireShipment, 'shipping_label_url')
            ]);
        }


        return $shipment;
    }

    public string $commandSignature = 'delivery_note:faire_shipment {delivery_note}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail();

        $this->handle($deliveryNote);

        return 0;
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisation($deliveryNote->organisation, $request);

        $this->handle($deliveryNote);
    }
}
