<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\OrgAction;
use App\Models\Dispatching\Shipper;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetFaireShipment extends OrgAction
{

    /**
     * @throws \Throwable
     */
    public function handle(Order $order): array
    {
        $deliveryNote = $order->deliveryNotes->first();
        $faireOrder = GetSpecificFaireOrder::run($order);

        $shipment = Arr::get($faireOrder, 'shipments.0');

        $shipper = Shipper::where('code', Arr::get($shipment, 'carrier'))->first();

        if(! $shipper) {
            $shipper = StoreShipper::make()->action($order->organisation, [
                'code' => Arr::get($shipment, 'carrier'),
                'name' => Arr::get($shipment, 'carrier'),
                'trade_as' => Arr::get($shipment, 'carrier')
            ]);
        }

        StoreShipment::make()->action($deliveryNote, $shipper, [
            'reference' => Arr::get($shipment, 'id'),
            'tracking' => Arr::get($shipment, 'tracking_code'),
            'combined_label_url' => Arr::get($shipment, 'shipping_label_url')
        ]);

        return $shipment;
    }

    public string $commandSignature = 'faire:shipment {order}';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();

        $this->handle($order);

        return 0;
    }
}
