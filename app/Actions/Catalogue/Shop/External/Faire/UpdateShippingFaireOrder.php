<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Sentry;

class UpdateShippingFaireOrder extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, Command|null $command = null): void
    {
        $order = $deliveryNote->orders()->first();
        if ($order && $order->shop->type == ShopTypeEnum::EXTERNAL && $order->external_id && !$order->is_shipping_by_external
            && app()->isProduction()
        ) {
            try {
                $shipments = [];


                foreach ($deliveryNote->shipments as $shipment) {
                    $shipments[] = [
                        'carrier'          => $shipment->shipper->trade_as,
                        'tracking_code'    => $shipment->tracking,
                        'maker_cost_cents' => $shipment->cost ? (int)$shipment->cost * 100 : 0
                    ];
                }

                $result = $order->shop->updateShippingFaireOrder($order->external_id, [
                    'shipments' => $shipments
                ]);
                if ($command) {
                    $command->info('Order '.$order->external_id.' updated');
                    print_r($result);
                }
            } catch (\Exception $e) {
                $command?->error('Order '.$order->external_id.' not updated '.$e->getMessage());
                Sentry::captureException($e);
            }
        }
    }

    public function getCommandSignature(): string
    {
        return 'shop:update_shipping_faire_order {delivery_note}';
    }

    public function asCommand(Command $command): void
    {
        $deliveryNote = DeliveryNote::where('slug', $command->argument('delivery_note'))->firstOrFail();
        $this->handle($deliveryNote);
    }
}
